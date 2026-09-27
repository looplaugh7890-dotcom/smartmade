<?php

require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/seo.php';

if (!isset($pageTitle)) {
    $pageTitle = setting('site_title', 'SmartMade Embroidery');
} else {
    $pageTitle .= ' | ' . setting('site_title', 'SmartMade Embroidery');
}

if (!isset($pageDescription)) {
    $pageDescription = setting('meta_description', 'SmartMade — original custom embroidery from Scotland.');
}

if (!isset($bodyClass)) {
    $bodyClass = '';
}

if (!isset($ogImage)) {
    $ogImage = SITE_URL . '/assets/images/og-default.svg';
}

if (!isset($canonicalPath)) {
    $canonicalPath = str_replace('/index.php', '/', '/' . basename($_SERVER['PHP_SELF']));
}
$canonicalUrl = canonical_url($canonicalPath);

if (!isset($robots)) {
    $robots = 'index, follow';
}

if (!isset($jsonLd)) {
    $jsonLd = [org_jsonld()];
}

$currentScript = basename($_SERVER['PHP_SELF']);
$cartCount = cart_count();
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <noscript><style>.reveal { opacity: 1 !important; transform: none !important; }</style></noscript>

    <link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">
    <?php foreach ($jsonLd as $schema): ?>
        <?= is_array($schema) ? jsonld($schema) : $schema ?>
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header class="site-header" id="site-header">
        <div class="container header-inner">
            <a href="<?= SITE_URL ?>/" class="logo" aria-label="SmartMade home">
                <span class="logo-mark" aria-hidden="true">SM</span>
                <span class="logo-text">SmartMade</span>
            </a>

            <nav class="primary-nav" id="primary-nav" aria-label="Primary navigation">
                <ul class="nav-list">
                    <li class="nav-item<?= $currentScript === 'index.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Home</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'shop.php' || in_array($currentScript, ['product.php', 'cart.php', 'checkout.php'], true) ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/shop.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Shop</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'portfolio.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/portfolio.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Portfolio</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'gallery.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/gallery.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Gallery</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'services.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/services.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Services</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'about.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/about.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>About</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'contact.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/contact.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Contact</a>
                    </li>
                    <li class="nav-item nav-item-cta">
                        <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary nav-cta-mobile">Get a Quote</a>
                    </li>
                </ul>
            </nav>

            <div class="header-actions">
                <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary btn-sm header-cta">Get a Quote</a>

                <a href="<?= SITE_URL ?>/cart.php" class="mini-cart" id="mini-cart" aria-label="Basket, <?= (int)$cartCount ?> items">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    <span class="mini-cart-count<?= $cartCount === 0 ? ' is-empty' : '' ?>" id="mini-cart-count" aria-hidden="true"><?= (int)$cartCount ?></span>
                </a>
            </div>

            <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="Open navigation menu">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>
        </div>
    </header>

    <div class="nav-backdrop" id="nav-backdrop" aria-hidden="true"></div>

    <main id="main-content" class="site-main">