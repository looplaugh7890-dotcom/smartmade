<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payments.php';

$sessionId = (string)($_GET['session_id'] ?? '');

if ($sessionId === '') {
    header('Location: ' . SITE_URL . '/shop.php');
    exit;
}

try {
    $session = stripe_request('checkout/sessions/' . $sessionId, [], 'GET');
    $orderId = (int)($session['metadata']['order_id'] ?? 0);

    if (!$orderId) {
        throw new RuntimeException('No order reference on the Stripe session.');
    }

    if (($session['payment_status'] ?? '') === 'paid') {
        $intent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : ($session['payment_intent']['id'] ?? null);
        mark_order_paid($orderId, 'stripe', $intent, ['session_id' => $sessionId]);
        $ref = $session['metadata']['order_number'] ?? null;

        if (!$ref) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $ref = $stmt->fetchColumn();
        }

        unset($_SESSION['last_order_number']);
        header('Location: ' . SITE_URL . '/order-confirmation.php?ref=' . urlencode($ref) . '&placed=1');
        exit;
    }

    // Payment not completed yet — send them back to checkout.
    global $pdo;
    $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $ref = $stmt->fetchColumn();
    header('Location: ' . SITE_URL . '/checkout.php?cancelled=1');
    exit;
} catch (Throwable $e) {
    error_log('payment_callback failed: ' . $e->getMessage());
    header('Location: ' . SITE_URL . '/checkout.php?cancelled=1');
    exit;
}
