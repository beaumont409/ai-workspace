import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = fileURLToPath(new URL('.', import.meta.url));
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript' };
const allowed = new Set(['/index.html', '/src/app.js', '/src/status.js', '/src/style.css']);
export function createServer({ fetcher = fetch, timeout = 8000, maxBytes = 262144 } = {}) {
return http.createServer(async (req, res) => {
  const pathname = new URL(req.url, 'http://localhost').pathname;
  if (pathname === '/api/status') {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.setHeader('Cache-Control', 'no-store');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    if (req.method !== 'GET') { res.writeHead(405, { Allow: 'GET' }); res.end(JSON.stringify({ error: 'Method not allowed' })); return; }
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    let upstream;
    try {
      upstream = await fetcher('https://status.triangle250r.com/api/status', { signal: controller.signal, redirect: 'error', headers: { Accept: 'application/json' } });
      if (!upstream.ok) throw new Error(`Upstream returned HTTP ${upstream.status}`);
      if (Number(upstream.headers.get('content-length')) > maxBytes) throw new Error('Upstream response exceeds size limit');
      let size = 0;
      const chunks = [];
      for await (const chunk of upstream.body) {
        size += chunk.byteLength;
        if (size > maxBytes) throw new Error('Upstream response exceeds size limit');
        chunks.push(Buffer.from(chunk));
      }
      let payload;
      try { payload = JSON.parse(Buffer.concat(chunks).toString('utf8')); }
      catch { throw new Error('Upstream returned invalid JSON'); }
      if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new Error('Upstream returned an invalid status object');
      res.writeHead(200); res.end(JSON.stringify(payload));
    } catch (error) {
      const timedOut = controller.signal.aborted;
      controller.abort();
      res.writeHead(timedOut ? 504 : 502);
      res.end(JSON.stringify({ error: timedOut ? 'Upstream request timed out' : /^(Upstream returned|Upstream response)/.test(error.message) ? error.message : 'Unable to reach upstream status API' }));
    } finally { clearTimeout(timer); }
    return;
  }
  const file = pathname === '/' ? '/index.html' : pathname;
  if (!allowed.has(file)) { res.writeHead(404); res.end('Not found'); return; }
  try {
    const body = await readFile(path.join(root, file));
    res.writeHead(200, { 'Content-Type': `${types[path.extname(file)]}; charset=utf-8`, 'X-Content-Type-Options': 'nosniff' });
    res.end(body);
  } catch { res.writeHead(500); res.end('Unable to load file'); }
});
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const port = Number(process.env.PORT) || 3000;
  createServer().listen(port, '127.0.0.1', () => console.log(`Dashboard: http://127.0.0.1:${port}`));
}
