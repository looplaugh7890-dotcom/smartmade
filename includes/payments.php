<?php

require_once __DIR__ . '/functions.php';

/**
 * Payment handling.
 * - manual / bank_transfer: order is placed and waits for the studio to confirm.
 * - stripe: raw REST calls (no SDK), Payment Intents + signed webhook.
 */

function payment_methods_available(): array {
    $methods = [];

    if (setting('enable_bank_transfer', '1') === '1') {
        $methods['bank_transfer'] = [
            'label' => 'Bank transfer / invoice',
            'description' => 'We will email you bank details and confirm your order.',
        ];
    }
    if (setting('enable_stripe', '0') === '1' && setting('stripe_secret_key') !== '') {
        $methods['stripe'] = [
            'label' => 'Pay by card',
            'description' => 'Visa, Mastercard, Amex — powered by Stripe.',
        ];
    }
    if (setting('enable_paypal', '0') === '1') {
        $methods['paypal'] = [
            'label' => 'PayPal',
            'description' => 'Pay securely with your PayPal account.',
        ];
    }

    return $methods;
}

function stripe_request(string $path, array $params = [], string $method = 'POST'): array {
    $secret = setting('stripe_secret_key');
    if ($secret === '') {
        throw new RuntimeException('Stripe is not configured.');
    }

    $ch = curl_init('https://api.stripe.com/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => http_build_query($params),
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Stripe request failed: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($status >= 400) {
        throw new RuntimeException('Stripe error: ' . ($data['error']['message'] ?? 'unknown'));
    }
    return $data;
}

function stripe_create_payment_intent(array $order): array {
    $items = [];
    foreach (order_items_for($order['id']) as $item) {
        $items[] = $item['product_name'] . ($item['variant_label'] ? ' — ' . $item['variant_label'] : '');
    }

    return stripe_request('payment_intents', [
        'amount' => (int)round(((float)$order['grand_total']) * 100),
        'currency' => strtolower($order['currency'] ?? 'GBP'),
        'description' => 'SmartMade order ' . $order['order_number'],
        'receipt_email' => $order['email'],
        'metadata[order_number]' => $order['order_number'],
        'metadata[order_id]' => (string)$order['id'],
        'automatic_payment_methods[enabled]' => 'true',
    ]);
}

function stripe_verify_webhook(string $payload, string $signatureHeader, string $secret): array {
    if ($signatureHeader === '' || $secret === '') {
        throw new RuntimeException('Missing Stripe signature or webhook secret.');
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) {
            continue;
        }
        if ($pair[0] === 't') {
            $timestamp = $pair[1];
        } elseif ($pair[0] === 'v1') {
            $signatures[] = $pair[1];
        }
    }

    if (!$timestamp || empty($signatures)) {
        throw new RuntimeException('Malformed Stripe signature header.');
    }
    if (abs(time() - (int)$timestamp) > 300) {
        throw new RuntimeException('Stripe signature timestamp outside tolerance.');
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return json_decode($payload, true) ?: [];
        }
    }
    throw new RuntimeException('Stripe signature verification failed.');
}

function order_items_for(int $orderId): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT oi.*, v.size, v.colour_name
        FROM order_items oi
        LEFT JOIN product_variants v ON oi.variant_id = v.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['variant_label'] = trim(($item['size'] ?? '') . ' ' . ($item['colour_name'] ?? ''));
        $item['personalisation'] = $item['personalisation_json'] ? (json_decode($item['personalisation_json'], true) ?: []) : [];
        $item['artwork'] = order_artwork_for((int)$item['id']);
    }
    unset($item);
    return $items;
}

function order_artwork_for(int $orderItemId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM order_artwork WHERE order_item_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$orderItemId]);
    return $stmt->fetchAll();
}

function record_payment(int $orderId, string $provider, ?string $providerId, float $amount, string $currency, string $status, array $payload = [], ?string $error = null): void {
    global $pdo;

    if ($providerId) {
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE provider = ? AND provider_payment_id = ? LIMIT 1");
        $stmt->execute([$provider, $providerId]);
        if ($stmt->fetch()) {
            return; // idempotent
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO payments (order_id, provider, provider_payment_id, amount, currency, status, payload_json, failure_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $provider,
        $providerId,
        $amount,
        $currency,
        $status,
        $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        $error,
    ]);
}

function mark_order_paid(int $orderId, string $provider, ?string $providerId, array $payload = []): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return false;
    }

    if ($order['payment_status'] === 'paid') {
        record_payment($orderId, $provider, $providerId, (float)$order['grand_total'], $order['currency'], 'succeeded', $payload);
        return true; // already processed — idempotent
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE orders SET payment_status = 'paid', status = 'paid', updated_at = NOW() WHERE id = ?
        ")->execute([$orderId]);

        // Stock is reserved at order placement — here we only record the event.
        $pdo->prepare("INSERT INTO order_events (order_id, event_type, from_status, to_status, actor, note) VALUES (?, 'payment_received', 'awaiting_payment', 'paid', 'gateway', ?)")
            ->execute([$orderId, $provider . ($providerId ? ' ' . $providerId : '')]);

        $pdo->prepare("
            UPDATE products p
            JOIN order_items oi ON oi.product_id = p.id
            SET p.sales_count = p.sales_count + oi.quantity
            WHERE oi.order_id = ?
        ")->execute([$orderId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('mark_order_paid failed: ' . $e->getMessage());
        return false;
    }

    record_payment($orderId, $provider, $providerId, (float)$order['grand_total'], $order['currency'], 'succeeded', $payload);

    require_once __DIR__ . '/mail.php';
    send_order_confirmation($order, order_items_for($orderId));

    return true;
}

function fail_order_payment(int $orderId, string $provider, ?string $providerId, string $reason, array $payload = []): void {
    global $pdo;

    $stmt = $pdo->prepare("SELECT grand_total, currency FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    record_payment($orderId, $provider, $providerId, (float)$row['grand_total'], $row['currency'], 'failed', $payload, $reason);
    $pdo->prepare("UPDATE orders SET payment_status = 'failed', updated_at = NOW() WHERE id = ? AND payment_status <> 'paid'")
        ->execute([$orderId]);
}
