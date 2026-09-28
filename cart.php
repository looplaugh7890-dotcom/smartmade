<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/seo.php';

$errors = [];

function cart_wants_json(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function cart_send_json(bool $ok, string $message): void
{
    $totals = cart_totals();
    $items = $totals['items'];
    $drawerItems = $items;
    $drawerTotals = $totals;

    ob_start();
    include __DIR__ . '/includes/cart_drawer_partial.php';
    $html = ob_get_clean();

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => $ok,
        'message' => $message,
        'count' => array_sum(array_column($items, 'quantity')),
        'html' => $html,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = cart_wants_json();

    if (!verify_csrf()) {
        $message = 'Security token invalid. Please refresh and try again.';
        if ($isAjax) {
            cart_send_json(false, $message);
        }
        $errors[] = $message;
    } else {
        $action = $_POST['action'] ?? '';
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($action === 'update') {
            $result = cart_update_item($itemId, (int)($_POST['quantity'] ?? 1));
            if (!$result['ok']) {
                if ($isAjax) {
                    cart_send_json(false, $result['message']);
                }
                $errors[] = $result['message'];
            } else {
                if ($isAjax) {
                    cart_send_json(true, $result['message']);
                }
                set_flash('success', $result['message']);
                header('Location: ' . canonical_url('cart'));
                exit;
            }
        } elseif ($action === 'remove') {
            $result = cart_remove_item($itemId);
            if ($isAjax) {
                cart_send_json((bool)$result['ok'], $result['message']);
            }
            set_flash('info', $result['message']);
            header('Location: ' . canonical_url('cart'));
            exit;
        } elseif ($action === 'coupon') {
            $result = cart_set_coupon(trim((string)($_POST['coupon'] ?? '')));
            if ($isAjax) {
                cart_send_json((bool)$result['ok'], $result['message']);
            }
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            header('Location: ' . canonical_url('cart'));
            exit;
        } elseif ($action === 'clear') {
            cart_clear();
            if ($isAjax) {
                cart_send_json(true, 'Your basket has been emptied.');
            }
            set_flash('info', 'Your basket has been emptied.');
            header('Location: ' . canonical_url('cart'));
            exit;
        }
    }
}

$totals = cart_totals();
$items = $totals['items'];
$coupon = $totals['coupon'];

$pageTitle = 'Your Basket';
$pageDescription = 'Review the items in your SmartMade basket.';
$canonicalPath = 'cart';
$bodyClass = 'cart-page';
$robots = 'noindex, follow';
$jsonLd = [org_jsonld()];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="cart-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Nearly there</span>
        <h1 class="page-title reveal" id="cart-title">Your Basket</h1>
    </div>
</section>

<section class="section cart-section">
    <div class="container">
        <?php if ($flashSuccess = get_flash('success')): ?>
            <div class="alert alert-success"><?= e($flashSuccess) ?></div>
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

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <h2>Your basket is empty</h2>
                <p>Have a browse — or if it is a bespoke job, send us the details and we will price it up.</p>
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Shop the collection</a>
                <a href="<?= SITE_URL ?>/quote.php" class="btn btn-secondary">Get a quote</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($items as $item): ?>
                        <article class="cart-line<?= $item['out_of_stock'] ? ' is-unavailable' : '' ?>">
                            <div class="cart-line-media">
                                <img src="<?= e($item['image'] ?: SITE_URL . '/assets/images/og-default.svg') ?>"
                                     alt="<?= e($item['name']) ?>" width="120" height="150" loading="lazy">
                            </div>

                            <div class="cart-line-body">
                                <h2 class="cart-line-title">
                                    <a href="<?= canonical_url('product/' . $item['slug']) ?>"><?= e($item['name']) ?></a>
                                </h2>

                                <?php if ($item['variant_label']): ?>
                                    <p class="cart-line-variant"><?= e($item['variant_label']) ?></p>
                                <?php endif; ?>

                                <?php if ($item['personalisation']): ?>
                                    <dl class="cart-line-personalisation">
                                        <?php foreach ($item['personalisation'] as $key => $value): ?>
                                            <dt><?= e(ucwords(str_replace('_', ' ', $key))) ?></dt>
                                            <dd><?= e((string)$value) ?></dd>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>

                                <?php if ($item['artwork']): ?>
                                    <div class="cart-line-artwork">
                                        <span class="cart-line-artwork-label">Your artwork:</span>
                                        <?php foreach ($item['artwork'] as $path): ?>
                                            <?php if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $path)): ?>
                                                <a href="<?= SITE_URL . '/' . e($path) ?>" target="_blank" rel="noopener">
                                                    <img src="<?= SITE_URL . '/' . e($path) ?>" alt="Uploaded artwork" width="56" height="56" loading="lazy">
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= SITE_URL . '/' . e($path) ?>" target="_blank" rel="noopener" class="artwork-file">PDF</a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($item['out_of_stock']): ?>
                                    <p class="cart-line-warning">Out of stock — please reduce the quantity or remove this item.</p>
                                <?php endif; ?>
                            </div>

                            <div class="cart-line-actions">
                                <form action="" method="post" class="cart-line-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <label class="visually-hidden" for="qty-<?= (int)$item['id'] ?>">Quantity</label>
                                    <input type="number" class="qty-input" id="qty-<?= (int)$item['id'] ?>"
                                           name="quantity" value="<?= (int)$item['quantity'] ?>"
                                           min="0"
                                           <?= $item['max_order_qty'] !== null ? 'max="' . (int)$item['max_order_qty'] . '"' : '' ?>>
                                    <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                                </form>

                                <p class="cart-line-price"><?= e(money($item['line_total'])) ?></p>
                                <p class="cart-line-unit"><?= e(money($item['unit_price'])) ?> each</p>

                                <form action="" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="link-btn link-btn-danger">Remove</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <div class="cart-footer-actions">
                        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-secondary">← Continue shopping</a>
                        <form action="" method="post" onsubmit="return confirm('Empty your entire basket?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="link-btn link-btn-danger">Empty basket</button>
                        </form>
                    </div>
                </div>

                <aside class="cart-summary" aria-label="Order summary">
                    <h2 class="cart-summary-title">Order summary</h2>

                    <form action="" method="post" class="coupon-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="coupon">
                        <label for="coupon" class="visually-hidden">Promo code</label>
                        <input type="text" id="coupon" name="coupon" class="form-input" placeholder="Promo code"
                               value="<?= e($coupon['code'] ?? '') ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
                    </form>
                    <?php if ($coupon): ?>
                        <p class="coupon-applied">
                            Code <strong><?= e($coupon['code']) ?></strong> applied
                            <?= $coupon['type'] === 'percent' ? e($coupon['value'] . '% off') : ($coupon['type'] === 'fixed' ? e(money($coupon['value']) . ' off') : 'free delivery') ?>
                        </p>
                    <?php endif; ?>

                    <dl class="summary-lines">
                        <div><dt>Subtotal</dt><dd><?= e(money($totals['subtotal'])) ?></dd></div>
                        <?php if ($totals['discount'] > 0): ?>
                            <div class="summary-discount"><dt>Discount</dt><dd>−<?= e(money($totals['discount'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Delivery</dt><dd>Calculated at checkout</dd></div>
                        <?php if ($totals['tax'] > 0): ?>
                            <div><dt>VAT <?= e(number_format($totals['rate'] * 100, 0)) ?>%</dt><dd><?= e(money($totals['tax'])) ?></dd></div>
                        <?php endif; ?>
                        <div class="summary-total"><dt>Total</dt><dd><?= e(money($totals['subtotal'] - $totals['discount'])) ?></dd></div>
                    </dl>

                    <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary btn-lg btn-block">Proceed to checkout</a>
                    <p class="cart-summary-note">Delivery calculated at the next step. Secure payment, proof before stitching.</p>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
