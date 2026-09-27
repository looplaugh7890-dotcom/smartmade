<?php

require_once __DIR__ . '/../includes/admin_header.php';

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;

$summary = $pdo->prepare("
    SELECT
        COUNT(*) AS orders,
        COALESCE(SUM(grand_total), 0) AS revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS paid_revenue,
        COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN grand_total END), 0) AS avg_order,
        COALESCE(SUM(discount_total), 0) AS discounts
    FROM orders
    WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$summary->execute([$days]);
$summary = $summary->fetch();

$byStatus = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt, COALESCE(SUM(grand_total), 0) AS total
    FROM orders
    WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY status
    ORDER BY cnt DESC
");
$byStatus->execute([$days]);
$byStatus = $byStatus->fetchAll();

$topProducts = $pdo->prepare("
    SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      AND o.payment_status IN ('paid', 'authorised')
    GROUP BY oi.product_name
    ORDER BY revenue DESC
    LIMIT 10
");
$topProducts->execute([$days]);
$topProducts = $topProducts->fetchAll();

$daily = $pdo->prepare("
    SELECT DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS revenue
    FROM orders
    WHERE placed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(placed_at)
    ORDER BY day ASC
");
$daily->execute([$days]);
$daily = $daily->fetchAll();

$lowStockThreshold = (int) setting('low_stock_threshold', '5');
$lowStock = $pdo->query("
    SELECT p.id, p.name, p.stock_quantity
    FROM products p
    WHERE p.manage_stock = 1 AND p.is_active = 1 AND p.stock_quantity <= $lowStockThreshold
    ORDER BY p.stock_quantity ASC
    LIMIT 15
")->fetchAll();

$lowStockVariants = $pdo->query("
    SELECT p.name, v.size, v.colour_name, v.stock_quantity
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE v.manage_stock = 1 AND v.is_active = 1 AND v.stock_quantity <= $lowStockThreshold
    ORDER BY v.stock_quantity ASC
    LIMIT 15
")->fetchAll();

$recentOrders = $pdo->query("
    SELECT order_number, name, grand_total, status, payment_status, placed_at
    FROM orders
    ORDER BY placed_at DESC
    LIMIT 8
")->fetchAll();

$maxDaily = 1;
foreach ($daily as $d) {
    $maxDaily = max($maxDaily, (int)$d['revenue']);
}

$pageTitle = 'Reports';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Reports</h1>
    <form method="get" class="admin-toolbar" style="margin:0;">
        <select name="days" class="admin-input" onchange="this.form.submit()">
            <?php foreach ([7, 30, 90, 365] as $d): ?>
                <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Last <?= $d ?> days</option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="admin-btn admin-btn-secondary">Apply</button></noscript>
    </form>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-tile"><span class="stat-label">Orders</span><span class="stat-value"><?= (int)$summary['orders'] ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Paid revenue</span><span class="stat-value"><?= money($summary['paid_revenue']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Average order</span><span class="stat-value"><?= money($summary['avg_order']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Discounts given</span><span class="stat-value"><?= money($summary['discounts']) ?></span></div>
</div>

<div class="admin-two-col" style="align-items:start;">
    <div class="admin-card reveal">
        <div class="admin-card-header"><h2 class="admin-card-title">Daily revenue</h2></div>
        <div style="padding:8px 4px;">
            <?php if (empty($daily)): ?>
                <p class="admin-empty">No orders in this period.</p>
            <?php else: ?>
                <?php foreach ($daily as $d): ?>
                    <div style="display:grid;grid-template-columns:90px 1fr 90px;gap:10px;align-items:center;padding:5px 0;font-size:0.85rem;">
                        <span style="color:#6B6B6B;"><?= format_date($d['day'], 'j M') ?></span>
                        <span style="display:block;height:14px;background:#0A0A0A;border-radius:3px;width:<?= max(2, (int)round($d['revenue'] / $maxDaily * 100)) ?>%;"></span>
                        <span style="text-align:right;font-weight:600;"><?= money($d['revenue']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Orders by status</h2></div>
            <div style="padding:4px;">
                <?php if (empty($byStatus)): ?>
                    <p class="admin-empty">Nothing yet.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>Status</th><th>Orders</th><th>Value</th></tr></thead>
                        <tbody>
                            <?php foreach ($byStatus as $row): ?>
                                <tr>
                                    <td><a href="<?= SITE_URL ?>/admin/orders.php?status=<?= e($row['status']) ?>"><?= order_status_badge($row['status']) ?></a></td>
                                    <td><?= (int)$row['cnt'] ?></td>
                                    <td><?= money($row['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-card reveal">
            <div class="admin-card-header"><h2 class="admin-card-title">Best sellers</h2></div>
            <div style="padding:4px;">
                <?php if (empty($topProducts)): ?>
                    <p class="admin-empty">No paid sales yet.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
                        <tbody>
                            <?php foreach ($topProducts as $t): ?>
                                <tr><td><?= e($t['product_name']) ?></td><td><?= (int)$t['qty'] ?></td><td><?= money($t['revenue']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="admin-two-col" style="align-items:start;margin-top:20px;">
    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Low stock (products)</h2>
            <a href="<?= SITE_URL ?>/admin/products.php" class="admin-btn admin-btn-secondary admin-btn-sm">All products</a>
        </div>
        <div style="padding:4px;">
            <?php if (empty($lowStock)): ?>
                <p class="admin-empty">Stock levels look healthy.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Stock</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStock as $p): ?>
                            <tr><td><a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= (int)($p['id'] ?? 0) ?>"><?= e($p['name']) ?></a></td><td><span class="badge badge-error"><?= (int)$p['stock_quantity'] ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card reveal">
        <div class="admin-card-header"><h2 class="admin-card-title">Low stock (variants)</h2></div>
        <div style="padding:4px;">
            <?php if (empty($lowStockVariants)): ?>
                <p class="admin-empty">No variants running low.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Variant</th><th>Stock</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStockVariants as $v): ?>
                            <tr>
                                <td><?= e($v['name']) ?></td>
                                <td><?= e(trim(($v['size'] ?? '') . ' ' . ($v['colour_name'] ?? ''))) ?></td>
                                <td><span class="badge badge-error"><?= (int)$v['stock_quantity'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="admin-card reveal" style="margin-top:20px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">Recent orders</h2>
        <a href="<?= SITE_URL ?>/admin/orders.php" class="admin-btn admin-btn-secondary admin-btn-sm">All orders</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="6" class="admin-empty">No orders yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><a href="<?= SITE_URL ?>/admin/orders.php?q=<?= urlencode($o['order_number']) ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
                            <td><?= e($o['name']) ?></td>
                            <td><?= money($o['grand_total']) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $o['payment_status']))) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><?= format_date($o['placed_at'], 'j M Y H:i') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
