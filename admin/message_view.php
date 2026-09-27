<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    set_flash('error', 'Message not found.');
    header('Location: ' . SITE_URL . '/admin/messages.php');
    exit;
}

$markRead = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
$markRead->execute([$id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    $update = $pdo->prepare("UPDATE contact_messages SET admin_notes = ? WHERE id = ?");
    $update->execute([$adminNotes, $id]);
    set_flash('success', 'Notes saved.');
    header('Location: ' . SITE_URL . '/admin/message_view.php?id=' . $id);
    exit;
}

$pageTitle = 'Message from ' . $message['name'];
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Contact Message</h1>
    <a href="<?= SITE_URL ?>/admin/messages.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<div class="admin-detail reveal">
    <div class="admin-detail-row">
        <div class="admin-detail-label">From</div>
        <div class="admin-detail-value"><?= e($message['name']) ?></div>
    </div>
    <div class="admin-detail-row">
        <div class="admin-detail-label">Email</div>
        <div class="admin-detail-value"><a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a></div>
    </div>
    <div class="admin-detail-row">
        <div class="admin-detail-label">Subject</div>
        <div class="admin-detail-value"><?= e($message['subject'] ?: '—') ?></div>
    </div>
    <div class="admin-detail-row">
        <div class="admin-detail-label">Received</div>
        <div class="admin-detail-value"><?= format_date($message['created_at'], 'j M Y H:i') ?></div>
    </div>
    <div class="admin-detail-row">
        <div class="admin-detail-label">Message</div>
        <div class="admin-detail-value" style="white-space:pre-wrap;"><?= e($message['message']) ?></div>
    </div>
</div>

<div class="admin-card reveal" style="margin-top: 24px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">Admin Notes</h2>
    </div>
    <form action="" method="post" class="admin-form" style="border:none; box-shadow:none; padding:20px;">
        <?= csrf_field() ?>
        <div class="admin-form-group">
            <textarea name="admin_notes" rows="5"><?= e($message['admin_notes'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-actions" style="border:none; padding:0;">
            <button type="submit" class="admin-btn admin-btn-primary">Save Notes</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
