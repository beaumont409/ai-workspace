#!/usr/bin/env bash
# Read-only server health check. Never restarts, modifies, deploys or deletes.
# Exit codes: 0 = HEALTHY, 1 = WARNING, 2 = CRITICAL.
# Timeouts are reported as WARNING, never CRITICAL.
#
# Test hooks (optional env overrides, used only for validation):
#   HC_DISK_PCT HC_RAM_PCT HC_SWAP_PCT HC_LOAD HC_NPROC  - override measurements
#   HC_DOMAINS="a.com b.com"                             - override domain list
#   HC_HTTP_RESULTS="https://a.com/=200 https://b.com/=302|https://b.com/x"
#                                                        - override probe results (code|redirect)
#   HC_SKIP_SERVICES=1                                   - skip service/docker/port checks
#   HC_LIB_ONLY=1                                        - define functions only (for sourcing)
set -uo pipefail

# ---- thresholds (percent unless noted) -------------------------------------
DISK_WARN=80;  DISK_CRIT=90
RAM_WARN=85;   RAM_CRIT=95
SWAP_WARN=80;  SWAP_CRIT=95
LOAD_WARN_X=1; LOAD_CRIT_X=2       # multiples of CPU count
BACKUP_MAX_AGE_H=30                 # disaster-backup must succeed at least this recently

# Intentionally decommissioned sites: never probed, never reported.
DECOMMISSIONED_RE='(^|\.)(k2prisonsheetz\.com|oemunlocks\.com|blueprinttechnology\.dev)$'

SERVICES="nginx mariadb docker live-dashboard designer-luxury-payments"
CONTAINERS="n8n uptime-kuma inst-lab-db"

RESULTS_CRIT=(); RESULTS_WARN=(); RESULTS_OK=()
add() { # level name detail
  case "$1" in
    crit) RESULTS_CRIT+=("🔴 $2 — $3") ;;
    warn) RESULTS_WARN+=("🟡 $2 — $3") ;;
    *)    RESULTS_OK+=("🟢 $2 — $3") ;;
  esac
}

# Numeric level for a percentage: prints ok|warn|crit
level_pct() { # value warn crit
  awk -v v="$1" -v w="$2" -v c="$3" 'BEGIN{ if (v+0>=c) print "crit"; else if (v+0>=w) print "warn"; else print "ok" }'
}

# Load level relative to CPU count (float-safe)
level_load() { # load nproc
  awk -v l="$1" -v n="$2" -v w="$LOAD_WARN_X" -v c="$LOAD_CRIT_X" \
    'BEGIN{ if (l+0>=n*c) print "crit"; else if (l+0>=n*w) print "warn"; else print "ok" }'
}

# ---- expected responses ------------------------------------------------------
# Every probed URL must return exactly its expected code. Default expectation: 200.
# Redirects (3xx) must also point to the listed target prefix (after ">").
# Auth/method rejections (401/403/405) are healthy ONLY where listed here.
EXPECT=(
  "https://status.triangle250r.com/=302>https://status.triangle250r.com/dashboard"
  "https://qwen.triangle250r.com/=405"
  "https://www.triangle250r.com/=301>https://triangle250r.com/"
  # Payment/checkout endpoints (GET only; submit endpoints must reject GET)
  "https://designerluxury.org/payment/=200"
  "https://designerluxury.org/api/v1/checkout/products/datejust=200"
  "https://designerluxury.org/api/v1/payments/submit=403"
  "https://designerluxury.org/api/v1/inquiries/submit=403"
  "https://designerluxury.org/api/v1/orders=405"
  "https://supergradeawatches.com/checkout.php=200"
)
# Extra (non-root) URLs to probe in addition to each domain's "/".
EXTRA_URLS="https://designerluxury.org/payment/ https://designerluxury.org/api/v1/checkout/products/datejust https://designerluxury.org/api/v1/payments/submit https://designerluxury.org/api/v1/inquiries/submit https://designerluxury.org/api/v1/orders https://supergradeawatches.com/checkout.php"

expect_for() { # url -> "code" or "code>target"
  local e; for e in "${EXPECT[@]}"; do [ "${e%%=*}" = "$1" ] && { echo "${e#*=}"; return; }; done
  echo 200
}

# Classify probe result against expectation. Prints "level|detail".
#   exact match (and redirect target match)      -> ok
#   000 (timeout/TLS/DNS/connection)             -> warn (never crit)
#   5xx                                          -> crit
#   anything else unexpected (incl. 3xx/401/403/405 where not expected, 404) -> warn
classify_http() { # code redirect expected_spec
  local code="$1" loc="$2" spec="$3" want target
  want="${spec%%>*}"; target=""; [ "$spec" != "$want" ] && target="${spec#*>}"
  if [ "$code" = "$want" ]; then
    if [ -n "$target" ] && [ "${loc#"$target"}" = "$loc" ]; then
      echo "warn|HTTP $code -> ${loc:-none} (expected -> $target)"; return
    fi
    echo "ok|HTTP $code${loc:+ -> $loc}"; return
  fi
  case "$code" in
    000) echo "warn|no response (timeout/TLS/DNS/connection), expected $want" ;;
    5??) echo "crit|HTTP $code (expected $want)" ;;
    *)   echo "warn|HTTP $code${loc:+ -> $loc} (expected $want)" ;;
  esac
}

# Domains served by enabled nginx config: real public hostnames only.
list_domains() {
  if [ -n "${HC_DOMAINS:-}" ]; then printf '%s\n' $HC_DOMAINS; return; fi
  /usr/sbin/nginx -T 2>/dev/null \
    | awk '$1=="server_name"{ for(i=2;i<=NF;i++){ gsub(/;/,"",$i); print tolower($i) } }' \
    | grep -E '^[a-z0-9-]+(\.[a-z0-9-]+)+$' \
    | grep -vE '\.(onion|local|localhost|preview|internal|test)$' \
    | grep -vE '^[0-9.]+$' \
    | sort -u
}

probe() { # url -> "code|redirect_url"
  if [ -n "${HC_HTTP_RESULTS:-}" ]; then
    local kv; for kv in $HC_HTTP_RESULTS; do [ "${kv%%=*}" = "$1" ] && { echo "${kv#*=}"; return; }; done
    echo "000|"; return
  fi
  local r
  r=$(curl -s -o /dev/null -w '%{http_code}|%{redirect_url}' --max-time 10 "$1" 2>/dev/null) || true
  [ -z "$r" ] && r="000|"
  echo "${r:0:3}|${r#*|}"
}

[ "${HC_LIB_ONLY:-0}" = "1" ] && return 0 2>/dev/null

# ---- 1. services, containers, gateway -------------------------------------
if [ "${HC_SKIP_SERVICES:-0}" != "1" ]; then
  for svc in $SERVICES; do
    st=$(systemctl is-active "$svc" 2>/dev/null); st=${st:-unknown}
    [ "$st" = "active" ] && add ok "$svc" "active" || add crit "$svc" "$st"
  done
  for c in $CONTAINERS; do
    st=$(docker ps --format '{{.Status}}' --filter "name=^${c}$" 2>/dev/null | head -1)
    case "$st" in
      *unhealthy*) add warn "$c" "$st" ;;
      Up*)         add ok "$c" "$st" ;;
      *)           add crit "$c" "${st:-not running}" ;;
    esac
  done
  if ss -tlnH 2>/dev/null | grep -q ':18789 '; then
    add ok "OpenClaw gateway" "listening on TCP 18789"
  else
    add crit "OpenClaw gateway" "not listening on TCP 18789"
  fi
  if out=$(/usr/sbin/nginx -t 2>&1) && echo "$out" | grep -q 'test is successful'; then
    add ok "nginx -t" "successful"
  else
    add crit "nginx -t" "$(echo "$out" | tail -1)"
  fi
  failed=$(systemctl list-units --type=service --state=failed --no-legend --plain 2>/dev/null | awk '{print $1}')
  nf=$(printf '%s' "$failed" | grep -c . || true)
  [ "$nf" -eq 0 ] && add ok "Failed units" "0" || add warn "Failed units" "$nf ($(echo $failed | tr ' ' ','))"

  # disaster-backup: age of last successful completion in its log
  last=$(grep -h '^Backup completed successfully:' /var/log/disaster-backup.log 2>/dev/null | tail -1 | sed 's/^Backup completed successfully: //')
  if [ -n "$last" ] && ts=$(date -d "$last" +%s 2>/dev/null); then
    age_h=$(( ( $(date +%s) - ts ) / 3600 ))
    [ "$age_h" -le "$BACKUP_MAX_AGE_H" ] && add ok "Last off-site backup" "${age_h}h ago" \
      || add crit "Last off-site backup" "${age_h}h ago (limit ${BACKUP_MAX_AGE_H}h)"
  else
    add warn "Last off-site backup" "no successful run found in log"
  fi
fi

# ---- 2. resources ----------------------------------------------------------
disk=${HC_DISK_PCT:-$(df -P / | awk 'NR==2{gsub(/%/,"",$5); print $5}')}
add "$(level_pct "$disk" $DISK_WARN $DISK_CRIT)" "Disk /" "${disk}% used (warn ${DISK_WARN}%, crit ${DISK_CRIT}%)"

if [ -n "${HC_RAM_PCT:-}" ]; then ram=$HC_RAM_PCT; ram_note=""
else
  read -r ram_total ram_avail < <(free -m | awk '/^Mem:/{print $2, $7}')
  ram=$(( 100 - ram_avail * 100 / ram_total )); ram_note=", ${ram_avail}MB avail"
fi
add "$(level_pct "$ram" $RAM_WARN $RAM_CRIT)" "RAM" "${ram}% used${ram_note}"

if [ -n "${HC_SWAP_PCT:-}" ]; then swap=$HC_SWAP_PCT
else
  read -r swap_total swap_used < <(free -m | awk '/^Swap:/{print $2, $3}')
  if [ "${swap_total:-0}" -gt 0 ]; then swap=$(( swap_used * 100 / swap_total )); else swap=""; fi
fi
if [ -n "$swap" ]; then add "$(level_pct "$swap" $SWAP_WARN $SWAP_CRIT)" "Swap" "${swap}% used"
else add warn "Swap" "none configured"; fi

load=${HC_LOAD:-$(cut -d' ' -f1 /proc/loadavg)}
cpus=${HC_NPROC:-$(nproc)}
add "$(level_load "$load" "$cpus")" "Load 1m" "$load on $cpus CPUs"

# ---- 3. HTTPS for active production domains and payment endpoints --------
urls=$( { list_domains | sed 's#.*#https://&/#'; [ -z "${HC_DOMAINS:-}" ] && printf '%s\n' $EXTRA_URLS; } )
while read -r u; do
  [ -z "$u" ] && continue
  host=${u#https://}; host=${host%%/*}
  echo "$host" | grep -qE "$DECOMMISSIONED_RE" && continue
  r=$(probe "$u"); code=${r%%|*}; loc=${r#*|}
  res=$(classify_http "$code" "$loc" "$(expect_for "$u")")
  name=${u#https://}; name=${name%/}
  add "${res%%|*}" "$name" "${res#*|}"
done <<< "$urls"

# ---- output: problems first ------------------------------------------------
for r in "${RESULTS_CRIT[@]+"${RESULTS_CRIT[@]}"}"; do echo "$r"; done
for r in "${RESULTS_WARN[@]+"${RESULTS_WARN[@]}"}"; do echo "$r"; done
for r in "${RESULTS_OK[@]+"${RESULTS_OK[@]}"}";   do echo "$r"; done
echo
if [ ${#RESULTS_CRIT[@]} -gt 0 ]; then echo "Overall server status: CRITICAL"; exit 2
elif [ ${#RESULTS_WARN[@]} -gt 0 ]; then echo "Overall server status: WARNING"; exit 1
else echo "Overall server status: HEALTHY"; exit 0; fi
