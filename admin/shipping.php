<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $sid = (int)($_POST['id'] ?? 0);
            $code = slugify(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $base = round((float)($_POST['base_price'] ?? 0), 2);
            $perItem = round((float)($_POST['per_item_price'] ?? 0), 2);
            $freeOver = trim($_POST['free_over'] ?? '') !== '' ? round((float)$_POST['free_over'], 2) : null;
            $minDays = max(0, (int)($_POST['min_days'] ?? 3));
            $maxDays = max($minDays, (int)($_POST['max_days'] ?? 7));
            $sort = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') $errors[] = 'Name is required.';
            if ($code === '') $errors[] = 'Code is required.';

            if (empty($errors) && $action === 'add') {
                $check = $pdo->prepare("SELECT id FROM shipping_methods WHERE code = ? LIMIT 1");
                $check->execute([$code]);
                if ($check->fetch()) $errors[] = 'That code already exists.';
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $ins = $pdo->prepare("
                        INSERT INTO shipping_methods (code, name, description, base_price, per_item_price, free_over, min_days, max_days, sort_order, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$code, $name, $description ?: null, $base, $perItem, $freeOver, $minDays, $maxDays, $sort, $active]);
                    activity_log('shipping.create', 'shipping', (int)$pdo->lastInsertId(), $name);
                    set_flash('success', 'Shipping method added.');
                } else {
                    $upd = $pdo->prepare("
                        UPDATE shipping_methods SET name = ?, description = ?, base_price = ?, per_item_price = ?,
                        free_over = ?, min_days = ?, max_days = ?, sort_order = ?, is_active = ? WHERE id = ?
                    ");
                    $upd->execute([$name, $description ?: null, $base, $perItem, $freeOver, $minDays, $maxDays, $sort, $active, $sid]);
                    activity_log('shipping.update', 'shipping', $sid, $name);
                    set_flash('success', 'Shipping method updated.');
                }
                header('Location: ' . SITE_URL . '/admin/shipping.php');
                exit;
            }
        } elseif ($action === 'delete') {
            $sid = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM shipping_methods WHERE id = ?")->execute([$sid]);
            activity_log('shipping.delete', 'shipping', $sid);
            set_flash('success', 'Shipping method deleted.');
            header('Location: ' . SITE_URL . '/admin/shipping.php');
            exit;
        }
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $sel = $pdo->prepare("SELECT * FROM shipping_methods WHERE id = ?");
    $sel->execute([(int)$_GET['edit']]);
    $editing = $sel->fetch() ?: null;
}

$methods = $pdo->query("SELECT * FROM shipping_methods ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle = 'Shipping Methods';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Shipping Methods</h1>
    <a href="<?= SITE_URL ?>/admin/settings.php?tab=store" class="admin-btn admin-btn-secondary">Store settings</a>
</div>

<p class="admin-form-hint" style="margin-bottom:18px;">Offered at checkout. Cost = base + (per item × items), dropped to free when the order qualifies via “free over”.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title"><?= $editing ? 'Edit method' : 'Add method' ?></h2>
        <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/shipping.php" class="admin-btn admin-btn-secondary admin-btn-sm">Cancel edit</a><?php endif; ?>
    </div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" placeholder="Royal Mail Tracked 48" required>
            </div>
            <div class="admin-form-group">
                <label for="code">Code *</label>
                <input type="text" id="code" name="code" value="<?= e($editing['code'] ?? '') ?>" placeholder="tracked48" <?= $editing ? 'readonly' : '' ?>>
            </div>
            <div class="admin-form-group">
                <label for="base_price">Base price (£)</label>
                <input type="number" step="0.01" min="0" id="base_price" name="base_price" value="<?= e($editing['base_price'] ?? '0.00') ?>">
            </div>
            <div class="admin-form-group">
                <label for="per_item_price">Per item (£)</label>
                <input type="number" step="0.01" min="0" id="per_item_price" name="per_item_price" value="<?= e($editing['per_item_price'] ?? '0.00') ?>">
            </div>
            <div class="admin-form-group">
                <label for="free_over">Free over (£)</label>
                <input type="number" step="0.01" min="0" id="free_over" name="free_over" value="<?= e($editing['free_over'] ?? '') ?>" placeholder="never">
            </div>
            <div class="admin-form-group">
                <label for="min_days">Min days</label>
                <input type="number" min="0" id="min_days" name="min_days" value="<?= e($editing['min_days'] ?? '3') ?>">
            </div>
            <div class="admin-form-group">
                <label for="max_days">Max days</label>
                <input type="number" min="0" id="max_days" name="max_days" value="<?= e($editing['max_days'] ?? '7') ?>">
            </div>
            <div class="admin-form-group">
                <label for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e($editing['sort_order'] ?? '0') ?>">
            </div>
            <div class="admin-form-group full">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" value="<?= e($editing['description'] ?? '') ?>">
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary"><?= $editing ? 'Save Method' : 'Add Method' ?></button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Code</th><th>Cost</th><th>Free over</th><th>ETA</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($methods)): ?>
                    <tr><td colspan="8" class="admin-empty">No shipping methods — checkout will show no delivery options.</td></tr>
                <?php else: ?>
                    <?php foreach ($methods as $m): ?>
                        <tr>
                            <td><strong><?= e($m['name']) ?></strong></td>
                            <td><code><?= e($m['code']) ?></code></td>
                            <td><?= money($m['base_price']) ?><?= (float)$m['per_item_price'] > 0 ? ' + ' . money($m['per_item_price']) . '/item' : '' ?></td>
                            <td><?= $m['free_over'] !== null ? money($m['free_over']) : '—' ?></td>
                            <td><?= (int)$m['min_days'] ?>–<?= (int)$m['max_days'] ?> days</td>
                            <td><?= (int)$m['sort_order'] ?></td>
                            <td><span class="badge badge-<?= $m['is_active'] ? 'success' : 'error' ?>"><?= $m['is_active'] ? 'Active' : 'Off' ?></span></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="?edit=<?= (int)$m['id'] ?>">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this shipping method?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
