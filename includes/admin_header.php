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

$adminHelpMap = [
    'dashboard.php'         => 'Your at-a-glance overview. Anything with a number needs your attention today.',
    'products.php'          => 'Add products, adjust prices and stock, and open a product to manage its photos, sizes and personalisation options.',
    'product_add.php'       => 'Only the name, price and category are required. You can add photos and options after saving.',
    'product_edit.php'      => 'Changes here go live on the site as soon as you save.',
    'product_images.php'    => 'Drag-free photo management: upload, set the main image, or remove photos.',
    'product_variants.php'  => 'Sizes, colours and stock levels. Stock counts update automatically when orders are placed.',
    'product_pers.php'      => 'Personalisation questions customers fill in when they order (name, position, extra notes).',
    'product_categories.php'=> 'Categories are used on the shop page and in the customer filters.',
    'orders.php'            => 'Open an order to update payment and production status — the customer is emailed automatically.',
    'order_view.php'        => 'Update the status when you move the order along. Status changes are emailed to the customer.',
    'customers.php'         => 'Everyone who has ordered or created an account.',
    'customer_view.php'     => 'Order history and contact details for this customer.',
    'coupons.php'           => 'Discount codes and delivery charges shown at checkout.',
    'shipping.php'          => 'Delivery options and prices customers pick at checkout.',
    'quotes.php'            => 'Quote requests from the website. Open one to reply and mark it completed.',
    'quote_view.php'        => 'Reply to the customer, then mark the quote completed so it leaves your todo list.',
    'messages.php'          => 'Messages sent from the contact form on your site.',
    'reviews.php'           => 'Approve, feature or reject customer reviews — only approved reviews appear on the site.',
    'gallery.php'           => 'The work gallery customers browse for inspiration.',
    'portfolio.php'         => 'Portfolio pieces shown on the Portfolio page.',
    'blog.php'              => 'Journal posts (news and articles) shown on your site.',
    'testimonials.php'      => 'Short quotes from happy customers, shown across the site.',
    'reports.php'           => 'Sales, orders and product performance over time.',
    'subscribers.php'       => 'People who signed up for your newsletter.',
    'seo.php'               => 'Page titles and descriptions Google shows in search results.',
    'activity.php'          => 'A record of changes made in the admin panel — useful if something looks wrong.',
    'settings.php'          => 'Store-wide settings. Press Save at the bottom of each tab — changes apply immediately.',
    'profile.php'           => 'Your sign-in details for this admin panel.',
];
$adminHelp = $pageHelp ?? ($adminHelpMap[$currentScript] ?? null);
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
                <div class="admin-topbar" id="admin-topbar">
                    <nav class="admin-breadcrumb" id="admin-breadcrumb" aria-label="Breadcrumb"></nav>
                    <?php if (!empty($adminHelp)): ?>
                        <p class="admin-page-help"><?= e($adminHelp) ?></p>
                    <?php endif; ?>
                </div>
