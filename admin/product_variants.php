<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? $_POST['product_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, name, base_price FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . SITE_URL . '/admin/products.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $size = trim($_POST['size'] ?? '');
            $colour = trim($_POST['colour_name'] ?? '');
            $hex = trim($_POST['colour_hex'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $delta = round((float)($_POST['price_delta'] ?? 0), 2);
            $stock = (int)($_POST['stock_quantity'] ?? 0);
            $manage = isset($_POST['manage_stock']) ? 1 : 0;
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($size === '' && $colour === '') $errors[] = 'Enter at least a size or a colour.';
            if ($hex !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) $errors[] = 'Colour hex must look like #C9A227.';

            if (empty($errors)) {
                if ($sku !== '') {
                    $check = $pdo->prepare("SELECT id FROM product_variants WHERE sku = ? LIMIT 1");
                    $check->execute([$sku]);
                    if ($check->fetch()) $errors[] = 'That variant SKU is already in use.';
                }

                if (empty($errors)) {
                    $dup = $pdo->prepare("SELECT id FROM product_variants WHERE product_id = ? AND size <=> ? AND colour_name <=> ? LIMIT 1");
                    $dup->execute([$id, $size ?: null, $colour ?: null]);
                    if ($dup->fetch()) {
                        $errors[] = 'That size / colour combination already exists for this product.';
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO product_variants (product_id, sku, size, colour_name, colour_hex, price_delta, stock_quantity, manage_stock, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            $id, $sku ?: null, $size ?: null, $colour ?: null,
                            $hex !== '' ? $hex : null, $delta, $stock, $manage, $active
                        ]);
                        activity_log('variant.create', 'product', $id, trim($size . ' ' . $colour));
                        set_flash('success', 'Variant added.');
                        header('Location: ' . SITE_URL . '/admin/product_variants.php?id=' . $id);
                        exit;
                    }
                }
            }
        } elseif ($action === 'toggle') {
            $vid = (int)($_POST['variant_id'] ?? 0);
            $pdo->prepare("UPDATE product_variants SET is_active = 1 - is_active WHERE id = ? AND product_id = ?")->execute([$vid, $id]);
            activity_log('variant.toggle', 'product', $id, 'variant #' . $vid);
            set_flash('success', 'Variant visibility updated.');
            header('Location: ' . SITE_URL . '/admin/product_variants.php?id=' . $id);
            exit;
        } elseif ($action === 'delete') {
            $vid = (int)($_POST['variant_id'] ?? 0);
            $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?")->execute([$vid, $id]);
            activity_log('variant.delete', 'product', $id, 'variant #' . $vid);
            set_flash('success', 'Variant deleted.');
            header('Location: ' . SITE_URL . '/admin/product_variants.php?id=' . $id);
            exit;
        }
    }
}

$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order ASC, size ASC, colour_name ASC");
$variants->execute([$id]);
$variants = $variants->fetchAll();

$pageTitle = 'Product Variants';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Variants: <?= e($product['name']) ?></h1>
    <div>
        <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= $id ?>" class="admin-btn admin-btn-secondary">← Back to product</a>
        <a href="<?= SITE_URL ?>/admin/product_pers.php?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Personalisation fields</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header"><h2 class="admin-card-title">Add variant</h2></div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= $id ?>">
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="size">Size</label>
                <input type="text" id="size" name="size" placeholder="S / M / L / XL">
            </div>
            <div class="admin-form-group">
                <label for="colour_name">Colour</label>
                <input type="text" id="colour_name" name="colour_name" placeholder="Black">
            </div>
            <div class="admin-form-group">
                <label for="colour_hex">Colour hex</label>
                <input type="text" id="colour_hex" name="colour_hex" placeholder="#111111">
            </div>
            <div class="admin-form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku">
            </div>
            <div class="admin-form-group">
                <label for="price_delta">Price delta (£)</label>
                <input type="number" step="0.01" id="price_delta" name="price_delta" value="0.00">
                <p class="admin-form-hint">Added to the base price (<?= money($product['base_price']) ?>).</p>
            </div>
            <div class="admin-form-group">
                <label for="stock_quantity">Stock</label>
                <input type="number" id="stock_quantity" name="stock_quantity" value="0">
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="manage_stock" value="1" checked> Track stock</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">Add Variant</button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-card-header"><h2 class="admin-card-title">Variants (<?= count($variants) ?>)</h2></div>
    <?php if (empty($variants)): ?>
        <p class="admin-empty">No variants yet. Enable “Has size / colour variants” on the product so the shop asks buyers to choose one.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Size</th><th>Colour</th><th>SKU</th><th>Delta</th><th>Final price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $v): ?>
                        <tr>
                            <td><?= e($v['size'] ?? '—') ?></td>
                            <td>
                                <?php if ($v['colour_hex']): ?><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= e($v['colour_hex']) ?>;margin-right:6px;vertical-align:middle;"></span><?php endif; ?>
                                <?= e($v['colour_name'] ?? '—') ?>
                            </td>
                            <td><?= e($v['sku'] ?? '—') ?></td>
                            <td><?= $v['price_delta'] >= 0 ? '+' : '' ?><?= money($v['price_delta']) ?></td>
                            <td><strong><?= money($product['base_price'] + (float)$v['price_delta']) ?></strong></td>
                            <td>
                                <?= (int)$v['stock_quantity'] ?>
                                <?php if ($v['manage_stock'] && (int)$v['stock_quantity'] <= (int)setting('low_stock_threshold', '5')): ?>
                                    <span class="badge badge-error">Low</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $v['is_active'] ? 'success' : 'error' ?>"><?= $v['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                            <td>
                                <div class="admin-table-actions">
                                    <form action="" method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="product_id" value="<?= $id ?>">
                                        <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">
                                        <button type="submit" class="link-btn" style="font-size:inherit;"><?= $v['is_active'] ? 'Hide' : 'Show' ?></button>
                                    </form>
                                    <form action="" method="post" style="display:inline;" onsubmit="return confirm('Delete this variant?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= $id ?>">
                                        <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">
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
