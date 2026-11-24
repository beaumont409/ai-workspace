# oemunlocks.com sanitized archive

Contains repository application source from the locally available `origin/main` snapshot, the current deployed website files and assets, the two isolated backend source trees (without virtual environments), the cinematic preview, and site-specific Nginx/systemd/SSL renewal configuration references.

Excluded: `.env` files, token-bearing `payment.*` files from the deployed tree and server-blueprint mirror, TLS certificates/private keys, live or backup database contents, payment records, and runtime state. Crypto wallet address literals found in source were replaced with `REDACTED_WALLET_ADDRESS`. The Git source snapshot passed the local gitleaks scan; the deployed mirror's payment variants were flagged for a Telegram bot token and are omitted. Do not treat file presence as proof that services are active.

Known private database/records locations for separate encrypted archival: `/root/server-blueprint-backups/oemunlocks-payments-20261025T220545Z.sqlite3`, `/root/server-blueprint-backups/oemunlocks-payments-20261025T233014Z.sqlite3`, `/root/payment-options-backups/oemunlocks-orders-20261026T000126Z.sqlite3`, and `/root/oemunlocks-reviews-before-removal-20261110T034244043604Z.sqlite`. These files were not copied or opened.

Environment files not copied: `/opt/oemunlocks/.env`, `/opt/oemunlocks-new/.env`, `/etc/oemunlocks-form.env`, and `/etc/oemunlocks-production/production.env` (the last path is referenced by the unit). Private-key and credential material requires encrypted archival outside Git.

A separate 132-byte `/var/www/oemunlocks.com/index.html` path snapshot is included under `legacy-webroot-snapshot/`; the canonical Nginx vhost uses `/var/www/oemunlocks`.
