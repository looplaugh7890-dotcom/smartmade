<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_customer.php';
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/includes/seo.php';

$allowedPages = ['login', 'register', 'forgot', 'reset', 'orders', 'order', 'profile'];
$page = in_array($_GET['page'] ?? '', $allowedPages, true) ? $_GET['page'] : (is_customer_logged_in() ? 'orders' : 'login');

// Guest-only pages
if (in_array($page, ['login', 'register'], true) && is_customer_logged_in()) {
    header('Location: ' . SITE_URL . '/account.php?page=orders');
    exit;
}
// Auth-required pages
if (in_array($page, ['orders', 'order', 'profile'], true)) {
    require_customer();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            $result = customer_login($_POST);
            if ($result['ok']) {
                set_flash('success', $result['message']);
                $redirect = $_SESSION['redirect_after_customer_login'] ?? SITE_URL . '/account.php?page=orders';
                unset($_SESSION['redirect_after_customer_login']);
                header('Location: ' . $redirect);
                exit;
            }
            $errors[] = $result['message'];
        } elseif ($action === 'register') {
            $result = customer_register($_POST);
            if ($result['ok']) {
                set_flash('success', $result['message']);
                header('Location: ' . SITE_URL . '/account.php?page=orders');
                exit;
            }
            $errors[] = $result['message'];
        } elseif ($action === 'forgot') {
            $result = customer_request_reset($_POST['email'] ?? '');
            $success = $result['message'];
        } elseif ($action === 'reset') {
            $result = customer_reset_password($_POST['token'] ?? '', $_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
            if ($result['ok']) {
                set_flash('success', $result['message']);
                header('Location: ' . SITE_URL . '/account.php?page=login');
                exit;
            }
            $errors[] = $result['message'];
        } elseif ($action === 'profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            if (mb_strlen($name) < 2) {
                $errors[] = 'Please enter your name.';
            }
            if ($newPassword !== '' && strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }

            if (!$errors) {
                global $pdo;
                $pdo->prepare("UPDATE customers SET name = ?, phone = ?, company = ? WHERE id = ?")
                    ->execute([$name, $phone ?: null, $company ?: null, customer_id()]);
                if ($newPassword !== '') {
                    $currentHash = $pdo->prepare("SELECT password_hash FROM customers WHERE id = ?");
                    $currentHash->execute([customer_id()]);
                    if (!password_verify($_POST['current_password'] ?? '', $currentHash->fetchColumn())) {
                        $errors[] = 'Current password is incorrect.';
                    } else {
                        $pdo->prepare("UPDATE customers SET password_hash = ? WHERE id = ?")
                            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), customer_id()]);
                    }
                }

                if (!$errors) {
                    $_SESSION['customer_name'] = $name;
                    set_flash('success', 'Your details have been updated.');
                    header('Location: ' . SITE_URL . '/account.php?page=profile');
                    exit;
                }
            }
        } elseif ($action === 'address_save') {
            global $pdo;
            $fields = [
                trim($_POST['label'] ?? 'Home') ?: 'Home',
                trim($_POST['full_name'] ?? ''),
                trim($_POST['line1'] ?? ''),
                trim($_POST['line2'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['county'] ?? ''),
                trim($_POST['postcode'] ?? ''),
                trim($_POST['country'] ?? 'United Kingdom') ?: 'United Kingdom',
                trim($_POST['phone'] ?? ''),
            ];
            if ($fields[2] === '' || $fields[4] === '' || $fields[6] === '') {
                $errors[] = 'Address, town/city and postcode are required.';
            } else {
                if (!empty($_POST['set_default'])) {
                    $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")->execute([customer_id()]);
                }
                $stmt = $pdo->prepare("
                    INSERT INTO customer_addresses (customer_id, label, full_name, line1, line2, city, county, postcode, country, phone, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_merge([customer_id()], $fields, [!empty($_POST['set_default']) ? 1 : 0]));
                set_flash('success', 'Address saved.');
                header('Location: ' . SITE_URL . '/account.php?page=profile');
                exit;
            }
        } elseif ($action === 'logout') {
            customer_logout();
            header('Location: ' . SITE_URL . '/');
            exit;
        }
    }
}

$customer = current_customer();

$orderView = null;
$orderItems = [];
if ($page === 'order') {
    global $pdo;
    $ref = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['ref'] ?? ''));
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1");
    $stmt->execute([$ref, customer_id()]);
    $orderView = $stmt->fetch();
    if ($orderView) {
        $orderItems = order_items_for((int)$orderView['id']);
    }
}

$titles = [
    'login' => 'Sign In',
    'register' => 'Create Account',
    'forgot' => 'Reset Password',
    'reset' => 'Choose a New Password',
    'orders' => 'Your Orders',
    'order' => 'Order Details',
    'profile' => 'Your Profile',
];

$pageTitle = $titles[$page] ?? 'Your Account';
$pageDescription = 'Manage your SmartMade account, orders and details.';
$canonicalPath = 'account' . ($page !== 'orders' ? '/' . $page : '');
$bodyClass = 'account-page';
$robots = 'noindex, follow';
$jsonLd = [org_jsonld()];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="account-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);"><?= $customer ? 'Hello, ' . e($customer['name']) : 'Your account' ?></span>
        <h1 class="page-title reveal" id="account-title"><?= e($pageTitle) ?></h1>
    </div>
</section>

<section class="section account-section">
    <div class="container">
        <?php if ($flashSuccess = get_flash('success')): ?>
            <div class="alert alert-success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashInfo = get_flash('info')): ?>
            <div class="alert alert-info"><?= e($flashInfo) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0 0 0 18px;padding:0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($customer): ?>
            <nav class="account-nav" aria-label="Account sections">
                <a href="<?= SITE_URL ?>/account.php?page=orders" class="<?= $page === 'orders' || $page === 'order' ? 'is-active' : '' ?>">Orders</a>
                <a href="<?= SITE_URL ?>/account.php?page=profile" class="<?= $page === 'profile' ? 'is-active' : '' ?>">Profile & addresses</a>
                <form method="post" class="account-logout">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="link-btn">Sign out</button>
                </form>
            </nav>
        <?php endif; ?>

        <?php if ($page === 'login'): ?>
            <div class="form-card reveal" style="max-width: 480px; margin: 0 auto;">
                <h2 style="margin-top:0;">Sign in</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label" for="login-email">Email address</label>
                        <input type="email" id="login-email" name="email" class="form-input" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
                </form>
                <p class="account-links">
                    <a href="<?= SITE_URL ?>/account.php?page=forgot">Forgotten your password?</a>
                    · <a href="<?= SITE_URL ?>/account.php?page=register">Create an account</a>
                </p>
            </div>

        <?php elseif ($page === 'register'): ?>
            <div class="form-card reveal" style="max-width: 480px; margin: 0 auto;">
                <h2 style="margin-top:0;">Create an account</h2>
                <p class="form-hint">Track orders, save your details and reorder in a couple of clicks.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label" for="reg-name">Full name</label>
                        <input type="text" id="reg-name" name="name" class="form-input" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-email">Email address</label>
                        <input type="email" id="reg-email" name="email" class="form-input" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-phone">Phone <span>(optional)</span></label>
                        <input type="tel" id="reg-phone" name="phone" class="form-input" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-password">Password <span>(min 8 characters)</span></label>
                        <input type="password" id="reg-password" name="password" class="form-input" required minlength="8">
                    </div>
                    <label class="admin-checkbox">
                        <input type="checkbox" name="marketing_optin" value="1">
                        Email me occasional studio updates
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Create account</button>
                </form>
                <p class="account-links">Already registered? <a href="<?= SITE_URL ?>/account.php?page=login">Sign in</a></p>
            </div>

        <?php elseif ($page === 'forgot'): ?>
            <div class="form-card reveal" style="max-width: 480px; margin: 0 auto;">
                <h2 style="margin-top:0;">Reset your password</h2>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="forgot">
                    <div class="form-group">
                        <label class="form-label" for="forgot-email">Email address</label>
                        <input type="email" id="forgot-email" name="email" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
                </form>
                <p class="account-links"><a href="<?= SITE_URL ?>/account.php?page=login">Back to sign in</a></p>
            </div>

        <?php elseif ($page === 'reset'): ?>
            <div class="form-card reveal" style="max-width: 480px; margin: 0 auto;">
                <h2 style="margin-top:0;">Choose a new password</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= e($_GET['token'] ?? '') ?>">
                    <div class="form-group">
                        <label class="form-label" for="reset-password">New password</label>
                        <input type="password" id="reset-password" name="password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reset-confirm">Confirm new password</label>
                        <input type="password" id="reset-confirm" name="password_confirm" class="form-input" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Update password</button>
                </form>
            </div>

        <?php elseif ($page === 'orders'): ?>
            <?php
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY placed_at DESC");
            $stmt->execute([customer_id()]);
            $orders = $stmt->fetchAll();
            ?>
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <h2>No orders yet</h2>
                    <p>When you place an order it will appear here with live status updates.</p>
                    <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Start shopping</a>
                </div>
            <?php else: ?>
                <div class="admin-card reveal">
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><strong><?= e($o['order_number']) ?></strong></td>
                                        <td><?= e(format_date($o['placed_at'])) ?></td>
                                        <td><?= order_status_badge($o['status']) ?></td>
                                        <td><?= e(money($o['grand_total'])) ?></td>
                                        <td><a href="<?= SITE_URL ?>/account.php?page=order&ref=<?= e($o['order_number']) ?>">View →</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($page === 'order'): ?>
            <?php if (!$orderView): ?>
                <div class="empty-state">
                    <h2>Order not found</h2>
                    <a href="<?= SITE_URL ?>/account.php?page=orders" class="btn btn-primary">Back to your orders</a>
                </div>
            <?php else: ?>
                <div class="form-card reveal">
                    <div class="order-card-head">
                        <div>
                            <p class="order-number">Order <strong><?= e($orderView['order_number']) ?></strong></p>
                            <p class="order-date"><?= e(format_date($orderView['placed_at'], 'j M Y, H:i')) ?></p>
                        </div>
                        <div class="order-statuses">
                            <?= order_status_badge($orderView['status']) ?>
                            <?= order_status_badge($orderView['payment_status']) ?>
                        </div>
                    </div>

                    <div class="order-lines">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="order-line">
                                <div>
                                    <strong><?= e($item['product_name']) ?></strong>
                                    <?php if ($item['variant_label']): ?><span class="checkout-line-variant"><?= e($item['variant_label']) ?></span><?php endif; ?>
                                    <?php if ($item['personalisation']): ?>
                                        <div class="checkout-line-pers">
                                            <?php foreach ($item['personalisation'] as $k => $v): ?>
                                                <?= e(ucwords(str_replace('_', ' ', $k))) ?>: <?= e((string)$v) ?><br>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['artwork']): ?>
                                        <div class="order-artwork">
                                            Artwork:
                                            <?php foreach ($item['artwork'] as $art): ?>
                                                <a href="<?= SITE_URL . '/' . e($art['file_path']) ?>" target="_blank" rel="noopener"><?= e($art['original_name'] ?: 'file') ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-line-qty">× <?= (int)$item['quantity'] ?></div>
                                <div class="order-line-total"><?= e(money($item['line_total'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <dl class="summary-lines">
                        <div><dt>Subtotal</dt><dd><?= e(money($orderView['subtotal'])) ?></dd></div>
                        <?php if ((float)$orderView['discount_total'] > 0): ?>
                            <div class="summary-discount"><dt>Discount</dt><dd>−<?= e(money($orderView['discount_total'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Delivery</dt><dd><?= (float)$orderView['shipping_total'] > 0 ? e(money($orderView['shipping_total'])) : 'Free' ?></dd></div>
                        <div><dt>VAT</dt><dd><?= e(money($orderView['tax_total'])) ?></dd></div>
                        <div class="summary-total"><dt>Total</dt><dd><?= e(money($orderView['grand_total'])) ?></dd></div>
                    </dl>

                    <p><a href="<?= SITE_URL ?>/account.php?page=orders" class="btn btn-secondary">← All orders</a></p>
                </div>
            <?php endif; ?>

        <?php elseif ($page === 'profile'): ?>
            <div class="account-grid">
                <div class="form-card reveal">
                    <h2 style="margin-top:0;">Your details</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="profile">
                        <div class="form-group">
                            <label class="form-label" for="prof-name">Full name</label>
                            <input type="text" id="prof-name" name="name" class="form-input" required value="<?= e($customer['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prof-email">Email</label>
                            <input type="email" class="form-input" value="<?= e($customer['email']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prof-phone">Phone</label>
                            <input type="tel" id="prof-phone" name="phone" class="form-input" value="<?= e($customer['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prof-company">Company <span>(optional)</span></label>
                            <input type="text" id="prof-company" name="company" class="form-input" value="<?= e($customer['company'] ?? '') ?>">
                        </div>

                        <hr style="border:0;border-top:1px solid var(--color-border, #E4DCC8);margin:20px 0;">

                        <div class="form-group">
                            <label class="form-label" for="prof-current">Current password <span>(to change password)</span></label>
                            <input type="password" id="prof-current" name="current_password" class="form-input" autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prof-new">New password</label>
                            <input type="password" id="prof-new" name="new_password" class="form-input" minlength="8" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </form>
                </div>

                <div class="form-card reveal">
                    <h2 style="margin-top:0;">Saved addresses</h2>
                    <?php
                    global $pdo;
                    $addrStmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id ASC");
                    $addrStmt->execute([customer_id()]);
                    $addresses = $addrStmt->fetchAll();
                    ?>
                    <?php foreach ($addresses as $addr): ?>
                        <div class="saved-address">
                            <strong><?= e($addr['label']) ?><?= $addr['is_default'] ? ' · default' : '' ?></strong>
                            <p>
                                <?= e($addr['full_name']) ?><br>
                                <?= e($addr['line1']) ?><br>
                                <?php if ($addr['line2']): ?><?= e($addr['line2']) ?><br><?php endif; ?>
                                <?= e($addr['city']) ?>, <?= e($addr['postcode']) ?><br>
                                <?= e($addr['country']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>

                    <h3>Add an address</h3>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="address_save">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="addr-label">Label</label>
                                <input type="text" id="addr-label" name="label" class="form-input" value="Home">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-name">Full name</label>
                                <input type="text" id="addr-name" name="full_name" class="form-input" value="<?= e($customer['name']) ?>">
                            </div>
                            <div class="form-group form-group-full">
                                <label class="form-label" for="addr-line1">Address</label>
                                <input type="text" id="addr-line1" name="line1" class="form-input" required>
                            </div>
                            <div class="form-group form-group-full">
                                <label class="form-label" for="addr-line2">Address line 2</label>
                                <input type="text" id="addr-line2" name="line2" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-city">Town / city</label>
                                <input type="text" id="addr-city" name="city" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-county">County</label>
                                <input type="text" id="addr-county" name="county" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-postcode">Postcode</label>
                                <input type="text" id="addr-postcode" name="postcode" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-country">Country</label>
                                <input type="text" id="addr-country" name="country" class="form-input" value="United Kingdom">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="addr-phone">Phone</label>
                                <input type="tel" id="addr-phone" name="phone" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="admin-checkbox">
                                    <input type="checkbox" name="set_default" value="1"> Set as default
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save address</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
