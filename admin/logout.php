<?php

require_once __DIR__ . '/../includes/functions.php';

activity_log('admin.logout', 'admin', isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: ' . SITE_URL . '/admin/login.php');
exit;
