<?php

require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/payments.php';

function checkout_generate_order_number(): string {
    global $pdo;

    $year = date('Y');
    for ($i = 0; $i < 10; $i++) {
        $candidate = 'SM-' . $year . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
    }
    return 'SM-' . $year . '-' . time();
}

function checkout_validate(array $input): array {
    $errors = [];

    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $line1 = trim($input['line1'] ?? '');
    $city = trim($input['city'] ?? '');
    $postcode = trim($input['postcode'] ?? '');
    $shipping = $input['shipping_method'] ?? '';
    $payment = $input['payment_method'] ?? '';

    if (mb_strlen($name) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($shipping === 'local_pickup') {
        // Collection — address not required
    } else {
        if ($line1 === '') {
            $errors[] = 'Please enter your delivery address.';
        }
        if ($city === '') {
            $errors[] = 'Please enter your town or city.';
        }
        if ($postcode === '') {
            $errors[] = 'Please enter your postcode.';
        }
    }

    $methods = payment_methods_available();
    if (!isset($methods[$payment])) {
        $errors[] = 'Please choose a payment method.';
    }

    $shippingCodes = array_column(shipping_methods_all(), 'code');
    if (!in_array($shipping, $shippingCodes, true)) {
        $errors[] = 'Please choose a delivery option.';
    }

    return $errors;
}

/**
 * Creates the order inside a single transaction. Stock is reserved (decremented)
 * at placement; card payments confirm afterwards via callback/webhook.
 *
 * @return array{ok:bool,message:string,order?:array}
 */
function checkout_create_order(array $input): array {
    global $pdo;

    $items = cart_items();
    if (empty($items)) {
        return ['ok' => false, 'message' => 'Your basket is empty.'];
    }

    foreach ($items as $item) {
        if ($item['out_of_stock']) {
            return ['ok' => false, 'message' => '“' . $item['name'] . '” is no longer available in that quantity. Please update your basket.'];
        }
    }

    $errors = checkout_validate($input);
    if ($errors) {
        return ['ok' => false, 'message' => implode(' ', $errors)];
    }

    $shippingCode = $input['shipping_method'];
    $totals = cart_totals($shippingCode);
    $paymentMethod = $input['payment_method'];

    $address = [
        'full_name' => trim($input['name'] ?? ''),
        'line1' => trim($input['line1'] ?? ''),
        'line2' => trim($input['line2'] ?? ''),
        'city' => trim($input['city'] ?? ''),
        'county' => trim($input['county'] ?? ''),
        'postcode' => trim($input['postcode'] ?? ''),
        'country' => trim($input['country'] ?? 'United Kingdom') ?: 'United Kingdom',
        'phone' => trim($input['phone'] ?? ''),
    ];

    $shippingRow = $totals['shippingMethod'];
    $initialStatus = $paymentMethod === 'stripe' ? 'awaiting_payment' : 'awaiting_payment';

    $pdo->beginTransaction();
    try {
        $orderNumber = checkout_generate_order_number();
        $customerId = customer_id();

        $stmt = $pdo->prepare("
            INSERT INTO orders
            (order_number, customer_id, email, name, phone, status, payment_status, payment_method,
             currency, subtotal, discount_total, shipping_total, tax_total, grand_total,
             coupon_code, shipping_method, shipping_address_json, billing_address_json,
             customer_notes, ip_address, placed_at)
            VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $orderNumber,
            $customerId,
            trim($input['email']),
            trim($input['name']),
            trim($input['phone'] ?? '') ?: null,
            $initialStatus,
            $paymentMethod,
            setting('currency', 'GBP'),
            $totals['subtotal'],
            $totals['discount'],
            $totals['shipping'],
            $totals['tax'],
            $totals['total'],
            $totals['coupon']['code'] ?? null,
            $shippingRow['name'] ?? $shippingCode,
            json_encode($address, JSON_UNESCAPED_UNICODE),
            json_encode($address, JSON_UNESCAPED_UNICODE),
            trim($input['notes'] ?? '') ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $orderId = (int)$pdo->lastInsertId();
        $artworkInsert = $pdo->prepare("
            INSERT INTO order_artwork (order_item_id, file_path, original_name, file_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $variantLabel = trim(($item['size'] ?? '') . ' ' . ($item['colour_name'] ?? ''));

            $stmt = $pdo->prepare("
                INSERT INTO order_items
                (order_id, product_id, variant_id, product_name, variant_label, sku,
                 quantity, unit_price, line_total, tax_amount, personalisation_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['variant_id'] ?: null,
                $item['name'],
                $variantLabel ?: null,
                $item['sku'] ?? null,
                (int)$item['quantity'],
                $item['unit_price'],
                $item['line_total'],
                0,
                !empty($item['personalisation']) ? json_encode($item['personalisation'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $orderItemId = (int)$pdo->lastInsertId();

            foreach ($item['artwork'] as $path) {
                $fullPath = SITE_PATH . '/' . ltrim($path, '/');
                $artworkInsert->execute([
                    $orderItemId,
                    $path,
                    basename($path),
                    is_file($fullPath) && function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: null) : null,
                    is_file($fullPath) ? filesize($fullPath) : null,
                ]);
            }

            // Reserve stock at placement
            if ($item['variant_id']) {
                $pdo->prepare("UPDATE product_variants SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ? AND manage_stock = 1")
                    ->execute([(int)$item['quantity'], $item['variant_id']]);
            } elseif ($item['manage_stock']) {
                $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ? AND manage_stock = 1")
                    ->execute([(int)$item['quantity'], $item['product_id']]);
            }
            $pdo->prepare("UPDATE products SET sales_count = sales_count + ? WHERE id = ?")
                ->execute([(int)$item['quantity'], $item['product_id']]);
        }

        if ($totals['coupon']) {
            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$totals['coupon']['id']]);
        }

        $pdo->prepare("
            INSERT INTO order_events (order_id, event_type, from_status, to_status, actor, note)
            VALUES (?, 'order_placed', NULL, ?, 'customer', ?)
        ")->execute([$orderId, $initialStatus, 'Checkout via ' . $paymentMethod]);

        $pdo->prepare("UPDATE carts SET status = 'converted' WHERE id = ?")->execute([cart_active_id(false)]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('checkout_create_order failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'We could not complete your order. Please try again.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    $orderItems = order_items_for($orderId);

    require_once __DIR__ . '/mail.php';
    send_order_confirmation($order, $orderItems);
    send_admin_new_order($order, $orderItems);

    // Reset basket session for the next shop
    unset($_SESSION['cart_id']);

    return ['ok' => true, 'message' => 'Order placed.', 'order' => $order];
}

function checkout_stripe_redirect(array $order): string {
    $params = [
        'mode' => 'payment',
        'client_reference_id' => $order['order_number'],
        'customer_email' => $order['email'],
        'success_url' => SITE_URL . '/payment_callback.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => SITE_URL . '/checkout.php?cancelled=1',
        'metadata[order_id]' => (string)$order['id'],
        'metadata[order_number]' => $order['order_number'],
    ];

    $i = 0;
    foreach (order_items_for($order['id']) as $item) {
        $label = $item['product_name'] . ($item['variant_label'] ? ' — ' . $item['variant_label'] : '');
        $params['line_items[' . $i . '][quantity]'] = (int)$item['quantity'];
        $params['line_items[' . $i . '][price_data][currency]'] = strtolower($order['currency'] ?? 'gbp');
        $params['line_items[' . $i . '][price_data][unit_amount]'] = (int)round(((float)$item['unit_price']) * 100);
        $params['line_items[' . $i . '][price_data][product_data][name]'] = $label;
        $i++;
    }
    if ((float)$order['shipping_total'] > 0) {
        $params['line_items[' . $i . '][quantity]'] = 1;
        $params['line_items[' . $i . '][price_data][currency]'] = strtolower($order['currency'] ?? 'gbp');
        $params['line_items[' . $i . '][price_data][unit_amount]'] = (int)round(((float)$order['shipping_total']) * 100);
        $params['line_items[' . $i . '][price_data][product_data][name]'] = $order['shipping_method'] ?: 'Delivery';
        $i++;
    }
    if ((float)$order['discount_total'] > 0) {
        $params['line_items[' . $i . '][quantity]'] = 1;
        $params['line_items[' . $i . '][price_data][currency]'] = strtolower($order['currency'] ?? 'gbp');
        $params['line_items[' . $i . '][price_data][unit_amount]'] = -((int)round(((float)$order['discount_total']) * 100));
        $params['line_items[' . $i . '][price_data][product_data][name]'] = 'Discount (' . $order['coupon_code'] . ')';
        $i++;
    }

    $session = stripe_request('checkout/sessions', $params);
    return $session['url'];
}
