<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? $_POST['product_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . SITE_URL . '/admin/products.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Security token invalid.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload') {
            $failed = 0;
            $files = $_FILES['images'] ?? null;
            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $name) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $single = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $path = upload_image($single, 'products');
                    if ($path === false) { $failed++; continue; }

                    $check = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                    $check->execute([$id]);
                    $isFirst = (int)$check->fetchColumn() === 0;
                    $maxSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?");
                    $maxSort->execute([$id]);
                    $sort = (int) $maxSort->fetchColumn();

                    $ins = $pdo->prepare("INSERT INTO product_images (product_id, path, sort_order, is_primary) VALUES (?, ?, ?, ?)");
                    $ins->execute([$id, $path, $sort, $isFirst ? 1 : 0]);
                }
            }
            activity_log('product.images_upload', 'product', $id, $product['name']);
            set_flash($failed ? 'error' : 'success', $failed ? "Some uploads failed ($failed)." : 'Images uploaded.');
        } elseif ($action === 'primary') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            $pdo->prepare("UPDATE product_images SET is_primary = IF(id = ?, 1, 0) WHERE product_id = ?")->execute([$imgId, $id]);
            activity_log('product.image_primary', 'product', $id, 'image #' . $imgId);
            set_flash('success', 'Primary image updated.');
        } elseif ($action === 'delete') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            $sel = $pdo->prepare("SELECT path, is_primary FROM product_images WHERE id = ? AND product_id = ?");
            $sel->execute([$imgId, $id]);
            if ($img = $sel->fetch()) {
                delete_upload($img['path']);
                $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
                if ($img['is_primary']) {
                    $first = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
                    $first->execute([$id]);
                    if ($next = $first->fetch()) {
                        $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$next['id']]);
                    }
                }
                activity_log('product.image_delete', 'product', $id, 'image #' . $imgId);
                set_flash('success', 'Image removed.');
            }
        }
    }

    header('Location: ' . SITE_URL . '/admin/product_images.php?id=' . $id);
    exit;
}

$images = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$images->execute([$id]);
$images = $images->fetchAll();

$pageTitle = 'Product Images';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Images: <?= e($product['name']) ?></h1>
    <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= $id ?>" class="admin-btn admin-btn-secondary">← Back to product</a>
</div>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header"><h2 class="admin-card-title">Upload images</h2></div>
    <form action="" method="post" enctype="multipart/form-data" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="product_id" value="<?= $id ?>">
        <div class="admin-form-grid">
            <div class="admin-form-group full">
                <label for="images">Choose images (JPG, PNG, WebP, GIF — up to 10 at once)</label>
                <input type="file" id="images" name="images[]" accept="image/*" multiple>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">Upload</button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-card-header"><h2 class="admin-card-title">Current images (<?= count($images) ?>)</h2></div>
    <?php if (empty($images)): ?>
        <p class="admin-empty">No images yet. Products without images show a placeholder.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Primary</th><th>Sort</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($images as $img): ?>
                        <tr>
                            <td><img src="<?= SITE_URL . '/' . e($img['path']) ?>" alt="" class="admin-thumb"></td>
                            <td>
                                <?php if ($img['is_primary']): ?>
                                    <span class="badge badge-success">Primary</span>
                                <?php else: ?>
                                    <form action="" method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="primary">
                                        <input type="hidden" name="product_id" value="<?= $id ?>">
                                        <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                        <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Make primary</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$img['sort_order'] ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <form action="" method="post" style="display:inline;" onsubmit="return confirm('Delete this image?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= $id ?>">
                                        <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                        <button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
