<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/includes/auth_customer.php';
require_once __DIR__ . '/includes/seo.php';

$ref = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['ref'] ?? $_POST['ref'] ?? ''));

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$ref]);
$order = $stmt->fetch();

$verified = false;
$verifyError = '';

if ($order) {
    $ownedBySession = ($_SESSION['last_order_number'] ?? '') === $order['order_number'];
    $ownedByCustomer = customer_id() && (int)$order['customer_id'] === customer_id();
    $verified = $ownedBySession || $ownedByCustomer;

    if (!$verified && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $verifyError = 'Security token invalid. Please try again.';
        } else {
            $email = strtolower(trim($_POST['email'] ?? ''));
            if ($email !== '' && strtolower($order['email']) === $email) {
                $verified = true;
                $_SESSION['last_order_number'] = $order['order_number'];
            } else {
                $verifyError = 'That email does not match this order.';
            }
        }
    }
}

$pageTitle = $order && $verified ? 'Order ' . $order['order_number'] : 'Order Confirmation';
$pageDescription = 'Your SmartMade order.';
$canonicalPath = 'order/' . ($ref ?: 'unknown');
$bodyClass = 'order-page';
$robots = 'noindex, nofollow';
$jsonLd = [org_jsonld()];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="order-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);"><?= $verified ? 'Thank you' : 'Order lookup' ?></span>
        <h1 class="page-title reveal" id="order-title">
            <?= $verified ? 'Order received' : 'Find your order' ?>
        </h1>
    </div>
</section>

<section class="section order-section">
    <div class="container">
        <?php if (!$order): ?>
            <div class="empty-state">
                <h2>Order not found</h2>
                <p>We could not find an order with that reference. Check your confirmation email, or <a href="<?= SITE_URL ?>/contact.php">contact us</a>.</p>
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Back to the shop</a>
            </div>

        <?php elseif (!$verified): ?>
            <div class="form-card reveal" style="max-width: 520px; margin: 0 auto;">
                <h2 style="margin-top:0;">Confirm it's you</h2>
                <p style="color: var(--color-text-muted);">Enter the email address used for order <strong><?= e($order['order_number']) ?></strong> to view it.</p>
                <?php if ($verifyError): ?>
                    <div class="alert alert-error"><?= e($verifyError) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ref" value="<?= e($order['order_number']) ?>">
                    <div class="form-group">
                        <label class="form-label" for="verify-email">Email address</label>
                        <input type="email" id="verify-email" name="email" class="form-input" required>
                    </div>
                    <button type="submit" class="btn btn-primary">View my order</button>
                </form>
            </div>

        <?php else: ?>
            <?php if (isset($_GET['placed'])): ?>
                <div class="alert alert-success">
                    <strong>Order placed.</strong> A confirmation has been sent to <?= e($order['email']) ?>.
                </div>
            <?php endif; ?>

            <div class="order-card form-card reveal">
                <div class="order-card-head">
                    <div>
                        <p class="order-number">Order <strong><?= e($order['order_number']) ?></strong></p>
                        <p class="order-date"><?= e(format_date($order['placed_at'], 'j M Y, H:i')) ?></p>
                    </div>
                    <div class="order-statuses">
                        <?= order_status_badge($order['status']) ?>
                        <?= order_status_badge($order['payment_status']) ?>
                    </div>
                </div>

                <?php if ($order['payment_status'] !== 'paid' && $order['payment_method'] === 'bank_transfer'): ?>
                    <div class="alert alert-info">
                        <strong>Waiting for payment.</strong> We will email bank details to <?= e($order['email']) ?> shortly.
                        Once payment lands we start straight away. Reference: <?= e($order['order_number']) ?>
                    </div>
                <?php elseif ($order['payment_status'] === 'paid'): ?>
                    <div class="alert alert-success">Payment received. We will email you when your order ships.</div>
                <?php endif; ?>

                <div class="order-lines">
                    <?php foreach (order_items_for((int)$order['id']) as $item): ?>
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
                    <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                    <?php if ((float)$order['discount_total'] > 0): ?>
                        <div class="summary-discount"><dt>Discount (<?= e($order['coupon_code']) ?>)</dt><dd>−<?= e(money($order['discount_total'])) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Delivery — <?= e($order['shipping_method']) ?></dt><dd><?= (float)$order['shipping_total'] > 0 ? e(money($order['shipping_total'])) : 'Free' ?></dd></div>
                    <div><dt>VAT</dt><dd><?= e(money($order['tax_total'])) ?></dd></div>
                    <div class="summary-total"><dt>Total</dt><dd><?= e(money($order['grand_total'])) ?></dd></div>
                </dl>

                <div class="order-addresses">
                    <div>
                        <h3>Delivering to</h3>
                        <?php $addr = json_decode($order['shipping_address_json'] ?? 'null', true) ?: []; ?>
                        <p>
                            <?= e($order['name']) ?><br>
                            <?php if (!empty($addr['line1'])): ?><?= e($addr['line1']) ?><br><?php endif; ?>
                            <?php if (!empty($addr['line2'])): ?><?= e($addr['line2']) ?><br><?php endif; ?>
                            <?php if (!empty($addr['city'])): ?><?= e($addr['city']) ?><br><?php endif; ?>
                            <?php if (!empty($addr['postcode'])): ?><?= e($addr['postcode']) ?><br><?php endif; ?>
                            <?php if (!empty($addr['country'])): ?><?= e($addr['country']) ?><?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <h3>Questions?</h3>
                        <p>Reply to your confirmation email or <a href="<?= SITE_URL ?>/contact.php">contact the studio</a>. We will send a proof before stitching begins.</p>
                    </div>
                </div>

                <div class="order-actions">
                    <a href="<?= SITE_URL ?>/shop.php" class="btn btn-secondary">Continue shopping</a>
                    <?php if (is_customer_logged_in()): ?>
                        <a href="<?= SITE_URL ?>/account.php?page=orders" class="btn btn-primary">View all orders</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
