<?php

require_once __DIR__ . '/../includes/admin_header.php';

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'new', 'in_progress', 'completed', 'archived'];
$statusFilter = in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : 'all';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$sql = "SELECT COUNT(*) FROM quote_requests WHERE 1=1";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
$countStmt = $pdo->prepare($sql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$sql = "SELECT * FROM quote_requests WHERE 1=1";
if ($statusFilter !== 'all') {
    $sql .= " AND status = ?";
}
$sql .= " ORDER BY submitted_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$execParams = $params;
$execParams[] = $perPage;
$execParams[] = $pagination['offset'];
$stmt->execute($execParams);
$quotes = $stmt->fetchAll();

$pageTitle = 'Quote Requests';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Quote Requests</h1>
    <div style="display:flex;gap:8px;">
        <?php foreach ($allowedStatuses as $s): ?>
            <a href="?status=<?= $s ?>" class="admin-btn <?= $statusFilter === $s ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" style="text-transform:capitalize;"><?= str_replace('_', ' ', $s) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotes)): ?>
                    <tr><td colspan="6" class="admin-empty">No quote requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotes as $q): ?>
                        <tr>
                            <td><strong><?= e($q['name']) ?></strong><br><small style="color:var(--admin-muted);"><?= e($q['email']) ?></small></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $q['order_type']))) ?></td>
                            <td><?= (int)$q['quantity'] ?></td>
                            <td><span class="badge badge-<?= e(str_replace(['new','in_progress','completed','archived'], ['new','progress','completed','archived'], $q['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $q['status']))) ?></span></td>
                            <td><?= format_date($q['submitted_at'], 'j M Y H:i') ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/quote_view.php?id=<?= (int)$q['id'] ?>">View</a>
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
                <a href="?status=<?= e($statusFilter) ?>&page=<?= $pagination['page'] - 1 ?>">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?status=<?= e($statusFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?>
                <a href="?status=<?= e($statusFilter) ?>&page=<?= $pagination['page'] + 1 ?>">→</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
