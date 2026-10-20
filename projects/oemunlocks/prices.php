<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    oem_json(405, ['message' => 'Method not allowed.']);
}
try {
    $rate = oem_rate(oem_db());
    $services = [];
    foreach (OEM_SERVICES as $key => $name) {
        $services[$key] = [
            'name' => $name, 'usd' => number_format(OEM_PRICES_USD_CENTS[$key] / 100, 2, '.', ''),
            'btc' => oem_btc(OEM_PRICES_USD_CENTS[$key], $rate['usd_cents']),
        ];
    }
    oem_json(200, [
        'services' => $services, 'btc_usd' => $rate['usd_cents'] / 100,
        'as_of' => gmdate('c', $rate['fetched_at']), 'source' => 'Coinbase spot',
    ]);
} catch (Throwable $failure) {
    oem_json(503, ['message' => 'Live BTC conversion is temporarily unavailable.']);
}
