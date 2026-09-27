<?php

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'Invalid portfolio item.');
    header('Location: ' . SITE_URL . '/admin/portfolio.php');
    exit;
}

if (!verify_csrf($_GET['csrf'] ?? '')) {
    set_flash('error', 'Security token invalid.');
    header('Location: ' . SITE_URL . '/admin/portfolio.php');
    exit;
}

$stmt = $pdo->prepare("SELECT image FROM portfolio_items WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch();

if ($item) {
    if (!empty($item['image'])) {
        delete_upload($item['image']);
    }
    $delete = $pdo->prepare("DELETE FROM portfolio_items WHERE id = ?");
    $delete->execute([$id]);
    set_flash('success', 'Portfolio item deleted.');
} else {
    set_flash('error', 'Portfolio item not found.');
}

header('Location: ' . SITE_URL . '/admin/portfolio.php');
exit;
