<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

function review_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

function review_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('oem_review_session');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Could not start review session.');
    }
    if (!isset($_SESSION['review_csrf'])) {
        $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
    }
}

function review_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $directory = realpath('/var/lib/oemunlocks');
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/oemunlocks');
    if ($directory === false || $documentRoot === false
        || $directory === $documentRoot || str_starts_with($directory, $documentRoot . '/')) {
        throw new RuntimeException('Private review storage is unavailable.');
    }
    $path = $directory . '/reviews.sqlite';
    if (is_link($path)) {
        throw new RuntimeException('Review database cannot be a symbolic link.');
    }

    umask(0077);
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = DELETE');
    $db->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        display_name TEXT NOT NULL,
        review_text TEXT NOT NULL,
        created_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
        moderated_at TEXT
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS reviews_status_created ON reviews (status, id)');
    $db->exec("CREATE TABLE IF NOT EXISTS review_rate_limits (
        session_hash TEXT PRIMARY KEY,
        window_started INTEGER NOT NULL,
        attempts INTEGER NOT NULL
    )");
    if (!chmod($path, 0600)) {
        throw new RuntimeException('Review database permissions could not be applied.');
    }
    return $db;
}

function review_length(string $value): int
{
    if (preg_match('//u', $value) !== 1 || preg_match_all('/./us', $value, $matches) === false) {
        return -1;
    }
    return count($matches[0]);
}

function review_rate_limit(PDO $db): bool
{
    $now = time();
    $sessionHash = hash('sha256', session_id());
    $db->exec('BEGIN IMMEDIATE');
    try {
        $cleanup = $db->prepare('DELETE FROM review_rate_limits WHERE window_started < ?');
        $cleanup->execute([$now - 900]);
        $select = $db->prepare('SELECT window_started, attempts FROM review_rate_limits WHERE session_hash = ?');
        $select->execute([$sessionHash]);
        $row = $select->fetch();
        if ($row && (int)$row['window_started'] >= $now - 900 && (int)$row['attempts'] >= 3) {
            $db->exec('COMMIT');
            return false;
        }
        if ($row && (int)$row['window_started'] >= $now - 900) {
            $update = $db->prepare('UPDATE review_rate_limits SET attempts = attempts + 1 WHERE session_hash = ?');
            $update->execute([$sessionHash]);
        } else {
            $replace = $db->prepare('INSERT INTO review_rate_limits (session_hash, window_started, attempts)
                VALUES (?, ?, 1) ON CONFLICT(session_hash) DO UPDATE SET window_started = excluded.window_started, attempts = 1');
            $replace->execute([$sessionHash, $now]);
        }
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

try {
    review_session();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $db = review_db();
        $approved = $db->query("SELECT display_name, review_text FROM reviews
            WHERE status = 'approved' ORDER BY id ASC LIMIT 200")->fetchAll();
        review_reply(200, ['csrf' => $_SESSION['review_csrf'], 'reviews' => $approved]);
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        review_reply(405, ['message' => 'This request is not supported.']);
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        review_reply(413, ['message' => 'Your review is too large to submit.']);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !in_array($origin, ['https://oemunlocks.com', 'https://www.oemunlocks.com'], true)) {
        review_reply(403, ['message' => 'Please submit this form from the website.']);
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals((string)$_SESSION['review_csrf'], $csrf)) {
        review_reply(403, ['message' => 'This form has expired. Refresh the page and try again.']);
    }
    $honeypot = $_POST['website'] ?? '';
    if (!is_string($honeypot) || $honeypot !== '') {
        review_reply(400, ['message' => 'Please check your submission and try again.']);
    }

    $name = $_POST['display_name'] ?? null;
    $text = $_POST['review_text'] ?? null;
    if (!is_string($name) || !is_string($text)) {
        review_reply(422, ['message' => 'Please complete both fields.', 'errors' => [
            'display_name' => 'Enter a display name.',
            'review_text' => 'Enter your review.',
        ]]);
    }
    $name = trim($name);
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    $nameLength = review_length($name);
    $textLength = review_length($text);
    $errors = [];
    if ($nameLength < 1 || $nameLength > 60 || preg_match('/[\p{Cc}\r\n]/u', $name) === 1) {
        $errors['display_name'] = 'Use 1 to 60 characters for your display name.';
    }
    if ($textLength < 10 || $textLength > 1500
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) === 1) {
        $errors['review_text'] = 'Use 10 to 1,500 characters for your review.';
    }
    if ($errors !== []) {
        review_reply(422, ['message' => 'Please correct the fields below.', 'errors' => $errors]);
    }

    $db = review_db();
    if (!review_rate_limit($db)) {
        review_reply(429, ['message' => 'You have submitted several reviews recently. Please try again in 15 minutes.']);
    }
    $insert = $db->prepare("INSERT INTO reviews (display_name, review_text, created_at, status)
        VALUES (?, ?, ?, 'pending')");
    $insert->execute([$name, $text, gmdate('Y-m-d H:i:s')]);
    review_reply(201, ['message' => 'Thank you. Your review has been submitted for approval. It will appear on the site after approval.']);
} catch (Throwable $error) {
    error_log('OEM review endpoint error: ' . $error->getMessage());
    review_reply(500, ['message' => 'We could not save your review just now. Please try again.']);
}
