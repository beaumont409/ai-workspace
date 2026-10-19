<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app.php';
$failed = false;
try {
    oem_csrf();
    $sessionsReady = true;
} catch (Throwable $error) {
    $sessionsReady = false;
}
function check(string $label, bool $success): void
{
    global $failed;
    echo ($success ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $failed = $failed || !$success;
}
check('PHP 8.3 or newer', PHP_VERSION_ID >= 80300);
check('PHP sessions writable', $sessionsReady);
check('PDO SQLite', in_array('sqlite', PDO::getAvailableDrivers(), true));
check('cURL for exchange rates and Telegram', function_exists('curl_init'));
check('Administrator username configured', (getenv('OEM_ADMIN_USER') ?: '') !== '');
$hash = getenv('OEM_ADMIN_PASSWORD_HASH') ?: '';
check('Administrator password hash configured', (password_get_info($hash)['algoName'] ?? 'unknown') !== 'unknown');
check('Telegram environment configured', (getenv('TELEGRAM_BOT_TOKEN') ?: '') !== '' && (getenv('TELEGRAM_CHAT_ID') ?: '') !== '');
try {
    $db = oem_db();
    check('Writable private order storage', true);
    oem_rate($db);
    check('Current BTC/USD rate available', true);
} catch (Throwable $error) {
    check('Storage and live pricing ready (check local configuration)', false);
}
echo "No Telegram messages sent. This CLI check does not verify PHP-FPM environment, TLS, or Nginx routing.\n";
exit($failed ? 1 : 0);
