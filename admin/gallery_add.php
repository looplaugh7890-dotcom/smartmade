<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $service = in_array($_POST['service_type'] ?? '', ['embroidery', 'printing', 'promotional', 'other'], true) ? $_POST['service_type'] : 'embroidery';
        $description = trim($_POST['description'] ?? '');
        $client = trim($_POST['client_name'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published';

        if ($title === '') $errors[] = 'Title is required.';
        if ($slug === '') $slug = slugify($title);

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM gallery_items WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if ($check->fetch()) $slug .= '-' . time();
        }

        if (empty($errors)) {
            $image = upload_image($_FILES['image'] ?? [], 'gallery');
            if ($image === false) $errors[] = 'Image upload failed. A main image is required.';
        }

        if (empty($errors)) {
            $additional = null;
            if (!empty($_FILES['additional_images']['tmp_name'])) {
                $paths = [];
                foreach ((array)$_FILES['additional_images']['tmp_name'] as $i => $tmp) {
                    if (($_FILES['additional_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $single = [
                        'name' => $_FILES['additional_images']['name'][$i],
                        'type' => $_FILES['additional_images']['type'][$i],
                        'tmp_name' => $tmp,
                        'error' => $_FILES['additional_images']['error'][$i],
                        'size' => $_FILES['additional_images']['size'][$i],
                    ];
                    $p = upload_image($single, 'gallery');
                    if ($p !== false) $paths[] = $p;
                }
                if ($paths) $additional = json_encode($paths);
            }

            $ins = $pdo->prepare("
                INSERT INTO gallery_items (title, slug, service_type, description, image, additional_images, client_name, tags, is_featured, display_order, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$title, $slug, $service, $description, $image, $additional, $client ?: null, $tags ?: null, $isFeatured, $displayOrder, $status]);

            activity_log('gallery.create', 'gallery', (int)$pdo->lastInsertId(), $title);
            set_flash('success', 'Gallery item added.');
            header('Location: ' . SITE_URL . '/admin/gallery.php');
            exit;
        }
    }
}

$pageTitle = 'Add Gallery Item';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Add Gallery Item</h1>
    <a href="<?= SITE_URL ?>/admin/gallery.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
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
        </div>
        <div class="admin-form-group">
            <label for="service_type">Service</label>
            <select id="service_type" name="service_type">
                <?php foreach (['embroidery', 'printing', 'promotional', 'other'] as $s): ?>
                    <option value="<?= $s ?>" <?= (($_POST['service_type'] ?? 'embroidery') === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="client_name">Client</label>
            <input type="text" id="client_name" name="client_name" value="<?= e($_POST['client_name'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="display_order">Display order</label>
            <input type="number" id="display_order" name="display_order" value="<?= e($_POST['display_order'] ?? '0') ?>">
        </div>
        <div class="admin-form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="published" <?= (($_POST['status'] ?? 'published') === 'published') ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="is_featured" value="1" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>> Featured</label>
        </div>
        <div class="admin-form-group full">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group">
            <label for="tags">Tags (comma separated)</label>
            <input type="text" id="tags" name="tags" value="<?= e($_POST['tags'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="image">Main image *</label>
            <input type="file" id="image" name="image" accept="image/*" data-preview="#gallery-preview" required>
            <img id="gallery-preview" class="admin-image-preview" style="display:none;" alt="">
        </div>
        <div class="admin-form-group full">
            <label for="additional_images">Additional images (optional, multiple)</label>
            <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Item</button>
        <a href="<?= SITE_URL ?>/admin/gallery.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
