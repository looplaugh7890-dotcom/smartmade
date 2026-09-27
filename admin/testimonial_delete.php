<?php

require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !verify_csrf($_GET['csrf'] ?? '')) {
    set_flash('error', 'Invalid request.');
    header('Location: ' . SITE_URL . '/admin/testimonials.php');
    exit;
}

$stmt = $pdo->prepare("SELECT image FROM testimonials WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch();

if ($item) {
    if (!empty($item['image'])) {
        delete_upload($item['image']);
    }
    $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
    set_flash('success', 'Testimonial deleted.');
} else {
    set_flash('error', 'Testimonial not found.');
}

header('Location: ' . SITE_URL . '/admin/testimonials.php');
exit;
