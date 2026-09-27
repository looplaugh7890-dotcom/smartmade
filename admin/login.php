<?php

require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Please enter both email and password.';
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $attempts = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) AS ip_fails,
                    SUM(CASE WHEN email = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) AS email_fails
                FROM admin_login_attempts
            ");
            $attempts->execute([$ip, $email]);
            $attempts = $attempts->fetch();

            if ((int)$attempts['ip_fails'] >= 10 || (int)$attempts['email_fails'] >= 5) {
                $errors[] = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
                $record = $pdo->prepare("INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
                $record->execute([$email, $ip]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM admin_users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $record = $pdo->prepare("INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
                    $record->execute([$email, $ip]);

                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['admin_logged_in'] = true;

                    $update = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);

                    activity_log('admin.login', 'admin', (int)$user['id'], $user['email']);

                    $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/admin/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $record = $pdo->prepare("INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
                    $record->execute([$email, $ip]);
                    $errors[] = 'Invalid email or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SmartMade</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-login-page">
    <div class="admin-login-card">
        <div class="admin-login-logo">
            <span class="logo-mark">SM</span>
            <span>SmartMade</span>
        </div>
        <p class="admin-login-subtitle">Admin panel sign-in</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p style="margin: 0 0 6px;"><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="<?= SITE_URL ?>/admin/login.php" method="post" class="admin-login-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Sign In</button>
        </form>

        <p style="font-size: 0.8rem; color: var(--admin-muted); margin-top: 20px; text-align: center;">
            Default: admin@smartmade.example / password<br>
            Change this immediately after first login.
        </p>
    </div>
</body>
</html>
