<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$total = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM contact_messages
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$messages = $stmt->fetchAll();

$pageTitle = 'Contact Messages';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Contact Messages</h1>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Read</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr><td colspan="5" class="admin-empty">No messages yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <tr style="<?= !$m['is_read'] ? 'font-weight:600;' : '' ?>">
                            <td>
                                <?= e($m['name']) ?>
                                <?php if (!$m['is_read']): ?><span class="badge badge-new">New</span><?php endif; ?>
                            </td>
                            <td><?= e($m['subject'] ?: '(No subject)') ?></td>
                            <td><?= $m['is_read'] ? 'Yes' : 'No' ?></td>
                            <td><?= format_date($m['created_at'], 'j M Y H:i') ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/message_view.php?id=<?= (int)$m['id'] ?>">View</a>
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
            <?php if ($pagination['hasPrev']): ?>
                <a href="?page=<?= $pagination['page'] - 1 ?>">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?>
                <a href="?page=<?= $pagination['page'] + 1 ?>">→</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
