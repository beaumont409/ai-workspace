<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    oem_json(405, ['message' => 'Method not allowed.']);
}

$rates = [];
try {
    $btc = oem_rate(oem_db());
    $rates['BTC'] = ['usd_cents' => $btc['usd_cents'], 'fetched_at' => $btc['fetched_at']];
} catch (Throwable) {
    $rates['BTC'] = null;
}
foreach (['LTC', 'XMR'] as $currency) {
    try {
        $rate = payment_rate($currency);
        $rates[$currency] = ['usd_cents' => $rate['usd_cents'], 'fetched_at' => $rate['fetched_at']];
    } catch (Throwable) {
        $rates[$currency] = null;
    }
}

$services = [];
foreach (OEM_SERVICES as $key => $name) {
    $estimates = [];
    foreach ($rates as $currency => $rate) {
        if ($rate === null) {
            $estimates[$currency] = null;
            continue;
        }
        try {
            $estimates[$currency] = [
                'amount' => payment_amount(OEM_PRICES_USD_CENTS[$key], $rate['usd_cents'], $currency),
                'as_of' => gmdate('c', $rate['fetched_at']),
            ];
        } catch (Throwable) {
            $estimates[$currency] = null;
        }
    }
    $services[$key] = [
        'name' => $name,
        'usd' => number_format(OEM_PRICES_USD_CENTS[$key] / 100, 2, '.', ''),
        'estimates' => $estimates,
    ];
}
$btcRate = $rates['BTC'];
oem_json(200, [
    'services' => $services,
    // Keep the legacy fields used by any existing BTC checkout display.
    'btc_usd' => $btcRate === null ? null : $btcRate['usd_cents'] / 100,
    'as_of' => $btcRate === null ? null : gmdate('c', $btcRate['fetched_at']),
    'source' => 'Coinbase spot',
    'sources' => ['BTC' => 'Coinbase spot', 'LTC' => 'CoinGecko', 'XMR' => 'CoinGecko'],
]);
