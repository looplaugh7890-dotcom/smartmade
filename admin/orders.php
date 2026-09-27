<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$q = trim($_GET['q'] ?? '');

$validStatuses = ['pending', 'awaiting_payment', 'paid', 'in_production', 'ready', 'shipped', 'completed', 'cancelled', 'refunded'];
$validPayments = ['unpaid', 'authorised', 'paid', 'failed', 'refunded', 'partially_refunded'];

$where = ['1=1'];
$params = [];

if (in_array($status, $validStatuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if (in_array($payment, $validPayments, true)) {
    $where[] = 'o.payment_status = ?';
    $params[] = $payment;
}
if ($q !== '') {
    $where[] = '(o.order_number LIKE ? OR o.name LIKE ? OR o.email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$countPdo = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$countPdo->execute($params);
$total = (int) $countPdo->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT o.*,
        (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
    FROM orders o
    WHERE $whereSql
    ORDER BY o.placed_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$orders = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS revenue,
        SUM(status IN ('pending','awaiting_payment')) AS open_count,
        SUM(status = 'in_production') AS production_count
    FROM orders
")->fetch();

$pageTitle = 'Orders';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Orders</h1>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="admin-btn admin-btn-secondary">Reports</a>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-tile"><span class="stat-label">All orders</span><span class="stat-value"><?= (int)$stats['total'] ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Paid revenue</span><span class="stat-value"><?= money($stats['revenue']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Awaiting payment</span><span class="stat-value"><?= (int)$stats['open_count'] ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">In production</span><span class="stat-value"><?= (int)$stats['production_count'] ?></span></div>
</div>

<form method="get" class="admin-toolbar">
    <input type="text" name="q" class="admin-input" placeholder="Order number, name or email…" value="<?= e($q) ?>">
    <select name="status" class="admin-input">
        <option value="">All statuses</option>
        <?php foreach ($validStatuses as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="payment" class="admin-input">
        <option value="">All payment states</option>
        <?php foreach ($validPayments as $p): ?>
            <option value="<?= $p ?>" <?= $payment === $p ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $p)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
    <a href="<?= SITE_URL ?>/admin/orders.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="admin-empty">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong><?= e($o['order_number']) ?></strong></td>
                            <td>
                                <?= e($o['name']) ?><br>
                                <small style="color:#6B6B6B;"><?= e($o['email']) ?></small>
                            </td>
                            <td><?= (int)$o['item_count'] ?></td>
                            <td><strong><?= money($o['grand_total']) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= in_array($o['payment_status'], ['paid', 'partially_refunded']) ? 'success' : (in_array($o['payment_status'], ['failed', 'refunded']) ? 'error' : 'info') ?>">
                                    <?= ucwords(str_replace('_', ' ', $o['payment_status'])) ?>
                                </span>
                            </td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><?= format_date($o['placed_at'], 'j M Y H:i') ?></td>
                            <td><a href="<?= SITE_URL ?>/admin/order_view.php?id=<?= (int)$o['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['hasPrev']): ?>
                <a href="<?= pagination_url($pagination['page'] - 1, ['q' => $q, 'status' => $status, 'payment' => $payment]) ?>">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= pagination_url($i, ['q' => $q, 'status' => $status, 'payment' => $payment]) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?>
                <a href="<?= pagination_url($pagination['page'] + 1, ['q' => $q, 'status' => $status, 'payment' => $payment]) ?>">→</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
