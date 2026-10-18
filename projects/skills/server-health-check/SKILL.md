---
name: server-health-check
description: "Run a read-only server health inspection when the user asks for 'server status'. Checks systemd services, Docker containers, OpenClaw gateway port 18789, nginx -t, disk, RAM, swap, load, failed units, and HTTPS status of production domains found in enabled nginx configs."
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
- Exit code: 0 = HEALTHY, non-zero = WARNING (never CRITICAL/failed for a timeout).
