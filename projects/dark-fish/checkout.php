<?php
declare(strict_types=1);

const BTC_ADDRESS = '1BvBMSEYstWetqTFn5Au4m4GFg7xWBwgWV';
const SERVICE_PRICES = ['bootloader' => 45, 'frp' => 30];
const QUOTE_LIFETIME = 1800;

final class CheckoutError extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function servicePrice($service): int
{
    if (!is_string($service) || !isset(SERVICE_PRICES[$service])) {
        throw new CheckoutError('Choose a valid service: bootloader or frp.');
    }
    return SERVICE_PRICES[$service];
}

function fetchJson(string $url): array
{
    $request = curl_init($url);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
    if ($body === false || $status !== 200) {
        throw new CheckoutError('The price or Bitcoin verification service is unavailable. Please retry.', 503);
    }
    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new CheckoutError('The upstream service returned invalid data.', 503);
    }
    if (!is_array($data)) {
        throw new CheckoutError('The upstream service returned invalid data.', 503);
    }
    return $data;
}

function quoteSatoshis(int $usd, $rate): int
{
    // Coinbase returns its spot amount as a decimal string.
    if ((!is_string($rate) && !is_int($rate) && !is_float($rate)) || !is_numeric($rate)) {
        throw new CheckoutError('A valid Bitcoin price is unavailable.', 503);
    }
    $rate = (float) $rate;
    if (!is_finite($rate) || $rate <= 0) {
        throw new CheckoutError('A valid Bitcoin price is unavailable.', 503);
    }
    $satoshis = ceil($usd / $rate * 100000000);
    if (!is_finite($satoshis) || $satoshis < 1 || $satoshis > 2100000000000000) {
        throw new CheckoutError('A valid Bitcoin quote is unavailable.', 503);
    }
    return (int) $satoshis;
}

function bitcoinPrice(): float
{
    $data = fetchJson('https://api.coinbase.com/v2/prices/BTC-USD/spot');
    $rate = $data['data']['amount'] ?? null;
    if (($data['data']['base'] ?? null) !== 'BTC' || ($data['data']['currency'] ?? null) !== 'USD') {
        throw new CheckoutError('A valid Bitcoin price is unavailable.', 503);
    }
    quoteSatoshis(45, $rate);
    quoteSatoshis(30, $rate);
    return (float) $rate;
}

function orderDatabase(): PDO
{
    $path = getenv('DARK_FISH_ORDER_DB');
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: __DIR__;
    $directory = $path ? realpath(dirname($path)) : false;
    if (!$path || $path[0] !== '/' || !$directory || $directory === $root || str_starts_with($directory, $root . '/')) {
        throw new CheckoutError('Checkout is unavailable until private order storage is configured.', 503);
    }
    $oldMask = umask(0077);
    try {
        $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } finally {
        umask($oldMask);
    }
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('CREATE TABLE IF NOT EXISTS quotes (
        id TEXT PRIMARY KEY, service TEXT NOT NULL, satoshis INTEGER NOT NULL,
        created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY, quote_id TEXT NOT NULL UNIQUE, txid TEXT NOT NULL UNIQUE,
        model TEXT NOT NULL, imei TEXT NOT NULL, verified_at INTEGER NOT NULL,
        FOREIGN KEY (quote_id) REFERENCES quotes(id)
    )');
    return $db;
}

function verifyTransaction(array $transaction, string $txid, array $quote): void
{
    if (($transaction['txid'] ?? null) !== $txid || ($transaction['status']['confirmed'] ?? false) !== true) {
        throw new CheckoutError('The Bitcoin transaction is not confirmed yet. Retry after confirmation.', 409);
    }
    $blockTime = $transaction['status']['block_time'] ?? null;
    if (!is_int($blockTime) || $blockTime < (int) $quote['created_at'] || $blockTime > (int) $quote['expires_at']) {
        throw new CheckoutError('This transaction is outside the quote payment window.', 409);
    }
    $paid = 0;
    foreach ($transaction['vout'] ?? [] as $output) {
        if (($output['scriptpubkey_address'] ?? null) === BTC_ADDRESS) {
            $value = $output['value'] ?? null;
            if (!is_int($value) || $value < 0 || $value > 2100000000000000 - $paid) {
                throw new CheckoutError('The Bitcoin service returned an invalid payment.', 503);
            }
            $paid += $value;
        }
    }
    if ($paid !== (int) $quote['satoshis']) {
        throw new CheckoutError('The transaction does not pay the quoted amount to the checkout address.', 409);
    }
    // A transaction spending from the shared destination cannot prove a new customer payment.
    foreach ($transaction['vin'] ?? [] as $input) {
        if (($input['prevout']['scriptpubkey_address'] ?? null) === BTC_ADDRESS) {
            throw new CheckoutError('This transaction spends from the checkout address.', 409);
        }
    }
}

function submitOrder(PDO $db, array $input, ?callable $loadTransaction = null): array
{
    foreach (['quoteId' => 64, 'txid' => 64, 'model' => 120, 'imei' => 64] as $field => $limit) {
        if (!isset($input[$field]) || !is_string($input[$field])) {
            throw new CheckoutError('Provide device details, a quote, and a Bitcoin transaction ID.');
        }
        $input[$field] = trim($input[$field]);
        if ($input[$field] === '' || strlen($input[$field]) > $limit) {
            throw new CheckoutError('One or more order fields are invalid.');
        }
    }
    $txid = strtolower($input['txid']);
    if (!preg_match('/^[a-f0-9]{64}$/D', $txid) || !preg_match('/^[a-f0-9]{64}$/D', $input['quoteId'])) {
        throw new CheckoutError('The quote or Bitcoin transaction ID is invalid.');
    }
    $statement = $db->prepare('SELECT * FROM quotes WHERE id = ?');
    $statement->execute([$input['quoteId']]);
    $quote = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        throw new CheckoutError('The quote was not found. Reload checkout.', 409);
    }
    $statement = $db->prepare('SELECT id, txid FROM orders WHERE quote_id = ?');
    $statement->execute([$quote['id']]);
    $existing = $statement->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['txid'] !== $txid) {
            throw new CheckoutError('This quote already has a paid order.', 409);
        }
        return ['status' => 'verified', 'orderId' => $existing['id']];
    }
    // Keep expired quotes verifiable for payments confirmed during their payment window.
    $loadTransaction ??= fn (string $id): array => fetchJson('https://blockstream.info/api/tx/' . $id);
    $transaction = $loadTransaction($txid);
    verifyTransaction($transaction, $txid, $quote);
    $orderId = bin2hex(random_bytes(16));
    try {
        $statement = $db->prepare('INSERT INTO orders (id, quote_id, txid, model, imei, verified_at) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$orderId, $quote['id'], $txid, $input['model'], $input['imei'], time()]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            $statement = $db->prepare('SELECT id, txid FROM orders WHERE quote_id = ?');
            $statement->execute([$quote['id']]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            if ($existing && $existing['txid'] === $txid) {
                return ['status' => 'verified', 'orderId' => $existing['id']];
            }
            throw new CheckoutError('This transaction or quote has already been used for an order.', 409);
        }
        throw $error;
    }
    return ['status' => 'verified', 'orderId' => $orderId];
}
