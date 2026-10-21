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
    resolveRequest({ ok: true, json: async () => ({ cpu: 42, ram: 22, disk: 31, uptime_hours: 25, k2: true, kuma: true, mariadb: true, n8n: true, nginx: true, openclaw: true, containers: { '<script>unsafe</script>': { state: 'running', cpu: '0.01%', memory: '32MiB', memory_percent: 2 } } }) });
    await tick();
    assert.equal(get('cpu-value').textContent, '42.0%');
    assert.equal(get('uptime-value').textContent, '1d 1h');
    assert.equal(get('dashboard').dataset.health, 'healthy');
    assert.equal(get('refresh').disabled, false);
    assert.equal(get('service-list').children[6].children[0].textContent, '<script>unsafe</script>');
    assert.match(get('service-list').children[6].children[0].children[0].textContent, /CPU: 0\.01%/);
    get('refresh').click();
    resolveRequest({ ok: false, status: 503 }); await tick();
    assert.equal(get('cpu-value').textContent, '42.0%');
    assert.equal(get('live-label').textContent, 'STALE DATA');
    assert.equal(get('notice').hidden, false);
    assert.match(get('notice').textContent, /last successful snapshot/);
    get('refresh').click();
    resolveRequest({ ok: true, json: async () => ({ status: 'degraded', cpu: 70, containers: { invalid: { cpu: 'bad%' }, missing: {} } }) }); await tick();
    assert.equal(get('notice').hidden, true);
    for (const row of get('service-list').children) assert.match(row.children[0].children[0].textContent, /CPU: Unknown/);
    assert.equal(get('cpu-value').textContent, '70.0%');
    assert.equal(get('dashboard').dataset.health, 'degraded');
    assert.equal(get('dashboard').attributes['aria-busy'], 'false');
  } finally { Object.assign(globalThis, original); }
});
