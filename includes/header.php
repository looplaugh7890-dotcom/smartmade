<?php

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

$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">

    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">

    <link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">
</head>
<body class="<?= e($bodyClass) ?>">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header class="site-header" id="site-header">
        <div class="container header-inner">
            <a href="<?= SITE_URL ?>/" class="logo" aria-label="SmartMade home">
                <span class="logo-mark" aria-hidden="true">SM</span>
                <span class="logo-text">SmartMade</span>
            </a>

            <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="Open navigation menu">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>

            <nav class="primary-nav" id="primary-nav" aria-label="Primary navigation">
                <ul class="nav-list">
                    <li class="nav-item<?= $currentScript === 'index.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Home</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'portfolio.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/portfolio.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Portfolio</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'services.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/services.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Services</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'about.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/about.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>About</a>
                    </li>
                    <li class="nav-item<?= in_array($currentScript, ['blog.php', 'blog-post.php']) ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/blog.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Journal</a>
                    </li>
                    <li class="nav-item<?= $currentScript === 'contact.php' ? ' is-active' : '' ?>">
                        <a href="<?= SITE_URL ?>/contact.php" class="nav-link"><span class="nav-thread" aria-hidden="true"></span>Contact</a>
                    </li>
                    <li class="nav-item nav-item-cta">
                        <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary">Get a Quote</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="main-content" class="site-main">