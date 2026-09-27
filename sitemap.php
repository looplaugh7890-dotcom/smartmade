<?php

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

global $pdo;

$staticPages = [
    ['', '1.0', 'daily'],
    ['shop', '0.8', 'daily'],
    ['portfolio', '0.8', 'weekly'],
    ['gallery', '0.7', 'weekly'],
    ['services', '0.8', 'monthly'],
    ['about', '0.6', 'monthly'],
    ['blog', '0.7', 'weekly'],
    ['testimonials', '0.5', 'monthly'],
    ['reviews', '0.7', 'weekly'],
    ['faq', '0.6', 'monthly'],
    ['contact', '0.6', 'monthly'],
    ['quote', '0.7', 'monthly'],
    ['shipping', '0.4', 'yearly'],
    ['privacy', '0.3', 'yearly'],
    ['terms', '0.3', 'yearly'],
];

$urls = [];

foreach ($staticPages as [$path, $priority, $freq]) {
    $urls[] = [
        'loc' => rtrim(SITE_URL, '/') . '/' . $path . ($path ? '/' : ''),
        'priority' => $priority,
        'changefreq' => $freq,
    ];
}

try {
    foreach ($pdo->query("SELECT slug, updated_at FROM products WHERE is_active = 1") as $row) {
        $urls[] = [
            'loc' => SITE_URL . '/product/' . $row['slug'],
            'lastmod' => date('Y-m-d', strtotime($row['updated_at'])),
            'priority' => '0.9',
            'changefreq' => 'weekly',
        ];
    }
    foreach ($pdo->query("SELECT slug FROM product_categories WHERE status = 'active'") as $row) {
        $urls[] = ['loc' => SITE_URL . '/shop/' . $row['slug'], 'priority' => '0.8', 'changefreq' => 'weekly'];
    }
    foreach ($pdo->query("SELECT slug, updated_at FROM portfolio_items WHERE status = 'published'") as $row) {
        $urls[] = [
            'loc' => SITE_URL . '/portfolio.php',
            'lastmod' => date('Y-m-d', strtotime($row['updated_at'])),
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
        break; // portfolio is modal-driven — one entry is enough
    }
    foreach ($pdo->query("SELECT id, published_at FROM blog_posts WHERE status = 'published'") as $row) {
        $urls[] = [
            'loc' => SITE_URL . '/blog-post.php?id=' . (int)$row['id'],
            'lastmod' => date('Y-m-d', strtotime($row['published_at'])),
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
    }
} catch (Throwable $e) {
    error_log('sitemap query failed: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($url['lastmod'])) {
        echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
    }
    if (!empty($url['changefreq'])) {
        echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    }
    if (!empty($url['priority'])) {
        echo '    <priority>' . $url['priority'] . "</priority>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
