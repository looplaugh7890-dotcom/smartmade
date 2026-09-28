<?php

require_once __DIR__ . '/functions.php';

function current_customer(): ?array {
    global $pdo;

    if (!is_customer_logged_in()) {
        return null;
    }

    static $customer = null;
    if ($customer !== null) {
        return $customer;
    }

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([customer_id()]);
    $customer = $stmt->fetch() ?: null;

    if (!$customer) {
        customer_logout();
    }
    return $customer;
}

function require_customer(): void {
    if (!is_customer_logged_in()) {
        $_SESSION['redirect_after_customer_login'] = $_SERVER['REQUEST_URI'] ?? (SITE_URL . '/account.php');
        set_flash('info', 'Please sign in to continue.');
        header('Location: ' . SITE_URL . '/account.php');
        exit;
    }
}

function customer_register(array $input): array {
    global $pdo;

    $name = trim($input['name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    if (mb_strlen($name) < 2) {
        return ['ok' => false, 'message' => 'Please enter your full name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Your password must be at least 8 characters.'];
    }

    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => 'An account already exists for that email address. Try signing in.'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO customers (name, email, password_hash, phone, marketing_optin)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        trim($input['phone'] ?? '') ?: null,
        !empty($input['marketing_optin']) ? 1 : 0,
    ]);

    $customerId = (int)$pdo->lastInsertId();
    customer_start_session($customerId, $name, $email);
    merge_guest_cart($customerId);

    return ['ok' => true, 'message' => 'Welcome! Your account has been created.'];
}

function customer_login(array $input): array {
    global $pdo;

    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter your email and password.'];
    }

    $stmt = $pdo->prepare("SELECT id, name, email, status, password_hash FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if (!$customer || !password_verify($password, $customer['password_hash'])) {
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }
    if ($customer['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This account has been disabled. Please contact us.'];
    }

    $pdo->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?")->execute([$customer['id']]);
    customer_start_session((int)$customer['id'], $customer['name'], $customer['email']);
    merge_guest_cart((int)$customer['id']);

    return ['ok' => true, 'message' => 'Signed in successfully.'];
}

function customer_start_session(int $id, string $name, string $email): void {
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
    $_SESSION['customer_name'] = $name;
    $_SESSION['customer_email'] = $email;
    $_SESSION['customer_logged_in'] = true;
}

function customer_logout(): void {
    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['customer_logged_in']);
    session_regenerate_id(true);
}

function merge_guest_cart(int $customerId): void {
    global $pdo;

    $sid = session_id();
    if ($sid === '') {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$customerId]);
    $existing = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sid]);
    $guest = $stmt->fetch();

    if ($guest && $existing && (int)$guest['id'] !== (int)$existing['id']) {
        $pdo->prepare("UPDATE cart_items SET cart_id = ? WHERE cart_id = ?")->execute([$existing['id'], $guest['id']]);
        $pdo->prepare("UPDATE carts SET status = 'merged' WHERE id = ?")->execute([$guest['id']]);
    } elseif ($guest) {
        $pdo->prepare("UPDATE carts SET customer_id = ?, session_id = ? WHERE id = ?")
            ->execute([$customerId, $sid, $guest['id']]);
    }
}

function customer_request_reset(string $email): array {
    global $pdo;

    $email = strtolower(trim($email));
    $stmt = $pdo->prepare("SELECT id, name, email FROM customers WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if (!$customer) {
        // Do not reveal whether the account exists.
        return ['ok' => true, 'message' => 'If an account exists for that address, a reset link has been sent.'];
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $pdo->prepare("DELETE FROM customer_tokens WHERE customer_id = ? AND type = 'password_reset' AND used_at IS NULL")
        ->execute([$customer['id']]);
    $pdo->prepare("INSERT INTO customer_tokens (customer_id, token_hash, type, expires_at) VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL 1 HOUR))")
        ->execute([$customer['id'], $hash]);

    $resetUrl = SITE_URL . '/account.php?page=reset&token=' . urlencode($token);
    require_once __DIR__ . '/mail.php';
    send_password_reset_email($customer['email'], $customer['name'], $resetUrl);

    return ['ok' => true, 'message' => 'If an account exists for that address, a reset link has been sent.'];
}

function customer_reset_password(string $token, string $password, string $confirm): array {
    global $pdo;

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Your new password must be at least 8 characters.'];
    }
    if ($password !== $confirm) {
        return ['ok' => false, 'message' => 'The passwords do not match.'];
    }

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT id, customer_id FROM customer_tokens
        WHERE token_hash = ? AND type = 'password_reset' AND used_at IS NULL AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'message' => 'That reset link is invalid or has expired. Please request a new one.'];
    }

    $pdo->prepare("UPDATE customers SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_DEFAULT), $row['customer_id']]);
    $pdo->prepare("UPDATE customer_tokens SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);

    return ['ok' => true, 'message' => 'Your password has been updated. You can sign in now.'];
}

function customer_default_address(int $customerId): ?array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id ASC LIMIT 1");
    $stmt->execute([$customerId]);
    return $stmt->fetch() ?: null;
}
