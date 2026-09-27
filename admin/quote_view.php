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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $status = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'completed', 'archived'], true) ? $_POST['status'] : 'new';
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        $update = $pdo->prepare("UPDATE quote_requests SET status = ?, admin_notes = ? WHERE id = ?");
        $update->execute([$status, $adminNotes, $id]);

        set_flash('success', 'Quote request updated.');
        header('Location: ' . SITE_URL . '/admin/quote_view.php?id=' . $id);
        exit;
    }
}

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
    </div>

    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Update Status</h2>
        </div>
        <form action="" method="post" class="admin-form" style="border:none; box-shadow:none; padding:20px;">
            <?= csrf_field() ?>
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
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
