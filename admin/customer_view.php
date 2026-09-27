<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('error', 'Customer not found.');
    header('Location: ' . SITE_URL . '/admin/customers.php');
    exit;
}

$orders = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY placed_at DESC");
$orders->execute([$id]);
$orders = $orders->fetchAll();

$addresses = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id ASC");
$addresses->execute([$id]);
$addresses = $addresses->fetchAll();

$reviews = $pdo->prepare("SELECT * FROM reviews WHERE customer_id = ? ORDER BY created_at DESC");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$pageTitle = $customer['name'];
?>

<div class="admin-page-header">
    <h1 class="admin-page-title"><?= e($customer['name']) ?></h1>
    <a href="<?= SITE_URL ?>/admin/customers.php" class="admin-btn admin-btn-secondary">← All customers</a>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-tile"><span class="stat-label">Orders</span><span class="stat-value"><?= count($orders) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Paid spend</span><span class="stat-value"><?= money(array_sum(array_map(fn($o) => $o['payment_status'] === 'paid' ? (float)$o['grand_total'] : 0, $orders))) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Member since</span><span class="stat-value" style="font-size:1rem;"><?= format_date($customer['created_at']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Verified</span><span class="stat-value" style="font-size:1rem;"><?= $customer['email_verified_at'] ? 'Yes' : 'No' ?></span></div>
</div>

<div class="admin-two-col" style="align-items:start;">
    <div class="admin-card reveal">
        <div class="admin-card-header"><h2 class="admin-card-title">Orders</h2></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="5" class="admin-empty">No orders yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><a href="<?= SITE_URL ?>/admin/order_view.php?id=<?= (int)$o['id'] ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
                                <td><?= money($o['grand_total']) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $o['payment_status']))) ?></td>
                                <td><?= order_status_badge($o['status']) ?></td>
                                <td><?= format_date($o['placed_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Contact</h2></div>
            <div style="padding:4px;">
                <dl class="admin-kv">
                    <dt>Email</dt><dd><?= e($customer['email']) ?></dd>
                    <dt>Phone</dt><dd><?= e($customer['phone'] ?: '—') ?></dd>
                    <dt>Marketing</dt><dd><?= $customer['marketing_optin'] ? 'Opted in' : 'Not opted in' ?></dd>
                </dl>
            </div>
        </div>

        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Addresses</h2></div>
            <div style="padding:4px;">
                <?php if (empty($addresses)): ?>
                    <p class="admin-empty">No saved addresses.</p>
                <?php else: ?>
                    <?php foreach ($addresses as $a): ?>
                        <div class="saved-address" style="border:1px solid rgba(10,10,10,0.1);border-radius:8px;padding:12px 14px;margin-bottom:10px;">
                            <strong><?= e($a['label']) ?><?= $a['is_default'] ? ' (default)' : '' ?></strong>
                            <p style="margin:6px 0 0;font-size:0.88rem;line-height:1.6;color:#6B6B6B;">
                                <?= e($a['full_name']) ?><br>
                                <?= e($a['line1']) ?><?= $a['line2'] ? '<br>' . e($a['line2']) : '' ?><br>
                                <?= e($a['city']) ?><?= $a['county'] ? ', ' . e($a['county']) : '' ?>, <?= e($a['postcode']) ?><br>
                                <?= e($a['country']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-card reveal">
            <div class="admin-card-header"><h2 class="admin-card-title">Reviews</h2></div>
            <div style="padding:4px;">
                <?php if (empty($reviews)): ?>
                    <p class="admin-empty">No reviews from this customer.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div style="padding:10px 0;border-bottom:1px solid rgba(10,10,10,0.06);">
                            <strong><?= star_rating((int)$r['rating']) ?></strong>
                            <span class="badge badge-<?= e($r['status']) === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'error' : 'info') ?>" style="margin-left:8px;"><?= e(ucfirst($r['status'])) ?></span>
                            <br><small><?= e(excerpt($r['content'], 90)) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
