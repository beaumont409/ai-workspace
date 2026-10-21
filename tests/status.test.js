import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeStatus, percent, health, formatUptime, formatBytes, fetchStatus, API_URL } from '../src/status.js';

test('normalizes host metrics and individual services', () => {
  const data = normalizeStatus({ status: 'ok', system: { cpu: { usage: 32 }, memory: { used: 1024, total: 4096 }, disk: { percent: 60 }, uptime: 90061 }, services: [{ name: 'Web', status: 'online' }] });
  assert.equal(data.cpu.percent, 32); assert.equal(data.ram.percent, 25); assert.equal(data.disk.percent, 60);
  assert.equal(data.uptime, 90061); assert.equal(data.overall, 'healthy'); assert.equal(data.services[0].name, 'Web');
});
test('supports data wrappers, numeric strings and service dictionaries', () => {
  const data = normalizeStatus({ data: { metrics: { cpu_usage: '12.5%', ram_usage: '33', disk_usage: 0, uptime_seconds: '60' }, services: { database: { healthy: true }, web: 'offline' } } });
  assert.equal(data.cpu.percent, 12.5); assert.equal(data.disk.percent, 0); assert.equal(data.uptime, 60); assert.equal(data.overall, 'down');
});
test('degraded services override a healthy aggregate', () => {
  assert.equal(normalizeStatus({ status: 'ok', services: { web: 'degraded' } }).overall, 'degraded');
});
test('missing and invalid values remain unavailable', () => {
  const data = normalizeStatus({ status: 'ok', cpu: -5, memory: { used: 2, total: 0 } });
  assert.equal(data.cpu.percent, null); assert.equal(data.ram.percent, null); assert.equal(data.uptime, null);
  for (const value of [null, '', 'garbage', 101, -1, Infinity, true]) assert.equal(percent(value), null);
  assert.equal(health('unexpected'), 'unknown');
});
test('rejects invalid and unrecognized API payloads', () => {
  for (const payload of [null, [], 'ok', {}, { message: 'hello' }]) assert.throws(() => normalizeStatus(payload));
});
test('formats uptime and byte values', () => {
  assert.equal(formatUptime(90061), '1d 1h'); assert.equal(formatUptime(3660), '1h 1m'); assert.equal(formatUptime(59), '0m'); assert.equal(formatUptime(null), 'Unavailable'); assert.equal(formatUptime(-1), 'Unavailable'); assert.equal(formatUptime('2 days'), '2 days');
  assert.equal(formatBytes(1073741824), '1.0 GiB'); assert.equal(formatBytes(0), '0 B');
});
test('fetches the specified API without caching', async () => {
  const result = await fetchStatus(async (url, options) => {
    assert.equal(url, API_URL); assert.equal(options.cache, 'no-store'); assert.ok(options.signal);
    return { ok: true, json: async () => ({ status: 'ok' }) };
  });
  assert.equal(result.overall, 'healthy');
});
test('reports HTTP, network and malformed JSON failures', async () => {
  await assert.rejects(fetchStatus(async () => ({ ok: false, status: 503 })), /HTTP 503/);
  await assert.rejects(fetchStatus(async () => { throw new TypeError('offline'); }), /offline/);
  await assert.rejects(fetchStatus(async () => ({ ok: true, json: async () => { throw new SyntaxError('bad JSON'); } })), /bad JSON/);
});
test('aborts stalled requests', async () => {
  await assert.rejects(fetchStatus((_url, { signal }) => new Promise((_resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('Timeout', 'AbortError')))), 5), { name: 'AbortError' });
});
