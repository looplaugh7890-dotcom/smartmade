<?php

if (!isset($drawerItems)) {
    $drawerItems = $items ?? [];
}
if (!isset($drawerTotals)) {
    $drawerTotals = $totals ?? cart_totals();
}
$drawerCount = 0;
foreach ($drawerItems as $di) {
    $drawerCount += (int)$di['quantity'];
}
?>
<div class="cart-drawer-head">
    <div class="cart-drawer-heading">
        <p class="cart-drawer-title">Your basket</p>
        <p class="cart-drawer-count" data-cart-drawer-count><?= $drawerCount === 1 ? '1 item' : (int)$drawerCount . ' items' ?></p>
    </div>
    <button type="button" class="cart-drawer-close" data-cart-drawer-close aria-label="Close basket">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"></path></svg>
    </button>
</div>

<?php if (empty($drawerItems)): ?>
    <div class="cart-drawer-empty">
        <span class="cart-drawer-empty-mark" aria-hidden="true">SM</span>
        <h2>Your basket is empty</h2>
        <p>Have a browse — or send us the details for a bespoke job and we'll price it up.</p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary" data-cart-drawer-close>Shop the collection</a>
        <a href="<?= SITE_URL ?>/quote.php" class="btn btn-secondary" data-cart-drawer-close>Get a quote</a>
    </div>
<?php else: ?>
    <div class="cart-drawer-body">
        <?php foreach ($drawerItems as $item): ?>
            <article class="cart-drawer-line<?= $item['out_of_stock'] ? ' is-unavailable' : '' ?>" data-item-id="<?= (int)$item['id'] ?>">
                <a class="cart-drawer-line-media" href="<?= canonical_url('product/' . $item['slug']) ?>" data-cart-drawer-close>
                    <img src="<?= e($item['image'] ?: SITE_URL . '/assets/images/og-default.svg') ?>" alt="" width="72" height="90" loading="lazy">
                </a>

                <div class="cart-drawer-line-body">
                    <a class="cart-drawer-line-title" href="<?= canonical_url('product/' . $item['slug']) ?>" data-cart-drawer-close><?= e($item['name']) ?></a>

                    <?php if ($item['variant_label']): ?>
                        <p class="cart-drawer-line-variant"><?= e($item['variant_label']) ?></p>
                    <?php endif; ?>

                    <?php if ($item['out_of_stock']): ?>
                        <p class="cart-drawer-line-warning">Out of stock</p>
                    <?php endif; ?>

                    <form class="cart-drawer-line-form" method="post" action="<?= SITE_URL ?>/cart.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">

                        <div class="cart-drawer-qty">
                            <button type="submit" name="action" value="update" class="cart-drawer-qty-btn" data-qty-delta="-1" aria-label="Decrease quantity">&minus;</button>
                            <input type="number" name="quantity" class="cart-drawer-qty-input" value="<?= (int)$item['quantity'] ?>" min="1" inputmode="numeric" aria-label="Quantity for <?= e($item['name']) ?>">
                            <button type="submit" name="action" value="update" class="cart-drawer-qty-btn" data-qty-delta="1" aria-label="Increase quantity">+</button>
                        </div>

                        <button type="submit" name="action" value="remove" class="cart-drawer-remove">Remove</button>
                    </form>
                </div>

                <span class="cart-drawer-line-total"><?= money($item['line_total']) ?></span>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="cart-drawer-foot">
        <div class="cart-drawer-subtotal">
            <span>Subtotal</span>
            <strong data-cart-drawer-subtotal><?= money($drawerTotals['subtotal']) ?></strong>
        </div>
        <?php if (!empty($drawerTotals['coupon'])): ?>
            <div class="cart-drawer-subtotal is-coupon">
                <span>Discount (<?= e($drawerTotals['coupon']['code']) ?>)</span>
                <strong>&minus;<?= money($drawerTotals['discount']) ?></strong>
            </div>
        <?php endif; ?>
        <p class="cart-drawer-note">Shipping and discounts are worked out at checkout.</p>
        <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary btn-block" data-cart-drawer-close>Checkout now</a>
        <a href="<?= SITE_URL ?>/cart.php" class="btn btn-secondary btn-block" data-cart-drawer-close>View basket</a>
    </div>
<?php endif; ?>
