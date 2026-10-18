#!/usr/bin/env bash
# Read-only server health check. No restarts/modifications/deletes.
set -eo pipefail
RED='\033[0;31m'; YEL='\033[0;33m'; GRN='\033[0;32m'; RST='\033[0m'
WARN=0
results=()

check() {
  local name="$1" expected="$2" actual="$3"
  if [ "$actual" = "$expected" ]; then
    results+=("🟢 $name — $actual")
  else
    results+=("🟡 $name — $actual (expected: $expected)")
    WARN=1
  fi
}

check_status() {
  local svc="$1" label="${2:-$svc}"
  local st
  st=$(systemctl is-active "$svc" 2>/dev/null || echo "unknown")
  if [ "$st" = "active" ]; then
    check "$label" "active" "$st"
  else
    check "$label" "active" "$st"
  fi
}

# 1. systemd services
check_status nginx
check_status mariadb
check_status docker
check_status k2prisonsheetz
check_status live-dashboard

# 2. Docker containers (n8n/uptime-kuma/inst-lab-db may not have systemd units)
for c in n8n uptime-kuma inst-lab-db; do
  st=$(docker ps --format '{{.Status}}' --filter "name=$c" 2>/dev/null | head -1)
  if echo "$st" | grep -q "Up"; then
    check "$c" "Up" "$st"
  else
    check "$c" "Up" "${st:-not running}"
  fi
done

# 3. OpenClaw gateway on TCP 18789
if ss -tlnp 2>/dev/null | grep -q ':18789 '; then
  check "OpenClaw gateway (18789)" "healthy — Gateway listening on TCP 18789" "healthy — Gateway listening on TCP 18789"
else
  check "OpenClaw gateway (18789)" "healthy — Gateway listening on TCP 18789" "not listening"
fi

# 4. nginx config test — use absolute executable
if /usr/sbin/nginx -t 2>&1 | grep -q "test is successful"; then
  check "nginx -t" "successful" "successful"
else
  err_msg=$(/usr/sbin/nginx -t 2>&1 | tail -1)
  check "nginx -t" "successful" "$err_msg"
fi

# 5. disk
pct=$(df -h / | awk 'NR==2{print $5}' | tr -d '%')
check "Disk /" "<80%" "${pct}% used"

# 6. RAM
ram_avail=$(free -m | awk '/Mem:/{print $7}')
ram_total=$(free -m | awk '/Mem:/{print $2}')
ram_pct=$(( 100 - ram_avail * 100 / ram_total ))
check "RAM" "<90%" "${ram_pct}% used (${ram_avail}MB avail)"

# 7. swap
swap_total=$(free -m | awk '/Swap:/{print $2}')
if [ "$swap_total" -gt 0 ]; then
  swap_used=$(free -m | awk '/Swap:/{print $3}')
  swap_pct=$(( swap_used * 100 / swap_total ))
  check "Swap" "<50%" "${swap_pct}% used"
fi

# 8. system load
load=$(cut -d' ' -f1 /proc/loadavg)
cpu=$(nproc)
check "load 1m (<$cpu)" "<$cpu" "$load"

# 9. failed systemd units
failed=$(systemctl list-units --type=service --state=failed --no-legend 2>/dev/null | wc -l)
check "Failed units" "0" "$failed"

# 10. HTTPS status of production domains from enabled nginx configs
for cfg in /etc/nginx/sites-enabled/*; do
  [ -f "$cfg" ] || continue
  domain=$(basename "$cfg")
  [ "$domain" = "default" ] && continue
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "https://$domain" 2>/dev/null || echo "000")
  if [ "$code" = "200" ]; then
    check "$domain HTTPS" "200" "$code"
  else
    check "$domain HTTPS" "200" "$code"
  fi
done

# Output — problems first
for r in "${results[@]}"; do
  case "$r" in 🟡*) echo "$r";; esac
done
for r in "${results[@]}"; do
  case "$r" in 🟢*) echo "$r";; esac
done

if [ "$WARN" -eq 0 ]; then
  echo -e "\nOverall server status: ${GRN}HEALTHY${RST}"
else
  echo -e "\nOverall server status: ${YEL}WARNING${RST}"
  exit 1
fi