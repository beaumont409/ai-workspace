<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/checkout.php';

$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}
function rejects(callable $action, int $status): void
{
    try {
        $action();
    } catch (CheckoutError $error) {
        check($error->status === $status, $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected checkout rejection');
}

check(servicePrice('bootloader') === 45, 'Bootloader price');
check(servicePrice('frp') === 30, 'FRP price');
foreach ([null, '', 'unknown', [], 'FRP'] as $service) {
    rejects(fn () => servicePrice($service), 400);
}
check(quoteSatoshis(45, '60000') === 75000, 'Bootloader amount');
check(quoteSatoshis(30, '60000') === 50000, 'FRP amount');
check(quoteSatoshis(30, '70000') === 42858, 'Round up to whole satoshis');
foreach ([null, [], true, 'bad', 0, -1, INF, NAN, '1e999', '1e-999', 1e-300] as $price) {
    rejects(fn () => quoteSatoshis(30, $price), 503);
}

$directory = sys_get_temp_dir() . '/dark-fish-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory . '/orders.sqlite';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
putenv('DARK_FISH_ORDER_DB');
rejects(fn () => orderDatabase(), 503);
putenv('DARK_FISH_ORDER_DB=' . dirname(__DIR__) . '/public.sqlite');
rejects(fn () => orderDatabase(), 503);
putenv('DARK_FISH_ORDER_DB=' . $path);
try {
    $db = orderDatabase();
    check((fileperms($path) & 0777) === 0600, 'Database must be private');
    $now = time();
    $quoteId = str_repeat('a', 64);
    $txid = str_repeat('b', 64);
    $quote = ['id' => $quoteId, 'service' => 'frp', 'satoshis' => 50000, 'created_at' => $now - 10, 'expires_at' => $now + 1800];
    $insert = $db->prepare('INSERT INTO quotes VALUES (?, ?, ?, ?, ?)');
    $insert->execute(array_values($quote));
    $transaction = ['txid' => $txid, 'status' => ['confirmed' => true, 'block_time' => $now],
        'vout' => [['scriptpubkey_address' => BTC_ADDRESS, 'value' => 50000]], 'vin' => []];
    $input = ['quoteId' => $quoteId, 'txid' => $txid, 'model' => 'Test Phone', 'imei' => 'TEST-SERIAL'];

    $bad = $transaction;
    $bad['status']['confirmed'] = false;
    rejects(fn () => submitOrder($db, $input, fn () => $bad), 409);
    check((int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 0, 'Unconfirmed payment must not create an order');
    $bad = $transaction;
    $bad['vout'][0]['value'] = 49999;
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    $bad['vout'][0]['value'] = 50001;
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    $bad['vout'][0]['value'] = -1;
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 503);
    $split = $transaction;
    $split['vout'] = [
        ['scriptpubkey_address' => BTC_ADDRESS, 'value' => 20000],
        ['scriptpubkey_address' => BTC_ADDRESS, 'value' => 30000],
        ['scriptpubkey_address' => 'another-address', 'value' => 10000],
    ];
    verifyTransaction($split, $txid, $quote);
    $split['vout'][] = ['scriptpubkey_address' => BTC_ADDRESS, 'value' => 1];
    rejects(fn () => verifyTransaction($split, $txid, $quote), 409);
    $bad = $transaction;
    $bad['vout'][0]['scriptpubkey_address'] = 'wrong-address';
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    foreach ([$now - 20, $now + 1900] as $blockTime) {
        $bad = $transaction;
        $bad['status']['block_time'] = $blockTime;
        rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    }
    $bad = $transaction;
    $bad['txid'] = str_repeat('c', 64);
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    $bad = $transaction;
    $bad['vin'] = [['prevout' => ['scriptpubkey_address' => BTC_ADDRESS]]];
    rejects(fn () => verifyTransaction($bad, $txid, $quote), 409);
    foreach (['model' => '', 'imei' => str_repeat('x', 65), 'txid' => '../bad', 'quoteId' => []] as $field => $value) {
        $badInput = array_replace($input, [$field => $value]);
        rejects(fn () => submitOrder($db, $badInput), 400);
    }
    $result = submitOrder($db, $input, fn () => $transaction);
    check($result['status'] === 'verified', 'Confirmed payment accepted');
    $saved = $db->query('SELECT * FROM orders')->fetch(PDO::FETCH_ASSOC);
    check($saved['model'] === $input['model'] && $saved['imei'] === $input['imei'] && $saved['txid'] === $txid, 'Device and transaction persisted');
    $replayed = submitOrder($db, $input, fn () => throw new RuntimeException('Should not refetch a saved order'));
    check($result === $replayed, 'Retries return the existing order');
    check((int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Retry does not duplicate orders');
    $secondQuoteId = str_repeat('c', 64);
    $insert->execute([$secondQuoteId, 'bootloader', 50000, $now - 10, $now + 1800]);
    rejects(fn () => submitOrder($db, array_replace($input, ['quoteId' => $secondQuoteId]), fn () => $transaction), 409);
    check((int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Transaction cannot be reused for a different quote');

    // Insert a competing order after the initial lookup, before submitOrder inserts.
    foreach ([true, false] as $sameTransaction) {
        $raceQuoteId = str_repeat($sameTransaction ? 'd' : 'e', 64);
        $raceTxid = str_repeat($sameTransaction ? 'f' : '1', 64);
        $insert->execute([$raceQuoteId, 'frp', 50000, $now - 10, $now + 1800]);
        $raceInput = array_replace($input, ['quoteId' => $raceQuoteId, 'txid' => $raceTxid]);
        $raceTransaction = array_replace($transaction, ['txid' => $raceTxid]);
        $existingId = 'existing-' . $raceQuoteId;
        $loadTransaction = function () use ($db, $existingId, $raceQuoteId, $raceTxid, $sameTransaction, $raceTransaction, $now): array {
            $statement = $db->prepare('INSERT INTO orders VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([$existingId, $raceQuoteId, $sameTransaction ? $raceTxid : str_repeat('2', 64), 'Other Phone', 'OTHER-SERIAL', $now]);
            return $raceTransaction;
        };
        if ($sameTransaction) {
            $retried = submitOrder($db, $raceInput, $loadTransaction);
            check($retried === ['status' => 'verified', 'orderId' => $existingId], 'Insert conflict returns the matching order');
        } else {
            rejects(fn () => submitOrder($db, $raceInput, $loadTransaction), 409);
        }
        $statement = $db->prepare('SELECT COUNT(*) FROM orders WHERE quote_id = ?');
        $statement->execute([$raceQuoteId]);
        check((int) $statement->fetchColumn() === 1, 'Insert conflict does not duplicate orders');
    }
} finally {
    unset($db, $insert);
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
    putenv('DARK_FISH_ORDER_DB');
}
echo "Passed $count checkout assertions.\n";
