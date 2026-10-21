# Triangle status dashboard

A new, standalone, dependency-free dashboard for https://status.triangle250r.com/api/status. Includes responsive resource cards, service health, uptime, manual refresh, a 60-second refresh interval while the tab is visible, a 12-second request timeout, loading feedback, and connection errors. Failed refreshes preserve the last successful snapshot and label it as stale. No example data is presented as live data.

## Run locally

Requires Node.js 20 or newer. No installation or external packages needed.

```sh
npm start
```

Open http://127.0.0.1:3000. The server binds only to the local machine. Optionally set `PORT` to change the port. This project does not deploy anything or change server configuration.

## Checks

```sh
npm test
npm run check
```

Tests run entirely offline using injected fetch functions and local fixtures. They cover normalization, absent and invalid metrics, aggregate service health, formatting, request options, HTTP/network/JSON errors and timeout cancellation. No API credentials are required or used. `claw-validation.json` lists the reproducible offline checks.

## API integration and assumptions

The browser requests the API directly over HTTPS. The endpoint must permit cross-origin browser requests (CORS). If it does not, the dashboard explains the connection failure; it cannot establish whether services are down from a failed request. No proxy is configured.

The API was not reachable from the build environment, so its exact schema and live integration could not be verified. `src/status.js` is the adapter to update after verifying a real response. It currently accepts a root object or `data` wrapper, host metrics at root or under `system` / `metrics`, and these common fields:

- CPU: `cpu`, `cpu_usage`, `cpu_percent`; scalar percentage or object with `usage`, `percent`, `percentage`, `usage_percent`.
- RAM: `ram`, `memory`, `memory_usage`, `ram_usage`; disk: `disk`, `disk_usage`. Resource objects may have `used` / `total` or `used_bytes` / `total_bytes` (assumed bytes), from which a percentage is calculated when none is supplied.
- Uptime: `uptime` or `uptime_seconds`; numbers are seconds, descriptive strings are displayed verbatim.
- Services: an array of objects with `name`, `status` and optional `description`, or an object keyed by service name. Known status words and boolean health values map to operational, degraded, unavailable or unknown.
- Aggregate health: `status`, `health` or `healthy`. Down/degraded services override a healthy aggregate; otherwise unknown health stays unknown. Missing metrics display a dash. Out-of-range percentages are rejected, never fabricated or clamped.

The UI uses system fonts, local CSS, safe text rendering for API values, accessible status announcements and keyboard focus styles, and reduced-motion support. Unused starter assets have been removed. There is no contact form or simulated submission.
