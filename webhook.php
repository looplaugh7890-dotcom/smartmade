<?php

/**
 * Stripe webhook endpoint.
 * Configure in the Stripe dashboard: https://YOUR-DOMAIN/webhook.php
 * Events handled: checkout.session.completed, checkout.session.async_payment_failed
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payments.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = setting('stripe_webhook_secret');

try {
    $event = stripe_verify_webhook((string)$payload, (string)$signature, $secret);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$type = $event['type'] ?? '';
$dataObject = $event['data']['object'] ?? [];

if ($type === 'checkout.session.completed') {
    $orderId = (int)($dataObject['metadata']['order_id'] ?? 0);
    if ($orderId && ($dataObject['payment_status'] ?? '') === 'paid') {
        $intent = is_string($dataObject['payment_intent'] ?? null)
            ? $dataObject['payment_intent']
            : ($dataObject['payment_intent']['id'] ?? null);
        mark_order_paid($orderId, 'stripe', $intent, ['event' => $type]);
    }
    echo json_encode(['received' => true]);
    exit;
}

if ($type === 'checkout.session.async_payment_failed') {
    $orderId = (int)($dataObject['metadata']['order_id'] ?? 0);
    if ($orderId) {
        fail_order_payment($orderId, 'stripe', null, 'Async payment failed', ['event' => $type]);
    }
    echo json_encode(['received' => true]);
    exit;
}

echo json_encode(['received' => true, 'ignored' => $type]);
