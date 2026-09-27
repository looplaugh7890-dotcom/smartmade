<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$testimonial = $stmt->fetch();

if (!$testimonial) {
    set_flash('error', 'Testimonial not found.');
    header('Location: ' . SITE_URL . '/admin/testimonials.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $authorName = trim($_POST['author_name'] ?? '');
        $authorTitle = trim($_POST['author_title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';

        if (empty($authorName)) $errors[] = 'Author name is required.';
        if (empty($content)) $errors[] = 'Testimonial content is required.';

        if (empty($errors)) {
            $imagePath = $testimonial['image'];
            if (!empty($_FILES['image']['tmp_name'])) {
                $newImage = upload_image($_FILES['image'], 'testimonials');
                if ($newImage === false) {
                    $errors[] = 'Image upload failed.';
                } else {
                    delete_upload($imagePath);
                    $imagePath = $newImage;
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("
                    UPDATE testimonials SET
                        author_name = ?, author_title = ?, content = ?, rating = ?, image = ?,
                        is_featured = ?, display_order = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$authorName, $authorTitle, $content, $rating, $imagePath, $isFeatured, $displayOrder, $status, $id]);

                set_flash('success', 'Testimonial updated.');
                header('Location: ' . SITE_URL . '/admin/testimonials.php');
                exit;
            }
        }
    }
} else {
    $_POST = $testimonial;
}

$pageTitle = 'Edit Testimonial';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Edit Testimonial</h1>
    <a href="<?= SITE_URL ?>/admin/testimonials.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error reveal"><?php foreach ($errors as $e) echo '<p style="margin:0 0 6px;">' . e($e) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data" class="admin-form reveal">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="author_name">Author Name *</label>
            <input type="text" id="author_name" name="author_name" value="<?= e($_POST['author_name'] ?? '') ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="author_title">Author Title / Source</label>
            <input type="text" id="author_title" name="author_title" value="<?= e($_POST['author_title'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="rating">Rating</label>
            <select id="rating" name="rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= (($_POST['rating'] ?? 5) == $i) ? 'selected' : '' ?>><?= $i ?> stars</option>
                <?php endfor; ?>
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
                <input type="checkbox" name="is_featured" value="1" <?= (!empty($_POST['is_featured'])) ? 'checked' : '' ?>>
                Featured on homepage
            </label>
        </div>
        <div class="admin-form-group full">
            <label for="content">Testimonial Content *</label>
            <textarea id="content" name="content" required><?= e($_POST['content'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="image">Author Photo</label>
            <?php if (!empty($_POST['image'])): ?>
                <img src="<?= SITE_URL . '/' . e($_POST['image']) ?>" class="admin-image-preview" id="image-preview" alt="">
            <?php else: ?>
                <img id="image-preview" class="admin-image-preview" style="display:none;" alt="">
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/*" data-preview="#image-preview">
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Update Testimonial</button>
        <a href="<?= SITE_URL ?>/admin/testimonials.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
