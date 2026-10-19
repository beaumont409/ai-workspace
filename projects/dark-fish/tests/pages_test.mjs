import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const directory = new URL('../', import.meta.url);
const storefront = readFileSync(new URL('index.html', directory), 'utf8');
const payment = execFileSync('php', [new URL('payment.php', directory).pathname], { encoding: 'utf8' });

function page(html, fetch, search = '?service=frp', { storage = new Map(), now = Date.now() } = {}) {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, { textContent: '', disabled: true, hidden: true,
            style: {}, addEventListener() {}, reportValidity: () => true,
            elements: { model: { value: 'Test Phone' }, imei: { value: 'TEST-SERIAL' }, txid: { value: 'b'.repeat(64) } } });
        return elements.get(id);
    };
    const buttons = [element('bootloader-button'), element('frp-button')];
    const alerts = [];
    const clock = { now };
    const timers = new Map();
    let timerId = 0;
    const context = vm.createContext({ fetch, URLSearchParams, Date: class extends Date { static now() { return clock.now; } }, Number, JSON, Error,
        localStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
        window: { location: { search } }, document: { getElementById: element, querySelectorAll: () => buttons },
        navigator: { clipboard: { writeText: async () => {} } }, alert: message => alerts.push(message),
        console: { error() {} }, setInterval() {},
        setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); } });
    const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
    vm.runInContext(script, context);
    return { context, element, buttons, alerts, storage, clock, timers, run: code => vm.runInContext(code, context) };
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

test('only the latest price request can change amounts or buttons', async () => {
    for (const olderFails of [false, true]) {
        for (const latestFails of [false, true]) {
            const requests = [];
            const view = page(storefront, () => new Promise((resolve, reject) => requests.push({ resolve, reject })));
            const latest = view.run('updateBTC()');
            if (latestFails) requests[1].reject(new Error('Latest failed'));
            else requests[1].resolve(ok({ btcUsd: 60000 }));
            await latest;
            if (olderFails) requests[0].reject(new Error('Older failed'));
            else requests[0].resolve(ok({ btcUsd: 30000 }));
            await flush();
            assert.equal(view.element('btc-bootloader').textContent, latestFails ? 'Unavailable' : '0.00075000');
            assert.equal(view.element('btc-frp').textContent, latestFails ? 'Unavailable' : '0.00050000');
            assert.ok(view.buttons.every(button => button.disabled === latestFails));
        }
    }
});

test('an older price response cannot enable buying while the latest request is pending', async () => {
    let resolveJson;
    let resolveLatest;
    let count = 0;
    const view = page(storefront, () => ++count === 1
        ? Promise.resolve({ ok: true, json: () => new Promise(resolve => { resolveJson = resolve; }) })
        : new Promise(resolve => { resolveLatest = resolve; }));
    await flush();
    const latest = view.run('updateBTC()');
    resolveJson({ btcUsd: 30000 });
    await flush();
    assert.equal(view.element('btc-frp').textContent, '');
    assert.ok(view.buttons.every(button => button.disabled));
    resolveLatest(ok({ btcUsd: 60000 }));
    await latest;
    assert.equal(view.element('btc-frp').textContent, '0.00050000');
    assert.ok(view.buttons.every(button => !button.disabled));
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

test('invalid quotes and failed initial quote requests leave payment unavailable', async () => {
    const invalid = [0, -1, NaN, Infinity, 0.5, '50000', null];
    for (const satoshis of invalid) {
        const view = page(payment, async () => ok({ ...quote(), satoshis }));
        await flush();
        assert.equal(view.element('submit-btn').disabled, true);
        assert.equal(view.element('payment-destination').hidden, true);
    }
    const view = page(payment, async () => ({ ok: false, json: async () => ({ error: 'Price unavailable' }) }));
    await flush();
    assert.equal(view.element('submit-btn').disabled, true);
    assert.equal(view.element('quote-status').textContent, 'Price unavailable');
});

test('quotes survive refresh and reload only for their matching service', async () => {
    const storage = new Map();
    const saved = quote();
    const first = page(payment, async () => ok(saved), '?service=frp', { storage });
    await flush();
    assert.deepEqual(JSON.parse(storage.get('dark-fish-quote:frp')), { ...saved, service: 'frp' });
    first.context.fetch = () => { throw new Error('Must reuse the active quote'); };
    await first.run('updateAmount()');
    const restored = page(payment, () => { throw new Error('Must restore without fetching'); }, '?service=frp', { storage });
    await flush();
    assert.equal(restored.run('quote.quoteId'), saved.quoteId);
    assert.equal(restored.element('payment-destination').hidden, false);
    assert.equal(restored.element('submit-btn').disabled, false);

    const bootloaderQuote = { ...quote(), quoteId: 'c'.repeat(64), satoshis: 75000 };
    const other = page(payment, async () => ok(bootloaderQuote), '?service=bootloader', { storage });
    await flush();
    assert.equal(other.run('quote.quoteId'), bootloaderQuote.quoteId);
    assert.equal(JSON.parse(storage.get('dark-fish-quote:frp')).quoteId, saved.quoteId);
    storage.set('dark-fish-quote:frp', JSON.stringify({ ...bootloaderQuote, service: 'bootloader' }));
    const mismatched = page(payment, async () => ok(saved), '?service=frp', { storage });
    await flush();
    assert.equal(mismatched.run('quote.quoteId'), saved.quoteId);
});

test('expired quotes hide the address and survive reload and failed verification until verified', async () => {
    const storage = new Map();
    const saved = quote();
    const initial = page(payment, async () => ok(saved), '?service=frp', { storage });
    await flush();
    const [timer] = initial.timers.values();
    assert.ok(timer.delay > 0);
    initial.clock.now = saved.expiresAt * 1000;
    timer.callback();
    assert.equal(initial.element('payment-destination').hidden, true);
    assert.equal(initial.element('submit-btn').disabled, false);
    assert.equal(initial.run('quote.quoteId'), saved.quoteId);
    let verified = false;
    const restored = page(payment, async (url, options) => {
        assert.equal(options.method, 'POST', 'Expired quote must not be replaced');
        assert.equal(JSON.parse(options.body).quoteId, saved.quoteId);
        return ok(verified ? { status: 'verified', orderId: 'saved-order' } : { status: 'pending' });
    }, '?service=frp', { storage, now: saved.expiresAt * 1000 + 1000 });
    await flush();
    assert.equal(restored.element('payment-destination').hidden, true);
    assert.equal(restored.element('submit-btn').disabled, false);
    assert.match(restored.element('quote-status').textContent, /Quote expired/);
    assert.equal(restored.timers.size, 0);
    await restored.run('handleSubmit({ preventDefault() {} })');
    assert.ok(storage.has('dark-fish-quote:frp'));
    assert.equal(restored.run('quote.quoteId'), saved.quoteId);
    storage.set('dark-fish-quote:bootloader', 'other quote');
    verified = true;
    await restored.run('handleSubmit({ preventDefault() {} })');
    assert.equal(storage.has('dark-fish-quote:frp'), false);
    assert.ok(storage.has('dark-fish-quote:bootloader'));
    assert.equal(restored.run('quote'), null);
    assert.equal(restored.element('submit-btn').disabled, true);
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
    assert.ok(view.storage.has('dark-fish-quote:frp'));
});
