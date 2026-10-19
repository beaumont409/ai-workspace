<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$admin = oem_admin();
$message = '';
$error = '';
try {
    $db = oem_db();
    $csrf = oem_csrf();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        http_response_code(405);
        exit('Method not allowed.');
    }
    if ($method === 'POST') {
        if (!oem_valid_csrf()) {
            http_response_code(403);
            $error = 'Form expired. Reload and try again.';
        } else {
            $id = oem_input('id');
            $status = oem_input('status');
            $notes = oem_input('notes');
            $version = filter_var(oem_input('version'), FILTER_VALIDATE_INT);
            if (!in_array($status, OEM_STATUSES, true) || strlen($notes) > 2000
                || !preg_match('//u', $notes) || $version === false || $version < 0) {
                http_response_code(422);
                $error = 'Invalid status, notes, or version.';
            } else {
                $db->beginTransaction();
                try {
                    $query = $db->prepare('SELECT status, version FROM orders WHERE id = ?');
                    $query->execute([$id]);
                    $current = $query->fetch();
                    $allowed = [
                        'pending_review' => ['pending_review', 'payment_verified', 'rejected'],
                        'payment_verified' => ['payment_verified', 'completed', 'rejected'],
                        'completed' => ['completed'],
                        'rejected' => ['rejected', 'pending_review'],
                    ];
                    if (!$current) {
                        http_response_code(404);
                        $error = 'Order not found.';
                    } elseif ((int)$current['version'] !== $version) {
                        http_response_code(409);
                        $error = 'Another update changed this order. Reload before saving.';
                    } elseif (!in_array($status, $allowed[$current['status']] ?? [], true)) {
                        http_response_code(422);
                        $error = 'Invalid transition. Verify payment before completing an order.';
                    } elseif ($status === 'payment_verified' && $current['status'] !== 'payment_verified'
                        && oem_input('verification') !== '1') {
                        http_response_code(422);
                        $error = 'Confirm your independent payment and ownership checks first.';
                    } else {
                        $now = gmdate('c');
                        $update = $db->prepare('UPDATE orders SET status = ?, notes = ?,
                            updated_at = ?, version = version + 1 WHERE id = ? AND version = ?');
                        $update->execute([$status, $notes, $now, $id, $version]);
                        if ($update->rowCount() !== 1) {
                            throw new RuntimeException('Concurrent order update.');
                        }
                        $audit = $db->prepare('INSERT INTO order_events
                            (order_id, created_at, actor, status, notes) VALUES (?, ?, ?, ?, ?)');
                        $audit->execute([$id, $now, $admin, $status, $notes]);
                        $message = 'Order updated: ' . $id;
                    }
                    $db->commit();
                } catch (Throwable $failure) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $failure;
                }
            }
        }
    }
    $page = max(1, min(1000000, (int)($_GET['page'] ?? 1)));
    $total = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $query = $db->prepare('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 50 OFFSET ?');
    $query->bindValue(1, ($page - 1) * 50, PDO::PARAM_INT);
    $query->execute();
    $orders = $query->fetchAll();
} catch (Throwable $failure) {
    error_log('OEM order manager storage operation failed.');
    http_response_code(503);
    exit('Order manager storage is unavailable. No success has been reported.');
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OEM Order Manager</title><link rel="stylesheet" href="assets/site.css"></head>
<body>
<header class="site-header"><a class="brand" href="index.html">O.E.M. <span>UNLOCKS7T</span></a><span>Order manager</span></header>
<main class="admin-main">
<p class="eyebrow">Administration · <?= oem_escape($admin) ?></p>
<h1>Orders <span>(<?= $total ?>)</span></h1>
<p class="muted">New submissions are pending review. Before marking payment verified, independently check the Bitcoin transaction, exact destination, amount and confirmations, and verify device ownership and eligibility. A submitted TXID alone is not proof of payment.</p>
<?php if ($message !== ''): ?><p class="alert" role="status"><?= oem_escape($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="alert error" role="alert"><?= oem_escape($error) ?></p><?php endif; ?>
<?php if (!$orders): ?><div class="panel">No orders on this page.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th scope="col">Order</th><th scope="col">Customer / device</th><th scope="col">Transaction</th><th scope="col">Review</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?>
<tr>
<td><strong><?= oem_escape($order['id']) ?></strong><br><?= oem_escape($order['created_at']) ?><br><?= oem_escape(OEM_SERVICES[$order['service']] ?? $order['service']) ?><br><span class="muted">Telegram: <?= oem_escape($order['notification']) ?></span></td>
<td><?= oem_escape($order['email']) ?><br><?= oem_escape($order['model']) ?></td>
<td><code><?= oem_escape($order['txid']) ?></code><br><?= oem_escape($order['amount_btc']) ?> BTC<br>$<?= number_format($order['usd_cents'] / 100, 2) ?> USD service price<br><span class="muted">Quoted BTC rate: $<?= number_format($order['rate_usd_cents'] / 100, 2) ?><br><?= gmdate('c', (int)$order['quoted_at']) ?></span><br><code><?= oem_escape($order['wallet']) ?></code></td>
<td><form method="post">
<input type="hidden" name="csrf" value="<?= oem_escape($csrf) ?>">
<input type="hidden" name="id" value="<?= oem_escape($order['id']) ?>">
<input type="hidden" name="version" value="<?= (int)$order['version'] ?>">
<label for="status-<?= oem_escape($order['id']) ?>">Status</label>
<select id="status-<?= oem_escape($order['id']) ?>" name="status">
<?php foreach (OEM_STATUSES as $status): ?><option value="<?= $status ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= oem_escape(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?>
</select>
<label for="notes-<?= oem_escape($order['id']) ?>">Internal notes</label>
<input id="notes-<?= oem_escape($order['id']) ?>" name="notes" maxlength="2000" value="<?= oem_escape($order['notes']) ?>">
<label class="check"><input type="checkbox" name="verification" value="1"><span>I independently checked payment, ownership, and eligibility (required when marking payment verified).</span></label>
<button class="small-btn" type="submit">Save review</button>
</form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<nav class="pager" aria-label="Order pages"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Previous</a><?php endif; ?><?php if ($page * 50 < $total): ?><a href="?page=<?= $page + 1 ?>">Next →</a><?php endif; ?></nav>
</main>
</body></html>
