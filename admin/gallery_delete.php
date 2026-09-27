<?php

require_once __DIR__ . '/../includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!hash_equals(csrf_token(), (string)$csrf)) {
    set_flash('error', 'Security token invalid.');
    header('Location: ' . SITE_URL . '/admin/gallery.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Gallery item not found.');
    header('Location: ' . SITE_URL . '/admin/gallery.php');
    exit;
}

delete_upload($item['image']);
foreach (json_decode($item['additional_images'] ?? '', true) ?: [] as $extra) {
    delete_upload($extra);
}

$pdo->prepare("DELETE FROM gallery_items WHERE id = ?")->execute([$id]);

activity_log('gallery.delete', 'gallery', $id, $item['title']);
set_flash('success', 'Gallery item deleted.');
header('Location: ' . SITE_URL . '/admin/gallery.php');
exit;
