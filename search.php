<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/seo.php';

$q = trim((string)($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    global $pdo;
    $like = '%' . $q . '%';

    $products = fetch_products(['q' => $q, 'per_page' => 8, 'page' => 1])['rows'];

    $stmt = $pdo->prepare("
        SELECT id, title, slug, description AS excerpt, image, 'portfolio' AS kind FROM portfolio_items
        WHERE status = 'published' AND (title LIKE ? OR description LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$like, $like]);
    $portfolio = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, featured_image AS image, 'journal' AS kind FROM blog_posts
        WHERE status = 'published' AND (title LIKE ? OR content LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$like, $like]);
    $posts = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT question AS title, answer AS excerpt, id, 'faq' AS kind FROM faq_items
        WHERE status = 'published' AND (question LIKE ? OR answer LIKE ?)
        LIMIT 6
    ");
    $stmt->execute([$like, $like]);
    $faqs = $stmt->fetchAll();

    $results = [
        'products' => $products,
        'portfolio' => $portfolio,
        'journal' => $posts,
        'faq' => $faqs,
    ];
}

$totalResults = 0;
foreach ($results as $group) {
    $totalResults += count($group);
}

$pageTitle = $q !== '' ? 'Search: ' . $q : 'Search';
$pageDescription = 'Search SmartMade for products, portfolio pieces, articles and answers.';
$canonicalPath = 'search' . ($q ? '?q=' . urlencode($q) : '');
$bodyClass = 'search-page';
$robots = 'noindex, follow';
$jsonLd = [org_jsonld()];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="search-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Find it fast</span>
        <h1 class="page-title reveal" id="search-title">Search</h1>
    </div>
</section>

<section class="section search-section">
    <div class="container">
        <form action="<?= SITE_URL ?>/search.php" method="get" class="search-form">
            <label for="search-q" class="visually-hidden">Search the site</label>
            <input type="search" id="search-q" name="q" class="form-input" value="<?= e($q) ?>" placeholder="Search products, work, articles…" autofocus>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <?php if ($q !== ''): ?>
            <p class="shop-results-count"><?= $totalResults ?> result<?= $totalResults === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>

            <?php if ($totalResults === 0): ?>
                <div class="empty-state">
                    <h2>Nothing matched</h2>
                    <p>Try a different word, or browse the <a href="<?= SITE_URL ?>/shop.php">shop</a> and <a href="<?= SITE_URL ?>/portfolio.php">portfolio</a>.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($results['products'])): ?>
                    <div class="search-group">
                        <h2>Products</h2>
                        <div class="product-grid">
                            <?php foreach ($results['products'] as $product): ?>
                                <?= product_card_html($product) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['portfolio'])): ?>
                    <div class="search-group">
                        <h2>Portfolio</h2>
                        <ul class="search-results-list">
                            <?php foreach ($results['portfolio'] as $row): ?>
                                <li>
                                    <a href="<?= SITE_URL ?>/portfolio.php?modal=<?= (int)$row['id'] ?>">
                                        <?php if ($row['image']): ?>
                                            <img src="<?= SITE_URL . '/' . e($row['image']) ?>" alt="" width="64" height="64" loading="lazy">
                                        <?php endif; ?>
                                        <span>
                                            <strong><?= e($row['title']) ?></strong>
                                            <em><?= e(excerpt((string)($row['excerpt'] ?: ''), 120)) ?></em>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['journal'])): ?>
                    <div class="search-group">
                        <h2>Journal</h2>
                        <ul class="search-results-list">
                            <?php foreach ($results['journal'] as $row): ?>
                                <li>
                                    <a href="<?= SITE_URL ?>/blog-post.php?id=<?= (int)$row['id'] ?>">
                                        <span>
                                            <strong><?= e($row['title']) ?></strong>
                                            <em><?= e(excerpt((string)$row['excerpt'], 120)) ?></em>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['faq'])): ?>
                    <div class="search-group">
                        <h2>FAQ</h2>
                        <ul class="search-results-list">
                            <?php foreach ($results['faq'] as $row): ?>
                                <li>
                                    <a href="<?= SITE_URL ?>/faq.php">
                                        <span>
                                            <strong><?= e($row['title']) ?></strong>
                                            <em><?= e(excerpt((string)$row['excerpt'], 140)) ?></em>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
