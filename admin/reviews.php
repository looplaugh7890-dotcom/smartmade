<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$status = $_GET['status'] ?? '';
$rating = (int)($_GET['rating'] ?? 0);

$where = ['1=1'];
$params = [];

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($rating >= 1 && $rating <= 5) {
    $where[] = 'r.rating = ?';
    $params[] = $rating;
}
$whereSql = implode(' AND ', $where);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security token invalid.');
    } else {
        $action = $_POST['action'] ?? '';
        $rid = (int)($_POST['review_id'] ?? 0);

        $sel = $pdo->prepare("SELECT id, status FROM reviews WHERE id = ?");
        $sel->execute([$rid]);
        $review = $sel->fetch();

        if ($review) {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$rid]);
                activity_log('review.approve', 'review', $rid);
                set_flash('success', 'Review approved and published.');
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$rid]);
                activity_log('review.reject', 'review', $rid);
                set_flash('success', 'Review rejected.');
            } elseif ($action === 'feature') {
                $pdo->prepare("UPDATE reviews SET is_featured = 1 - is_featured WHERE id = ?")->execute([$rid]);
                activity_log('review.feature', 'review', $rid);
                set_flash('success', 'Featured flag updated.');
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$rid]);
                activity_log('review.delete', 'review', $rid);
                set_flash('success', 'Review deleted.');
            }
        }

        header('Location: ' . SITE_URL . '/admin/reviews.php?' . http_build_query(['status' => $status, 'page' => $page, 'rating' => $rating]));
        exit;
    }
}

$count = $pdo->prepare("SELECT COUNT(*) FROM reviews r WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT r.*, p.name AS product_name, o.order_number
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN orders o ON r.order_id = o.id
    WHERE $whereSql
    ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$reviews = $stmt->fetchAll();

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();

$pageTitle = 'Reviews';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Reviews <?= $pendingCount ? '<span class="badge badge-error">' . $pendingCount . ' pending</span>' : '' ?></h1>
</div>

<form method="get" class="admin-toolbar">
    <select name="status" class="admin-input">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="rating" class="admin-input">
        <option value="">Any rating</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= $i ?> star</option>
        <?php endfor; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
    <a href="<?= SITE_URL ?>/admin/reviews.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Rating</th><th>Author</th><th>Review</th><th>Product</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="7" class="admin-empty">No reviews found.</td></tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?= star_rating((int)$r['rating']) ?></td>
                            <td>
                                <strong><?= e($r['author_name']) ?></strong>
                                <?php if ($r['is_featured']): ?> <span class="badge badge-info">Featured</span><?php endif; ?><br>
                                <small style="color:#6B6B6B;"><?= e($r['email']) ?></small>
                            </td>
                            <td style="max-width:340px;">
                                <?php if ($r['title']): ?><strong><?= e($r['title']) ?></strong><br><?php endif; ?>
                                <?= e(excerpt($r['content'], 160)) ?>
                                <?php if ($r['admin_reply']): ?><br><em style="color:#6B6B6B;">You replied: <?= e(excerpt($r['admin_reply'], 80)) ?></em><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['product_name']): ?>
                                    <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= (int)$r['product_id'] ?>"><?= e($r['product_name']) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                                <?php if ($r['order_number']): ?><br><small><?= e($r['order_number']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= e($r['status']) === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'error' : 'pending') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                            <td><?= format_date($r['created_at']) ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/review_view.php?id=<?= (int)$r['id'] ?>">Open</a>
                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button type="submit" class="link-btn" style="font-size:inherit;">Approve</button></form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button type="submit" class="link-btn" style="font-size:inherit;">Unpublish</button></form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="feature"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button type="submit" class="link-btn" style="font-size:inherit;"><?= $r['is_featured'] ? 'Unfeature' : 'Feature' ?></button></form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this review?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['hasPrev']): ?><a href="<?= pagination_url($pagination['page'] - 1, ['status' => $status, 'rating' => $rating]) ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= pagination_url($i, ['status' => $status, 'rating' => $rating]) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?><a href="<?= pagination_url($pagination['page'] + 1, ['status' => $status, 'rating' => $rating]) ?>">→</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
