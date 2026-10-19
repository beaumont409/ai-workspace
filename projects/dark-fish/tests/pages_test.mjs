import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const directory = new URL('../', import.meta.url);
const storefront = readFileSync(new URL('index.html', directory), 'utf8');
const payment = execFileSync('php', [new URL('payment.php', directory).pathname], { encoding: 'utf8' });

function page(html, fetch, search = '?service=frp') {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, { textContent: '', disabled: true, hidden: true,
            style: {}, addEventListener() {}, reportValidity: () => true,
            elements: { model: { value: 'Test Phone' }, imei: { value: 'TEST-SERIAL' }, txid: { value: 'b'.repeat(64) } } });
        return elements.get(id);
    };
    const buttons = [element('bootloader-button'), element('frp-button')];
    const alerts = [];
    const context = vm.createContext({ fetch, URLSearchParams, Date, Number, JSON, Error,
        window: { location: { search } }, document: { getElementById: element, querySelectorAll: () => buttons },
        navigator: { clipboard: { writeText: async () => {} } }, alert: message => alerts.push(message),
        console: { error() {} }, setInterval() {}, setTimeout() {}, clearTimeout() {} });
    const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
    vm.runInContext(script, context);
    return { context, element, buttons, alerts, run: code => vm.runInContext(code, context) };
}
const flush = () => new Promise(resolve => setImmediate(resolve));
const ok = data => ({ ok: true, json: async () => data });
const quote = () => ({ quoteId: 'a'.repeat(64), satoshis: 50000, expiresAt: Math.floor(Date.now() / 1000) + 1800 });

test('storefront updates both amounts and disables buying after an invalid refresh', async () => {
    let rate = 60000;
    const view = page(storefront, async () => ok({ btcUsd: rate }));
    await flush();
    assert.equal(view.element('btc-bootloader').textContent, '0.00075000');
    assert.equal(view.element('btc-frp').textContent, '0.00050000');
    assert.ok(view.buttons.every(button => !button.disabled));
    for (rate of [0, -1, NaN, Infinity, null, '60000', 1e-300]) {
        await view.run('updateBTC()');
        assert.ok(view.buttons.every(button => button.disabled));
        assert.equal(view.element('btc-frp').textContent, 'Unavailable');
    }
});

test('absent and unknown services prevent quoting and payment', async () => {
    for (const search of ['', '?service=unknown', '?service=FRP']) {
        const view = page(payment, () => { throw new Error('Must not fetch'); }, search);
        await flush();
        assert.equal(view.element('display-amount').textContent, 'Unavailable');
        assert.equal(view.element('submit-btn').disabled, true);
        assert.match(view.element('quote-status').textContent, /Invalid service/);
    }
});

test('both valid services quote independently', async () => {
    for (const service of ['bootloader', 'frp']) {
        const view = page(payment, async url => {
            assert.match(url, new RegExp('service=' + service));
            return ok(quote());
        }, '?service=' + service);
        await flush();
        assert.match(view.element('quote-status').textContent, service === 'frp' ? /\$30 quote/ : /\$45 quote/);
        assert.equal(view.element('submit-btn').disabled, false);
        assert.equal(view.element('payment-destination').hidden, false);
    }
});

test('invalid quotes and failed price requests leave payment unavailable', async () => {
    const invalid = [0, -1, NaN, Infinity, 0.5, '50000', null];
    for (const satoshis of invalid) {
        const view = page(payment, async () => ok({ ...quote(), satoshis }));
        await flush();
        assert.equal(view.element('submit-btn').disabled, true);
        assert.equal(view.element('payment-destination').hidden, true);
    }
    let failPrice = false;
    const refreshed = page(payment, async () => failPrice
        ? { ok: false, json: async () => ({ error: 'Price unavailable' }) } : ok(quote()));
    await flush();
    failPrice = true;
    await refreshed.run('updateAmount()');
    assert.equal(refreshed.element('submit-btn').disabled, true);
    assert.equal(refreshed.element('payment-destination').hidden, true);
    const view = page(payment, async () => ({ ok: false, json: async () => ({ error: 'Price unavailable' }) }));
    await flush();
    assert.equal(view.element('submit-btn').disabled, true);
    assert.equal(view.element('quote-status').textContent, 'Price unavailable');
});

test('clipboard success waits for resolution and rejection shows an error', async () => {
    const view = page(payment, async () => ok(quote()));
    let resolveWrite;
    view.context.navigator.clipboard.writeText = value => {
        assert.equal(value, '1BvBMSEYstWetqTFn5Au4m4GFg7xWBwgWV');
        return new Promise(resolve => { resolveWrite = resolve; });
    };
    const copying = view.run('copyAddress()');
    assert.deepEqual(view.alerts, []);
    resolveWrite();
    await copying;
    assert.deepEqual(view.alerts, ['Address copied to clipboard']);
    view.context.navigator.clipboard.writeText = async () => { throw new Error('Denied'); };
    await view.run('copyAddress()');
    assert.match(view.alerts[1], /Could not copy/);
});

test('order submission sends details and waits for verified server response', async () => {
    let resolveOrder;
    const view = page(payment, async (url, options) => {
        if (options?.method !== 'POST') return ok(quote());
        assert.equal(url, 'api.php?action=order');
        assert.equal(options.method, 'POST');
        assert.deepEqual(JSON.parse(options.body), { quoteId: 'a'.repeat(64), model: 'Test Phone', imei: 'TEST-SERIAL', txid: 'b'.repeat(64) });
        return new Promise(resolve => { resolveOrder = resolve; });
    });
    await flush();
    const submitting = view.run('handleSubmit({ preventDefault() {} })');
    assert.equal(view.element('submit-btn').style.display, undefined);
    assert.equal(view.element('order-status').textContent, '');
    resolveOrder(ok({ status: 'verified', orderId: 'synthetic-order' }));
    await submitting;
    assert.match(view.element('order-status').textContent, /Payment verified.*synthetic-order/);
    assert.equal(view.element('submit-btn').disabled, true);
});

test('unconfirmed or failed server verification allows retry without success', async () => {
    const view = page(payment, async (url, options) => options?.method === 'POST'
        ? { ok: false, json: async () => ({ error: 'Transaction is unconfirmed' }) }
        : ok(quote()));
    await flush();
    await view.run('handleSubmit({ preventDefault() {} })');
    assert.equal(view.element('submit-btn').disabled, false);
    assert.equal(view.element('submit-btn').style.display, undefined);
    assert.equal(view.element('order-status').textContent, 'Transaction is unconfirmed');
});
