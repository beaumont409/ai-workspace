# Triangle status dashboard

Run `npm start` with Node.js 20+. The preview binds to 127.0.0.1:3000 (or PORT).

The browser requests same-origin GET /api/status. The server fetches only
https://status.triangle250r.com/api/status, rejects redirects, limits the complete
request to 8 seconds and the response to 256 KiB. Upstream failures return JSON
with HTTP 502; timeouts return 504. Responses are not cached.

The adapter handles numeric cpu/ram/disk percentages, uptime_hours, the six root
service booleans and keyed containers with state/cpu/memory/memory_percent.
Container CPU accepts numeric values and percentage strings such as `"0.01%"`.
Invalid, out-of-range, or missing percentages display Unknown.
Unknown container states remain visible; absent values remain unavailable.
Failed refreshes preserve the last successful snapshot with a stale label.

Run `npm test` and `npm run check`. Checks are offline and use injected upstream
responses and direct server request-handler calls; no credentials are needed. Live verification is a
separate operational check and may require network access unavailable here.
