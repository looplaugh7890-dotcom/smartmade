<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? $_POST['review_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT r.*, p.name AS product_name, o.order_number, c.name AS customer_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN orders o ON r.order_id = o.id
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$review = $stmt->fetch();

if (!$review) {
    set_flash('error', 'Review not found.');
    header('Location: ' . SITE_URL . '/admin/reviews.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security token invalid.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'reply') {
            $reply = trim($_POST['admin_reply'] ?? '');
            $pdo->prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?")->execute([$reply !== '' ? $reply : null, $id]);
            activity_log('review.reply', 'review', $id);
            set_flash('success', $reply !== '' ? 'Reply saved and shown publicly with the review.' : 'Reply removed.');
        } elseif ($action === 'approve') {
            $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$id]);
            activity_log('review.approve', 'review', $id);
            set_flash('success', 'Review approved.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$id]);
            activity_log('review.reject', 'review', $id);
            set_flash('success', 'Review rejected.');
        } elseif ($action === 'feature') {
            $pdo->prepare("UPDATE reviews SET is_featured = 1 - is_featured WHERE id = ?")->execute([$id]);
            activity_log('review.feature', 'review', $id);
            set_flash('success', 'Featured flag updated.');
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
            activity_log('review.delete', 'review', $id);
            set_flash('success', 'Review deleted.');
            header('Location: ' . SITE_URL . '/admin/reviews.php');
            exit;
        }
    }

    header('Location: ' . SITE_URL . '/admin/review_view.php?id=' . $id);
    exit;
}

$pageTitle = 'Review';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Review by <?= e($review['author_name']) ?></h1>
    <a href="<?= SITE_URL ?>/admin/reviews.php" class="admin-btn admin-btn-secondary">← All reviews</a>
</div>

<div class="admin-two-col" style="align-items:start;">
    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><?= star_rating((int)$review['rating']) ?></h2>
            <span class="badge badge-<?= $review['status'] === 'approved' ? 'success' : ($review['status'] === 'rejected' ? 'error' : 'pending') ?>"><?= e(ucfirst($review['status'])) ?></span>
        </div>
        <div style="padding:4px;">
            <?php if ($review['title']): ?>
                <h3 style="font-family:'Playfair Display',serif;margin:0 0 10px;"><?= e($review['title']) ?></h3>
            <?php endif; ?>
            <p style="line-height:1.8;white-space:pre-wrap;"><?= e($review['content']) ?></p>

            <dl class="admin-kv" style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(10,10,10,0.08);">
                <dt>Author</dt><dd><?= e($review['author_name']) ?> · <?= e($review['email']) ?></dd>
                <dt>Date</dt><dd><?= format_date($review['created_at'], 'j M Y H:i') ?></dd>
                <dt>Product</dt><dd><?= $review['product_name'] ? '<a href="' . SITE_URL . '/admin/product_edit.php?id=' . (int)$review['product_id'] . '">' . e($review['product_name']) . '</a>' : '— (site-wide)' ?></dd>
                <dt>Order</dt><dd><?= $review['order_number'] ? '<a href="' . SITE_URL . '/admin/order_view.php?id=' . (int)$review['order_id'] . '">' . e($review['order_number']) . '</a>' : '—' ?></dd>
                <dt>Customer</dt><dd><?= $review['customer_id'] ? '<a href="' . SITE_URL . '/admin/customer_view.php?id=' . (int)$review['customer_id'] . '">' . e($review['customer_name'] ?? '') . '</a>' : 'Guest' ?></dd>
                <dt>IP</dt><dd><?= e($review['ip_address'] ?? '—') ?></dd>
            </dl>
        </div>
    </div>

    <div>
        <div class="admin-card reveal" style="margin-bottom:20px;">
            <div class="admin-card-header"><h2 class="admin-card-title">Moderation</h2></div>
            <div style="padding:4px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($review['status'] !== 'approved'): ?>
                    <form method="post" onsubmit="return confirm('Publish this review?');"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= $id ?>"><button class="admin-btn admin-btn-primary">Approve</button></form>
                <?php else: ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="review_id" value="<?= $id ?>"><button class="admin-btn admin-btn-secondary">Unpublish</button></form>
                <?php endif; ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="feature"><input type="hidden" name="review_id" value="<?= $id ?>"><button class="admin-btn admin-btn-secondary"><?= $review['is_featured'] ? 'Remove feature' : 'Feature it' ?></button></form>
                <form method="post" onsubmit="return confirm('Delete this review permanently?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= $id ?>"><button class="admin-btn admin-btn-secondary">Delete</button></form>
            </div>
        </div>

        <div class="admin-card reveal">
            <div class="admin-card-header"><h2 class="admin-card-title">Public reply</h2></div>
            <form action="" method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="review_id" value="<?= $id ?>">
                <div class="admin-form-group">
                    <textarea name="admin_reply" rows="5" placeholder="Thanks for the kind words — glad the stitching hit the mark!"><?= e($review['admin_reply'] ?? '') ?></textarea>
                    <p class="admin-form-hint">Shown under the review on the public reviews page and product page. Leave empty and save to remove.</p>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">Save Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
