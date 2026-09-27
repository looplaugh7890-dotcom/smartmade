<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/seo.php';

$categorySlug = preg_replace('/[^a-z0-9\-]/', '', (string)($_GET['category'] ?? ''));
$sort = in_array($_GET['sort'] ?? '', ['newest', 'price_asc', 'price_desc', 'name', 'popular'], true) ? $_GET['sort'] : 'newest';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$result = fetch_products([
    'category_slug' => $categorySlug ?: null,
    'sort' => $sort,
    'q' => $q,
    'page' => $page,
    'per_page' => 12,
]);

$categories = product_categories_with_counts();
$activeCategory = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) {
        $activeCategory = $cat;
    }
}

$pageTitle = $activeCategory ? $activeCategory['name'] . ' — Shop' : setting('shop_page_title', 'Shop');
$pageDescription = $activeCategory && $activeCategory['description']
    ? $activeCategory['description']
    : 'Shop custom embroidery, printing and promotional merchandise from SmartMade. Personalise it, upload your artwork, we stitch the rest.';
$canonicalPath = 'shop' . ($categorySlug ? '/' . $categorySlug : '') . ($q ? '?q=' . urlencode($q) : '');
$bodyClass = 'shop-page';

$jsonLd = [org_jsonld(), [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'url' => canonical_url($canonicalPath),
]];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="shop-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Wear it, gift it, brand it</span>
        <h1 class="page-title reveal" id="shop-title"><?= e($activeCategory ? $activeCategory['name'] : 'The Shop') ?></h1>
        <p class="page-intro reveal">
            <?= $activeCategory ? e($activeCategory['description'] ?: 'Browse the collection.') : 'Embroidery, printing and promotional goods — personalise them, upload your artwork, and we will handle the rest.' ?>
        </p>
    </div>
</section>

<?= breadcrumb_html([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Shop', 'url' => canonical_url('shop')],
    ...($activeCategory ? [['name' => $activeCategory['name'], 'url' => canonical_url('shop/' . $activeCategory['slug'])]] : []),
]) ?>

<section class="section shop-section">
    <div class="container">
        <div class="shop-layout">
            <aside class="shop-sidebar" aria-label="Shop filters">
                <form action="<?= SITE_URL ?>/shop.php" method="get" class="shop-search-form">
                    <label for="shop-q" class="visually-hidden">Search products</label>
                    <input type="search" id="shop-q" name="q" value="<?= e($q) ?>" placeholder="Search products…" class="form-input">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                </form>

                <div class="shop-filter-group">
                    <h2 class="shop-filter-title">Categories</h2>
                    <ul class="shop-filter-list">
                        <li>
                            <a href="<?= SITE_URL ?>/shop.php<?= $q ? '?q=' . urlencode($q) : '' ?>" class="<?= $categorySlug === '' ? 'is-active' : '' ?>">
                                All products <span>(<?= (int)$result['total'] + ($categorySlug ? 0 : 0) ?>)</span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="<?= SITE_URL ?>/shop.php?category=<?= e($cat['slug']) ?>" class="<?= $categorySlug === $cat['slug'] ? 'is-active' : '' ?>">
                                    <?= e($cat['name']) ?> <span>(<?= (int)$cat['product_count'] ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="shop-filter-group">
                    <h2 class="shop-filter-title">Need something bespoke?</h2>
                    <p class="shop-filter-copy">Not everything has a fixed price. Send us the job and we will quote it properly.</p>
                    <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary btn-sm">Get a Quote</a>
                </div>
            </aside>

            <div class="shop-main">
                <div class="shop-toolbar">
                    <p class="shop-results-count">
                        <?= (int)$result['total'] ?> <?= $result['total'] === 1 ? 'product' : 'products' ?>
                        <?php if ($q): ?> matching “<?= e($q) ?>”<?php endif; ?>
                    </p>
                    <form action="<?= SITE_URL ?>/shop.php" method="get" class="shop-sort-form">
                        <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
                        <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                        <label for="shop-sort" class="visually-hidden">Sort by</label>
                        <select id="shop-sort" name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($result['rows'])): ?>
                    <div class="empty-state">
                        <h2>Nothing here yet</h2>
                        <p>We are still stocking this shelf. Try another category, or ask us about a custom job.</p>
                        <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary">Browse everything</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($result['rows'] as $product): ?>
                            <?= product_card_html($product) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $pagination = $result['pagination']; ?>
                <?php if ($pagination['totalPages'] > 1): ?>
                    <nav class="pagination" aria-label="Product pages">
                        <?php
                        $baseParams = array_filter(['category' => $categorySlug, 'q' => $q, 'sort' => $sort !== 'newest' ? $sort : '']);
                        if ($pagination['hasPrev']):
                            $baseParams['page'] = $pagination['page'] - 1;
                        ?>
                            <a href="<?= SITE_URL ?>/shop.php?<?= e(http_build_query($baseParams)) ?>" class="page-prev">← Previous</a>
                        <?php endif; ?>
                        <span class="page-current">Page <?= $pagination['page'] ?> of <?= $pagination['totalPages'] ?></span>
                        <?php
                        if ($pagination['hasNext']):
                            $baseParams['page'] = $pagination['page'] + 1;
                        ?>
                            <a href="<?= SITE_URL ?>/shop.php?<?= e(http_build_query($baseParams)) ?>" class="page-next">Next →</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
