<?php

require_once __DIR__ . '/functions.php';

function product_card_image(array $product): string {
    $image = $product['image'] ?? null;
    if (!$image && !empty($product['id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
        $stmt->execute([$product['id']]);
        $image = $stmt->fetchColumn() ?: null;
    }
    return $image ? SITE_URL . '/' . ltrim($image, '/') : SITE_URL . '/assets/images/og-default.svg';
}

function product_price_html(array $product, array $prices = []): string {
    $symbol = setting('currency_symbol', '£');
    $min = (float)$product['base_price'];
    $max = (float)$product['base_price'];

    if (!empty($prices)) {
        $values = array_map(static fn($p) => (float)$p, $prices);
        $min = min($values);
        $max = max($values);
    }

    $from = $min < $max || ($product['pricing_unit'] ?? 'each') === 'from';

    if ($product['compare_at_price'] !== null && (float)$product['compare_at_price'] > $max) {
        return '<span class="price"><span class="price-now">' . $symbol . number_format($min, 2)
            . '</span> <span class="price-was">' . $symbol . number_format((float)$product['compare_at_price'], 2) . '</span></span>';
    }

    return '<span class="price">' . ($from ? '<span class="price-from">from </span>' : '')
        . $symbol . number_format($min, 2) . '</span>';
}

function product_rating_html(int $productId): string {
    if (!setting('enable_reviews', '1')) {
        return '';
    }
    require_once __DIR__ . '/seo.php';
    $summary = product_rating_summary($productId);
    if ($summary['count'] === 0) {
        return '<span class="rating rating-empty">No reviews yet</span>';
    }
    return '<span class="rating"><span class="star-rating" aria-label="' . $summary['average'] . ' out of 5">'
        . str_repeat('★', (int)round($summary['average'])) . str_repeat('☆', 5 - (int)round($summary['average']))
        . '</span> <span class="rating-count">(' . $summary['count'] . ')</span></span>';
}

function product_card_html(array $product): string {
    $url = canonical_url('product/' . $product['slug']);
    $image = product_card_image($product);
    $name = e($product['name']);
    $excerpt = e($product['short_description'] ?? '');
    $price = product_price_html($product, $product['prices'] ?? []);
    $rating = product_rating_html((int)$product['id']);

    $badge = '';
    if (!$product['is_active']) {
        $badge = '<span class="product-badge is-hidden">Unavailable</span>';
    } elseif ((int)($product['sales_count'] ?? 0) >= 5) {
        $badge = '<span class="product-badge is-hot">Bestseller</span>';
    } elseif ((float)($product['compare_at_price'] ?? 0) > (float)$product['base_price']) {
        $badge = '<span class="product-badge is-sale">Sale</span>';
    }

    return '<article class="product-card reveal">'
        . '<a href="' . e($url) . '" class="product-card-link">'
        . '<div class="product-card-media">' . $badge
        . '<img src="' . e($image) . '" alt="' . $name . '" loading="lazy" decoding="async" width="600" height="750">'
        . '</div>'
        . '<div class="product-card-body">'
        . '<h3 class="product-card-title">' . $name . '</h3>'
        . ($excerpt ? '<p class="product-card-excerpt">' . $excerpt . '</p>' : '')
        . '<div class="product-card-meta">' . $price . $rating . '</div>'
        . '</div></a></article>';
}

/**
 * @return array{rows:array,total:int}
 */
function fetch_products(array $filters = []): array {
    global $pdo;

    $where = ['1 = 1'];
    $params = [];

    if (!empty($filters['active_only'] ?? true)) {
        $where[] = 'p.is_active = 1';
    }
    if (!empty($filters['category_slug'])) {
        $where[] = 'c.slug = ?';
        $params[] = $filters['category_slug'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'p.category_id = ?';
        $params[] = (int)$filters['category_id'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
        $like = '%' . $filters['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    $order = match ($filters['sort'] ?? 'newest') {
        'price_asc' => 'p.base_price ASC',
        'price_desc' => 'p.base_price DESC',
        'name' => 'p.name ASC',
        'popular' => 'p.sales_count DESC, p.view_count DESC',
        default => 'p.is_featured DESC, p.created_at DESC',
    };

    $whereSql = implode(' AND ', $where);

    $total = 0;
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN product_categories c ON p.category_id = c.id WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $perPage = max(1, (int)($filters['per_page'] ?? 12));
    $page = max(1, (int)($filters['page'] ?? 1));
    $pagination = paginate($total, $page, $perPage);

    $sql = "
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image,
               (SELECT MIN(v.price_delta) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS min_delta,
               (SELECT MAX(v.price_delta) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS max_delta
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE $whereSql
        ORDER BY $order
        LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $prices = [(float)$row['base_price']];
        if ($row['min_delta'] !== null) {
            $prices[] = (float)$row['base_price'] + (float)$row['min_delta'];
        }
        if ($row['max_delta'] !== null) {
            $prices[] = (float)$row['base_price'] + (float)$row['max_delta'];
        }
        $row['prices'] = $prices;
    }
    unset($row);

    return ['rows' => $rows, 'total' => $total, 'pagination' => $pagination];
}

function product_categories_with_counts(): array {
    global $pdo;

    return $pdo->query("
        SELECT c.*, COUNT(p.id) AS product_count
        FROM product_categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.name ASC
    ")->fetchAll();
}

function product_by_slug(string $slug): ?array {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function product_images_for(int $productId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function product_variants_for(int $productId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY sort_order ASC, size ASC, colour_name ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function product_personalisation_for(int $productId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM product_personalisation WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}
