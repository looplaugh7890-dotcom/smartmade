<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    set_flash('error', 'Post not found.');
    header('Location: ' . SITE_URL . '/admin/blog.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $authorName = trim($_POST['author_name'] ?? 'SmartMade');
        $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'draft';
        $publishedAt = $_POST['published_at'] ?? null;
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');

        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($content)) $errors[] = 'Content is required.';
        if (empty($slug)) $slug = slugify($title);

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1");
            $check->execute([$slug, $id]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }

            $imagePath = $post['featured_image'];
            if (!empty($_FILES['featured_image']['tmp_name'])) {
                $newImage = upload_image($_FILES['featured_image'], 'blog');
                if ($newImage === false) {
                    $errors[] = 'Featured image upload failed.';
                } else {
                    delete_upload($imagePath);
                    $imagePath = $newImage;
                }
            }

            if (empty($errors)) {
                $published = ($status === 'published') ? ($publishedAt ?: ($post['published_at'] ?: date('Y-m-d H:i:s'))) : null;
                $stmt = $pdo->prepare("
                    UPDATE blog_posts SET
                        title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, author_name = ?,
                        status = ?, published_at = ?, meta_title = ?, meta_description = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $slug, $excerpt, $content, $imagePath, $authorName, $status, $published,
                    $metaTitle, $metaDescription, $id
                ]);

                set_flash('success', 'Blog post updated.');
                header('Location: ' . SITE_URL . '/admin/blog.php');
                exit;
            }
        }
    }
} else {
    $_POST = $post;
    $_POST['published_at'] = $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : '';
}

$pageTitle = 'Edit Journal Post';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Edit Journal Post</h1>
    <a href="<?= SITE_URL ?>/admin/blog.php" class="admin-btn admin-btn-secondary">← Back</a>
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
        </div>
        <div class="admin-form-group">
            <label for="author_name">Author</label>
            <input type="text" id="author_name" name="author_name" value="<?= e($_POST['author_name'] ?? 'SmartMade') ?>">
        </div>
        <div class="admin-form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= (($_POST['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= (($_POST['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="published_at">Publish Date</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?= e($_POST['published_at'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="featured_image">Featured Image</label>
            <?php if (!empty($_POST['featured_image'])): ?>
                <img src="<?= SITE_URL . '/' . e($_POST['featured_image']) ?>" class="admin-image-preview" id="image-preview" alt="">
            <?php else: ?>
                <img id="image-preview" class="admin-image-preview" style="display:none;" alt="">
            <?php endif; ?>
            <input type="file" id="featured_image" name="featured_image" accept="image/*" data-preview="#image-preview">
        </div>
        <div class="admin-form-group full">
            <label for="excerpt">Excerpt</label>
            <textarea id="excerpt" name="excerpt" rows="3"><?= e($_POST['excerpt'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="content">Content *</label>
            <textarea id="content" name="content" rows="12" required><?= e($_POST['content'] ?? '') ?></textarea>
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
        <button type="submit" class="admin-btn admin-btn-primary">Update Post</button>
        <a href="<?= SITE_URL ?>/admin/blog.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
