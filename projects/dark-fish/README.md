# Dark Fish checkout

Serve this directory with PHP 8.0+ and the cURL and PDO SQLite extensions. A
static HTML server cannot run checkout. The storefront uses the same-origin
`api.php` price proxy backed by Coinbase's public BTC/USD spot API. Orders are
verified against Blockstream's public Bitcoin mainnet transaction API; neither
API needs a key. Outbound HTTPS must be available.

Set `DARK_FISH_ORDER_DB` to an absolute SQLite file path in a private, writable
directory **outside the web document root**. Back up this database: it holds
device details, quotes, and paid orders, and its unique transaction constraint
prevents the same payment being credited twice. Keep directory permissions at
0700 and database permissions at 0600. Checkout stays unavailable if storage is
not configured.

Example for local development, from this directory:

```sh
install -d -m 700 "$HOME/.local/share/dark-fish"
DARK_FISH_ORDER_DB="$HOME/.local/share/dark-fish/orders.sqlite" php -S 0.0.0.0:8080 -t .
```

A server quote lasts 30 minutes and uses eight decimal places (whole satoshis).
Customers submit device model, IMEI/serial, and the Bitcoin transaction ID. The
server accepts a payment only after it is confirmed, pays the fixed checkout
address, and has a block timestamp within the quote's payment window. A payment
confirmed within that window can be submitted afterward. Late confirmations
need manual reconciliation; do not send a new payment to retry verification.
Only verified orders are stored, and fulfillment remains a manual operation.

The destination address is unchanged. A fixed shared address and public
transaction IDs do not establish the sender's identity. Keep this limitation in
mind when reconciling ownership disputes; this flow does not implement wallet
signatures or allocate per-customer addresses.

Run regression checks from the repository root:

```sh
php projects/dark-fish/tests/checkout_test.php
node --test projects/dark-fish/tests/pages_test.mjs
```

The tests use synthetic transaction data and temporary storage. They never send
Bitcoin or call a real transaction a paid order.
