<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/cart.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['slug'] ?? $_POST['product_slug'] ?? '')));
$product = $slug ? product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    $pageDescription = 'That product could not be found.';
    $robots = 'noindex, follow';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <h1>Product not found</h1>
                <p>That item has moved or is no longer available.</p>
                <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Back to the shop</a>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
$added = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $result = cart_add([
            'product_id' => (int)$product['id'],
            'variant_id' => (int)($_POST['variant_id'] ?? 0),
            'quantity' => (int)($_POST['quantity'] ?? 1),
            'personalisation' => $_POST['personalisation'] ?? [],
        ]);
        if ($result['ok']) {
            set_flash('success', $result['message']);
            header('Location: ' . canonical_url('product/' . $product['slug']) . '?added=1');
            exit;
        }
        $errors[] = $result['message'];
    }
}

$images = product_images_for((int)$product['id']);
$variants = product_variants_for((int)$product['id']);
$rules = product_personalisation_for((int)$product['id']);

$prices = [(float)$product['base_price']];
foreach ($variants as $v) {
    $prices[] = (float)$product['base_price'] + (float)$v['price_delta'];
}

$primaryImage = null;
foreach ($images as $img) {
    if ($img['is_primary']) {
        $primaryImage = $img;
        break;
    }
}
if (!$primaryImage && $images) {
    $primaryImage = $images[0];
}

$rating = setting('enable_reviews', '1') ? product_rating_summary((int)$product['id']) : ['count' => 0, 'average' => 0];

$breadcrumb = [
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Shop', 'url' => canonical_url('shop')],
];
if ($product['category_slug']) {
    $breadcrumb[] = ['name' => $product['category_name'], 'url' => canonical_url('shop/' . $product['category_slug'])];
}
$breadcrumb[] = ['name' => $product['name'], 'url' => canonical_url('product/' . $product['slug'])];

$pageTitle = $product['meta_title'] ?: $product['name'];
$pageDescription = $product['meta_description'] ?: ($product['short_description'] ?: excerpt((string)$product['description'], 155));
$canonicalPath = 'product/' . $product['slug'];
$bodyClass = 'product-page';
$ogImage = product_card_image($product);

$allImages = array_map(static fn($i) => SITE_URL . '/' . $i['path'], $images);
$jsonLd = [org_jsonld(), json_decode(product_jsonld($product, $allImages ?: [$ogImage], $rating['count'] ? $rating['average'] : null, $rating['count']), true)];
if ($breadcrumb) {
    $jsonLd[] = json_decode(breadcrumb_jsonld($breadcrumb), true);
}

include __DIR__ . '/includes/header.php';
?>

<section class="product-section section">
    <div class="container">
        <?= breadcrumb_html($breadcrumb) ?>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                Added to your basket. <a href="<?= SITE_URL ?>/cart.php">View basket</a> or <a href="<?= SITE_URL ?>/shop.php">keep shopping</a>.
            </div>
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

        <div class="product-layout">
            <div class="product-gallery">
                <div class="product-gallery-main">
                    <?php if ($primaryImage): ?>
                        <img src="<?= SITE_URL . '/' . e($primaryImage['path']) ?>"
                             alt="<?= e($product['name']) ?>"
                             id="product-main-image"
                             width="900" height="1125" decoding="async">
                    <?php else: ?>
                        <img src="<?= SITE_URL ?>/assets/images/og-default.svg" alt="<?= e($product['name']) ?>" id="product-main-image" width="900" height="1125">
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="product-gallery-thumbs" role="list">
                        <?php foreach ($images as $i => $img): ?>
                            <button type="button" class="product-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                    data-image="<?= SITE_URL . '/' . e($img['path']) ?>"
                                    aria-label="View image <?= $i + 1 ?>">
                                <img src="<?= SITE_URL . '/' . e($img['path']) ?>" alt="" loading="lazy" width="120" height="120">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <span class="section-label" style="color: var(--color-gold-light);"><?= e($product['category_name'] ?: 'SmartMade') ?></span>
                <h1 class="product-title"><?= e($product['name']) ?></h1>

                <div class="product-meta-row">
                    <?= product_price_html($product, $prices) ?>
                    <?php if ($rating['count'] > 0): ?>
                        <span class="rating">
                            <span class="star-rating" aria-label="<?= $rating['average'] ?> out of 5"><?= str_repeat('★', (int)round($rating['average'])) . str_repeat('☆', 5 - (int)round($rating['average'])) ?></span>
                            <a href="#product-reviews" class="rating-count">(<?= (int)$rating['count'] ?> reviews)</a>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($product['short_description']): ?>
                    <p class="product-short-desc"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <form action="<?= canonical_url('product/' . $product['slug']) ?>" method="post" enctype="multipart/form-data" class="product-form" id="product-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="product_slug" value="<?= e($product['slug']) ?>">

                    <?php if (!empty($variants)): ?>
                        <?php
                        $sizes = [];
                        $colours = [];
                        foreach ($variants as $v) {
                            if ($v['size'] !== null && $v['size'] !== '' && !in_array($v['size'], $sizes, true)) {
                                $sizes[] = $v['size'];
                            }
                            if ($v['colour_name'] !== null && $v['colour_name'] !== '' && !in_array($v['colour_name'], $colours, true)) {
                                $colours[] = $v['colour_name'];
                            }
                        }
                        $variantData = array_map(static fn($v) => [
                            'id' => (int)$v['id'],
                            'size' => $v['size'],
                            'colour' => $v['colour_name'],
                            'hex' => $v['colour_hex'],
                            'delta' => (float)$v['price_delta'],
                            'stock' => (int)$v['stock_quantity'],
                            'manage' => (bool)$v['manage_stock'],
                        ], $variants);
                        ?>

                        <div class="product-options" id="product-options"
                             data-variants='<?= e(json_encode($variantData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'
                             data-base-price="<?= e((string)$product['base_price']) ?>"
                             data-symbol="<?= e(setting('currency_symbol', '£')) ?>">
                            <?php if ($sizes): ?>
                                <fieldset class="option-group">
                                    <legend>Size <span class="option-selected" data-selected-size></span></legend>
                                    <div class="option-pills">
                                        <?php foreach ($sizes as $size): ?>
                                            <label class="option-pill">
                                                <input type="radio" name="size_choice" value="<?= e($size) ?>">
                                                <span><?= e($size) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>

                            <?php if ($colours): ?>
                                <fieldset class="option-group">
                                    <legend>Colour <span class="option-selected" data-selected-colour></span></legend>
                                    <div class="option-swatches">
                                        <?php foreach ($colours as $colour): ?>
                                            <label class="option-swatch" title="<?= e($colour) ?>">
                                                <input type="radio" name="colour_choice" value="<?= e($colour) ?>">
                                                <span><?= e($colour) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>

                            <input type="hidden" name="variant_id" id="variant_id" value="">
                            <p class="option-error" id="option-error" hidden></p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($rules as $rule): ?>
                        <?php if ($rule['input_type'] === 'file') continue; ?>
                        <div class="form-group">
                            <label class="form-label" for="pers_<?= e($rule['field_key']) ?>">
                                <?= e($rule['label']) ?><?= $rule['required'] ? ' *' : '' ?>
                                <?php if ((float)$rule['price_add'] > 0): ?>
                                    <span class="price-add">+<?= e(money($rule['price_add'])) ?></span>
                                <?php endif; ?>
                            </label>
                            <?php if ($rule['input_type'] === 'textarea'): ?>
                                <textarea id="pers_<?= e($rule['field_key']) ?>" name="personalisation[<?= e($rule['field_key']) ?>]"
                                          class="form-textarea" <?= $rule['required'] ? 'required' : '' ?>
                                          <?= $rule['max_length'] ? 'maxlength="' . (int)$rule['max_length'] . '"' : '' ?>
                                          placeholder="<?= e($rule['placeholder'] ?? '') ?>"><?= e($_POST['personalisation'][$rule['field_key']] ?? '') ?></textarea>
                            <?php elseif ($rule['input_type'] === 'select'): ?>
                                <select id="pers_<?= e($rule['field_key']) ?>" name="personalisation[<?= e($rule['field_key']) ?>]"
                                        class="form-select" <?= $rule['required'] ? 'required' : '' ?>>
                                    <option value="">Choose…</option>
                                    <?php foreach (json_decode($rule['options_json'] ?? '[]', true) ?: [] as $option): ?>
                                        <option value="<?= e((string)$option) ?>"><?= e((string)$option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($rule['input_type'] === 'checkbox'): ?>
                                <label class="admin-checkbox">
                                    <input type="checkbox" name="personalisation[<?= e($rule['field_key']) ?>]" value="yes">
                                    <?= e($rule['help_text'] ?: $rule['label']) ?>
                                </label>
                            <?php elseif ($rule['input_type'] === 'date'): ?>
                                <input type="date" id="pers_<?= e($rule['field_key']) ?>" name="personalisation[<?= e($rule['field_key']) ?>]"
                                       class="form-input" <?= $rule['required'] ? 'required' : '' ?>>
                            <?php else: ?>
                                <input type="text" id="pers_<?= e($rule['field_key']) ?>" name="personalisation[<?= e($rule['field_key']) ?>]"
                                       class="form-input" <?= $rule['required'] ? 'required' : '' ?>
                                       <?= $rule['max_length'] ? 'maxlength="' . (int)$rule['max_length'] . '"' : '' ?>
                                       placeholder="<?= e($rule['placeholder'] ?? '') ?>"
                                       value="<?= e($_POST['personalisation'][$rule['field_key']] ?? '') ?>">
                            <?php endif; ?>
                            <?php if ($rule['help_text']): ?>
                                <p class="form-hint"><?= e($rule['help_text']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($product['allow_artwork_upload']): ?>
                        <div class="form-group">
                            <label class="form-label" for="artwork">Your logo / artwork <span>(optional, up to 10MB)</span></label>
                            <input type="file" id="artwork" name="artwork[]" class="form-input form-file"
                                   multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
                            <p class="form-hint"><?= e($product['artwork_help_text'] ?: 'JPG, PNG, WebP, GIF or PDF. Vector files are ideal — we will digitise whatever you send.') ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="product-buy-row">
                        <div class="qty-field">
                            <label for="quantity" class="visually-hidden">Quantity</label>
                            <button type="button" class="qty-btn" data-qty="-1" aria-label="Decrease quantity">−</button>
                            <input type="number" id="quantity" name="quantity" class="qty-input"
                                   value="<?= (int)$product['min_order_qty'] ?>"
                                   min="<?= (int)$product['min_order_qty'] ?>"
                                   <?= $product['max_order_qty'] !== null ? 'max="' . (int)$product['max_order_qty'] . '"' : '' ?>>
                            <button type="button" class="qty-btn" data-qty="1" aria-label="Increase quantity">+</button>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg product-add-btn">Add to basket</button>
                    </div>

                    <ul class="product-trust">
                        <li>Lead time: <strong><?= (int)$product['lead_time_days'] ?> days</strong> from approval</li>
                        <li>Proof sent before we stitch</li>
                        <li>UK delivery or studio pickup in Fife</li>
                    </ul>
                </form>
            </div>
        </div>

        <?php if ($product['description']): ?>
            <div class="product-description">
                <h2>About this item</h2>
                <?= nl2br(e($product['description'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($rating['count'] > 0): ?>
            <div class="product-reviews" id="product-reviews">
                <h2>Customer reviews</h2>
                <?php
                global $pdo;
                $reviewStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 10");
                $reviewStmt->execute([(int)$product['id']]);
                $productReviews = $reviewStmt->fetchAll();
                ?>
                <?php foreach ($productReviews as $review): ?>
                    <article class="review-card">
                        <div class="review-card-head">
                            <span class="star-rating" aria-label="<?= (int)$review['rating'] ?> out of 5"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                            <strong><?= e($review['author_name']) ?></strong>
                            <time datetime="<?= e($review['created_at']) ?>"><?= e(format_date($review['created_at'])) ?></time>
                        </div>
                        <?php if ($review['title']): ?><h3><?= e($review['title']) ?></h3><?php endif; ?>
                        <p><?= nl2br(e($review['content'])) ?></p>
                        <?php if ($review['admin_reply']): ?>
                            <div class="review-reply"><strong>SmartMade replied:</strong> <?= nl2br(e($review['admin_reply'])) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <a href="<?= SITE_URL ?>/reviews.php" class="btn btn-secondary">Write a review</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
