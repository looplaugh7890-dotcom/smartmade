<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals(csrf_token(), (string)$csrf)) {
    set_flash('error', 'Security token invalid.');
    header('Location: ' . SITE_URL . '/admin/products.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, slug FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . SITE_URL . '/admin/products.php');
    exit;
}

$images = $pdo->prepare("SELECT path FROM product_images WHERE product_id = ?");
$images->execute([$id]);
foreach ($images->fetchAll() as $img) {
    delete_upload($img['path']);
}

$pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

activity_log('product.delete', 'product', $id, $product['name'] . ' (' . $product['slug'] . ')');
set_flash('success', 'Product deleted.');
header('Location: ' . SITE_URL . '/admin/products.php');
exit;
