<?php

require_once __DIR__ . '/includes/functions.php';

$referer = $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $referer);
    exit;
}

if (!verify_csrf()) {
    set_flash('error', 'Security token expired. Please try again.');
    header('Location: ' . $referer);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please enter a valid email address.');
    header('Location: ' . $referer);
    exit;
}

$check = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1");
$check->execute([$email]);
$existing = $check->fetch();

if ($existing) {
    if ($existing['status'] !== 'active') {
        $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active', subscribed_at = NOW() WHERE id = ?")
            ->execute([$existing['id']]);
        set_flash('success', "You're back on the list — nice one!");
    } else {
        set_flash('info', "You're already subscribed. We'll be in touch.");
    }
} else {
    $ins = $pdo->prepare("INSERT INTO newsletter_subscribers (email, status, token) VALUES (?, 'active', ?)");
    $ins->execute([$email, bin2hex(random_bytes(16))]);
    set_flash('success', "You're on the list — watch your inbox for new drops.");
}

header('Location: ' . $referer);
exit;
