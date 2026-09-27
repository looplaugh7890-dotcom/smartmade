<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$total = (int) $pdo->query("SELECT COUNT(*) FROM portfolio_items")->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM portfolio_items p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.display_order ASC, p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$items = $stmt->fetchAll();

$pageTitle = 'Portfolio Manager';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Portfolio</h1>
    <a href="<?= SITE_URL ?>/admin/portfolio_add.php" class="admin-btn admin-btn-primary">+ Add Item</a>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="admin-empty">No portfolio items yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['image']): ?>
                                    <img src="<?= SITE_URL . '/' . e($item['image']) ?>" alt="">
                                <?php else: ?>
                                    <div style="width:60px;height:60px;background:#0A0A0A;color:#C9A227;display:flex;align-items:center;justify-content:center;border-radius:4px;font-weight:700;">SM</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e($item['title']) ?></strong></td>
                            <td><?= e($item['category_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
                            <td><?= $item['is_featured'] ? 'Yes' : 'No' ?></td>
                            <td><?= (int)$item['display_order'] ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/portfolio_edit.php?id=<?= (int)$item['id'] ?>">Edit</a>
                                    <a href="<?= SITE_URL ?>/admin/portfolio_delete.php?id=<?= (int)$item['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="delete" data-confirm="Delete this portfolio item?">Delete</a>
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
