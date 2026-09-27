<?php

require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/payments.php';

$id = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: ' . SITE_URL . '/admin/orders.php');
    exit;
}

$validStatuses = ['pending', 'awaiting_payment', 'paid', 'in_production', 'ready', 'shipped', 'completed', 'cancelled', 'refunded'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'status') {
            $newStatus = $_POST['status'] ?? '';
            if (!in_array($newStatus, $validStatuses, true)) {
                $errors[] = 'Invalid status.';
            } elseif ($newStatus !== $order['status']) {
                $note = trim($_POST['status_note'] ?? '');

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$newStatus, $id]);
                    $pdo->prepare("INSERT INTO order_events (order_id, event_type, from_status, to_status, actor, note, admin_id) VALUES (?, 'status_change', ?, ?, 'admin', ?, ?)")
                        ->execute([$id, $order['status'], $newStatus, $note ?: null, $_SESSION['admin_id'] ?? null]);

                    if (in_array($newStatus, ['cancelled', 'refunded'], true) && !in_array($order['status'], ['cancelled', 'refunded'], true)) {
                        foreach (order_items_for($id) as $item) {
                            if (!empty($item['variant_id'])) {
                                $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?")
                                    ->execute([(int)$item['quantity'], (int)$item['variant_id']]);
                            }
                            if (!empty($item['product_id'])) {
                                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND manage_stock = 1")
                                    ->execute([(int)$item['quantity'], (int)$item['product_id']]);
                            }
                        }
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Could not update status: ' . $e->getMessage();
                }

                if (empty($errors)) {
                    activity_log('order.status', 'order', $id, $order['status'] . ' → ' . $newStatus);
                    set_flash('success', 'Order status updated to ' . ucwords(str_replace('_', ' ', $newStatus)) . '.');
                    header('Location: ' . SITE_URL . '/admin/order_view.php?id=' . $id);
                    exit;
                }
            }
        } elseif ($action === 'notes') {
            $adminNotes = trim($_POST['admin_notes'] ?? '');
            $pdo->prepare("UPDATE orders SET admin_notes = ?, updated_at = NOW() WHERE id = ?")->execute([$adminNotes, $id]);
            activity_log('order.notes', 'order', $id);
            set_flash('success', 'Internal notes saved.');
            header('Location: ' . SITE_URL . '/admin/order_view.php?id=' . $id);
            exit;
        } elseif ($action === 'tracking') {
            $tracking = trim($_POST['tracking_reference'] ?? '');
            $pdo->prepare("UPDATE orders SET tracking_reference = ?, updated_at = NOW() WHERE id = ?")->execute([$tracking ?: null, $id]);
            $pdo->prepare("INSERT INTO order_events (order_id, event_type, actor, note, admin_id) VALUES (?, 'tracking', 'admin', ?, ?)")
                ->execute([$id, $tracking ?: 'removed', $_SESSION['admin_id'] ?? null]);
            activity_log('order.tracking', 'order', $id, $tracking);
            set_flash('success', 'Tracking reference saved.');
            header('Location: ' . SITE_URL . '/admin/order_view.php?id=' . $id);
            exit;
        } elseif ($action === 'mark_paid') {
            if ($order['payment_status'] === 'paid') {
                set_flash('info', 'This order is already marked as paid.');
            } else {
                mark_order_paid($id, 'manual', null, ['source' => 'admin']);
                $pdo->prepare("INSERT INTO order_events (order_id, event_type, actor, note, admin_id) VALUES (?, 'payment_confirmed', 'admin', 'Manually marked as paid', ?)")
                    ->execute([$id, $_SESSION['admin_id'] ?? null]);
                activity_log('order.mark_paid', 'order', $id, $order['order_number']);
                set_flash('success', 'Order marked as paid. Confirmation email sent to the customer.');
            }
            header('Location: ' . SITE_URL . '/admin/order_view.php?id=' . $id);
            exit;
        }
    }
}

$items = order_items_for($id);
$events = $pdo->prepare("SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at DESC, id DESC");
$events->execute([$id]);
$events = $events->fetchAll();

$payments = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$shipAddr = json_decode($order['shipping_address_json'] ?? '', true) ?: [];
$billAddr = json_decode($order['billing_address_json'] ?? '', true) ?: [];
$shipAddr = $billAddr ?: $shipAddr;

$pageTitle = 'Order ' . $order['order_number'];
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Order <?= e($order['order_number']) ?></h1>
    <div>
        <a href="mailto:<?= e($order['email']) ?>" class="admin-btn admin-btn-secondary">Email customer</a>
        <a href="<?= SITE_URL ?>/admin/orders.php" class="admin-btn admin-btn-secondary">← All orders</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-stats-grid">
    <div class="admin-stat-tile"><span class="stat-label">Order status</span><span class="stat-value" style="font-size:1rem;"><?= order_status_badge($order['status']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Payment</span><span class="stat-value" style="font-size:1rem;"><span class="badge badge-<?= in_array($order['payment_status'], ['paid']) ? 'success' : (in_array($order['payment_status'], ['failed']) ? 'error' : 'info') ?>"><?= ucwords(str_replace('_', ' ', $order['payment_status'])) ?></span></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Total</span><span class="stat-value"><?= money($order['grand_total']) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Placed</span><span class="stat-value" style="font-size:1rem;"><?= format_date($order['placed_at'], 'j M Y H:i') ?></span></div>
</div>

<div class="admin-two-col" style="align-items:start;">
    <div>
        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Items</h2></div>
            <?php foreach ($items as $item): ?>
                <div style="padding:14px 4px;border-bottom:1px solid rgba(10,10,10,0.08);">
                    <div class="admin-order-line">
                        <div>
                            <strong><?= e($item['product_name']) ?></strong>
                            <?php if ($item['variant_label']): ?><br><small style="color:#6B6B6B;"><?= e($item['variant_label']) ?></small><?php endif; ?>
                            <?php if ($item['sku']): ?><br><small style="color:#6B6B6B;"><?= e($item['sku']) ?></small><?php endif; ?>
                            <?php if (!empty($item['personalisation'])): ?>
                                <dl class="admin-kv" style="margin-top:8px;">
                                    <?php foreach ($item['personalisation'] as $label => $value): ?>
                                        <dt><?= e(is_string($label) ? ucwords(str_replace('_', ' ', $label)) : 'Field') ?></dt>
                                        <dd><?= e(is_scalar($value) ? (string)$value : json_encode($value)) ?></dd>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                            <?php if (!empty($item['artwork'])): ?>
                                <div class="admin-artwork-list">
                                    <?php foreach ($item['artwork'] as $art): ?>
                                        <a href="<?= SITE_URL . '/' . e($art['file_path']) ?>" target="_blank">📎 <?= e($art['original_name'] ?? basename($art['file_path'])) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="line-qty" style="text-align:center;color:#6B6B6B;">×<?= (int)$item['quantity'] ?></div>
                        <div class="line-total"><?= money($item['line_total']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="padding:16px 4px 4px;">
                <dl class="admin-kv" style="justify-content:end;grid-template-columns:auto auto;">
                    <dt>Subtotal</dt><dd><?= money($order['subtotal']) ?></dd>
                    <?php if ((float)$order['discount_total'] > 0): ?>
                        <dt>Discount <?= e($order['coupon_code'] ?? '') ?></dt><dd>-<?= money($order['discount_total']) ?></dd>
                    <?php endif; ?>
                    <dt>Shipping (<?= e($order['shipping_method'] ?: 'standard') ?>)</dt><dd><?= money($order['shipping_total']) ?></dd>
                    <dt>VAT</dt><dd><?= money($order['tax_total']) ?></dd>
                    <dt><strong>Total</strong></dt><dd><strong><?= money($order['grand_total']) ?></strong></dd>
                </dl>
            </div>
        </div>

        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Customer</h2></div>
            <div style="padding:4px;">
                <dl class="admin-kv">
                    <dt>Name</dt><dd><?= e($order['name']) ?></dd>
                    <dt>Email</dt><dd><?= e($order['email']) ?></dd>
                    <dt>Phone</dt><dd><?= e($order['phone'] ?: '—') ?></dd>
                    <dt>Customer ID</dt><dd><?= $order['customer_id'] ? '#' . (int)$order['customer_id'] . ' <a href="' . SITE_URL . '/admin/customer_view.php?id=' . (int)$order['customer_id'] . '">view</a>' : 'Guest checkout' ?></dd>
                    <dt>Notes</dt><dd><?= nl2br(e($order['customer_notes'] ?: '—')) ?></dd>
                </dl>
            </div>

            <div class="admin-order-addresses" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:16px 4px 4px;">
                <div>
                    <h3 style="font-size:0.95rem;margin:0 0 6px;">Shipping address</h3>
                    <?php if ($shipAddr): ?>
                        <p style="margin:0;font-size:0.88rem;line-height:1.7;color:#6B6B6B;">
                            <?= e($shipAddr['full_name'] ?? $order['name']) ?><br>
                            <?= e($shipAddr['line1'] ?? '') ?><?= !empty($shipAddr['line2']) ? '<br>' . e($shipAddr['line2']) : '' ?><br>
                            <?= e($shipAddr['city'] ?? '') ?><?= !empty($shipAddr['county']) ? ', ' . e($shipAddr['county']) : '' ?><br>
                            <?= e($shipAddr['postcode'] ?? '') ?><br>
                            <?= e($shipAddr['country'] ?? 'United Kingdom') ?>
                        </p>
                    <?php else: ?>
                        <p style="margin:0;font-size:0.88rem;color:#6B6B6B;">Not provided.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="font-size:0.95rem;margin:0 0 6px;">Billing address</h3>
                    <?php if ($billAddr): ?>
                        <p style="margin:0;font-size:0.88rem;line-height:1.7;color:#6B6B6B;">
                            <?= e($billAddr['full_name'] ?? '') ?><br>
                            <?= e($billAddr['line1'] ?? '') ?><?= !empty($billAddr['line2']) ? '<br>' . e($billAddr['line2']) : '' ?><br>
                            <?= e($billAddr['city'] ?? '') ?><br>
                            <?= e($billAddr['postcode'] ?? '') ?>
                        </p>
                    <?php else: ?>
                        <p style="margin:0;font-size:0.88rem;color:#6B6B6B;">Same as shipping.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-card reveal">
            <div class="admin-card-header"><h2 class="admin-card-title">Activity</h2></div>
            <div style="padding:4px;">
                <?php if (empty($events)): ?>
                    <p class="admin-empty">No events recorded.</p>
                <?php else: ?>
                    <ul style="list-style:none;margin:0;padding:0;font-size:0.88rem;">
                        <?php foreach ($events as $ev): ?>
                            <li style="padding:9px 0;border-bottom:1px solid rgba(10,10,10,0.06);">
                                <strong><?= e(ucwords(str_replace('_', ' ', $ev['event_type']))) ?></strong>
                                <?php if ($ev['from_status'] || $ev['to_status']): ?>
                                    <span style="color:#6B6B6B;">(<?= e($ev['from_status'] ?? '—') ?> → <?= e($ev['to_status'] ?? '—') ?>)</span>
                                <?php endif; ?>
                                <?php if ($ev['note']): ?><br><?= e($ev['note']) ?><?php endif; ?>
                                <span style="color:#6B6B6B;float:right;"><?= format_date($ev['created_at'], 'j M Y H:i') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Update status</h2></div>
            <form action="" method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_id" value="<?= $id ?>">
                <div class="admin-form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($validStatuses as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="status_note">Note (optional)</label>
                    <textarea id="status_note" name="status_note" rows="2"></textarea>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">Update</button>
                </div>
            </form>

            <?php if ($order['payment_status'] !== 'paid'): ?>
                <form action="" method="post" style="margin-top:10px;" onsubmit="return confirm('Mark this order as paid? A confirmation email will be sent.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="order_id" value="<?= $id ?>">
                    <button type="submit" class="admin-btn admin-btn-secondary" style="width:100%;">Mark as paid (bank transfer received)</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Tracking</h2></div>
            <form action="" method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tracking">
                <input type="hidden" name="order_id" value="<?= $id ?>">
                <div class="admin-form-group">
                    <label for="tracking_reference">Reference / courier no.</label>
                    <input type="text" id="tracking_reference" name="tracking_reference" value="<?= e($order['tracking_reference'] ?? '') ?>">
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-secondary">Save tracking</button>
                </div>
            </form>
        </div>

        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Internal notes</h2></div>
            <form action="" method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="notes">
                <input type="hidden" name="order_id" value="<?= $id ?>">
                <div class="admin-form-group">
                    <textarea name="admin_notes" rows="5" placeholder="Only visible to the team."><?= e($order['admin_notes'] ?? '') ?></textarea>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">Save notes</button>
                </div>
            </form>
        </div>

        <div class="admin-card reveal">
            <div class="admin-card-header"><h2 class="admin-card-title">Payments</h2></div>
            <div style="padding:4px;">
                <?php if (empty($payments)): ?>
                    <p class="admin-empty">No payment records.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>Provider</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><?= e($pay['provider']) ?><?php if ($pay['provider_payment_id']): ?><br><small><?= e($pay['provider_payment_id']) ?></small><?php endif; ?></td>
                                    <td><?= money($pay['amount']) ?></td>
                                    <td><span class="badge badge-<?= $pay['status'] === 'succeeded' ? 'success' : ($pay['status'] === 'failed' ? 'error' : 'info') ?>"><?= e(ucfirst($pay['status'])) ?></span></td>
                                    <td><?= format_date($pay['created_at'], 'j M Y H:i') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
