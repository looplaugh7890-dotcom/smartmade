<?php

require_once __DIR__ . '/functions.php';

/**
 * Cart engine — session-backed basket stored in `carts` / `cart_items`.
 */

function cart_session_id(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return session_id();
}

function cart_active_id(bool $create = true): ?int {
    global $pdo;

    $sid = cart_session_id();
    if ($sid === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sid]);
    $row = $stmt->fetch();

    if ($row) {
        $cartId = (int)$row['id'];
        if (is_customer_logged_in() && customer_id()) {
            $upd = $pdo->prepare("UPDATE carts SET customer_id = ? WHERE id = ?");
            $upd->execute([customer_id(), $cartId]);
        }
        return $cartId;
    }

    if (!$create) {
        return null;
    }

    $pdo->prepare("UPDATE carts SET status = 'abandoned' WHERE session_id = ? AND status = 'active' AND expires_at <= NOW()")
        ->execute([$sid]);

    $customerId = is_customer_logged_in() ? customer_id() : null;
    $stmt = $pdo->prepare("INSERT INTO carts (customer_id, session_id, status, expires_at) VALUES (?, ?, 'active', DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $stmt->execute([$customerId, $sid]);

    return (int)$pdo->lastInsertId();
}

function cart_items(): array {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT ci.*, p.name, p.slug, p.base_price, p.short_description, p.min_order_qty, p.max_order_qty,
               p.lead_time_days, p.is_active, p.requires_personalisation, p.allow_artwork_upload,
               p.artwork_help_text, p.tax_rate, p.has_variants,
               v.size, v.colour_name, v.colour_hex, v.price_delta, v.stock_quantity AS variant_stock,
               v.manage_stock AS variant_manage_stock, v.is_active AS variant_active,
               COALESCE(pi.path, (SELECT path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1)) AS image
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_variants v ON ci.variant_id = v.id
        LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE ci.cart_id = ?
        GROUP BY ci.id
        ORDER BY ci.id ASC
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['personalisation'] = $item['personalisation_json'] ? (json_decode($item['personalisation_json'], true) ?: []) : [];
        $item['artwork'] = $item['artwork_paths'] ? (json_decode($item['artwork_paths'], true) ?: []) : [];
        $item['unit_price'] = cart_unit_price($item);
        $item['line_total'] = $item['unit_price'] * (int)$item['quantity'];
        $item['variant_label'] = trim(($item['size'] ?? '') . ' ' . ($item['colour_name'] ?? ''));
        $item['out_of_stock'] = cart_item_out_of_stock($item);
    }
    unset($item);

    return $items;
}

function cart_item_out_of_stock(array $item): bool {
    if (empty($item['is_active'])) {
        return true;
    }
    if (!empty($item['variant_id']) && empty($item['variant_active'])) {
        return true;
    }
    $manage = !empty($item['variant_id']) ? !empty($item['variant_manage_stock']) : !empty($item['manage_stock']);
    if ($manage) {
        $stock = !empty($item['variant_id']) ? (int)$item['variant_stock'] : (int)$item['stock_quantity'];
        if ($stock < (int)$item['quantity']) {
            return true;
        }
    }
    return false;
}

function cart_unit_price(array $item): float {
    if (isset($item['unit_price']) && $item['unit_price'] !== null) {
        return round((float)$item['unit_price'], 2);
    }

    $unit = (float)$item['base_price'];
    if (!empty($item['variant_id']) && isset($item['price_delta'])) {
        $unit += (float)$item['price_delta'];
    }
    foreach ($item['personalisation'] ?? [] as $key => $value) {
        $unit += cart_personalisation_addon((int)$item['product_id'], (string)$key, (string)$value);
    }
    return round($unit, 2);
}

function cart_personalisation_addon(int $productId, string $key, string $value): float {
    global $pdo;

    if ($value === '') {
        return 0.0;
    }

    static $cache = [];
    if (!isset($cache[$productId])) {
        $cache[$productId] = [];
        $stmt = $pdo->prepare("SELECT field_key, price_add FROM product_personalisation WHERE product_id = ?");
        $stmt->execute([$productId]);
        foreach ($stmt->fetchAll() as $row) {
            $cache[$productId][$row['field_key']] = (float)$row['price_add'];
        }
    }
    return $cache[$productId][$key] ?? 0.0;
}

function cart_count(): int {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Add an item to the basket.
 * @return array{ok:bool,message:string}
 */
function cart_add(array $input): array {
    global $pdo;

    $productId = (int)($input['product_id'] ?? 0);
    $variantId = (int)($input['variant_id'] ?? 0);
    $quantity = max(1, (int)($input['quantity'] ?? 1));

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return ['ok' => false, 'message' => 'Sorry, that product is no longer available.'];
    }

    $variant = null;
    if ($variantId) {
        $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$variantId, $productId]);
        $variant = $stmt->fetch();
        if (!$variant) {
            return ['ok' => false, 'message' => 'That option is no longer available. Please choose another.'];
        }
    }

    if ($product['has_variants'] && !$variant) {
        return ['ok' => false, 'message' => 'Please choose an option before adding to your basket.'];
    }

    if ($quantity < (int)$product['min_order_qty']) {
        $quantity = (int)$product['min_order_qty'];
    }
    if ($product['max_order_qty'] !== null && $quantity > (int)$product['max_order_qty']) {
        $quantity = (int)$product['max_order_qty'];
    }

    $manage = $variant ? !empty($variant['manage_stock']) : !empty($product['manage_stock']);
    if ($manage) {
        $stock = $variant ? (int)$variant['stock_quantity'] : (int)$product['stock_quantity'];
        if ($quantity > $stock) {
            return ['ok' => false, 'message' => $stock > 0
                ? 'Only ' . $stock . ' left in stock.'
                : 'Sorry, that item is out of stock.'];
        }
    }

    // Personalisation validation
    $rules = [];
    $stmt = $pdo->prepare("SELECT * FROM product_personalisation WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$productId]);
    $rules = $stmt->fetchAll();

    $personalisation = [];
    foreach ($rules as $rule) {
        $key = $rule['field_key'];
        if ($rule['input_type'] === 'file') {
            continue;
        }
        $value = trim((string)($input['personalisation'][$key] ?? ''));
        if ($rule['input_type'] === 'checkbox') {
            $value = isset($input['personalisation'][$key]) ? 'yes' : '';
        }
        if ($rule['required'] && $value === '') {
            return ['ok' => false, 'message' => 'Please complete: ' . $rule['label']];
        }
        if ($rule['max_length'] && mb_strlen($value) > (int)$rule['max_length']) {
            return ['ok' => false, 'message' => $rule['label'] . ' is too long (max ' . (int)$rule['max_length'] . ' characters).'];
        }
        if ($value !== '') {
            $personalisation[$key] = $value;
        }
    }

    // Artwork uploads
    $artwork = [];
    if (!empty($product['allow_artwork_upload']) && !empty($_FILES['artwork'])) {
        $files = $_FILES['artwork'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            $single = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            if ($single['error'] === UPLOAD_ERR_NO_FILE || $single['tmp_name'] === '') {
                continue;
            }
            $path = upload_artwork_file($single);
            if ($path === false) {
                return ['ok' => false, 'message' => 'Artwork upload failed. Use JPG, PNG, WebP, GIF or PDF under 10MB.'];
            }
            $artwork[] = $path;
        }
    }

    $cartId = cart_active_id();
    if (!$cartId) {
        return ['ok' => false, 'message' => 'Unable to start your basket. Please refresh and try again.'];
    }

    $price = $product['base_price'] + ($variant ? (float)$variant['price_delta'] : 0.0);
    foreach ($personalisation as $key => $value) {
        foreach ($rules as $rule) {
            if ($rule['field_key'] === $key) {
                $price += (float)$rule['price_add'];
            }
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price, personalisation_json, artwork_paths)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $cartId,
        $productId,
        $variantId ?: null,
        $quantity,
        round($price, 2),
        $personalisation ? json_encode($personalisation, JSON_UNESCAPED_UNICODE) : null,
        $artwork ? json_encode($artwork, JSON_UNESCAPED_UNICODE) : null,
    ]);

    $pdo->prepare("UPDATE carts SET expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?")->execute([$cartId]);

    return ['ok' => true, 'message' => 'Added to your basket.'];
}

function cart_update_item(int $itemId, int $quantity): array {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return ['ok' => false, 'message' => 'Basket not found.'];
    }

    $stmt = $pdo->prepare("
        SELECT ci.*, p.min_order_qty, p.max_order_qty, p.manage_stock, p.stock_quantity,
               v.stock_quantity AS variant_stock, v.manage_stock AS variant_manage_stock
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_variants v ON ci.variant_id = v.id
        WHERE ci.id = ? AND ci.cart_id = ? LIMIT 1
    ");
    $stmt->execute([$itemId, $cartId]);
    $item = $stmt->fetch();

    if (!$item) {
        return ['ok' => false, 'message' => 'Item not found.'];
    }

    if ($quantity < (int)$item['min_order_qty']) {
        $quantity = (int)$item['min_order_qty'];
    }
    if ($item['max_order_qty'] !== null && $quantity > (int)$item['max_order_qty']) {
        $quantity = (int)$item['max_order_qty'];
    }

    if ($quantity > 0) {
        $manage = $item['variant_id'] ? !empty($item['variant_manage_stock']) : !empty($item['manage_stock']);
        if ($manage) {
            $stock = $item['variant_id'] ? (int)$item['variant_stock'] : (int)$item['stock_quantity'];
            if ($quantity > $stock) {
                return ['ok' => false, 'message' => 'Only ' . $stock . ' left in stock.'];
            }
        }
        $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?")->execute([$quantity, $itemId]);
    } else {
        cart_remove_item($itemId);
    }

    return ['ok' => true, 'message' => 'Basket updated.'];
}

function cart_remove_item(int $itemId): array {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return ['ok' => false, 'message' => 'Basket not found.'];
    }

    $stmt = $pdo->prepare("SELECT artwork_paths FROM cart_items WHERE id = ? AND cart_id = ?");
    $stmt->execute([$itemId, $cartId]);
    $row = $stmt->fetch();

    if ($row) {
        $paths = $row['artwork_paths'] ? (json_decode($row['artwork_paths'], true) ?: []) : [];
        foreach ($paths as $path) {
            delete_upload($path);
        }
        $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?")->execute([$itemId, $cartId]);
    }

    return ['ok' => true, 'message' => 'Item removed.'];
}

function cart_clear(): void {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return;
    }
    $stmt = $pdo->prepare("SELECT artwork_paths FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    foreach ($stmt->fetchAll() as $row) {
        foreach ($row['artwork_paths'] ? (json_decode($row['artwork_paths'], true) ?: []) : [] as $path) {
            delete_upload($path);
        }
    }
    $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
    $pdo->prepare("UPDATE carts SET coupon_code = NULL WHERE id = ?")->execute([$cartId]);
}

function cart_subtotal(array $items): float {
    $total = 0.0;
    foreach ($items as $item) {
        $total += $item['line_total'];
    }
    return round($total, 2);
}

function cart_coupon(): ?array {
    global $pdo;

    $cartId = cart_active_id(false);
    if (!$cartId) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT coupon_code FROM carts WHERE id = ?");
    $stmt->execute([$cartId]);
    $code = $stmt->fetchColumn();
    if (!$code) {
        return null;
    }
    return coupon_validate($code, cart_subtotal(cart_items()));
}

function cart_set_coupon(?string $code): array {
    global $pdo;

    $cartId = cart_active_id();
    if (!$cartId) {
        return ['ok' => false, 'message' => 'Unable to update basket.'];
    }

    if ($code === null || $code === '') {
        $pdo->prepare("UPDATE carts SET coupon_code = NULL WHERE id = ?")->execute([$cartId]);
        return ['ok' => true, 'message' => 'Promo code removed.'];
    }

    $coupon = coupon_validate($code, cart_subtotal(cart_items()));
    if (!$coupon) {
        return ['ok' => false, 'message' => 'That promo code is not valid.'];
    }

    $pdo->prepare("UPDATE carts SET coupon_code = ? WHERE id = ?")->execute([$coupon['code'], $cartId]);
    return ['ok' => true, 'message' => 'Promo code applied.'];
}

function coupon_validate(string $code, float $subtotal): ?array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([strtoupper(trim($code))]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return null;
    }
    if ($coupon['starts_at'] && strtotime($coupon['starts_at']) > time()) {
        return null;
    }
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
        return null;
    }
    if ($coupon['max_uses'] !== null && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
        return null;
    }
    if ($coupon['min_subtotal'] !== null && $subtotal < (float)$coupon['min_subtotal']) {
        return null;
    }
    return $coupon;
}

function coupon_discount(?array $coupon, float $subtotal): float {
    if (!$coupon) {
        return 0.0;
    }
    $discount = 0.0;
    if ($coupon['type'] === 'percent') {
        $discount = $subtotal * ((float)$coupon['value'] / 100);
    } elseif ($coupon['type'] === 'fixed') {
        $discount = (float)$coupon['value'];
    }
    return round(min($discount, $subtotal), 2);
}

function shipping_methods_all(): array {
    global $pdo;
    return $pdo->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
}

function shipping_cost(array $method, float $subtotal, int $itemCount): float {
    if (!empty($method['free_over']) && $subtotal >= (float)$method['free_over']) {
        return 0.0;
    }
    $cost = (float)$method['base_price'] + ((float)$method['per_item_price'] * $itemCount);
    return round(max(0, $cost), 2);
}

/**
 * Basket totals.
 * VAT-inclusive pricing by default (setting `vat_included` = 1).
 */
function cart_totals(?string $shippingCode = null): array {
    $items = cart_items();
    $subtotal = cart_subtotal($items);
    $itemCount = 0;
    foreach ($items as $item) {
        $itemCount += (int)$item['quantity'];
    }

    $coupon = cart_coupon();
    $discount = coupon_discount($coupon, $subtotal);

    $shipping = 0.0;
    $shippingRow = null;
    if ($shippingCode) {
        foreach (shipping_methods_all() as $method) {
            if ($method['code'] === $shippingCode) {
                $shippingRow = $method;
                break;
            }
        }
        if ($shippingRow) {
            $shipping = shipping_cost($shippingRow, $subtotal - $discount, $itemCount);
            if ($coupon && $coupon['type'] === 'free_shipping') {
                $shipping = 0.0;
            }
        }
    }

    $net = $subtotal - $discount;
    $rate = (float)setting('vat_rate', '20.00') / 100;
    $vatIncluded = setting('vat_included', '1') === '1';

    if ($vatIncluded) {
        $tax = round($net - ($net / (1 + $rate)), 2);
        $total = $net + $shipping;
    } else {
        $tax = round($net * $rate, 2);
        $total = $net + $tax + $shipping;
    }

    return [
        'items' => $items,
        'itemCount' => $itemCount,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'shipping' => round($shipping, 2),
        'tax' => $tax,
        'total' => round($total, 2),
        'coupon' => $coupon,
        'shippingMethod' => $shippingRow,
        'vatIncluded' => $vatIncluded,
        'rate' => $rate,
    ];
}
