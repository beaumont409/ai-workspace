# Cleanup manifest: oemunlocks.com and blueprinttechnology.dev

**State: STOP. No files, services, databases, certificates, backups, domains, or DNS records were removed or changed. Owner approval of this manifest is required before any deletion.**

Prepared 2026-11-24 from read-only inventory. Candidate sizes reflect current on-disk allocation (`du -sx --block-size=1`) for trees and allocated blocks for individually listed backup files. Actual free-space change can differ because of filesystem metadata, hard links, open files, and later writes. The selected paths are a conditional ceiling, not a promise that every item is safe to remove.

## GitHub archive

- Target repository: `https://github.com/beaumont409/ai-workspace`
- Archive paths: `archives/decommissioned-sites/oemunlocks.com/` and `archives/decommissioned-sites/blueprinttechnology.dev/`
- Each archive has a SHA256SUMS file; checksums passed locally. A redacted gitleaks scan reported zero findings for both final archives.
- Remote API and Git transport verification failed at DNS before authentication; no remote commit/push can be claimed.

## Conditional removal candidates

All candidates require explicit approval. Active runtime trees also require verified stop/disable state, customer/payment data retention decision, and encrypted preservation of secrets before deletion. Git-tracked source in `/root/server-blueprint` requires a separate review because that repository is shared with other sites.

| Group | Exact path | Files | Logical bytes | Allocated bytes | Prerequisite |
|---|---|---:|---:|---:|---|
| Active OEM web roots | `/var/www/oemunlocks` | 39 | 2,277,154 | 2,396,160 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Active OEM web roots | `/var/www/oemunlocks.com` | 1 | 132 | 8,192 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| OEM backend roots | `/opt/oemunlocks` | 5 | 10,206 | 28,672 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| OEM backend roots | `/opt/oemunlocks-new` | 1568 | 45,258,096 | 25,608,192 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Server Blueprint site and preview source | `/root/server-blueprint/sites/oemunlocks.com` | 6 | 27,293 | 53,248 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Server Blueprint site and preview source | `/root/server-blueprint/sites/blueprinttechnology.dev` | 4 | 12,356 | 24,576 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Server Blueprint site and preview source | `/root/server-blueprint/previews/oemunlocks-cinematic` | 11 | 98,174 | 135,168 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Server Blueprint site and preview source | `/root/server-blueprint/previews/blueprinttechnology-3d-v2` | 7 | 59,486 | 90,112 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Server Blueprint site and preview source | `/root/server-blueprint/previews/blueprinttechnology-dev` | 2 | 21,387 | 32,768 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Site deployment backup copies | `/root/site-deployment-backups/oemunlocks.com` | 12 | 49,708 | 94,208 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Site deployment backup copies | `/root/site-deployment-backups/blueprinttechnology.dev` | 6 | 23,083 | 45,056 | Verify site is stopped; retain secrets/data separately; confirm no shared dependency |
| Nginx/systemd/renewal config | `/etc/systemd/system/oemunlocks-fpm.service` | 1 | 376 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/systemd/system/oemunlocks-form.service` | 1 | 282 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/systemd/system/oemunlocks-landing.service` | 1 | 325 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/nginx/sites-available/oemunlocks.com` | 1 | 1,850 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/nginx/sites-available/oemunlocks.com.backup` | 1 | 686 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/nginx/sites-available/blueprinttechnology.dev` | 1 | 510 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/nginx/sites-available/blueprinttechnology.dev.bak` | 1 | 1,326 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/letsencrypt/renewal/oemunlocks.com.conf` | 1 | 543 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| Nginx/systemd/renewal config | `/etc/letsencrypt/renewal/blueprinttechnology.dev.conf` | 1 | 588 | 4,096 | Host runtime access is blocked; archive retained; confirm no aliases/dependencies |
| TLS certificate/key archive | `/etc/letsencrypt/archive/oemunlocks.com` | 8 | 19,830 | 45,056 | Private key material; encrypted archival and TLS dependency check required |
| TLS certificate/key archive | `/etc/letsencrypt/archive/blueprinttechnology.dev` | 8 | 19,982 | 45,056 | Private key material; encrypted archival and TLS dependency check required |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261025-233008.bak` | 1 | 7,651 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__prices.php.20261026-002955.bak` | 1 | 1,500 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261107-081444.bak` | 1 | 8,642 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-134435.bak` | 1 | 25,792 | 28,672 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-004312.bak` | 1 | 4,160 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261025-233008.bak` | 1 | 4,776 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261026-000126.bak` | 1 | 7,651 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-194446.bak` | 1 | 39,451 | 40,960 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261025-233008.bak` | 1 | 511 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-000126.bak` | 1 | 5,875 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261026-000435.bak` | 1 | 511 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261025-220536.bak` | 1 | 12,248 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-131202.bak` | 1 | 9,563 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-213258.bak` | 1 | 30,771 | 32,768 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__branding__custom-cloud.svg.20261105-213258.bak` | 1 | 784 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261107-081542.bak` | 1 | 8,642 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261025-220536.bak` | 1 | 5,875 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261025-220536.bak` | 1 | 4,776 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261025-233008.bak` | 1 | 5,875 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-130701.bak` | 1 | 5,127 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-131157.bak` | 1 | 5,127 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261025-220536.bak` | 1 | 3,288 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-214538.bak` | 1 | 8,196 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__prices.php.20261026-002836.bak` | 1 | 827 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-050932.bak` | 1 | 8,461 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261026-000126.bak` | 1 | 4,776 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-002835.bak` | 1 | 6,247 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-212958.bak` | 1 | 30,003 | 32,768 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-213258.bak` | 1 | 8,473 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-000126.bak` | 1 | 3,288 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-132618.bak` | 1 | 24,888 | 28,672 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-212304.bak` | 1 | 30,003 | 32,768 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__prices.js.20261026-002955.bak` | 1 | 2,327 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261025-220536.bak` | 1 | 7,651 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261025-233008.bak` | 1 | 3,288 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__prices.js.20261026-002835.bak` | 1 | 2,581 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/etc__oemunlocks-production__fpm.conf.20261025-220537.bak` | 1 | 728 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-204922.bak` | 1 | 42,430 | 45,056 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-214538.bak` | 1 | 30,197 | 32,768 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261025-233008.bak` | 1 | 12,248 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-212958.bak` | 1 | 8,196 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261026-000126.bak` | 1 | 12,248 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/etc__oemunlocks-production__fpm.conf.20261025-233008.bak` | 1 | 728 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-134428.bak` | 1 | 5,996 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-130701.bak` | 1 | 9,563 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-002835.bak` | 1 | 3,330 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-132612.bak` | 1 | 5,975 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-194318.bak` | 1 | 39,451 | 40,960 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261025-220739.bak` | 1 | 511 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-004312.bak` | 1 | 6,861 | 8,192 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-212304.bak` | 1 | 8,196 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/index.html` | 1 | 8,696 | 12,288 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/rollback.py` | 1 | 371 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/deployment.json` | 1 | 167 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/protected-files.json` | 1 | 782 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/source/index.html` | 1 | 44,211 | 45,056 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/published/index.html` | 1 | 44,211 | 45,056 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/source/assets/cloudflare-mark.svg` | 1 | 803 | 4,096 | Review backup-retention policy; owner approval |
| Site-specific historical backup file | `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/published/assets/cloudflare-mark.svg` | 1 | 803 | 4,096 | Review backup-retention policy; owner approval |
| Sensitive historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261025-220536.bak` | 1 | 5,313 | 8,192 | Separate encrypted archive first; database/customer or token-bearing payment content |
| Sensitive historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261026-000126.bak` | 1 | 5,313 | 8,192 | Separate encrypted archive first; database/customer or token-bearing payment content |
| Sensitive historical backup file | `/root/server-blueprint-backups/oemunlocks-payments-20261025T233014Z.sqlite3` | 1 | 53,248 | 53,248 | Separate encrypted archive first; database/customer or token-bearing payment content |
| Sensitive historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261025-233008.bak` | 1 | 5,313 | 8,192 | Separate encrypted archive first; database/customer or token-bearing payment content |
| Sensitive historical backup file | `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261026-132621.bak` | 1 | 6,794 | 8,192 | Separate encrypted archive first; database/customer or token-bearing payment content |
| Sensitive historical backup file | `/root/server-blueprint-backups/oemunlocks-payments-20261025T220545Z.sqlite3` | 1 | 53,248 | 53,248 | Separate encrypted archive first; database/customer or token-bearing payment content |

**Conditional upper estimate for the explicitly listed candidates above: 29,589,504 allocated bytes (28.22 MiB), across 1,752 filesystem entries.** This includes runtime directories, config/certificate files, and sensitive backups that are not eligible for deletion until prerequisites are met. It excludes unreviewed temporary worktrees and full disaster-recovery snapshots.

## Sensitive material: separate encrypted archival only

Do not copy these files into Git or display their contents. Filesystem presence and sizes only were recorded. Known database files:
- `/root/server-blueprint-backups/oemunlocks-payments-20261025T220545Z.sqlite3` — 53,248 bytes; database/customer/payment data, not opened or archived.
- `/root/server-blueprint-backups/oemunlocks-payments-20261025T233014Z.sqlite3` — 53,248 bytes; database/customer/payment data, not opened or archived.
- `/root/payment-options-backups/blueprint-orders-postcutover-20261026T001135Z.sqlite3` — 28,672 bytes; suspected Blueprint orders database, not opened or archived.
- `/root/payment-options-backups/oemunlocks-orders-20261026T000126Z.sqlite3` — 53,248 bytes; database/customer/payment data, not opened or archived.
- `/root/oemunlocks-reviews-before-removal-20261110T034244043604Z.sqlite` — 24,576 bytes; database/customer/payment data, not opened or archived.

Other secret-bearing locations identified by path or token scan: `/opt/oemunlocks/.env`, `/opt/oemunlocks-new/.env`, `/etc/oemunlocks-form.env`, `/etc/oemunlocks-production/production.env`, token-bearing `payment.php` variants under `/var/www/oemunlocks` and historical server-blueprint backups, Telegram migration staging copies, wallet backup folders, and TLS private keys under `/etc/letsencrypt/archive/{oemunlocks.com,blueprinttechnology.dev}`. No contents were copied to the repository.

## Protected resources and unresolved dependencies

- `/var/www/html` is shared: the Blueprint vhost points at it, and it contains payment/application PHP, wallet-related assets, and other service content. Do not remove or bulk archive it.
- `/opt/blueprint-ad-studio`, `blueprint-ad-studio.service`, and `/etc/systemd/system/php8.3-fpm.service.d/blueprint-payment.conf` are shared; preserve.
- `redirect.blueprinttechnology.dev`, `unlocks.blueprinttechnology.dev`, and `bonuses.blueprinttechnology.dev` have separate Nginx/SSL artifacts. They are not authorized for removal; preserve their DNS and services.
- `designerluxury.org`, `supergradeawatches.com`, `triangle250r.com` and its subdomains, shared wallets/payment systems, Nginx, OpenClaw, Codex, n8n, and Uptime Kuma are protected.
- `/root/backups/YYYY-MM-DD` contains mixed full-system recovery sets. Fifty-three OEM-named snapshot files were found (6,996 logical bytes / 217,088 allocated bytes); the snapshot trees are not exclusively site-specific, so retain them and exclude them from the recovery estimate.
- `/root/disaster-recovery` and `/root/disaster-recovery-auto` are recovery sources and remain protected.
- `/root/blueprinttechnology-redesign-worktree` and `/root/server-blueprint-website-sync` are full multi-site worktrees. `/root/oemunlocks-futuristic-preview` contains Designer Luxury imagery mixed with OEM files. Preserve all three. OEM PR/import worktrees have unreviewed or dirty changes and are not in the removal estimate.
- `/root/telegram-migration-staging` and wallet/Telegram backup directories require secret handling; not eligible for removal until encrypted archive/review.

## Runtime and schedule checks

- No matching decommission worker process was found, and no matching task appeared in 30 OpenClaw build-job records or 19 local automation job records. This is a point-in-time scan, not a guarantee about other hosts.
- The root cron files were scanned; no site-specific line matched either domain. `systemctl list-timers` was blocked: `Failed to connect to bus: Operation not permitted`.
- Dedicated OEM unit files exist: `oemunlocks-fpm.service`, `oemunlocks-form.service`, `oemunlocks-landing.service`. Active state is unverified because systemd access is blocked.
- `docker ps` was blocked by permission denied on `/var/run/docker.sock`; no live container/volume inventory or negative claim is possible. No matching Compose file was found in the local active source tree.
- Nginx configuration files and SSL renewal metadata were readable and archived. No private keys/certificates were copied.
- `/var/www/html` and all protected/shared roots were excluded from candidates.

## Disk state

`df -P` on `/root` and `/var/www` reported 59,848,952 KB total, 53,888,276 KB used, 5,944,292 KB available, 91% used. No files were deleted; realized recovery is **0 bytes**.

## Per-file historical backup manifest

These matching files are only a subset of the mixed `/root/server-blueprint-backups` directory; rows are not authorization to remove. `SECURE_ARCHIVE_REQUIRED` means a token-bearing payment file or database.

| Path | Bytes | Classification |
|---|---:|---|
| `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261025-220739.bak` | 511 | approval/retention review |
| `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261025-233008.bak` | 511 | approval/retention review |
| `/root/server-blueprint-backups/etc__nginx__sites-enabled__blueprinttechnology.dev.20261026-000435.bak` | 511 | approval/retention review |
| `/root/server-blueprint-backups/etc__oemunlocks-production__fpm.conf.20261025-220537.bak` | 728 | approval/retention review |
| `/root/server-blueprint-backups/etc__oemunlocks-production__fpm.conf.20261025-233008.bak` | 728 | approval/retention review |
| `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/deployment.json` | 167 | approval/retention review |
| `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/index.html` | 8,696 | approval/retention review |
| `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/protected-files.json` | 782 | approval/retention review |
| `/root/server-blueprint-backups/oemunlocks-blue-20261109T180136104741Z/rollback.py` | 371 | approval/retention review |
| `/root/server-blueprint-backups/oemunlocks-payments-20261025T220545Z.sqlite3` | 53,248 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/oemunlocks-payments-20261025T233014Z.sqlite3` | 53,248 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/published/assets/cloudflare-mark.svg` | 803 | approval/retention review |
| `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/published/index.html` | 44,211 | approval/retention review |
| `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/source/assets/cloudflare-mark.svg` | 803 | approval/retention review |
| `/root/server-blueprint-backups/preview/blueprinttechnology-3d-v2-20261107-033520/source/index.html` | 44,211 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261025-220536.bak` | 12,248 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261025-233008.bak` | 12,248 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__app.php.20261026-000126.bak` | 12,248 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__branding__custom-cloud.svg.20261105-213258.bak` | 784 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__prices.js.20261026-002835.bak` | 2,581 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__prices.js.20261026-002955.bak` | 2,327 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261025-220536.bak` | 5,875 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261025-233008.bak` | 5,875 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-000126.bak` | 5,875 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-002835.bak` | 6,247 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-004312.bak` | 6,861 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-050932.bak` | 8,461 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-130701.bak` | 9,563 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-131202.bak` | 9,563 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-132618.bak` | 24,888 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261026-134435.bak` | 25,792 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-212304.bak` | 30,003 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-212958.bak` | 30,003 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-213258.bak` | 30,771 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__assets__site.css.20261105-214538.bak` | 30,197 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261025-220536.bak` | 3,288 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261025-233008.bak` | 3,288 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-000126.bak` | 3,288 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-002835.bak` | 3,330 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-004312.bak` | 4,160 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-130701.bak` | 5,127 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-131157.bak` | 5,127 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-132612.bak` | 5,975 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261026-134428.bak` | 5,996 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-212304.bak` | 8,196 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-212958.bak` | 8,196 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-213258.bak` | 8,473 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261105-214538.bak` | 8,196 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261107-081444.bak` | 8,642 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261107-081542.bak` | 8,642 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-194318.bak` | 39,451 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-194446.bak` | 39,451 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__index.html.20261109-204922.bak` | 42,430 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261025-220536.bak` | 7,651 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261025-233008.bak` | 7,651 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__orders.php.20261026-000126.bak` | 7,651 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261025-220536.bak` | 5,313 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261025-233008.bak` | 5,313 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261026-000126.bak` | 5,313 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/var__www__oemunlocks__payment.php.20261026-132621.bak` | 6,794 | SECURE_ARCHIVE_REQUIRED |
| `/root/server-blueprint-backups/var__www__oemunlocks__prices.php.20261026-002836.bak` | 827 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__prices.php.20261026-002955.bak` | 1,500 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261025-220536.bak` | 4,776 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261025-233008.bak` | 4,776 | approval/retention review |
| `/root/server-blueprint-backups/var__www__oemunlocks__process_order.php.20261026-000126.bak` | 4,776 | approval/retention review |

## Other site-related temporary and development paths (not in recovery estimate)

These were identified by name and size. They are not deletion candidates until their contents, git state, and dependencies are reviewed.
- `/root/blueprint-backup-20260817-031049.tar.gz` — 3,335 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-checkout-backups` — 6,563 logical bytes / 20,480 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-github` — 41,709 logical bytes / 249,856 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-green.log` — 107,472 logical bytes / 110,592 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-jobs` — 591,078 logical bytes / 638,976 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-logo-report.md` — 666 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-logo-trim-report.md` — 396 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-logo-trim.log` — 77,863 logical bytes / 81,920 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-logo.log` — 85,911 logical bytes / 86,016 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-security-audit` — 2,613,935 logical bytes / 2,805,760 allocated bytes; temporary or backup candidate, content review required.
- `/root/blueprint-service-prices.py` — 11,115 logical bytes / 12,288 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-chat-1794517351786821187.env` — 238 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-green-20261108T140708Z` — 268,971 logical bytes / 274,432 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-import-9lFFKM` — 9,738,723 logical bytes / 10,256,384 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-payload-inspect-7hh07pak` — 69,369 logical bytes / 118,784 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-pr2-branch` — 10,067,562 logical bytes / 10,899,456 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-pr2-fixes` — 181,394 logical bytes / 573,440 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-pr2-verify-imiorwxk` — 9,737,952 logical bytes / 10,235,904 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-preview-publish.log` — 168,029 logical bytes / 172,032 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-preview.html` — 3,365 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-release-pr2-28610c6` — 74,920 logical bytes / 135,168 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-release-preparation` — 200,442 logical bytes / 253,952 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-review` — 41,965 logical bytes / 53,248 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-review-form-NrQcia` — 222,368 logical bytes / 229,376 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-reviews-EEyTX7` — 106,646 logical bytes / 114,688 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-text-update-rudbve` — 189,321 logical bytes / 204,800 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-text-update.lock` — 0 logical bytes / 0 allocated bytes; temporary or backup candidate, content review required.
- `/root/oem-update.py` — 34,286 logical bytes / 36,864 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-blue-checkout-20261112T171833068050Z` — 50,309 logical bytes / 65,536 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-cloudflare-footer-candidate` — 65,816 logical bytes / 98,304 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-cloudflare-footer.patch` — 3,248 logical bytes / 4,096 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-dropdown-backup-20261112T154919634355Z` — 51,534 logical bytes / 57,344 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-reviews-before-removal-20261110T034244043604Z.sqlite` — 24,576 logical bytes / 24,576 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-reviews-footer-20261112T160017069119Z` — 51,708 logical bytes / 57,344 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-service-backup-20261112T153944774238Z` — 63,684 logical bytes / 73,728 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-telegram-backup-20261112T173200769328Z` — 242 logical bytes / 8,192 allocated bytes; temporary or backup candidate, content review required.
- `/root/oemunlocks-wallet-backup-20261112T171119777182Z` — 68,553 logical bytes / 77,824 allocated bytes; temporary or backup candidate, content review required.
