<?php

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !verify_csrf($_GET['csrf'] ?? '')) {
    set_flash('error', 'Invalid request.');
    header('Location: ' . SITE_URL . '/admin/blog.php');
    exit;
}

$stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();

if ($post) {
    if (!empty($post['featured_image'])) {
        delete_upload($post['featured_image']);
    }
    $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
    set_flash('success', 'Post deleted.');
} else {
    set_flash('error', 'Post not found.');
}

header('Location: ' . SITE_URL . '/admin/blog.php');
exit;
