---
name: server-health-check
description: "Run a read-only server health inspection when the user asks for 'server status'. Checks systemd services, Docker containers, OpenClaw gateway port 18789, nginx -t, disk, RAM, swap, load, failed units, and HTTPS status of production domains and payment endpoints, and off-site backup age."
allowed-tools: [exec, read]
---

# server-health-check

**Trigger:** User says `server status` (or similar).

**Run:** `bash /root/.openclaw/workspace/skills/server-health-check/references/health-check.sh`

Render the script's output verbatim: compact rows using 🟢 healthy / 🟡 warning / 🔴 failed, problems first, then healthy, ending with `Overall server status: HEALTHY / WARNING / CRITICAL`.

## Rules
- READ-ONLY. Never restart, modify, deploy, delete, or reconfigure anything.
- n8n / uptime-kuma / inst-lab-db may lack systemd units — check Docker instead.
- OpenClaw is healthy when its gateway listens on TCP 18789 (no systemd service required).
- Exit code: 0 = HEALTHY, 1 = WARNING, 2 = CRITICAL. Timeouts/DNS/TLS failures are WARNING, never CRITICAL.
- Thresholds are numeric (disk 80/90%, RAM 85/95%, swap 80/95%, load 1x/2x CPU count).
- Each probed URL must return its expected code (default 200). Redirects and 401/403/405 are healthy only where listed in `EXPECT` in the script; redirects must also match the expected target.
- Intentionally decommissioned domains (`DECOMMISSIONED_RE`) are never probed.
- Also checks payment/checkout endpoints (GET only) and the age of the last verified off-site backup.
