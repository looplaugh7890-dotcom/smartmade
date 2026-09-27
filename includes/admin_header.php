<?php

ob_start();

require_once __DIR__ . '/functions.php';
require_admin();

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$currentScript = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'Admin';
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
                <li class="<?= in_array($currentScript, ['portfolio.php', 'portfolio_add.php', 'portfolio_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/portfolio.php">Portfolio</a>
                </li>
                <li class="<?= in_array($currentScript, ['quotes.php', 'quote_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/quotes.php">Quote Requests</a>
                </li>
                <li class="<?= in_array($currentScript, ['messages.php', 'message_view.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/messages.php">Messages</a>
                </li>
                <li class="<?= in_array($currentScript, ['testimonials.php', 'testimonial_add.php', 'testimonial_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/testimonials.php">Testimonials</a>
                </li>
                <li class="<?= in_array($currentScript, ['blog.php', 'blog_add.php', 'blog_edit.php']) ? 'is-active' : '' ?>">
                    <a href="<?= SITE_URL ?>/admin/blog.php">Journal</a>
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
