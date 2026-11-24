# blueprinttechnology.dev sanitized archive

Contains the site-specific source from server-blueprint and its redesign worktree, two preview deployments, a narrow snapshot of the live shared webroot (only `index.html` and its Blueprint-branded image), and site-specific Nginx/SSL-renewal metadata. Nginx SSL private-key files and certificates are not included.

The production vhost points at `/var/www/html`. That directory also contains PHP payment/application files and assets; those shared files were not copied as a whole and must not be removed as part of this site's cleanup. `index.php`, `app.php`, `payment.php`, and `assets/payment.js` are intentionally omitted from the site-source copies because they are shared payment/backend code or wallet-sensitive. `blueprint-ad-studio.service` and the PHP-FPM payment drop-in are shared and not included.

The baseline source files are copied under `site-baseline/`. Its 1,175,629-byte image asset has the same SHA-256 as `shared-webroot-snapshot/assets/blueprint-scorezt-terminal.png`, so it is stored once. Nested Git metadata was not copied. The separate `blueprinttechnology.dev` certificate renewal metadata may refer to protected certificate paths; no key or certificate data is present here. Any crypto wallet address literal in the included source was replaced with `REDACTED_WALLET_ADDRESS`.

Known Blueprint orders database backup requiring separate encrypted archival: `/root/payment-options-backups/blueprint-orders-postcutover-20261026T001135Z.sqlite3` (28,672 bytes). It was not copied or opened.
