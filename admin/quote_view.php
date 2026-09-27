<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM quote_requests WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$quote = $stmt->fetch();

if (!$quote) {
    set_flash('error', 'Quote request not found.');
    header('Location: ' . SITE_URL . '/admin/quotes.php');
    exit;
}

$errors = [];

require_once __DIR__ . '/../includes/checkout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'convert') {
            $itemName = trim($_POST['item_name'] ?? '');
            $amount = round((float)($_POST['item_amount'] ?? 0), 2);
            $qty = max(1, (int)($_POST['item_quantity'] ?? 1));

            if ($itemName === '') $errors[] = 'Give the order line a name.';
            if ($amount <= 0) $errors[] = 'Enter the agreed amount.';

            if (empty($errors)) {
                $pdo->beginTransaction();
                try {
                    $orderNumber = checkout_generate_order_number();
                    $tax = 0.00;

                    $ins = $pdo->prepare("
                        INSERT INTO orders
                        (order_number, customer_id, email, name, phone, status, payment_status, payment_method,
                         currency, subtotal, discount_total, shipping_total, tax_total, grand_total,
                         customer_notes, admin_notes, placed_at)
                        VALUES (?, NULL, ?, ?, ?, 'awaiting_payment', 'unpaid', 'manual',
                         'GBP', ?, 0, 0, ?, ?, ?, ?, NOW())
                    ");
                    $ins->execute([
                        $orderNumber,
                        $quote['email'],
                        $quote['name'],
                        $quote['phone'],
                        $amount,
                        $tax,
                        $amount + $tax,
                        'Converted from quote #' . $quote['id'],
                        'Quote details: ' . $quote['description'],
                    ]);
                    $orderId = (int) $pdo->lastInsertId();

                    $lineTotal = $amount;
                    $insItem = $pdo->prepare("
                        INSERT INTO order_items
                        (order_id, product_id, product_name, quantity, unit_price, line_total, tax_amount, personalisation_json)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?)
                    ");
                    $insItem->execute([
                        $orderId,
                        $itemName,
                        $qty,
                        $amount / $qty,
                        $lineTotal,
                        $tax,
                        json_encode([
                            'quote_reference' => 'Quote #' . $quote['id'],
                            'description' => excerpt($quote['description'], 300),
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $pdo->prepare("UPDATE quote_requests SET converted_order_id = ?, status = 'completed', updated_at = NOW() WHERE id = ?")
                        ->execute([$orderId, $quote['id']]);
                    $pdo->prepare("INSERT INTO order_events (order_id, event_type, actor, note, admin_id) VALUES (?, 'created_from_quote', 'admin', ?, ?)")
                        ->execute([$orderId, 'Converted from quote request #' . $quote['id'], $_SESSION['admin_id'] ?? null]);

                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'Could not create the order: ' . $e->getMessage();
                }

                if (empty($errors)) {
                    activity_log('quote.convert', 'order', $orderId, $quote['name'] . ' → ' . $orderNumber);
                    set_flash('success', 'Order ' . $orderNumber . ' created from this quote.');
                    header('Location: ' . SITE_URL . '/admin/order_view.php?id=' . $orderId);
                    exit;
                }
            }
        } else {
            $status = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'completed', 'archived'], true) ? $_POST['status'] : 'new';
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            $update = $pdo->prepare("UPDATE quote_requests SET status = ?, admin_notes = ? WHERE id = ?");
            $update->execute([$status, $adminNotes, $id]);

            activity_log('quote.update', 'quote', $id, $status);
            set_flash('success', 'Quote request updated.');
            header('Location: ' . SITE_URL . '/admin/quote_view.php?id=' . $id);
            exit;
        }
    }
}

$attachments = $pdo->prepare("SELECT * FROM quote_attachments WHERE quote_id = ? ORDER BY id ASC");
$attachments->execute([$id]);
$attachments = $attachments->fetchAll();

$pageTitle = 'Quote from ' . $quote['name'];
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Quote Request</h1>
    <a href="<?= SITE_URL ?>/admin/quotes.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error reveal"><?php foreach ($errors as $e) echo '<p style="margin:0 0 6px;">' . e($e) . '</p>'; ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 0.45fr; gap: 24px; align-items: start;">
    <div class="admin-detail reveal">
        <div class="admin-detail-row">
            <div class="admin-detail-label">Name</div>
            <div class="admin-detail-value"><?= e($quote['name']) ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Email</div>
            <div class="admin-detail-value"><a href="mailto:<?= e($quote['email']) ?>"><?= e($quote['email']) ?></a></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Phone</div>
            <div class="admin-detail-value"><?= e($quote['phone'] ?: '—') ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Order Type</div>
            <div class="admin-detail-value"><?= e(ucwords(str_replace('_', ' ', $quote['order_type']))) ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Service</div>
            <div class="admin-detail-value"><?= e($quote['service'] ?: '—') ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Quantity</div>
            <div class="admin-detail-value"><?= (int)$quote['quantity'] ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Deadline</div>
            <div class="admin-detail-value"><?= $quote['deadline'] ? format_date($quote['deadline']) : '—' ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Budget</div>
            <div class="admin-detail-value"><?= e($quote['budget_range'] ?: '—') ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Submitted</div>
            <div class="admin-detail-value"><?= format_date($quote['submitted_at'], 'j M Y H:i') ?></div>
        </div>
        <div class="admin-detail-row">
            <div class="admin-detail-label">Idea / Description</div>
            <div class="admin-detail-value" style="white-space:pre-wrap;"><?= e($quote['description']) ?></div>
        </div>
        <?php if ($quote['reference_image']): ?>
            <div class="admin-detail-row">
                <div class="admin-detail-label">Reference Image</div>
                <div class="admin-detail-value">
                    <a href="<?= SITE_URL . '/' . e($quote['reference_image']) ?>" target="_blank">
                        <img src="<?= SITE_URL . '/' . e($quote['reference_image']) ?>" alt="Reference" style="max-width:240px; border-radius:4px;">
                    </a>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($attachments): ?>
            <div class="admin-detail-row">
                <div class="admin-detail-label">Attachments</div>
                <div class="admin-detail-value">
                    <div class="admin-artwork-list">
                        <?php foreach ($attachments as $att): ?>
                            <a href="<?= SITE_URL . '/' . e($att['file_path']) ?>" target="_blank">📎 <?= e($att['original_name'] ?? basename($att['file_path'])) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($quote['converted_order_id']): ?>
            <div class="admin-detail-row">
                <div class="admin-detail-label">Converted to</div>
                <div class="admin-detail-value">
                    <a href="<?= SITE_URL ?>/admin/order_view.php?id=<?= (int)$quote['converted_order_id'] ?>">View order →</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Update Status</h2>
        </div>
        <form action="" method="post" class="admin-form" style="border:none; box-shadow:none; padding:20px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <div class="admin-form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (['new', 'in_progress', 'completed', 'archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($quote['status'] === $s) ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="admin_notes">Admin Notes</label>
                <textarea id="admin_notes" name="admin_notes" rows="6"><?= e($quote['admin_notes'] ?? '') ?></textarea>
            </div>
            <div class="admin-form-actions" style="border:none; padding:0;">
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <?php if (!$quote['converted_order_id']): ?>
        <div class="admin-card reveal">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Convert to order</h2>
            </div>
            <form action="" method="post" class="admin-form" style="border:none; box-shadow:none; padding:20px;" onsubmit="return confirm('Create an order from this quote?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="convert">
                <div class="admin-form-group">
                    <label for="item_name">Order line name *</label>
                    <input type="text" id="item_name" name="item_name" value="<?= e($_POST['item_name'] ?? ($quote['service'] ?: $quote['name'] . ' — custom order')) ?>" required>
                </div>
                <div class="admin-form-group">
                    <label for="item_quantity">Quantity</label>
                    <input type="number" id="item_quantity" name="item_quantity" min="1" value="<?= e($_POST['item_quantity'] ?? max(1, (int)$quote['quantity'])) ?>">
                </div>
                <div class="admin-form-group">
                    <label for="item_amount">Agreed total (£) *</label>
                    <input type="number" id="item_amount" name="item_amount" step="0.01" min="0.01" value="<?= e($_POST['item_amount'] ?? '') ?>" required>
                </div>
                <p class="admin-form-hint">Creates a pending order linked to this quote, so the customer can pay by card or bank transfer from the usual order page.</p>
                <div class="admin-form-actions" style="border:none; padding:0;">
                    <button type="submit" class="admin-btn admin-btn-secondary">Create Order</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
