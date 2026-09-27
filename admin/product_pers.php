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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $key = slugify(trim($_POST['field_key'] ?? ''));
            $label = trim($_POST['label'] ?? '');
            $type = in_array($_POST['input_type'] ?? '', ['text', 'textarea', 'select', 'checkbox', 'date', 'file'], true) ? $_POST['input_type'] : 'text';
            $placeholder = trim($_POST['placeholder'] ?? '');
            $help = trim($_POST['help_text'] ?? '');
            $options = trim($_POST['options'] ?? '');
            $maxLen = trim($_POST['max_length'] ?? '') !== '' ? max(1, (int)$_POST['max_length']) : null;
            $required = isset($_POST['required']) ? 1 : 0;
            $priceAdd = round((float)($_POST['price_add'] ?? 0), 2);
            $sort = (int)($_POST['sort_order'] ?? 0);

            if ($key === '') $errors[] = 'Field key is required.';
            if ($label === '') $errors[] = 'Label is required.';
            if ($type === 'select' && $options === '') $errors[] = 'Select fields need one option per line.';

            if (empty($errors)) {
                $check = $pdo->prepare("SELECT id FROM product_personalisation WHERE product_id = ? AND field_key = ? LIMIT 1");
                $check->execute([$id, $key]);
                if ($check->fetch()) $errors[] = 'That field key already exists for this product.';
            }

            if (empty($errors)) {
                $optionsJson = null;
                if ($type === 'select' && $options !== '') {
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $options)), fn($o) => $o !== ''));
                    $optionsJson = json_encode($lines, JSON_UNESCAPED_UNICODE);
                }
                $ins = $pdo->prepare("
                    INSERT INTO product_personalisation
                    (product_id, field_key, label, input_type, placeholder, help_text, options_json, max_length, required, price_add, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$id, $key, $label, $type, $placeholder ?: null, $help ?: null, $optionsJson, $maxLen, $required, $priceAdd, $sort]);
                activity_log('personalisation.create', 'product', $id, $key);
                set_flash('success', 'Personalisation field added.');
                header('Location: ' . SITE_URL . '/admin/product_pers.php?id=' . $id);
                exit;
            }
        } elseif ($action === 'delete') {
            $pid = (int)($_POST['pers_id'] ?? 0);
            $pdo->prepare("DELETE FROM product_personalisation WHERE id = ? AND product_id = ?")->execute([$pid, $id]);
            activity_log('personalisation.delete', 'product', $id, 'field #' . $pid);
            set_flash('success', 'Field deleted.');
            header('Location: ' . SITE_URL . '/admin/product_pers.php?id=' . $id);
            exit;
        } elseif ($action === 'save') {
            $rows = $_POST['fields'] ?? [];
            if (is_array($rows)) {
                $upd = $pdo->prepare("UPDATE product_personalisation SET label = ?, required = ?, price_add = ?, sort_order = ?, placeholder = ?, help_text = ? WHERE id = ? AND product_id = ?");
                foreach ($rows as $rowId => $row) {
                    $upd->execute([
                        trim($row['label'] ?? ''),
                        isset($row['required']) ? 1 : 0,
                        round((float)($row['price_add'] ?? 0), 2),
                        (int)($row['sort_order'] ?? 0),
                        trim($row['placeholder'] ?? '') ?: null,
                        trim($row['help_text'] ?? '') ?: null,
                        (int)$rowId,
                        $id
                    ]);
                }
                activity_log('personalisation.update', 'product', $id);
                set_flash('success', 'Personalisation fields saved.');
            }
            header('Location: ' . SITE_URL . '/admin/product_pers.php?id=' . $id);
            exit;
        }
    }
}

$fields = $pdo->prepare("SELECT * FROM product_personalisation WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$fields->execute([$id]);
$fields = $fields->fetchAll();

$pageTitle = 'Personalisation Fields';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Personalisation: <?= e($product['name']) ?></h1>
    <div>
        <a href="<?= SITE_URL ?>/admin/product_variants.php?id=<?= $id ?>" class="admin-btn admin-btn-secondary">Variants</a>
        <a href="<?= SITE_URL ?>/admin/product_edit.php?id=<?= $id ?>" class="admin-btn admin-btn-secondary">← Back to product</a>
    </div>
</div>

<p class="admin-form-hint" style="margin-bottom:18px;">Fields shown to buyers on the product page (name to stitch, position, club number…). Values are saved with the order line so your team can read them in production.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header"><h2 class="admin-card-title">Add field</h2></div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= $id ?>">
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="label">Label *</label>
                <input type="text" id="label" name="label" placeholder="Name to embroider" required>
            </div>
            <div class="admin-form-group">
                <label for="field_key">Field key *</label>
                <input type="text" id="field_key" name="field_key" placeholder="name" required>
                <p class="admin-form-hint">Lowercase, no spaces (auto from label is fine).</p>
            </div>
            <div class="admin-form-group">
                <label for="input_type">Input type</label>
                <select id="input_type" name="input_type">
                    <option value="text">Text</option>
                    <option value="textarea">Long text</option>
                    <option value="select">Dropdown</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="date">Date</option>
                    <option value="file">File upload</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="placeholder">Placeholder</label>
                <input type="text" id="placeholder" name="placeholder">
            </div>
            <div class="admin-form-group">
                <label for="max_length">Max length</label>
                <input type="number" min="1" id="max_length" name="max_length">
            </div>
            <div class="admin-form-group">
                <label for="price_add">Extra price (£)</label>
                <input type="number" step="0.01" min="0" id="price_add" name="price_add" value="0.00">
            </div>
            <div class="admin-form-group">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="0">
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="required" value="1"> Required</label>
            </div>
            <div class="admin-form-group full">
                <label for="help_text">Help text</label>
                <input type="text" id="help_text" name="help_text">
            </div>
            <div class="admin-form-group full">
                <label for="options">Dropdown options (one per line)</label>
                <textarea id="options" name="options" rows="4" placeholder="Front left chest&#10;Back centre&#10;Left sleeve"></textarea>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">Add Field</button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-card-header"><h2 class="admin-card-title">Current fields (<?= count($fields) ?>)</h2></div>
    <?php if (empty($fields)): ?>
        <p class="admin-empty">No personalisation fields yet.</p>
    <?php else: ?>
        <form action="" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Key</th><th>Type</th><th>Label</th><th>Placeholder</th><th>Help</th><th>Extra</th><th>Required</th><th>Order</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td><code><?= e($f['field_key']) ?></code></td>
                                <td><?= e($f['input_type']) ?><?= $f['options_json'] ? ' <small>(' . count(json_decode($f['options_json']) ?: []) . ' options)</small>' : '' ?></td>
                                <td><input type="text" name="fields[<?= (int)$f['id'] ?>][label]" value="<?= e($f['label']) ?>" class="admin-input" style="min-width:140px;"></td>
                                <td><input type="text" name="fields[<?= (int)$f['id'] ?>][placeholder]" value="<?= e($f['placeholder'] ?? '') ?>" class="admin-input" style="min-width:120px;"></td>
                                <td><input type="text" name="fields[<?= (int)$f['id'] ?>][help_text]" value="<?= e($f['help_text'] ?? '') ?>" class="admin-input" style="min-width:120px;"></td>
                                <td><input type="number" step="0.01" name="fields[<?= (int)$f['id'] ?>][price_add]" value="<?= e($f['price_add']) ?>" class="admin-input" style="min-width:80px;"></td>
                                <td><input type="checkbox" name="fields[<?= (int)$f['id'] ?>][required]" value="1" <?= $f['required'] ? 'checked' : '' ?>></td>
                                <td><input type="number" name="fields[<?= (int)$f['id'] ?>][sort_order]" value="<?= (int)$f['sort_order'] ?>" class="admin-input" style="min-width:70px;"></td>
                                <td>
                                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Save</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <div style="padding: 14px 4px 4px;">
            <?php foreach ($fields as $f): ?>
                <form action="" method="post" style="display:inline-block;margin-right:14px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="pers_id" value="<?= (int)$f['id'] ?>">
                    <button type="submit" class="link-btn link-btn-danger" style="font-size:0.8rem;" onclick="return confirm('Delete <?= e($f['label']) ?>?');">Delete “<?= e($f['label']) ?>”</button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
