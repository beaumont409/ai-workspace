<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

function reply(int $code, string $message, ?string $reference = null): never
{
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        $data = ['status' => $code < 300 ? 'success' : 'error', 'message' => $message];
        if ($reference !== null) {
            $data['reference'] = $reference;
        }
        oem_json($code, $data);
    }
    if ($code < 300) {
        $_SESSION['receipt'] = ['reference' => $reference, 'service' => oem_input('service')];
        header('Location: payment.php?service=' . rawurlencode(oem_input('service')), true, 303);
        exit;
    }
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Order submission</title><link rel="stylesheet" href="assets/site.css"><main class="container checkout"><h1>Check your submission</h1>';
    echo '<p class="alert error">' . oem_escape($message) . '</p><a href="index.html">Return to services</a></main></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reply(405, 'Use the order form to submit your details.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    reply(413, 'Submission is too large.');
}
try {
    if (!oem_valid_csrf()) {
        reply(403, 'Your form expired. Reload the payment page and try again.');
    }
    $service = oem_input('service');
    $email = oem_input('email');
    $model = oem_input('model');
    $txid = strtolower(oem_input('txid'));
    if (!isset(OEM_SERVICES[$service])) {
        reply(422, 'Choose a valid service.');
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        reply(422, 'Enter a valid email address.');
    }
    if (strlen($model) < 2 || strlen($model) > 120
        || !preg_match('//u', $model) || preg_match('/[\x00-\x1f\x7f]/', $model)) {
        reply(422, 'Enter a device model between 2 and 120 characters.');
    }
    if (!preg_match('/\A[a-f0-9]{64}\z/', $txid)) {
        reply(422, 'Enter a valid 64-character Bitcoin transaction ID.');
    }
    if (oem_input('ownership') !== '1') {
        reply(422, 'Confirm that you own the device or have the owner’s authorization.');
    }
    $quoteId = oem_input('quote_id');
    $db = oem_db();
    $getQuote = $db->prepare('SELECT * FROM quotes WHERE id = ?');
    $getQuote->execute([$quoteId]);
    $quote = $getQuote->fetch();
    if (!isset($_SESSION['quotes'][$quoteId])
        || !is_array($quote) || $quote['service'] !== $service
        || $quote['created_at'] < time() - 86400) {
        reply(409, 'Your quote could not be found. Return to checkout. If you already paid, keep your transaction ID and do not pay again.');
    }
    if (!oem_rate_limit($db)) {
        header('Retry-After: 600');
        reply(429, 'Too many submissions. Please wait ten minutes and try again.');
    }
    $order = [
        'id' => 'OEM-' . strtoupper(bin2hex(random_bytes(8))),
        'service' => $service,
    ];
    $now = gmdate('c');
    try {
        $insert = $db->prepare('INSERT INTO orders
            (id, created_at, updated_at, service, email, model, txid, amount_btc, wallet,
             usd_cents, rate_usd_cents, quote_id, quoted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $order['id'], $now, $now, $service, $email, $model, $txid,
            $quote['btc'], OEM_WALLET, $quote['usd_cents'],
            $quote['rate_usd_cents'], $quoteId, $quote['created_at'],
        ]);
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? null) === 19) {
            reply(409, 'This transaction or quote has already been submitted. Do not pay again.');
        }
        throw $error;
    }
    if (isset($_SESSION['quotes'][$quoteId])) {
        $_SESSION['quotes'][$quoteId]['used'] = true;
    }
    // The stored order remains available even if the notification service fails.
    try {
        $notification = oem_notify($order);
        $update = $db->prepare('UPDATE orders SET notification = ? WHERE id = ?');
        $update->execute([$notification, $order['id']]);
    } catch (Throwable $error) {
        error_log('OEM notification failed; order remains saved.');
    }
    reply(201, 'Order saved for review. Payment has not yet been verified.', $order['id']);
} catch (Throwable $error) {
    error_log('OEM order submission failed; check server storage and PHP configuration.');
    reply(503, 'We could not save this submission. Keep your transaction ID and try again later; do not pay again.');
}
