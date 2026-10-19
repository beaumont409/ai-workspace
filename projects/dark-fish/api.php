<?php
declare(strict_types=1);
require_once __DIR__ . '/checkout.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    if ($action === 'price' && $method === 'GET') {
        $result = ['btcUsd' => bitcoinPrice()];
    } elseif ($action === 'quote' && $method === 'GET') {
        $service = $_GET['service'] ?? null;
        $usd = servicePrice($service);
        $db = orderDatabase();
        $satoshis = quoteSatoshis($usd, bitcoinPrice());
        $quoteId = bin2hex(random_bytes(32));
        $createdAt = time();
        $expiresAt = $createdAt + QUOTE_LIFETIME;
        $statement = $db->prepare('INSERT INTO quotes (id, service, satoshis, created_at, expires_at) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$quoteId, $service, $satoshis, $createdAt, $expiresAt]);
        $result = ['quoteId' => $quoteId, 'satoshis' => $satoshis, 'expiresAt' => $expiresAt];
    } elseif ($action === 'order' && $method === 'POST') {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new CheckoutError('Submit the order as JSON.', 415);
        }
        $body = file_get_contents('php://input', false, null, 0, 4097);
        if ($body === false || strlen($body) > 4096) {
            throw new CheckoutError('The order is too large.', 413);
        }
        try {
            $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new CheckoutError('The order is not valid JSON.');
        }
        if (!is_array($input)) {
            throw new CheckoutError('The order is invalid.');
        }
        $result = submitOrder(orderDatabase(), $input);
    } else {
        throw new CheckoutError('Unknown checkout request.', 404);
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (CheckoutError $error) {
    http_response_code($error->status);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Checkout is unavailable. Please retry later.']);
}
