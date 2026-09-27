<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Gallery item not found.');
    header('Location: ' . SITE_URL . '/admin/gallery.php');
    exit;
}

$errors = [];
$existingAdditional = json_decode($item['additional_images'] ?? '', true) ?: [];

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
        if ($slug === '') $slug = $item['slug'];

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM gallery_items WHERE slug = ? AND id != ? LIMIT 1");
            $check->execute([$slug, $id]);
            if ($check->fetch()) $slug .= '-' . time();
        }

        $image = $item['image'];
        if (empty($errors) && !empty($_FILES['image']['tmp_name'])) {
            $newImage = upload_image($_FILES['image'], 'gallery');
            if ($newImage === false) {
                $errors[] = 'Image upload failed.';
            } else {
                delete_upload($image);
                $image = $newImage;
            }
        }

        $additional = $item['additional_images'];
        if (empty($errors) && !empty($_FILES['additional_images']['tmp_name'])) {
            foreach ($existingAdditional as $old) {
                delete_upload($old);
            }
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
            $additional = $paths ? json_encode($paths) : null;
        }

        if (empty($errors)) {
            $upd = $pdo->prepare("
                UPDATE gallery_items SET
                    title = ?, slug = ?, service_type = ?, description = ?, image = ?, additional_images = ?,
                    client_name = ?, tags = ?, is_featured = ?, display_order = ?, status = ?
                WHERE id = ?
            ");
            $upd->execute([
                $title, $slug, $service, $description, $image, $additional,
                $client ?: null, $tags ?: null, $isFeatured, $displayOrder, $status, $id
            ]);

            activity_log('gallery.update', 'gallery', $id, $title);
            set_flash('success', 'Gallery item updated.');
            header('Location: ' . SITE_URL . '/admin/gallery.php');
            exit;
        }
    }
}

$pageTitle = 'Edit Gallery Item';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Edit: <?= e($item['title']) ?></h1>
    <a href="<?= SITE_URL ?>/admin/gallery.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data" class="admin-form reveal">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" value="<?= e($_POST['title'] ?? $item['title']) ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="<?= e($_POST['slug'] ?? $item['slug']) ?>">
        </div>
        <div class="admin-form-group">
            <label for="service_type">Service</label>
            <select id="service_type" name="service_type">
                <?php foreach (['embroidery', 'printing', 'promotional', 'other'] as $s): ?>
                    <option value="<?= $s ?>" <?= (($_POST['service_type'] ?? $item['service_type']) === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="client_name">Client</label>
            <input type="text" id="client_name" name="client_name" value="<?= e($_POST['client_name'] ?? ($item['client_name'] ?? '')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="display_order">Display order</label>
            <input type="number" id="display_order" name="display_order" value="<?= e($_POST['display_order'] ?? $item['display_order']) ?>">
        </div>
        <div class="admin-form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="published" <?= (($_POST['status'] ?? $item['status']) === 'published') ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="is_featured" value="1" <?= isset($_POST['title']) ? (isset($_POST['is_featured']) ? 'checked' : '') : ($item['is_featured'] ? 'checked' : '') ?>> Featured</label>
        </div>
        <div class="admin-form-group full">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($_POST['description'] ?? $item['description']) ?></textarea>
        </div>
        <div class="admin-form-group">
            <label for="tags">Tags (comma separated)</label>
            <input type="text" id="tags" name="tags" value="<?= e($_POST['tags'] ?? ($item['tags'] ?? '')) ?>">
        </div>
        <div class="admin-form-group">
            <label>Main image</label>
            <p style="margin:0 0 8px;"><img src="<?= SITE_URL . '/' . e($item['image']) ?>" alt="" style="max-width:180px;border-radius:6px;"></p>
            <input type="file" name="image" accept="image/*" data-preview="#edit-gallery-preview">
            <img id="edit-gallery-preview" class="admin-image-preview" style="display:none;" alt="">
        </div>
        <div class="admin-form-group full">
            <label>Additional images (<?= count($existingAdditional) ?>)</label>
            <?php if ($existingAdditional): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <?php foreach ($existingAdditional as $add): ?>
                        <img src="<?= SITE_URL . '/' . e($add) ?>" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:4px;">
                    <?php endforeach; ?>
                </div>
                <p class="admin-form-hint">Uploading new additional images replaces the ones above.</p>
            <?php endif; ?>
            <input type="file" name="additional_images[]" accept="image/*" multiple>
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
        <a href="<?= SITE_URL ?>/admin/gallery_delete.php?id=<?= $id ?>&csrf=<?= urlencode(csrf_token()) ?>" class="admin-btn admin-btn-secondary delete" data-confirm="Delete this gallery item?">Delete</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
