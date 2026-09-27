<?php

ob_start();

require_once __DIR__ . '/functions.php';
require_admin();

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$currentScript = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'Admin';

$adminOnlyScripts = ['settings.php', 'seo.php', 'activity.php', 'reports.php'];
if (in_array($currentScript, $adminOnlyScripts, true) && (($_SESSION['admin_role'] ?? 'editor') !== 'admin')) {
    set_flash('error', 'You need an administrator account to open that page.');
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | SmartMade Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-header-inner">
            <a href="<?= SITE_URL ?>/admin/dashboard.php" class="admin-logo">
                <span class="logo-mark">SM</span>
                <span>SmartMade Admin</span>
            </a>
            <button class="admin-nav-toggle" id="admin-nav-toggle" aria-expanded="false" aria-controls="admin-nav" aria-label="Toggle admin menu">
                <span></span><span></span><span></span>
            </button>
            <div class="admin-user">
                <span class="admin-user-name">Hi, <?= e($adminName) ?></span>
                <a href="<?= SITE_URL ?>/admin/profile.php" class="admin-user-link">Profile</a>
                <a href="<?= SITE_URL ?>/admin/logout.php" class="admin-user-link">Log Out</a>
            </div>
        </div>
    </header>

    <div class="admin-layout">
        <div class="admin-nav-backdrop" id="admin-nav-backdrop" aria-hidden="true"></div>
        <nav class="admin-nav" id="admin-nav" aria-label="Admin navigation">
            <ul>
                <li class="<?= $currentScript === 'dashboard.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/dashboard.php">Dashboard</a>
                </li>

                <li class="admin-nav-heading">Store</li>
                <li class="<?= in_array($currentScript, ['products.php', 'product_add.php', 'product_edit.php', 'product_images.php', 'product_variants.php', 'product_pers.php', 'product_categories.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/products.php">Products</a>
                </li>
                <li class="<?= in_array($currentScript, ['orders.php', 'order_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/orders.php">Orders</a>
                </li>
                <li class="<?= in_array($currentScript, ['customers.php', 'customer_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/customers.php">Customers</a>
                </li>
                <li class="<?= in_array($currentScript, ['coupons.php', 'shipping.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/coupons.php">Coupons &amp; Shipping</a>
                </li>

                <li class="admin-nav-heading">Content</li>
                <li class="<?= in_array($currentScript, ['portfolio.php', 'portfolio_add.php', 'portfolio_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/portfolio.php">Portfolio</a>
                </li>
                <li class="<?= in_array($currentScript, ['gallery.php', 'gallery_add.php', 'gallery_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/gallery.php">Gallery</a>
                </li>
                <li class="<?= in_array($currentScript, ['reviews.php', 'review_view.php', 'testimonials.php', 'testimonial_add.php', 'testimonial_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/reviews.php">Reviews &amp; Testimonials</a>
                </li>
                <li class="<?= in_array($currentScript, ['blog.php', 'blog_add.php', 'blog_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/blog.php">Journal</a>
                </li>

                <li class="admin-nav-heading">Enquiries</li>
                <li class="<?= in_array($currentScript, ['quotes.php', 'quote_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/quotes.php">Quote Requests</a>
                </li>
                <li class="<?= in_array($currentScript, ['messages.php', 'message_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/messages.php">Messages</a>
                </li>

                <li class="admin-nav-heading">Operations</li>
                <li class="<?= $currentScript === 'reports.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/reports.php">Reports</a>
                </li>
                <li class="<?= $currentScript === 'subscribers.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/subscribers.php">Subscribers</a>
                </li>
                <li class="<?= $currentScript === 'seo.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/seo.php">SEO &amp; Meta</a>
                </li>
                <li class="<?= $currentScript === 'activity.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/activity.php">Activity Log</a>
                </li>
                <li class="<?= $currentScript === 'settings.php' ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/settings.php">Settings</a>
                </li>

                <li class="admin-nav-spacer"></li>
                <li><a href="<?= SITE_URL ?>/" target="_blank">View Site ↗</a></li>
            </ul>
        </nav>

        <main class="admin-main" id="admin-main">
            <?php if (has_flash('success')): ?>
                <div class="alert alert-success"><?= e(get_flash('success')) ?></div>
            <?php endif; ?>
            <?php if (has_flash('error')): ?>
                <div class="alert alert-error"><?= e(get_flash('error')) ?></div>
            <?php endif; ?>
            <?php if (has_flash('info')): ?>
                <div class="alert alert-info"><?= e(get_flash('info')) ?></div>
            <?php endif; ?>

            <div class="admin-content">
