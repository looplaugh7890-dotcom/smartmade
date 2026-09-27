<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$status = $_GET['status'] ?? '';

$where = '1=1';
$params = [];
if (in_array($status, ['active', 'pending', 'unsubscribed'], true)) {
    $where = 'status = ?';
    $params[] = $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security token invalid.');
    } else {
        $action = $_POST['action'] ?? '';
        $sid = (int)($_POST['subscriber_id'] ?? 0);

        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$sid]);
            activity_log('subscriber.delete', 'subscriber', $sid);
            set_flash('success', 'Subscriber removed.');
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE newsletter_subscribers SET status = IF(status = 'active', 'unsubscribed', 'active') WHERE id = ?")->execute([$sid]);
            activity_log('subscriber.toggle', 'subscriber', $sid);
            set_flash('success', 'Subscriber status updated.');
        }

        header('Location: ' . SITE_URL . '/admin/subscribers.php?' . http_build_query(['status' => $status, 'page' => $page]));
        exit;
    }
}

$count = $pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE $where ORDER BY subscribed_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$subscribers = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM newsletter_subscribers GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Newsletter Subscribers';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Newsletter Subscribers</h1>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-tile"><span class="stat-label">Active</span><span class="stat-value"><?= (int)($counts['active'] ?? 0) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Pending</span><span class="stat-value"><?= (int)($counts['pending'] ?? 0) ?></span></div>
    <div class="admin-stat-tile"><span class="stat-label">Unsubscribed</span><span class="stat-value"><?= (int)($counts['unsubscribed'] ?? 0) ?></span></div>
</div>

<form method="get" class="admin-toolbar">
    <select name="status" class="admin-input">
        <option value="">All</option>
        <?php foreach (['active', 'pending', 'unsubscribed'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
    <a href="<?= SITE_URL ?>/admin/subscribers.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Email</th><th>Status</th><th>Subscribed</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr><td colspan="4" class="admin-empty">No subscribers yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $s): ?>
                        <tr>
                            <td><strong><?= e($s['email']) ?></strong></td>
                            <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : ($s['status'] === 'pending' ? 'info' : 'error') ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                            <td><?= format_date($s['subscribed_at']) ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="subscriber_id" value="<?= (int)$s['id'] ?>"><button type="submit" class="link-btn" style="font-size:inherit;">Toggle</button></form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Remove this subscriber?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="subscriber_id" value="<?= (int)$s['id'] ?>"><button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button></form>
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
            <?php if ($pagination['hasPrev']): ?><a href="<?= pagination_url($pagination['page'] - 1, ['status' => $status]) ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= pagination_url($i, ['status' => $status]) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?><a href="<?= pagination_url($pagination['page'] + 1, ['status' => $status]) ?>">→</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
