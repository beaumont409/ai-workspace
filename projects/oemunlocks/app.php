<?php
declare(strict_types=1);

// No credentials or customer data belong in this source directory.
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');

const OEM_WALLET = 'bc1qtema35w6qeeqmwnuxr3u9f3nx3gvqc79cl7dxv';
const OEM_SERVICES = ['bootloader' => 'Bootloader Unlock', 'frp' => 'FRP Removal'];
const OEM_PRICES_USD_CENTS = ['bootloader' => 4500, 'frp' => 3000];
const OEM_STATUSES = ['pending_review', 'payment_verified', 'completed', 'rejected'];

function oem_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oem_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('oem_session');
        session_set_cookie_params([
            'httponly' => true,
            // Only the disposable built-in HTTP test server may opt out.
            'secure' => PHP_SAPI !== 'cli-server' || getenv('OEM_TEST_HTTP') !== '1'
                || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Strict',
            'path' => '/',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Session unavailable.');
        }
    }
}

function oem_csrf(): string
{
    oem_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function oem_valid_csrf(): bool
{
    oem_session();
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function oem_input(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function oem_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }
    $configured = getenv('OEM_DATA_DIR') ?: '';
    $directory = realpath($configured);
    $root = realpath(__DIR__);
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: $root;
    if ($configured === '' || !str_starts_with($configured, '/')
        || $directory === false || !is_dir($directory) || !is_writable($directory)
        || $directory === $root || str_starts_with($directory, $root . '/')
        || $directory === $documentRoot || str_starts_with($directory, $documentRoot . '/')) {
        throw new RuntimeException('Configure writable storage outside the document root.');
    }
    // Enforce private storage; SQLite's journal inherits this restrictive mask.
    umask(0077);
    if (!chmod($directory, 0700)) {
        throw new RuntimeException('Private storage permissions could not be applied.');
    }
    $path = $directory . '/orders.sqlite';
    if (is_link($path)) {
        throw new RuntimeException('Database cannot be a symbolic link.');
    }
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if (!chmod($path, 0600)) {
        throw new RuntimeException('Private database permissions could not be applied.');
    }
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
        service TEXT NOT NULL, email TEXT NOT NULL, model TEXT NOT NULL,
        txid TEXT NOT NULL UNIQUE, amount_btc TEXT NOT NULL, wallet TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending_review',
        notification TEXT NOT NULL DEFAULT 'not_sent',
        notes TEXT NOT NULL DEFAULT '', version INTEGER NOT NULL DEFAULT 0,
        usd_cents INTEGER NOT NULL, rate_usd_cents INTEGER NOT NULL,
        quote_id TEXT NOT NULL UNIQUE, quoted_at INTEGER NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        client TEXT PRIMARY KEY, started INTEGER NOT NULL, attempts INTEGER NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS order_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_id TEXT NOT NULL,
        created_at TEXT NOT NULL, actor TEXT NOT NULL, status TEXT NOT NULL,
        notes TEXT NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS market_rate (
        id INTEGER PRIMARY KEY CHECK (id = 1), usd_cents INTEGER NOT NULL,
        fetched_at INTEGER NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS quotes (
        id TEXT PRIMARY KEY, service TEXT NOT NULL, usd_cents INTEGER NOT NULL,
        rate_usd_cents INTEGER NOT NULL, btc TEXT NOT NULL,
        created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS quotes_created_at ON quotes (created_at)');
    return $db;
}

function oem_rate(PDO $db, bool $refresh = false): array
{
    $cached = $db->query('SELECT usd_cents, fetched_at FROM market_rate WHERE id = 1')->fetch();
    if (!$refresh && $cached && (int)$cached['fetched_at'] >= time() - 60
        && (int)$cached['fetched_at'] <= time() && (int)$cached['usd_cents'] > 0) {
        return ['usd_cents' => (int)$cached['usd_cents'], 'fetched_at' => (int)$cached['fetched_at']];
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required for current prices.');
    }
    $handle = curl_init('https://api.coinbase.com/v2/prices/BTC-USD/spot');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $data = is_string($response) ? json_decode($response, true) : null;
    $amount = $data['data']['amount'] ?? null;
    if ($code !== 200 || !is_string($amount)
        || ($data['data']['currency'] ?? '') !== 'USD'
        || !preg_match('/^\\d{1,9}(?:\\.\\d{1,8})?$/', $amount)
        || (float)$amount < 1 || (float)$amount > 1000000000) {
        throw new RuntimeException('Current Bitcoin price is unavailable.');
    }
    $rate = ['usd_cents' => (int)round((float)$amount * 100), 'fetched_at' => time()];
    $save = $db->prepare('INSERT INTO market_rate (id, usd_cents, fetched_at) VALUES (1, ?, ?)
        ON CONFLICT(id) DO UPDATE SET usd_cents = excluded.usd_cents, fetched_at = excluded.fetched_at');
    $save->execute([$rate['usd_cents'], $rate['fetched_at']]);
    return $rate;
}

function oem_btc(int $usdCents, int $rateCents): string
{
    // Round UP to one satoshi using integer arithmetic.
    $satoshis = intdiv($usdCents * 100000000 + $rateCents - 1, $rateCents);
    return sprintf('%d.%08d', intdiv($satoshis, 100000000), $satoshis % 100000000);
}

function oem_quote(PDO $db, string $service): array
{
    oem_session();
    // Retain the full reporting window, not just the 15-minute send window.
    // Orders contain their own immutable quote evidence; never prune orders/events.
    $prune = $db->prepare('DELETE FROM quotes WHERE created_at < ?');
    $prune->execute([time() - 86400]);
    foreach ($_SESSION['quotes'] ?? [] as $id => $quote) {
        if ($quote['created_at'] < time() - 86400) {
            unset($_SESSION['quotes'][$id]);
        } elseif ($quote['service'] === $service && $quote['expires_at'] > time()
            && empty($quote['used'])) {
            return $quote;
        }
    }
    if (count($_SESSION['quotes'] ?? []) >= 50) {
        throw new RuntimeException('Too many open quotes.');
    }
    $rate = oem_rate($db);
    $quote = [
        'id' => bin2hex(random_bytes(16)), 'service' => $service,
        'usd_cents' => OEM_PRICES_USD_CENTS[$service],
        'rate_usd_cents' => $rate['usd_cents'],
        'btc' => oem_btc(OEM_PRICES_USD_CENTS[$service], $rate['usd_cents']),
        'created_at' => time(), 'expires_at' => time() + 900, 'used' => false,
    ];
    $save = $db->prepare('INSERT INTO quotes
        (id, service, usd_cents, rate_usd_cents, btc, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    $save->execute([
        $quote['id'], $service, $quote['usd_cents'], $quote['rate_usd_cents'],
        $quote['btc'], $quote['created_at'], $quote['expires_at'],
    ]);
    $_SESSION['quotes'][$quote['id']] = $quote;
    return $quote;
}

function oem_rate_limit(PDO $db): bool
{
    $now = time();
    // Keep only hashed connection addresses; never trust client-supplied forwarded headers.
    $client = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . OEM_WALLET);
    $db->exec('BEGIN IMMEDIATE');
    try {
        $cleanup = $db->prepare('DELETE FROM rate_limits WHERE started < ?');
        $cleanup->execute([$now - 600]);
        $query = $db->prepare('SELECT attempts FROM rate_limits WHERE client = ?');
        $query->execute([$client]);
        $row = $query->fetch();
        if ($row && (int)$row['attempts'] >= 5) {
            $db->exec('COMMIT');
            return false;
        }
        $statement = $db->prepare('INSERT INTO rate_limits (client, started, attempts)
            VALUES (?, ?, 1) ON CONFLICT(client) DO UPDATE SET attempts = attempts + 1');
        $statement->execute([$client, $now]);
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function oem_notify(array $order): string
{
    $token = getenv('TELEGRAM_BOT_TOKEN') ?: '';
    $chat = getenv('TELEGRAM_CHAT_ID') ?: '';
    if ($token === '' || $chat === '') {
        return 'not_configured';
    }
    if (!function_exists('curl_init')) {
        return 'failed';
    }
    $handle = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    if ($handle === false) {
        return 'failed';
    }
    // Plain text avoids Markdown injection. Full customer details stay in the manager.
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chat,
            'text' => "OEM order awaiting review\nReference: " . $order['id']
                . "\nService: " . OEM_SERVICES[$order['service']]
                . "\nPayment is not yet verified.",
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $response = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $result = is_string($response) ? json_decode($response, true) : null;
    return $code === 200 && is_array($result) && ($result['ok'] ?? false) === true
        ? 'sent' : 'failed';
}

function oem_json(int $code, array $data): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    exit;
}

function oem_admin(): string
{
    $username = getenv('OEM_ADMIN_USER') ?: '';
    $hash = getenv('OEM_ADMIN_PASSWORD_HASH') ?: '';
    if ($username === '' || $hash === '' || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') {
        http_response_code(503);
        exit('Order manager requires administrator configuration.');
    }
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $password = $_SERVER['PHP_AUTH_PW'] ?? '';
    $validPassword = password_verify($password, $hash);
    if (!hash_equals($username, $user) || !$validPassword) {
        header('WWW-Authenticate: Basic realm="OEM Order Manager", charset="UTF-8"');
        http_response_code(401);
        exit('Authentication required.');
    }
    oem_session();
    return $username;
}
