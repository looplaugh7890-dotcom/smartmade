<?php

require_once __DIR__ . '/functions.php';

function canonical_url(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function page_seo(string $pageKey): array {
    global $pdo;

    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = $pdo->query("SELECT page_key, path, meta_title, meta_description, og_image, canonical, robots FROM page_seo");
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['page_key']] = $row;
            }
        } catch (Throwable $e) {
            error_log('page_seo load failed: ' . $e->getMessage());
        }
    }
    return $cache[$pageKey] ?? [];
}

function jsonld(array $data): string {
    return '<script type="application/ld+json">'
        . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        . '</script>';
}

function org_jsonld(): string {
    return jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => setting('site_title', 'SmartMade'),
        'url' => SITE_URL . '/',
        'logo' => SITE_URL . '/assets/images/favicon.svg',
        'sameAs' => array_values(array_filter([
            setting('instagram_url'),
            setting('facebook_url'),
            setting('tiktok_url'),
        ])),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'email' => setting('contact_email'),
            'contactType' => 'customer service',
        ],
    ]);
}

function breadcrumb_jsonld(array $crumbs): string {
    $items = [];
    $i = 1;
    foreach ($crumbs as $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $i++,
            'name' => $crumb['name'],
            'item' => $crumb['url'],
        ];
    }
    return jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ]);
}

function breadcrumb_html(array $crumbs): string {
    if (empty($crumbs)) {
        return '';
    }
    $html = '<nav class="breadcrumbs container" aria-label="Breadcrumb"><ol>';
    $count = count($crumbs);
    foreach ($crumbs as $i => $crumb) {
        $last = ($i === $count - 1);
        $html .= '<li>';
        if ($last) {
            $html .= '<span aria-current="page">' . e($crumb['name']) . '</span>';
        } else {
            $html .= '<a href="' . e($crumb['url']) . '">' . e($crumb['name']) . '</a>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></nav>';
    return $html;
}

function product_jsonld(array $product, array $images, ?float $rating = null, int $reviewCount = 0): string {
    $offers = [
        '@type' => 'Offer',
        'url' => canonical_url('product/' . $product['slug']),
        'priceCurrency' => setting('currency', 'GBP'),
        'price' => number_format((float)$product['base_price'], 2, '.', ''),
        'availability' => $product['is_active']
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ];

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'description' => $product['short_description'] ?: strip_tags((string)$product['description']),
        'sku' => $product['sku'] ?: null,
        'image' => array_map(static fn($img) => str_starts_with($img, 'http') ? $img : SITE_URL . '/' . $img, array_slice($images, 0, 4)),
        'offers' => $offers,
    ];

    if ($rating && $reviewCount > 0) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($rating, 1, '.', ''),
            'reviewCount' => $reviewCount,
            'bestRating' => 5,
        ];
    }

    $data = array_filter($data, static fn($v) => $v !== null);
    return jsonld($data);
}

function review_jsonld(array $review): string {
    return jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'Review',
        'author' => ['@type' => 'Person', 'name' => $review['author_name']],
        'reviewRating' => [
            '@type' => 'Rating',
            'ratingValue' => (int)$review['rating'],
            'bestRating' => 5,
        ],
        'reviewBody' => $review['content'],
        'datePublished' => date('Y-m-d', strtotime($review['created_at'])),
    ]);
}

function product_rating_summary(int $productId): array {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
        FROM reviews
        WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$productId]);
    $row = $stmt->fetch();

    return [
        'count' => (int)$row['cnt'],
        'average' => round((float)$row['avg_rating'], 1),
    ];
}
