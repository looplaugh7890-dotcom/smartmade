<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $catId = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
            $description = trim($_POST['description'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['active', 'hidden'], true) ? $_POST['status'] : 'active';
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDescription = trim($_POST['meta_description'] ?? '');

            if ($name === '') $errors[] = 'Category name is required.';

            if (empty($errors)) {
                $check = $pdo->prepare("SELECT id FROM product_categories WHERE slug = ? AND id != ? LIMIT 1");
                $check->execute([$slug, $catId]);
                if ($check->fetch()) $errors[] = 'That slug is already in use.';
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $ins = $pdo->prepare("
                        INSERT INTO product_categories (name, slug, description, sort_order, status, meta_title, meta_description)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$name, $slug, $description, $sort, $status, $metaTitle ?: null, $metaDescription ?: null]);
                    activity_log('category.create', 'category', (int)$pdo->lastInsertId(), $name);
                    set_flash('success', 'Category added.');
                } else {
                    $upd = $pdo->prepare("
                        UPDATE product_categories SET name = ?, slug = ?, description = ?, sort_order = ?, status = ?,
                        meta_title = ?, meta_description = ? WHERE id = ?
                    ");
                    $upd->execute([$name, $slug, $description, $sort, $status, $metaTitle ?: null, $metaDescription ?: null, $catId]);
                    activity_log('category.update', 'category', $catId, $name);
                    set_flash('success', 'Category updated.');
                }
                header('Location: ' . SITE_URL . '/admin/product_categories.php');
                exit;
            }
        } elseif ($action === 'delete') {
            $catId = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM product_categories WHERE id = ?")->execute([$catId]);
            activity_log('category.delete', 'category', $catId);
            set_flash('success', 'Category deleted (its products are kept, now uncategorised).');
            header('Location: ' . SITE_URL . '/admin/product_categories.php');
            exit;
        }
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $sel = $pdo->prepare("SELECT * FROM product_categories WHERE id = ?");
    $sel->execute([(int)$_GET['edit']]);
    $editing = $sel->fetch() ?: null;
}

$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
    FROM product_categories c
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

$pageTitle = 'Product Categories';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Product Categories</h1>
    <a href="<?= SITE_URL ?>/admin/products.php" class="admin-btn admin-btn-secondary">← Products</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title"><?= $editing ? 'Edit category' : 'Add category' ?></h2>
        <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/product_categories.php" class="admin-btn admin-btn-secondary admin-btn-sm">Cancel edit</a><?php endif; ?>
    </div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
            </div>
            <div class="admin-form-group">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="auto-generated">
            </div>
            <div class="admin-form-group">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
            </div>
            <div class="admin-form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active" <?= (($editing['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="hidden" <?= (($editing['status'] ?? '') === 'hidden') ? 'selected' : '' ?>>Hidden</option>
                </select>
            </div>
            <div class="admin-form-group full">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
            </div>
            <div class="admin-form-group">
                <label for="meta_title">Meta title</label>
                <input type="text" id="meta_title" name="meta_title" value="<?= e($editing['meta_title'] ?? '') ?>">
            </div>
            <div class="admin-form-group">
                <label for="meta_description">Meta description</label>
                <input type="text" id="meta_description" name="meta_description" value="<?= e($editing['meta_description'] ?? '') ?>">
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? 'Save Category' : 'Add Category' ?></button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="6" class="admin-empty">No categories yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><strong><?= e($cat['name']) ?></strong></td>
                            <td><code><?= e($cat['slug']) ?></code></td>
                            <td><?= (int)$cat['product_count'] ?></td>
                            <td><?= (int)$cat['sort_order'] ?></td>
                            <td><span class="badge badge-<?= $cat['status'] === 'active' ? 'success' : 'error' ?>"><?= $cat['status'] === 'active' ? 'Active' : 'Hidden' ?></span></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="?edit=<?= (int)$cat['id'] ?>">Edit</a>
                                    <form action="" method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
