<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;

$total = (int) $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT a.*, u.name AS admin_name
    FROM activity_log a
    LEFT JOIN admin_users u ON a.admin_id = u.id
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$entries = $stmt->fetchAll();

$pageTitle = 'Activity Log';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Activity Log</h1>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="admin-empty">No activity recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= format_date($entry['created_at'], 'j M Y H:i') ?></td>
                            <td><?= e($entry['admin_name'] ?? ($entry['admin_id'] ? '#' . $entry['admin_id'] : 'system')) ?></td>
                            <td><strong><?= e($entry['action']) ?></strong></td>
                            <td>
                                <?php if ($entry['entity'] === 'product' && $entry['entity_id']): ?>
                                    <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= (int)$entry['entity_id'] ?>">product #<?= (int)$entry['entity_id'] ?></a>
                                <?php elseif ($entry['entity'] === 'order' && $entry['entity_id']): ?>
                                    <a href="<?= SITE_URL ?>/admin/order_view.php?id=<?= (int)$entry['entity_id'] ?>">order #<?= (int)$entry['entity_id'] ?></a>
                                <?php else: ?>
                                    <?= e(trim(($entry['entity'] ?? '') . ($entry['entity_id'] ? ' #' . $entry['entity_id'] : ''))) ?>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:320px;"><?= e(excerpt($entry['details'] ?? '', 120)) ?></td>
                            <td><?= e($entry['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['hasPrev']): ?><a href="<?= pagination_url($pagination['page'] - 1) ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= pagination_url($i) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?><a href="<?= pagination_url($pagination['page'] + 1) ?>">→</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
