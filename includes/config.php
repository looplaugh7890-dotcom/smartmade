<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

date_default_timezone_set('Europe/London');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

define('DB_HOST', 'localhost');
define('DB_NAME', 'smartmade_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL', 'http://localhost/smartmade');
define('SITE_PATH', dirname(__DIR__));

define('UPLOAD_PATH', SITE_PATH . '/uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');

define('ADMIN_EMAIL', 'hello@smartmade.example');
define('EMAIL_FROM_NAME', 'SmartMade Embroidery');
define('EMAIL_FROM_ADDRESS', 'hello@smartmade.example');

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

define('ALLOWED_ARTWORK_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf']);
define('MAX_ARTWORK_SIZE', 10 * 1024 * 1024);

// Optional local overrides (production DB credentials / keys) — never committed.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
