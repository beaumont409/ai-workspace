import test from 'node:test';
import assert from 'node:assert/strict';

test('UI handles loading, success, failed refresh with stale data, and recovery', async () => {
  const elements = new Map();
  function element() {
    return { textContent: '', hidden: false, disabled: false, dataset: {}, style: {}, children: [], attributes: {}, firstElementChild: { style: {} }, classList: { add() {}, remove() {} }, setAttribute(k, v) { this.attributes[k] = v; }, removeAttribute(k) { delete this.attributes[k]; }, append(...nodes) { this.children.push(...nodes); }, replaceChildren(...nodes) { this.children = nodes; }, addEventListener(event, handler) { this[event] = handler; } };
  }
  const original = { document: globalThis.document, fetch: globalThis.fetch, setInterval: globalThis.setInterval };
  let resolveRequest;
  let requestCount = 0;
  globalThis.document = { hidden: false, getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); }, createElement: element };
  globalThis.fetch = () => { requestCount++; return new Promise(resolve => { resolveRequest = resolve; }); };
  globalThis.setInterval = () => 0;
  const tick = () => new Promise(resolve => setTimeout(resolve, 0));
  try {
    await import('../src/app.js');
    const get = id => elements.get(id);
    assert.equal(get('refresh').disabled, true);
    assert.equal(get('dashboard').attributes['aria-busy'], 'true');
    get('refresh').click(); assert.equal(requestCount, 1);
    resolveRequest({ ok: true, json: async () => ({ status: 'ok', cpu: 42, uptime: 90000, services: [{ name: '<script>unsafe</script>', status: 'up' }] }) });
    await tick();
    assert.equal(get('cpu-value').textContent, '42.0%');
    assert.equal(get('dashboard').dataset.health, 'healthy');
    assert.equal(get('refresh').disabled, false);
    assert.equal(get('service-list').children[0].children[0].textContent, '<script>unsafe</script>');
    get('refresh').click();
    resolveRequest({ ok: false, status: 503 }); await tick();
    assert.equal(get('cpu-value').textContent, '42.0%');
    assert.equal(get('live-label').textContent, 'STALE DATA');
    assert.equal(get('notice').hidden, false);
    assert.match(get('notice').textContent, /last successful snapshot/);
    get('refresh').click();
    resolveRequest({ ok: true, json: async () => ({ status: 'degraded', cpu: 70 }) }); await tick();
    assert.equal(get('notice').hidden, true);
    assert.equal(get('cpu-value').textContent, '70.0%');
    assert.equal(get('dashboard').dataset.health, 'degraded');
    assert.equal(get('dashboard').attributes['aria-busy'], 'false');
  } finally { Object.assign(globalThis, original); }
});
