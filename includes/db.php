<?php

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Keep MySQL's NOW() on exactly the same clock as PHP's date(), otherwise
    // relative-time queries (CSRF expiry, cart expiry, order windows) drift.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Unable to connect to the database. Please try again later.');
}
