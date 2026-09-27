<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$service = $_GET['service'] ?? '';

$where = '1=1';
$params = [];
if (in_array($service, ['embroidery', 'printing', 'promotional', 'other'], true)) {
    $where = 'service_type = ?';
    $params[] = $service;
}

$count = $pdo->prepare("SELECT COUNT(*) FROM gallery_items WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE $where ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $pagination['offset']]));
$items = $stmt->fetchAll();

$pageTitle = 'Gallery';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Gallery</h1>
    <a href="<?= SITE_URL ?>/admin/gallery_add.php" class="admin-btn admin-btn-primary">+ Add Item</a>
</div>

<form method="get" class="admin-toolbar">
    <select name="service" class="admin-input">
        <option value="">All services</option>
        <?php foreach (['embroidery', 'printing', 'promotional', 'other'] as $s): ?>
            <option value="<?= $s ?>" <?= $service === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
    <a href="<?= SITE_URL ?>/admin/gallery.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Image</th><th>Title</th><th>Service</th><th>Client</th><th>Status</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="admin-empty">No gallery items yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><img src="<?= SITE_URL . '/' . e($item['image']) ?>" alt="" class="admin-thumb"></td>
                            <td>
                                <strong><?= e($item['title']) ?></strong>
                                <?php if ($item['is_featured']): ?> <span class="badge badge-info">Featured</span><?php endif; ?>
                            </td>
                            <td><?= e(ucfirst($item['service_type'])) ?></td>
                            <td><?= e($item['client_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $item['status'] === 'published' ? 'success' : 'error' ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                            <td><?= (int)$item['display_order'] ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/gallery_edit.php?id=<?= (int)$item['id'] ?>">Edit</a>
                                    <a href="<?= SITE_URL ?>/admin/gallery_delete.php?id=<?= (int)$item['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="delete" data-confirm="Delete this gallery item?">Delete</a>
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
            <?php if ($pagination['hasPrev']): ?><a href="<?= pagination_url($pagination['page'] - 1, ['service' => $service]) ?>">←</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= pagination_url($i, ['service' => $service]) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?><a href="<?= pagination_url($pagination['page'] + 1, ['service' => $service]) ?>">→</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
