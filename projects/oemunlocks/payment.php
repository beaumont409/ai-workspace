<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require __DIR__ . '/process_order.php';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
$service = $_GET['service'] ?? 'bootloader';
if (!is_string($service) || !isset(OEM_SERVICES[$service])) {
    http_response_code(400);
    exit('Unknown service. Return to the homepage and choose a service.');
}
$ready = true;
try {
    $csrf = oem_csrf();
    $db = oem_db();
    // Receipts remain available during a market data outage.
    if (!isset($_SESSION['receipt'])) {
        $quote = oem_quote($db, $service);
    }
} catch (Throwable $error) {
    $ready = false;
    http_response_code(503);
    error_log('OEM payment form requires private storage and a current BTC rate.');
}
$receipt = $_SESSION['receipt'] ?? null;
unset($_SESSION['receipt']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= oem_escape(OEM_SERVICES[$service]) ?> · OEM Unlocks7T</title>
<link rel="stylesheet" href="assets/site.css">
<script src="assets/prices.js" defer></script>
</head>
<body>
<header class="site-header"><a class="brand" href="index.html">O.E.M. <span>UNLOCKS7T</span></a><a class="header-link" href="index.html#services">← All services</a></header>
<main class="container checkout">
<p class="eyebrow">Service request</p>
<h1><?= oem_escape(OEM_SERVICES[$service]) ?></h1>
<?php if (is_array($receipt)): ?>
<div class="alert" role="status"><strong>Order saved for review.</strong><br>Reference: <?= oem_escape((string)$receipt['reference']) ?><br>Keep this reference. Payment has not yet been verified. We will use the email you supplied for follow-up.</div>
<p>Please do not send another payment or submit the same transaction again.</p>
<a class="text-link" href="index.html">Return to services →</a>
<?php elseif (!$ready): ?>
<div class="alert error" role="alert">A current payment quote could not load. Please try again later before sending a payment.</div>
<?php else: ?>
<p class="lede">Service price: <strong>$<?= number_format($quote['usd_cents'] / 100, 2) ?> USD</strong>. Review your Bitcoin quote, then submit your device details and transaction reference for manual review.</p>
<section class="panel" aria-labelledby="payment-title">
<h2 id="payment-title">Bitcoin payment</h2>
<p>Send exactly:</p>
<p class="amount"><?= oem_escape($quote['btc']) ?> BTC</p>
<p data-btc-total="<?= oem_escape($quote['btc']) ?>">Live USD equivalent: <span data-usd-equivalent>≈ $<?= number_format((float)$quote['btc'] * $quote['rate_usd_cents'] / 100, 2) ?> USD at quoted rate</span></p>
<p class="hint">Quoted at $<?= number_format($quote['rate_usd_cents'] / 100, 2) ?> USD per BTC. Network fees are separate; the destination must receive the full quoted BTC amount.</p>
<p class="hint" data-quote-expires="<?= $quote['expires_at'] ?>">Send this quoted amount by <?= gmdate('H:i', $quote['expires_at']) ?> UTC (15-minute quote). The BTC total stays fixed during this quote.</p>
<a class="text-link" href="payment.php?service=<?= oem_escape($service) ?>">Refresh quote after expiry</a>
<p>To this Bitcoin address:</p>
<code class="wallet" id="wallet"><?= oem_escape(OEM_WALLET) ?></code>
<p class="hint">Use the Bitcoin network. A transaction ID is a reference, not proof of payment. Amount, destination, and confirmations are checked before fulfillment.</p>
</section>
<form class="panel" method="post" action="process_order.php">
<h2>Submit for review</h2>
<input type="hidden" name="csrf" value="<?= oem_escape($csrf) ?>">
<input type="hidden" name="service" value="<?= oem_escape($service) ?>">
<input type="hidden" name="quote_id" value="<?= oem_escape($quote['id']) ?>">
<div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" autocomplete="email" maxlength="254" placeholder="you@example.com" required></div>
<div class="field"><label for="model">Device model</label><input type="text" id="model" name="model" minlength="2" maxlength="120" placeholder="Brand and exact model" required><p class="hint">Include the carrier if known. Do not enter account passwords or recovery codes.</p></div>
<div class="field"><label for="txid">Bitcoin transaction ID</label><input type="text" id="txid" name="txid" minlength="64" maxlength="64" pattern="[a-fA-F0-9]{64}" spellcheck="false" autocapitalize="none" placeholder="64-character transaction ID" required></div>
<div class="field"><label class="check"><input type="checkbox" name="ownership" value="1" required><span>I own this device or have the owner’s authorization. I understand that eligibility and payment require review before fulfillment.</span></label></div>
<p class="hint">Your email, device model, and transaction reference are stored so your request can be reviewed. FRP service requires proof of ownership before fulfillment.</p>
<button class="order-btn" type="submit">SUBMIT FOR REVIEW <span aria-hidden="true">→</span></button>
</form>
<?php endif; ?>
</main>
<footer class="site-footer">© 2026 O.E.M. Unlocks7T. All rights reserved.</footer>
</body>
</html>
