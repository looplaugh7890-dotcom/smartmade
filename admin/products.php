<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$status = $_GET['status'] ?? '';
$category = (int)($_GET['category'] ?? 0);
$q = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if (in_array($status, ['active', 'hidden'], true)) {
    $where[] = 'p.is_active = ?';
    $params[] = $status === 'active' ? 1 : 0;
}
if ($category > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $category;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$totalPdo = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$totalPdo->execute($params);
$total = (int) $totalPdo->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name,
        (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_primary DESC, i.sort_order ASC LIMIT 1) AS image,
        (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE $whereSql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$allParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt->execute($allParams);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

$pageTitle = 'Products';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Products</h1>
    <div>
        <a href="<?= SITE_URL ?>/admin/product_categories.php" class="admin-btn admin-btn-secondary">Categories</a>
        <a href="<?= SITE_URL ?>/admin/product_add.php" class="admin-btn admin-btn-primary">+ Add Product</a>
    </div>
</div>

<form method="get" class="admin-toolbar">
    <input type="text" name="q" class="admin-input" placeholder="Search name or SKU…" value="<?= e($q) ?>">
    <select name="status" class="admin-input">
        <option value="">All statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Hidden</option>
    </select>
    <select name="category" class="admin-input">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
    <a href="<?= SITE_URL ?>/admin/products.php" class="admin-btn admin-btn-secondary">Reset</a>
</form>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Variants</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="admin-empty">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['image']): ?>
                                    <img src="<?= SITE_URL . '/' . e($p['image']) ?>" alt="" class="admin-thumb">
                                <?php else: ?>
                                    <div class="admin-thumb" style="display:flex;align-items:center;justify-content:center;color:#C9A227;font-weight:700;">SM</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($p['name']) ?></strong>
                                <?php if ($p['sku']): ?><br><small style="color:#6B6B6B;"><?= e($p['sku']) ?></small><?php endif; ?>
                                <?php if ($p['is_featured']): ?> <span class="badge badge-info">Featured</span><?php endif; ?>
                            </td>
                            <td><?= e($p['category_name'] ?? '—') ?></td>
                            <td><?= money($p['base_price']) ?><?= $p['pricing_unit'] === 'from' ? ' <small>(from)</small>' : '' ?></td>
                            <td>
                                <?php if ($p['manage_stock']): ?>
                                    <?= (int)$p['stock_quantity'] ?><?= ((int)$p['stock_quantity'] <= (int)setting('low_stock_threshold', '5')) ? ' <span class="badge badge-error">Low</span>' : '' ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$p['variant_count'] ?></td>
                            <td>
                                <span class="badge badge-<?= $p['is_active'] ? 'success' : 'error' ?>"><?= $p['is_active'] ? 'Active' : 'Hidden' ?></span>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
                                    <a href="<?= SITE_URL ?>/admin/product_variants.php?id=<?= (int)$p['id'] ?>">Variants</a>
                                    <a href="<?= SITE_URL ?>/admin/product_images.php?id=<?= (int)$p['id'] ?>">Images</a>
                                    <a href="<?= SITE_URL ?>/product/<?= e($p['slug']) ?>" target="_blank">View</a>
                                    <a href="<?= SITE_URL ?>/admin/product_delete.php?id=<?= (int)$p['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="delete" data-confirm="Delete this product? This removes its variants, images and personalisation fields.">Delete</a>
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
                <a href="<?= pagination_url($pagination['page'] - 1, ['q' => $q, 'status' => $status, 'category' => $category]) ?>">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= pagination_url($i, ['q' => $q, 'status' => $status, 'category' => $category]) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?>
                <a href="<?= pagination_url($pagination['page'] + 1, ['q' => $q, 'status' => $status, 'category' => $category]) ?>">→</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
