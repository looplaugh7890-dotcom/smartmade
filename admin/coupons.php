<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $type = in_array($_POST['type'] ?? '', ['percent', 'fixed', 'free_shipping'], true) ? $_POST['type'] : 'percent';
            $value = round((float)($_POST['value'] ?? 0), 2);
            $minSubtotal = trim($_POST['min_subtotal'] ?? '') !== '' ? round((float)$_POST['min_subtotal'], 2) : null;
            $maxUses = trim($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
            $startsAt = trim($_POST['starts_at'] ?? '');
            $expiresAt = trim($_POST['expires_at'] ?? '');

            if ($code === '') $errors[] = 'Code is required.';
            if ($type === 'percent' && ($value <= 0 || $value > 100)) $errors[] = 'Percent discount must be between 0 and 100.';
            if ($type === 'fixed' && $value <= 0) $errors[] = 'Fixed discount must be greater than zero.';

            if (empty($errors)) {
                $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
                $check->execute([$code]);
                if ($check->fetch()) $errors[] = 'That coupon code already exists.';
            }

            if (empty($errors)) {
                $ins = $pdo->prepare("
                    INSERT INTO coupons (code, type, value, min_subtotal, max_uses, starts_at, expires_at, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $ins->execute([
                    $code, $type, $value, $minSubtotal, $maxUses,
                    $startsAt !== '' ? $startsAt . ':00' : null,
                    $expiresAt !== '' ? $expiresAt . ':00' : null,
                ]);
                activity_log('coupon.create', 'coupon', (int)$pdo->lastInsertId(), $code);
                set_flash('success', 'Coupon created.');
                header('Location: ' . SITE_URL . '/admin/coupons.php');
                exit;
            }
        } elseif ($action === 'toggle') {
            $cid = (int)($_POST['coupon_id'] ?? 0);
            $pdo->prepare("UPDATE coupons SET is_active = 1 - is_active WHERE id = ?")->execute([$cid]);
            activity_log('coupon.toggle', 'coupon', $cid);
            set_flash('success', 'Coupon status updated.');
            header('Location: ' . SITE_URL . '/admin/coupons.php');
            exit;
        } elseif ($action === 'delete') {
            $cid = (int)($_POST['coupon_id'] ?? 0);
            $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$cid]);
            activity_log('coupon.delete', 'coupon', $cid);
            set_flash('success', 'Coupon deleted.');
            header('Location: ' . SITE_URL . '/admin/coupons.php');
            exit;
        }
    }
}

$coupons = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.coupon_code = c.code) AS order_count
    FROM coupons c
    ORDER BY c.created_at DESC
")->fetchAll();

$pageTitle = 'Coupons';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Coupons</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header"><h2 class="admin-card-title">Create coupon</h2></div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="code">Code *</label>
                <input type="text" id="code" name="code" placeholder="WELCOME10" style="text-transform:uppercase;" required>
            </div>
            <div class="admin-form-group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="percent">Percentage off</option>
                    <option value="fixed">Fixed amount off</option>
                    <option value="free_shipping">Free shipping</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="value">Value</label>
                <input type="number" step="0.01" min="0" id="value" name="value" value="10.00">
            </div>
            <div class="admin-form-group">
                <label for="min_subtotal">Min subtotal</label>
                <input type="number" step="0.01" min="0" id="min_subtotal" name="min_subtotal" placeholder="optional">
            </div>
            <div class="admin-form-group">
                <label for="max_uses">Max uses</label>
                <input type="number" min="1" id="max_uses" name="max_uses" placeholder="unlimited">
            </div>
            <div class="admin-form-group">
                <label for="starts_at">Starts</label>
                <input type="datetime-local" id="starts_at" name="starts_at">
            </div>
            <div class="admin-form-group">
                <label for="expires_at">Expires</label>
                <input type="datetime-local" id="expires_at" name="expires_at">
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">Create Coupon</button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min</th><th>Used</th><th>Window</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="8" class="admin-empty">No coupons yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td><strong style="text-transform:uppercase;"><?= e($c['code']) ?></strong></td>
                            <td><?= e(str_replace('_', ' ', $c['type'])) ?></td>
                            <td>
                                <?php if ($c['type'] === 'percent'): ?><?= rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.') ?>%
                                <?php elseif ($c['type'] === 'fixed'): ?><?= money($c['value']) ?>
                                <?php else: ?>Free shipping<?php endif; ?>
                            </td>
                            <td><?= $c['min_subtotal'] !== null ? money($c['min_subtotal']) : '—' ?></td>
                            <td><?= (int)$c['used_count'] ?><?= $c['max_uses'] ? ' / ' . (int)$c['max_uses'] : '' ?> (<?= (int)$c['order_count'] ?> orders)</td>
                            <td>
                                <?= $c['starts_at'] ? format_date($c['starts_at'], 'j M Y') : 'Any time' ?><br>
                                <?= $c['expires_at'] ? '→ ' . format_date($c['expires_at'], 'j M Y') : '' ?>
                            </td>
                            <td><span class="badge badge-<?= $c['is_active'] ? 'success' : 'error' ?>"><?= $c['is_active'] ? 'Active' : 'Off' ?></span></td>
                            <td>
                                <div class="admin-table-actions">
                                    <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>"><button type="submit" class="link-btn" style="font-size:inherit;"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this coupon?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>"><button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button></form>
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
