# OEM Unlocks PR #2 release preparation

## Decision

Not ready to activate. See the continuation update below for current fix/test status;
the original-head evidence in the next section is historical. No merge, push, production write, configuration installation,
service reload/restart, real payment, or Telegram message was performed.

Reviewed repository AGENTS.md: never commit secrets; use environment variables;
existing sites/payment/wallet changes and deployment/restarts need explicit approval.
No nested AGENTS.md was found in the local repository copies. The PR changes only
16 OEM project files and does not add another AGENTS.md.

## Original-head evidence (historical; superseded by continuation below)

- PR: https://github.com/beaumont409/ai-workspace/pull/2
- Head: 28610c6ed9e12d13ecd12a5237369c0cea80486a.
- Base: 26abd1635464ed319627ef171d0400a23af362c7.
- GitHub: open, not draft, mergeable; two commits, 16 changed files.
- CodeRabbit commit status is pending. Its sole issue comment says processing.
  Submitted reviews and inline review threads are empty. This is not a completed
  review or an approval. No returned review finding can currently be marked resolved.
- GitHub Actions PR workflow query returned no runs; this is not passing CI.
  This connector query covers PR-triggered runs on its first page, not every
  possible check-run integration or branch-protection requirement.
- Downloaded all 16 files at that exact SHA to /root/oem-release-pr2-28610c6.
  source-sha256.txt records their local content hashes. Local git checkouts were
  older and were not used as proof of current PR behavior.
- PHP 8.3.6 syntax checks pass for app.php, payment.php, process_order.php,
  prices.php, orders.php, and tools/preflight.php.
- Ran python3 tests/integration.py unchanged. All 22 cases error in setUp at
  socket.socket(): PermissionError, Operation not permitted. No HTTP assertion
  ran. This is an environment blocker, not evidence of 22 application failures
  or a successful current run. The PR description's earlier pass remains a claim
  from development, not independently reproduced here.
- The PR wallet bytes occur exactly once in live payment.php and match exactly.
  No wallet edits were made. Baseline SHA256 of live payment.php:
  99dd84045768607746e438747a4fc3087227aea7722201c37bd6ce6998174cce.

## Read-only hosting comparison

| Requirement | Observed on disk / remaining verification |
| --- | --- |
| Root/index | OEM TLS vhost uses /var/www/oemunlocks and index.html, matching runtime manifest |
| TLS | HTTP redirects to HTTPS; certificate paths configured; handshake/expiry not verified |
| PHP routing | Existing PHP location uses fastcgi-php.conf and php8.3-fpm.sock; HTTPS parameter is passed |
| PHP 8.3, SQLite, cURL | CLI has required modules; FPM extension symlinks exist; actual FPM request still unverified |
| Sessions | /var/lib/php/sessions exists; actual FPM session write/cookie behavior still unverified |
| OEM environment | Only www.conf pool found, no active env[] entries; clear_env not explicitly overridden. Shell exports or .env alone do not configure FPM. Running process environment could not be established |
| Worker identity | Pool says www-data; existing socket metadata says nobody:nogroup. Resolve actual host/process ownership before choosing storage/socket permissions |
| Private storage | Proposed /var/lib/oemunlocks and staging sibling do not exist; no configured OEM_DATA_DIR was established |
| Dotfile/database/backup denial | Missing in OEM vhost; legacy backup files are inside served root and may expose old source/credentials. Do not fetch their contents over HTTP |
| Request/login limits | No explicit OEM nginx request/login limits or body cap found; application submission limiter does not protect login or quote creation |
| Other routes | /api/ proxies to 127.0.0.1:8081; /landing/ to 127.0.0.1:5000. Preserve these and every other site |

Systemd bus access and local sockets are denied in this execution environment.
Disk configuration is not proof of loaded configuration, service health, DNS,
TLS, upstream availability, outbound network access, or effective FPM settings.

## Original release gates and code observations (see continuation for dispositions)

1. Reproduce the unchanged integration suite successfully on a socket-capable
   isolated runner at the pinned SHA; rerun syntax checks there.
2. Obtain final CodeRabbit results and inspect every new finding, and verify
   required checks/branch protections directly before any merge decision.
3. Validate real FPM environment, identity, private writable data, sessions,
   TLS, CA store, Coinbase connectivity, administrator access, and staging routes.
4. Install approved OEM-specific protections before exposing the new manager.
   Existing source/backup exposure is a concrete configuration gap.
5. Quote records have no database cleanup; per-session quote cap can be bypassed
   by new sessions. Nginx limits mitigate traffic but do not provide retention.
   Establish monitoring/retention; do not delete quotes still reportable within
   24 hours or any order/audit data. No cleanup code was silently added.
6. A quote ID is looked up globally on submission rather than bound to its
   originating session. IDs are random 128-bit values and forms require CSRF,
   but a leaked ID could be submitted from another session and consume it.
   This deserves explicit review; no exploit or review resolution is claimed.

## Exact proposed change scope for approval

Application payload: only these nine deploy-files.txt entries, copied from the
pinned source directory relative to projects/oemunlocks:

    index.html
    oem-preview.html
    assets/site.css
    assets/prices.js
    app.php
    payment.php
    process_order.php
    prices.php
    orders.php

Do not recursively copy the repository, delete the destination, or deploy
landing/newpage, README, tests, tools, dotfiles, databases, or secret material.
Use rsync --files-from=deploy-files.txt without --delete into an offline release
directory first. Verify hashes and wallet bytes before installation.

oemunlocks.nginx.proposed.conf is a complete offline candidate based on the
existing OEM vhost. It preserves domain/root/index/TLS and both proxy blocks,
adds dotfile/database/backup denials, denies direct app.php access, adds 16k PHP
body limits, and applies separate admin/general PHP request limits. It points
only OEM PHP requests to a proposed dedicated /run/php/oemunlocks-fpm.sock.
oem-rate-limits.proposed.conf supplies its two http-context zones. These files
are proposals, have not passed nginx -t, and have not been installed.

Add a dedicated OEM FPM pool instead of adding credentials to the shared www
pool. Proposed pool name oemunlocks; worker www-data:www-data only after verifying
actual host identity; socket /run/php/oemunlocks-fpm.sock, nginx-accessible owner
and group, mode 0660; pm=ondemand, pm.max_children=5, idle timeout=10s;
clear_env=yes; explicit env entries for only the five required variable names.
Store secrets in a root-owned 0600 pool/include outside every served root.
Do not paste values into review output, command arguments, commits, or logs.
Review shared-FPM reload effects on other sites before authorizing activation.

## Deployment sequence after approval and gates

1. Recheck PR head and reviews. Any new SHA invalidates this verification.
   Finish isolated tests and investigate the observations above before activation.
2. Back up the entire current OEM document root, ownership/modes/symlinks,
   OEM nginx file and enabled link, relevant nginx include files, FPM pool and
   service configuration, and secret configuration into a root-only timestamped
   directory outside every served root. Record file hashes and restore commands.
   Discover existing order/private data without displaying contents and back it
   up separately. Use SQLite's online backup API or a quiesced consistent snapshot,
   not a bare copy of a database while it is writing. Verify a test restore.
3. Stage the nine manifest files in a private build directory, validate their
   hashes and the exact wallet match again. Keep release artifact immutable.
   Deploy web files root-owned, dirs 0755/files 0644, not worker-writable.
   Make an explicit inventory of paths newly introduced versus overwritten for
   rollback. Do not use a directory-wide delete; leave proxy content intact.
4. Create dedicated /var/lib/oemunlocks and /var/lib/oemunlocks-staging outside
   all nginx roots/aliases, owned by the verified worker, mode 0700. Application
   database must be 0600. Keep staging and production data/config independent.
   Confirm no other vhost/alias exposes these paths. Plan encrypted private data
   backups and restore checks separately from source, with agreed retention.
5. Configure actual dedicated FPM pool environment: OEM_DATA_DIR,
   OEM_ADMIN_USER, OEM_ADMIN_PASSWORD_HASH, TELEGRAM_BOT_TOKEN,
   TELEGRAM_CHAT_ID. Securely reuse approved existing Telegram credentials;
   do not scrape or print them. Generate a unique administrator password through
   a hidden-input workflow and PHP password_hash(PASSWORD_DEFAULT); store only
   the hash server-side and deliver the password through an approved private
   channel. Check correct literal handling of password-hash dollar signs.
6. Bring up an access-restricted staging TLS vhost with the same proposed FPM
   routing/protections but separate pool/socket/data. This step needs its own
   authorization to install config and reload services. No production DNS switch.
   Validate nginx -t and php-fpm8.3 -t without logging secret config dumps.
7. Run CLI preflight only with staging environment and worker identity: it writes
   schema/cache, so it was deliberately not run against production. It is not
   a substitute for requests through nginx/FPM. Do not expose a diagnostic page
   containing phpinfo or getenv values.
8. Verify staging HTTPS redirects/certificate, intended homepage and both service
   links ($45 bootloader, $30 FRP), live rates/expiry/fail-closed outages, no PHP
   source disclosure, no private data/dotfile/legacy backup access, body/rate
   limits returning appropriate errors, and cookie Secure/HttpOnly/SameSite.
   Verify manager 401 without credentials, 503 when unconfigured, valid login,
   bad CSRF, duplicate TXID/quote rejection, stale update rejection, and audited
   transitions. Submit synthetic orders with no real payment; confirm persistence
   and private permissions. Start with Telegram unset; a real notification test
   requires explicit messaging authorization and a staging destination. Check
   sent/failed indicators honestly; no customer email service exists.
9. Verify all five environment settings via application behavior without showing
   values. Confirm independent payment, ownership and eligibility review procedures
   with the administrator. Remove disposable staging records/data after evidence
   capture; never delete real production orders as test cleanup.
10. At an approved cutover window, take a final backup and keep checkout from
    accepting orders during a multi-file switch. Install only manifest files via
    temporary files/atomic renames, then approved OEM nginx/FPM changes; config
    tests precede the authorized reload. Verify wallet bytes, syntax, homepage,
    prices, manager auth and private storage immediately. No real notification
    or synthetic production payment/order without separate authorization.
    Check /api/, /landing/, redirects and other sites still behave as before.
    Monitor errors, order persistence, disk growth and notification failures.

## Rollback

Rollback triggers include source disclosure, wallet mismatch, storage/quote/order
failure, broken authentication, or routing regressions. Stop accepting new orders
first; retain all post-cutover data and audit records. Restore overwritten OEM
runtime files from the verified backup and remove only the inventoried newly
introduced runtime files. Restore the exact prior OEM nginx file/link and FPM
configuration, validate config, then perform only the authorized reload. Preserve
/api/ and /landing/ proxies. Recheck homepage, TLS and legacy routes.

Never overwrite current orders.sqlite with the pre-release snapshot as a normal
code rollback: that loses accepted orders. Back up post-cutover data consistently
and retain it privately for reconciliation. Reverting to the legacy storefront
does not migrate new orders into its old handler; staff must keep access through
an isolated compatible manager and reconcile outstanding paid requests. Restore
data only through a separately approved recovery operation. Record the rollback
timestamp, affected orders and verification results without exposing customer data.

## Original preparation boundary

Ready for review: pinned source snapshot, six passing syntax checks, exact wallet
comparison, source hash inventory, two offline nginx candidates, and this plan.
No application code fixes, PR edits, production modifications, merge, or deployment
were made. Approval may authorize staging/configuration preparation first; it
must not be interpreted as confirmation that unresolved tests or review gates pass.


## Continuation: confirmed defects and verified fixes

All source changes for this continuation are confined to projects/oemunlocks/
on a local checkout of PR branch import/oemunlocks-20261019-211335, extending original head
28610c6ed9e12d13ecd12a5237369c0cea80486a. No deployment or service action is included.
The local fix commit is identified in the accompanying release report; it is not yet published. Do not deploy
a later branch head without repeating verification.

### Actual impact and disposition

- **Quote retention: confirmed availability defect.** Anonymous fresh sessions can
  issue durable quotes indefinitely. The session limit is not a global limit;
  the growing SQLite store shares capacity with customer orders. New checkout
  calls now prune only quotes whose creation time is strictly older than 86400
  seconds, with an index on created_at. Cleanup precedes both quote reuse and
  creation, even if live pricing is unavailable. Quotes within the 24-hour
  reporting window remain, including those past the 15-minute send deadline.
  Orders keep full quote evidence; no orders or audit events are deleted.
  Cleanup bounds retention during continuing use, not issuance volume within
  a day or filesystem high-water size. SQLite reuses free pages; no VACUUM or
  production cleanup was run. Web-server limits, session cleanup and disk
  monitoring remain necessary. No unbounded-data-loss claim is made.
- **Session binding: confirmed quote-consumption defect.** Before this fix, someone
  possessing a leaked quote ID could obtain their own CSRF token, submit that
  quote from another session, and consume its unique order slot before its owner.
  The ID is random 128-bit; this is not practical guessing, price tampering,
  administrator bypass, or proof of payment. Submission now also requires the
  quote to exist in the submitting session's server-side quote list. A failed
  cross-session attempt does not consume it; the original owner can still submit.
  Losing/clearing the original browser session requires staff recovery of a paid
  request, not another payment. This limitation is documented.
- **Secure cookies: confirmed default weakness; current OEM nginx is configured
  to pass HTTPS.** Cookies now default to Secure even without that variable and
  do not trust arbitrary forwarded headers. Only PHP's disposable cli-server may
  honor OEM_TEST_HTTP=1 for plain-HTTP tests; FPM cannot opt out through this flag.
  Current nginx on-disk transport settings reduce the original risk, but actual
  external Set-Cookie behavior remains a staging gate.
- **Preflight: confirmed misleading readiness check.** A fresh cached rate could
  previously make preflight report live pricing ready while outbound requests
  were broken. Preflight now forces a live fetch. Normal checkout still caches
  rates for 60 seconds. A failed live check leaves an existing cache unchanged.

### Tests and review status

- Added six socket-independent regressions using actual PHP functions/handlers
  with temporary private databases and sessions. All six pass on the fixes.
  Against original head they reproduce binding, retention, cookie and preflight
  failures (five assertion/subtest failures), while reporting-window rejection
  and unchanged wallet checks pass.
- Added three HTTP integration cases for cross-session theft, cleanup retaining
  late-reportable quotes/order audit evidence, and default Secure cookies.
  Adjusted the disposable HTTP server to request its explicit test cookie opt-out.
- Ran the entire HTTP integration suite: all 25 tests stop in setUp at socket
  creation with PermissionError: Operation not permitted. No HTTP assertions ran.
  These are sandbox errors, not 25 demonstrated code failures or a passing run.
- Six PHP syntax checks pass; git diff --check passes. The regression suite does
  not replace HTTP/SAPI/routing verification. Logs are available locally in
  /root/oem-release-preparation/{original-regressions,fixed-regressions,fixed-integration}.log.
- CodeRabbit has now submitted a COMMENTED review for original head 28610c6,
  with three inline findings: retention, Secure cookie defaults, and live
  preflight. The fixes address these findings; no review thread was automatically
  resolved and no approval is claimed. The local fix commit needs review after publication.
  Original-head CodeRabbit status now reports success and check-runs returns an
  empty list. Reading main branch protection is unavailable through this
  integration; required-check rules are not established. Its walkthrough also
  reports a docstring coverage warning and PHPMD parser
  failures for two PHP files; these are not evidence of valid PHP syntax failure
  (PHP 8.3 lint passes). No blanket docstring/style rewrite was added.
- Production payment.php remains at the recorded SHA256 and the PR wallet is
  byte-for-byte equal to the original and production wallet. No secret values,
  customer data, production configuration or unrelated source were printed/changed.

### Read-only host checks: evidence and limitations

Repeated inspection confirms the OEM disk vhost still uses /var/www/oemunlocks,
index.html, HTTP-to-HTTPS redirect, existing TLS paths, the shared PHP 8.3 socket,
and unchanged /api/ and /landing/ upstreams. www.conf declares www-data worker
and socket ownership, with no active OEM env[] entries. No private production or
staging directory exists at the proposed /var/lib/oemunlocks paths. These facts
are disk observations, not proof that a different OEM_DATA_DIR is absent.

The execution sandbox exposes only its own processes, not host nginx/FPM.
The shared socket is mapped here as uid/gid 65534 (nobody), mode 0660; that does
not establish actual host worker identity. No host FPM environment values can be
inferred from that mapping. Socket connections and HTTP requests are denied;
nginx -T and FPM -tt also cannot finish their host configuration checks here.
Presence/permissions of the actual configured storage therefore remain unknown.
/proc/PID/environ alone would not establish effective pool env[] assignments,
which FPM can apply after process creation; it is not used as proof of absence.

The included tools/host-readonly-check.py privately captures potentially sensitive
config dumps, emits only selected routing, environment-presence booleans, process
identity and storage metadata, and makes a direct FastCGI request through the
existing OEM socket. The temporary PHP probe lives outside served roots, does
not include the OEM application, start a session, connect to SQLite, chmod storage,
write customer data, or send messages. It removes itself afterward. It does not
override PHP/FPM configuration or relax path restrictions; if FPM forbids /tmp
scripts, report that limitation and do not alter production to make the probe run.
Its supplied HTTPS parameter is not evidence of nginx's externally observed
transport. GET checks cover only existing root, preview and proxy routes; no
checkout/order endpoint is invoked. All these effective checks were blocked here.

### Publishing blocker and exact Termius commands

GitHub create-tree write failed with HTTP 403, Resource not accessible by
integration. Shell Git fetch could not resolve github.com. The connected read
API works, but these routes cannot publish a commit. No remote commit/ref was
changed. A local commit and Git bundle contain all fixes and this plan. Publishing
is a remaining task, not a claimed success. No force push is requested.

Use the actual host with sudo rights and its existing /root/ai-workspace checkout.
The bundle is /root/oem-release-preparation/oem-pr2-fixes.bundle; if it is not on
the host, copy this nonsecret artifact to that exact path first. The bundle is
based on original PR head 28610c6 and contains only project-scoped changes.
These commands fetch source and add a disposable checkout, without merging or
writing served files. They intentionally stop if the remote head has changed;
reconcile intervening changes rather than forcing the branch.

```bash
set -e
cd /root/ai-workspace
git fetch origin refs/heads/import/oemunlocks-20261019-211335
oem_remote_sha=$(git rev-parse FETCH_HEAD)
test "$oem_remote_sha" = 28610c6ed9e12d13ecd12a5237369c0cea80486a
git bundle verify /root/oem-release-preparation/oem-pr2-fixes.bundle
git fetch /root/oem-release-preparation/oem-pr2-fixes.bundle refs/heads/import/oemunlocks-20261019-211335
oem_fix_sha=$(git rev-parse FETCH_HEAD)
printf 'Testing OEM commit: %s\n' "$oem_fix_sha"
oem_test_run=$(mktemp -d /tmp/oem-pr2-tests.XXXXXX)
git worktree add --detach "$oem_test_run/repo" "$oem_fix_sha"
cd "$oem_test_run/repo/projects/oemunlocks"
sudo python3 tools/host-readonly-check.py
```

The read-only diagnostic runs separately from the test gate, so a socket-blocked
test runner does not prevent collecting host evidence. Do not run preflight with
production data: it creates schema/cache and is intentionally excluded from these
read-only commands. Do not install packages or change a pool to make a check pass.
The diagnostic scratch script removes itself; the disposable checkout remains
for review. Its output must not be interpreted as deployment approval.

From that same directory, the following separate block verifies the commit and
pushes only if the test and syntax commands pass. `set -e` prevents later lint
success from masking test failure. Publishing source does not authorize deployment.

```bash
set -eu
oem_fix_sha=$(git rev-parse HEAD)
unset OEM_TEST_ROOT PHP_COMMAND
python3 tests/quote_regression.py
python3 tests/integration.py
find . -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
git push origin "$oem_fix_sha:refs/heads/import/oemunlocks-20261019-211335"
```

The normal fast-forward push rejects a changed remote branch. A test failure
must be investigated, not bypassed. Preserve the reported SHA and outputs for
updating release evidence. Do not merge, deploy or reload services from these steps.

### Remaining release blockers

0. Publish the local fix commit: current API write permission and sandbox network
   restrictions prevented pushing. The remote PR still contains original code.
1. Full 25-test HTTP integration run on the exact fix commit and staging checks
   through actual nginx/FPM/TLS; six CLI regressions alone do not satisfy this gate.
2. Effective worker identity and five environment-variable presence checks,
   private storage ownership/modes/writability and exclusion from all served
   roots/aliases, session persistence, CA/live pricing, and manager authentication.
3. Backups plus a tested restoration path and permission/config validation of
   the offline OEM nginx/FPM proposal. Existing backup-source exposure and missing
   nginx request/login/body limits remain unchanged in production.
4. New-head CodeRabbit/required checks and unresolved review threads. No merge
   readiness claim follows from the original-head COMMENTED review or walkthrough.
5. Staging notification test requires explicit messaging authorization; no real
   Telegram or customer notification was sent. No customer email integration exists.

### Exact production changes still awaiting approval

- Back up existing OEM web files, OEM nginx/FPM/secret configuration and any private
  order data; verify restoration, and retain a final cutover snapshot.
- Create separate private production/staging directories outside all served roots,
  owned by the verified worker (0700; SQLite 0600), with backup/monitoring policy.
- Add the proposed dedicated OEM FPM pool/socket and five required process variables;
  establish administrator username and securely generated password hash. No secrets
  belong in the repository or webroot; no default password is introduced.
- Apply the reviewed OEM vhost and two http-context rate zones, with dotfile,
  database and legacy backup denial, 16k PHP request-body caps, request/login
  throttling and the dedicated socket. Preserve existing root/index/TLS, redirects,
  /api/, /landing/ and all unrelated sites. Candidates remain uninstalled/untested.
- After separate staging approval, verify the isolated staging release and all
  gates. Then install only the nine deploy-files.txt runtime paths at the approved
  commit. No tools, tests, docs, landing import, secrets or database gets deployed.
- Perform configuration validation and an explicitly approved FPM/nginx reload at
  cutover, followed by smoke checks. None has happened. Roll back code/config
  according to the earlier plan while retaining all newly accepted orders/audits;
  never overwrite them with a pre-release database snapshot.
