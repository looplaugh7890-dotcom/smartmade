<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY sort_order ASC, name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $shortDescription = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $basePrice = round((float)($_POST['base_price'] ?? 0), 2);
        $compareAt = trim($_POST['compare_at_price'] ?? '') !== '' ? round((float)$_POST['compare_at_price'], 2) : null;
        $costPrice = trim($_POST['cost_price'] ?? '') !== '' ? round((float)$_POST['cost_price'], 2) : null;
        $taxRate = round((float)($_POST['tax_rate'] ?? 20), 2);
        $pricingUnit = in_array($_POST['pricing_unit'] ?? '', ['each', 'from', 'set'], true) ? $_POST['pricing_unit'] : 'each';
        $minQty = max(1, (int)($_POST['min_order_qty'] ?? 1));
        $maxQty = trim($_POST['max_order_qty'] ?? '') !== '' ? max(1, (int)$_POST['max_order_qty']) : null;
        $stockQty = (int)($_POST['stock_quantity'] ?? 0);
        $manageStock = isset($_POST['manage_stock']) ? 1 : 0;
        $leadDays = max(0, (int)($_POST['lead_time_days'] ?? 7));
        $hasVariants = isset($_POST['has_variants']) ? 1 : 0;
        $requiresPers = isset($_POST['requires_personalisation']) ? 1 : 0;
        $allowUpload = isset($_POST['allow_artwork_upload']) ? 1 : 0;
        $artworkHelp = trim($_POST['artwork_help_text'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');

        if ($name === '') $errors[] = 'Product name is required.';
        if ($basePrice < 0) $errors[] = 'Price cannot be negative.';
        if ($maxQty !== null && $maxQty < $minQty) $errors[] = 'Max order quantity must be greater than the minimum.';

        if ($slug === '') $slug = slugify($name);

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if ($check->fetch()) $slug .= '-' . time();

            if ($sku !== '') {
                $checkSku = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
                $checkSku->execute([$sku]);
                if ($checkSku->fetch()) $errors[] = 'That SKU is already in use.';
            }
        }

        if (empty($errors)) {
            $imagePath = null;
            if (!empty($_FILES['image']['tmp_name'])) {
                $imagePath = upload_image($_FILES['image'], 'products');
                if ($imagePath === false) $errors[] = 'Image upload failed. Check file type and size.';
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("
                    INSERT INTO products
                    (category_id, name, slug, sku, short_description, description, base_price, compare_at_price,
                     cost_price, tax_rate, pricing_unit, min_order_qty, max_order_qty, stock_quantity, manage_stock,
                     lead_time_days, has_variants, requires_personalisation, allow_artwork_upload, artwork_help_text,
                     is_featured, is_active, meta_title, meta_description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $categoryId, $name, $slug, $sku ?: null, $shortDescription, $description, $basePrice, $compareAt,
                    $costPrice, $taxRate, $pricingUnit, $minQty, $maxQty, $stockQty, $manageStock,
                    $leadDays, $hasVariants, $requiresPers, $allowUpload, $artworkHelp,
                    $isFeatured, $isActive, $metaTitle ?: null, $metaDescription ?: null
                ]);
                $productId = (int) $pdo->lastInsertId();

                if ($imagePath) {
                    $img = $pdo->prepare("INSERT INTO product_images (product_id, path, sort_order, is_primary) VALUES (?, ?, 0, 1)");
                    $img->execute([$productId, $imagePath]);
                }

                activity_log('product.create', 'product', $productId, $name);
                set_flash('success', 'Product added. Now add images, variants and personalisation fields.');
                header('Location: ' . SITE_URL . '/admin/product_edit.php?id=' . $productId);
                exit;
            }
        }
    }
}

$pageTitle = 'Add Product';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Add Product</h1>
    <a href="<?= SITE_URL ?>/admin/products.php" class="admin-btn admin-btn-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data" class="admin-form reveal">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" class="js-slug-source" value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" class="js-slug-target" value="<?= e($_POST['slug'] ?? '') ?>">
            <p class="admin-form-hint">Leave blank to auto-generate.</p>
        </div>
        <div class="admin-form-group">
            <label for="sku">SKU</label>
            <input type="text" id="sku" name="sku" value="<?= e($_POST['sku'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">— None —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="base_price">Price (£) *</label>
            <input type="number" step="0.01" min="0" id="base_price" name="base_price" value="<?= e($_POST['base_price'] ?? '0.00') ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="compare_at_price">Compare-at price</label>
            <input type="number" step="0.01" min="0" id="compare_at_price" name="compare_at_price" value="<?= e($_POST['compare_at_price'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="cost_price">Cost price (internal)</label>
            <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="<?= e($_POST['cost_price'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="tax_rate">VAT rate (%)</label>
            <input type="number" step="0.01" min="0" id="tax_rate" name="tax_rate" value="<?= e($_POST['tax_rate'] ?? setting('vat_rate', '20')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="pricing_unit">Price display</label>
            <select id="pricing_unit" name="pricing_unit">
                <option value="each" <?= (($_POST['pricing_unit'] ?? 'each') === 'each') ? 'selected' : '' ?>>Each</option>
                <option value="from" <?= (($_POST['pricing_unit'] ?? '') === 'from') ? 'selected' : '' ?>>From (price range)</option>
                <option value="set" <?= (($_POST['pricing_unit'] ?? '') === 'set') ? 'selected' : '' ?>>Per set</option>
            </select>
        </div>
        <div class="admin-form-group">
            <label for="min_order_qty">Min order qty</label>
            <input type="number" min="1" id="min_order_qty" name="min_order_qty" value="<?= e($_POST['min_order_qty'] ?? '1') ?>">
        </div>
        <div class="admin-form-group">
            <label for="max_order_qty">Max order qty</label>
            <input type="number" min="1" id="max_order_qty" name="max_order_qty" value="<?= e($_POST['max_order_qty'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="stock_quantity">Stock quantity</label>
            <input type="number" id="stock_quantity" name="stock_quantity" value="<?= e($_POST['stock_quantity'] ?? '0') ?>">
        </div>
        <div class="admin-form-group">
            <label for="lead_time_days">Lead time (days)</label>
            <input type="number" min="0" id="lead_time_days" name="lead_time_days" value="<?= e($_POST['lead_time_days'] ?? '7') ?>">
        </div>
        <div class="admin-form-group full">
            <label for="short_description">Short description</label>
            <textarea id="short_description" name="short_description" rows="2" maxlength="400"><?= e($_POST['short_description'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="description">Full description</label>
            <textarea id="description" name="description" rows="8"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="image">Primary image</label>
            <input type="file" id="image" name="image" accept="image/*" data-preview="#image-preview">
            <img id="image-preview" class="admin-image-preview" style="display:none;" alt="">
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="manage_stock" value="1" <?= isset($_POST['manage_stock']) ? 'checked' : '' ?>> Track stock</label>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="has_variants" value="1" <?= isset($_POST['has_variants']) ? 'checked' : '' ?>> Has size / colour variants</label>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="requires_personalisation" value="1" <?= isset($_POST['requires_personalisation']) ? 'checked' : '' ?>> Requires personalisation</label>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="allow_artwork_upload" value="1" <?= isset($_POST['allow_artwork_upload']) ? 'checked' : '' ?>> Allow artwork upload</label>
        </div>
        <div class="admin-form-group full">
            <label for="artwork_help_text">Artwork help text</label>
            <input type="text" id="artwork_help_text" name="artwork_help_text" value="<?= e($_POST['artwork_help_text'] ?? '') ?>" placeholder="e.g. Upload a PNG under 10MB">
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="is_featured" value="1" <?= isset($_POST['is_featured']) ? 'checked' : '' ?>> Featured on homepage</label>
        </div>
        <div class="admin-form-group">
            <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= isset($_POST['is_active']) || !isset($_POST['name']) ? 'checked' : '' ?>> Active (visible in shop)</label>
        </div>
        <div class="admin-form-group">
            <label for="meta_title">Meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($_POST['meta_title'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="meta_description">Meta description</label>
            <input type="text" id="meta_description" name="meta_description" value="<?= e($_POST['meta_description'] ?? '') ?>">
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Product</button>
        <a href="<?= SITE_URL ?>/admin/products.php" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
