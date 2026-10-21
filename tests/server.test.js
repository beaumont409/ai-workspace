import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from '../server.js';

async function request(options, path = '/api/status', method = 'GET') {
  const server = createServer(options);
  return new Promise((resolve, reject) => {
    const headers = {};
    const res = {
      status: 200,
      setHeader(name, value) { headers[name.toLowerCase()] = value; },
      writeHead(status, values = {}) { this.status = status; for (const [name, value] of Object.entries(values)) this.setHeader(name, value); },
      end(body) { try { resolve({ status: this.status, body: JSON.parse(body), cache: headers['cache-control'] }); } catch (error) { reject(error); } }
    };
    server.emit('request', { url: path, method }, res);
  });
}
test('proxy uses only fixed destination, disables redirects, and returns uncached JSON', async () => {
  const result = await request({ fetcher: async (url, options) => {
    assert.equal(url, 'https://status.triangle250r.com/api/status');
    assert.equal(options.redirect, 'error');
    return Response.json({ cpu: 4, k2: true });
  } }, '/api/status?url=http://127.0.0.1/private');
  assert.equal(result.status, 200); assert.equal(result.cache, 'no-store'); assert.equal(result.body.cpu, 4);
});
test('proxy rejects other methods without contacting upstream', async () => {
  assert.equal((await request({ fetcher: () => assert.fail('unexpected fetch') }, '/api/status', 'POST')).status, 405);
});
test('upstream HTTP, network, JSON, size and redirect failures return 502', async () => {
  for (const fetcher of [async () => new Response('', { status: 503 }), async () => { throw new TypeError('network'); }, async () => new Response('invalid'), async () => new Response('x'.repeat(50)), async () => new Response('{}', { headers: { 'content-length': '50' } }), async () => Response.json([])]) {
    const result = await request({ fetcher, maxBytes: 20 });
    assert.equal(result.status, 502); assert.ok(result.body.error);
  }
});
test('timeout covers upstream headers and stalled response bodies', async () => {
  const stalled = signal => new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError'))));
  for (const fetcher of [(_url, { signal }) => stalled(signal), async (_url, { signal }) => ({ ok: true, headers: new Headers(), body: { async *[Symbol.asyncIterator]() { await stalled(signal); } } })]) {
    const result = await request({ fetcher, timeout: 10 });
    assert.equal(result.status, 504); assert.match(result.body.error, /timed out/);
  }
});
