<?php
declare(strict_types=1);

const PAYMENT_COINS = [
    'BTC' => ['name' => 'Bitcoin', 'decimals' => 8, 'scheme' => 'bitcoin', 'id' => 'bitcoin', 'wallet' => 'REDACTED_WALLET_ADDRESS'],
    'LTC' => ['name' => 'Litecoin', 'decimals' => 8, 'scheme' => 'litecoin', 'id' => 'litecoin', 'wallet' => 'REDACTED_WALLET_ADDRESS'],
    'XMR' => ['name' => 'Monero', 'decimals' => 12, 'scheme' => 'monero', 'id' => 'monero', 'wallet' => 'REDACTED_WALLET_ADDRESS'],
];

function payment_coin(mixed $currency): array {
    if (!is_string($currency) || !isset(PAYMENT_COINS[$currency])) {
        throw new InvalidArgumentException('Choose BTC, LTC or XMR.');
    }
    return PAYMENT_COINS[$currency];
}

function payment_parse_rate(array $data, string $currency, int $now): array {
    $coin = payment_coin($currency);
    $row = $data[$coin['id']] ?? [];
    $price = $row['usd'] ?? null;
    $at = $row['last_updated_at'] ?? null;
    // The upstream request explicitly selects two decimal USD precision.
    if ((!is_int($price) && !is_float($price)) || !is_finite((float)$price)
        || $price < 1 || $price > 10000000 || !is_int($at) || $at > $now || $at < $now - 120) {
        throw new RuntimeException('Current exchange rate unavailable.');
    }
    return ['usd_cents' => (int)round($price * 100), 'fetched_at' => $at, 'source' => 'coingecko'];
}

function payment_rate(string $currency): array {
    $coin = payment_coin($currency);
    $handle = curl_init('https://api.coingecko.com/api/v3/simple/price?ids=' . $coin['id']
        . '&vs_currencies=usd&include_last_updated_at=true&precision=2');
    $body = '';
    curl_setopt_array($handle, [CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'OEMUnlocks7T payment quote/1.0 (+https://oemunlocks.com)',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Cache-Control: no-cache'],
        CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 16384) return 0;
            $body .= $chunk; return strlen($chunk);
        }]);
    $ok = curl_exec($handle); $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle);
    $data = json_decode($body, true);
    if ($ok === false || $code !== 200 || !is_array($data)) throw new RuntimeException('Rate unavailable.');
    return payment_parse_rate($data, $currency, time());
}

function payment_units(int $numerator, int $denominator, string $currency): string {
    $scale = 10 ** payment_coin($currency)['decimals'];
    if ($numerator <= 0 || $denominator <= 0) throw new RuntimeException('Invalid amount.');
    $units = intdiv($numerator, $denominator) + (int)($numerator % $denominator > 0);
    return sprintf('%d.%0' . PAYMENT_COINS[$currency]['decimals'] . 'd', intdiv($units, $scale), $units % $scale);
}

function payment_amount(int $usdCents, int $rateCents, string $currency): string {
    $scale = 10 ** payment_coin($currency)['decimals'];
    if ($usdCents > intdiv(PHP_INT_MAX, $scale)) throw new RuntimeException('Amount outside supported range.');
    return payment_units($usdCents * $scale, $rateCents, $currency);
}

function payment_uri(string $currency, string $amount): string {
    $coin = payment_coin($currency);
    return $coin['scheme'] . ':' . $coin['wallet'] . '?' . ($currency === 'XMR' ? 'tx_amount' : 'amount') . '=' . $amount;
}

function payment_columns(PDO $db, string $table, array $columns): void {
    $existing = array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach ($columns as $name => $type) {
        if (!in_array($name, $existing, true)) $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $type);
    }
}
