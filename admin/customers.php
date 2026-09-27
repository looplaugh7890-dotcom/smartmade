<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$q = trim($_GET['q'] ?? '');

$where = '1=1';
$params = [];
if ($q !== '') {
    $where = '(c.name LIKE ? OR c.email LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}

$count = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
        (SELECT COALESCE(SUM(o.grand_total), 0) FROM orders o WHERE o.customer_id = c.id AND o.payment_status = 'paid') AS lifetime_value,
        (SELECT MAX(o.placed_at) FROM orders o WHERE o.customer_id = c.id) AS last_order_at
    FROM customers c
    WHERE $where
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Customers</h1>
</div>

<form method="get" class="admin-toolbar">
    <input type="text" name="q" class="admin-input" placeholder="Search name or email…" value="<?= e($q) ?>">
    <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
    <a href="<?= SITE_URL ?>/admin/customers.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Orders</th><th>Lifetime value</th><th>Last order</th><th>Joined</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="7" class="admin-empty">No customers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><strong><?= e($c['name']) ?></strong></td>
                            <td><?= e($c['email']) ?></td>
                            <td><?= (int)$c['order_count'] ?></td>
                            <td><strong><?= money($c['lifetime_value']) ?></strong></td>
                            <td><?= $c['last_order_at'] ? format_date($c['last_order_at']) : '—' ?></td>
                            <td><?= format_date($c['created_at']) ?></td>
                            <td><a href="<?= SITE_URL ?>/admin/customer_view.php?id=<?= (int)$c['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['hasPrev']): ?><a href="<?= pagination_url($pagination['page'] - 1, ['q' => $q]) ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= pagination_url($i, ['q' => $q]) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?><a href="<?= pagination_url($pagination['page'] + 1, ['q' => $q]) ?>">→</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
