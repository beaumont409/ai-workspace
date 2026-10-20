# OEM Unlocks7T

The original OEM storefront, with prominent lime BUY NOW buttons, a responsive
layout, service-specific Bitcoin quotes, persistent orders, and a protected order
manager. Work is confined to the PR; no production deployment is included.

## Prices and customer flow

- Bootloader Unlock: **$45.00 USD**.
- FRP Removal: **$30.00 USD**.
- The homepage, preview, buttons, and checkout use those prices.
- Coinbase's public BTC/USD spot endpoint supplies the conversion:
  https://docs.cdp.coinbase.com/coinbase-app/track-apis/prices
- The server caches the rate for up to 60 seconds. There is no fabricated or
  stale fallback. Conversion failures are shown explicitly.
- Checkout rounds up to one satoshi using integer arithmetic and stores a
  15-minute BTC quote. The BTC total stays fixed; the page also calculates its
  current USD equivalent every minute. Network fees are separate.
- A paid customer can still report a quote for up to 24 hours after creation,
  including after its send-by time. Expired quotes require manual review; never
  automatically approve a late payment.
- Only server-stored quote values are accepted. Submitted amounts, rates, wallet
  addresses, and USD values cannot override the quote.
- The existing Bitcoin wallet is preserved byte-for-byte in app.php.
- Orders are saved as pending_review. A TXID submission is not a verified
  payment. No automatic blockchain verification or automatic device unlocking
  is claimed or performed.

Customers select a service, review their BTC quote, send payment, and submit
their email, device model, transaction ID, and ownership acknowledgement. The
HTML form works without JavaScript. JavaScript adds live currency displays.
Order records contain the quoted USD price, BTC total, rate, time, and wallet.
Duplicate transaction IDs and duplicate quote submissions are rejected.
Successful HTML submissions redirect to a receipt to prevent refresh resubmits.

Quotes must be submitted from the browser session that created them. Keep that
session until reporting payment; if it is lost, retain the transaction ID and
contact staff rather than sending another payment. A quote ID alone cannot be
used to submit an order from a different session.

Checkout removes quote rows strictly older than the 24-hour reporting window,
including abandoned and used quotes. It preserves younger quotes even after the
15-minute send window expires. Orders and audit events are never pruned: orders
retain their own quoted amounts, rate, time, and wallet. An index supports this
cleanup; SQLite reuses freed pages but does not automatically shrink the file.
This is a retention policy, not a hard bound on traffic within 24 hours. Keep
web-server request limits, session cleanup, and disk-capacity monitoring in place.

## Runtime configuration

Requires PHP 8.3+, PDO SQLite, cURL with a working CA certificate store, and
working PHP sessions. Use HTTPS in production.

Session cookies default to Secure, HttpOnly, and SameSite=Strict, even when PHP
does not receive HTTPS from a TLS terminator. Do not trust client-supplied
forwarded headers to decide transport security. The disposable built-in PHP
HTTP integration server explicitly sets OEM_TEST_HTTP=1; only cli-server honors
that test opt-out, and PHP-FPM always uses Secure cookies.

Configure these in the PHP-FPM process environment, outside the document root:

| Variable | Purpose |
| --- | --- |
| OEM_DATA_DIR | Existing dedicated writable directory outside every served web root |
| OEM_ADMIN_USER | Order manager username |
| OEM_ADMIN_PASSWORD_HASH | Output of PHP password_hash with PASSWORD_DEFAULT |
| TELEGRAM_BOT_TOKEN | Existing accessible Telegram bot token |
| TELEGRAM_CHAT_ID | Your existing Telegram destination |

.env.example is a reference only; there is no automatic dotenv loader. Exporting
variables in an SSH shell does not configure an already-running PHP-FPM pool.
Configure the actual pool/process environment and verify it through staging.
Never commit credentials, private customer records, or SQLite files.

The application enforces 0700 on the dedicated data directory and 0600 on its
database, so the directory must be owned by the PHP worker. Do not point this
setting at a shared directory. It must also be backed up separately from source.

## Order management

Open orders.php over HTTPS. HTTP Basic authentication checks the configured
username and password hash; there is no default administrator password. A missing
configuration returns 503 and unauthenticated requests return 401. Browsers may
retain Basic credentials until closed.

Review states: pending_review → payment_verified → completed.
Requests may be rejected; a rejected order can be reopened for review.
Before payment_verified, independently check the real Bitcoin transaction:
destination, amount, confirmations, and whether the same payment is being reused.
Verify device ownership and service eligibility before fulfillment. The manager
requires a confirmation checkbox, records status changes and notes in an audit
table, and rejects stale concurrent edits. Do not place passwords in notes.

Notifications contain only the order reference and service. Full details remain
in the manager. Telegram failures never erase saved orders or claim a message
was delivered; the manager displays sent, failed, or not_configured. No customer
email service is configured; staff must follow up using the stored email.

## Verification

From this directory:

    python3 tests/integration.py
    python3 tests/quote_regression.py
    php tools/preflight.php

The integration suite uses disposable private databases and local PHP servers,
a deterministic cached rate, and no real Telegram messages or payments.
PHP_COMMAND may contain a JSON command array for a custom local runtime.
The quote regression suite invokes actual PHP handlers with disposable data
without sockets; it does not replace HTTP integration and staging verification.

The preflight checks PHP, extensions, configured values, storage, and live
pricing with the rate cache explicitly bypassed; it sends no Telegram message.
An unavailable live service fails preflight even if checkout has a fresh cache.
It does not prove PHP-FPM has the same
environment as CLI, that Nginx routes to this directory, or that TLS works.

## Deployment preparation

Deploy only the runtime paths listed in deploy-files.txt. Imported legacy
landing/newpage content is unrelated to this storefront and is excluded.
Do not deploy the repository root, tests, tools, .env files, or databases.

Before activation, back up the existing OEM files and private data; test the
runtime allowlist in an isolated directory using the intended PHP-FPM pool.
Verify the configured Nginx root/index actually serves the intended homepage.
Verify both service links, private storage, administrator authentication, live
prices, a synthetic order, and one explicitly authorized Telegram notification.
Remove the synthetic order from staging after testing.

Nginx must deny dotfiles and database/backup files and serve PHP through FPM,
not as source. Add request and administrator login rate limiting at the web
server, and limit request bodies. The application additionally limits valid
order submissions to five per connection IP in ten minutes. Do not trust
arbitrary X-Forwarded-For headers.

Keep the existing landing/API proxies and other websites out of this deployment.
No Nginx changes, service restarts, merge, or production deployment are performed
by this PR. Production readiness still depends on the real FPM environment,
domain routing, backups, and a staging smoke test.
