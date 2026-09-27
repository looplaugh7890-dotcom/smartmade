<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $categoryId = $_POST['category_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');

        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($slug)) $slug = slugify($title);

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM portfolio_items WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }

            $imagePath = null;
            if (!empty($_FILES['image']['tmp_name'])) {
                $imagePath = upload_image($_FILES['image'], 'portfolio');
                if ($imagePath === false) {
                    $errors[] = 'Image upload failed. Check file type and size.';
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("
                    INSERT INTO portfolio_items
                    (title, slug, category_id, description, image, is_featured, display_order, status, meta_title, meta_description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $slug, $categoryId ?: null, $description, $imagePath, $isFeatured,
                    $displayOrder, $status, $metaTitle, $metaDescription
                ]);

                set_flash('success', 'Portfolio item added successfully.');
                header('Location: ' . SITE_URL . '/admin/portfolio.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Add Portfolio Item';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Add Portfolio Item</h1>
    <a href="<?= SITE_URL ?>/admin/portfolio.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error reveal"><?php foreach ($errors as $e) echo '<p style="margin:0 0 6px;">' . e($e) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data" class="admin-form reveal">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" class="js-slug-source" value="<?= e($_POST['title'] ?? '') ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" class="js-slug-target" value="<?= e($_POST['slug'] ?? '') ?>">
            <p class="admin-form-hint">Leave blank to auto-generate from title.</p>
        </div>
        <div class="admin-form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">— None —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="published" <?= (($_POST['status'] ?? 'published') === 'published') ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>">
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox">
                <input type="checkbox" name="is_featured" value="1" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>>
                Featured on homepage
            </label>
        </div>
        <div class="admin-form-group full">
            <label for="description">Description / Caption</label>
            <textarea id="description" name="description"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="image">Image</label>
            <input type="file" id="image" name="image" accept="image/*" data-preview="#image-preview">
            <img id="image-preview" class="admin-image-preview" style="display:none;" alt="">
        </div>
        <div class="admin-form-group">
            <label for="meta_title">Meta Title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($_POST['meta_title'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="meta_description">Meta Description</label>
            <input type="text" id="meta_description" name="meta_description" value="<?= e($_POST['meta_description'] ?? '') ?>">
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Item</button>
        <a href="<?= SITE_URL ?>/admin/portfolio.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
