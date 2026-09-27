<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/auth_customer.php';
require_once __DIR__ . '/includes/seo.php';

$cancelled = isset($_GET['cancelled']);
$errors = [];

if (cart_count() === 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('info', 'Your basket is empty.');
    header('Location: ' . SITE_URL . '/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $result = checkout_create_order($_POST);

        if ($result['ok']) {
            $order = $result['order'];
            $_SESSION['last_order_number'] = $order['order_number'];

            if ($order['payment_method'] === 'stripe') {
                try {
                    header('Location: ' . checkout_stripe_redirect($order));
                    exit;
                } catch (Throwable $e) {
                    error_log('Stripe redirect failed: ' . $e->getMessage());
                    $errors[] = 'Card payment is temporarily unavailable. Please choose another payment method.';
                }
            } else {
                header('Location: ' . canonical_url('order/' . $order['order_number']) . '?placed=1');
                exit;
            }
        } else {
            $errors[] = $result['message'];
        }
    }
}

$totals = cart_totals($_POST['shipping_method'] ?? null);
$items = $totals['items'];
$itemCount = $totals['itemCount'];
$methods = shipping_methods_all();
$paymentMethods = payment_methods_available();

$customer = current_customer();
$defaultAddress = $customer ? customer_default_address((int)$customer['id']) : null;

$prefill = [
    'name' => $_POST['name'] ?? ($customer['name'] ?? ''),
    'email' => $_POST['email'] ?? ($customer['email'] ?? ''),
    'phone' => $_POST['phone'] ?? ($customer['phone'] ?? ''),
    'line1' => $_POST['line1'] ?? ($defaultAddress['line1'] ?? ''),
    'line2' => $_POST['line2'] ?? ($defaultAddress['line2'] ?? ''),
    'city' => $_POST['city'] ?? ($defaultAddress['city'] ?? ''),
    'county' => $_POST['county'] ?? ($defaultAddress['county'] ?? ''),
    'postcode' => $_POST['postcode'] ?? ($defaultAddress['postcode'] ?? ''),
    'country' => $_POST['country'] ?? ($defaultAddress['country'] ?? 'United Kingdom'),
    'notes' => $_POST['notes'] ?? '',
];

$pageTitle = 'Checkout';
$pageDescription = 'Complete your SmartMade order.';
$canonicalPath = 'checkout';
$bodyClass = 'checkout-page';
$robots = 'noindex, follow';
$jsonLd = [org_jsonld()];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="checkout-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Step 3 of 3</span>
        <h1 class="page-title reveal" id="checkout-title">Checkout</h1>
    </div>
</section>

<section class="section checkout-section">
    <div class="container">
        <?php if ($cancelled): ?>
            <div class="alert alert-info">Payment was cancelled — your basket is still here whenever you are ready.</div>
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

        <div class="checkout-layout">
            <form action="" method="post" class="checkout-form" id="checkout-form">
                <?= csrf_field() ?>

                <fieldset class="checkout-block">
                    <legend><span class="checkout-step">1</span> Your details</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">Full name *</label>
                            <input type="text" id="name" name="name" class="form-input" required value="<?= e($prefill['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email address *</label>
                            <input type="email" id="email" name="email" class="form-input" required value="<?= e($prefill['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone <span>(for delivery updates)</span></label>
                            <input type="tel" id="phone" name="phone" class="form-input" value="<?= e($prefill['phone']) ?>">
                        </div>
                        <?php if (!$customer && setting('allow_guest_checkout', '1') === '1'): ?>
                            <div class="form-group form-group-full">
                                <p class="form-hint">
                                    Already have an account? <a href="<?= SITE_URL ?>/account.php">Sign in</a> to prefill your details and track orders.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="checkout-block">
                    <legend><span class="checkout-step">2</span> Delivery</legend>

                    <div class="form-group form-group-full">
                        <label class="form-label" for="shipping_method">Delivery option *</label>
                        <div class="shipping-options" id="shipping-options">
                            <?php foreach ($methods as $method): ?>
                                <label class="shipping-option">
                                    <input type="radio" name="shipping_method" value="<?= e($method['code']) ?>"
                                        <?= $method['code'] === ($_POST['shipping_method'] ?? 'uk_standard') ? 'checked' : '' ?>
                                        data-base="<?= e((string)$method['base_price']) ?>"
                                        data-per-item="<?= e((string)$method['per_item_price']) ?>"
                                        data-free-over="<?= e((string)($method['free_over'] ?? '')) ?>">
                                    <span class="shipping-option-body">
                                        <span class="shipping-option-name"><?= e($method['name']) ?></span>
                                        <span class="shipping-option-desc"><?= e($method['description']) ?></span>
                                        <span class="shipping-option-time">Est. <?= (int)$method['min_days'] ?>–<?= (int)$method['max_days'] ?> working days</span>
                                    </span>
                                    <span class="shipping-option-price" data-shipping-price>
                                        <?php
                                        $cost = shipping_cost($method, $totals['subtotal'] - $totals['discount'], $itemCount);
                                        echo $cost > 0 ? e(money($cost)) : 'Free';
                                        ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-grid" id="address-fields">
                        <div class="form-group form-group-full">
                            <label class="form-label" for="line1">Address line 1 *</label>
                            <input type="text" id="line1" name="line1" class="form-input" value="<?= e($prefill['line1']) ?>">
                        </div>
                        <div class="form-group form-group-full">
                            <label class="form-label" for="line2">Address line 2</label>
                            <input type="text" id="line2" name="line2" class="form-input" value="<?= e($prefill['line2']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="city">Town / city *</label>
                            <input type="text" id="city" name="city" class="form-input" value="<?= e($prefill['city']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="county">County</label>
                            <input type="text" id="county" name="county" class="form-input" value="<?= e($prefill['county']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="postcode">Postcode *</label>
                            <input type="text" id="postcode" name="postcode" class="form-input" value="<?= e($prefill['postcode']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-input" value="<?= e($prefill['country']) ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="checkout-block">
                    <legend><span class="checkout-step">3</span> Payment</legend>

                    <div class="payment-options">
                        <?php foreach ($paymentMethods as $code => $method): ?>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="<?= e($code) ?>"
                                    <?= $code === ($_POST['payment_method'] ?? array_key_first($paymentMethods)) ? 'checked' : '' ?>>
                                <span class="payment-option-body">
                                    <span class="payment-option-name"><?= e($method['label']) ?></span>
                                    <span class="payment-option-desc"><?= e($method['description']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (isset($paymentMethods['bank_transfer'])): ?>
                        <?php $bank = [setting('bank_account_name'), setting('bank_sort_code'), setting('bank_account_number')]; ?>
                        <p class="form-hint">After you place the order we will email bank details and confirm your slot in the studio diary.</p>
                    <?php endif; ?>

                    <div class="form-group form-group-full">
                        <label class="form-label" for="notes">Order notes <span>(optional)</span></label>
                        <textarea id="notes" name="notes" class="form-textarea" rows="3" placeholder="Deadline, colour preferences, anything we should know…"><?= e($prefill['notes']) ?></textarea>
                    </div>
                </fieldset>
            </form>

            <aside class="checkout-summary" aria-label="Order summary">
                <h2 class="cart-summary-title">Your order</h2>

                <ul class="checkout-lines">
                    <?php foreach ($items as $item): ?>
                        <li class="checkout-line">
                            <img src="<?= e($item['image'] ?: SITE_URL . '/assets/images/og-default.svg') ?>" alt="" width="56" height="70" loading="lazy">
                            <div>
                                <strong><?= e($item['name']) ?></strong>
                                <?php if ($item['variant_label']): ?><span class="checkout-line-variant"><?= e($item['variant_label']) ?></span><?php endif; ?>
                                <?php if ($item['personalisation']): ?>
                                    <span class="checkout-line-pers">
                                        <?php foreach ($item['personalisation'] as $k => $v): ?>
                                            <?= e(ucwords(str_replace('_', ' ', $k))) ?>: <?= e((string)$v) ?><br>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="checkout-line-qty">Qty <?= (int)$item['quantity'] ?> × <?= e(money($item['unit_price'])) ?></span>
                            </div>
                            <span class="checkout-line-total"><?= e(money($item['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="summary-lines" id="checkout-totals"
                    data-subtotal="<?= e((string)$totals['subtotal']) ?>"
                    data-discount="<?= e((string)$totals['discount']) ?>"
                    data-tax="<?= e((string)$totals['tax']) ?>"
                    data-rate="<?= e((string)$totals['rate']) ?>"
                    data-vat-included="<?= $totals['vatIncluded'] ? '1' : '0' ?>"
                    data-item-count="<?= (int)$itemCount ?>"
                    data-symbol="<?= e(setting('currency_symbol', '£')) ?>">
                    <div><dt>Subtotal</dt><dd data-total-subtotal><?= e(money($totals['subtotal'])) ?></dd></div>
                    <?php if ($totals['discount'] > 0): ?>
                        <div class="summary-discount"><dt>Discount</dt><dd>−<?= e(money($totals['discount'])) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Delivery</dt><dd data-total-shipping><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : '—' ?></dd></div>
                    <div><dt>VAT</dt><dd data-total-tax><?= e(money($totals['tax'])) ?></dd></div>
                    <div class="summary-total"><dt>Total</dt><dd data-total-grand><?= e(money($totals['total'])) ?></dd></div>
                </dl>

                <button type="submit" form="checkout-form" class="btn btn-primary btn-lg btn-block">
                    <?= $totals['total'] > 0 ? 'Place order — ' . e(money($totals['total'])) : 'Place order' ?>
                </button>
                <p class="cart-summary-note">
                    By placing your order you agree to our <a href="<?= SITE_URL ?>/terms.php">terms</a>.
                    Questions? <a href="<?= SITE_URL ?>/contact.php">Get in touch</a>.
                </p>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
