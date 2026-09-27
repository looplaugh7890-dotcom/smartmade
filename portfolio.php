<?php

require_once __DIR__ . '/includes/functions.php';

$categoryFilter = $_GET['category'] ?? 'all';
$categoryFilter = in_array($categoryFilter, ['football', 'business', 'originals'], true) ? $categoryFilter : 'all';

$isAjax = isset($_GET['ajax']);
$modalId = isset($_GET['modal']) ? (int)$_GET['modal'] : 0;

if ($isAjax && $modalId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM portfolio_items p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'published'
        LIMIT 1
    ");
    $stmt->execute([$modalId]);
    $item = $stmt->fetch();

    if ($item) {
        echo '<div class="modal-media">';
        if ($item['image']) {
            echo '<img src="' . SITE_URL . '/' . e($item['image']) . '" alt="' . e($item['title']) . '">';
        } else {
            echo '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--color-gold);font-family:var(--font-display);font-size:4rem;font-weight:900;">SM</div>';
        }
        echo '</div>';
        echo '<div class="modal-info">';
        echo '<span class="modal-category">' . e($item['category_name'] ?? 'Original') . '</span>';
        echo '<h2 class="modal-title">' . e($item['title']) . '</h2>';
        echo '<div class="modal-description">' . nl2br(e($item['description'] ?? 'No description available.')) . '</div>';
        echo '<a href="' . SITE_URL . '/quote.php" class="btn btn-primary">Start a similar piece</a>';
        echo '</div>';
    } else {
        echo '<p>Item not found.</p>';
    }
    exit;
}

if ($isAjax) {
    $sql = "
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM portfolio_items p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'published'
    ";
    $params = [];
    if ($categoryFilter !== 'all') {
        $sql .= " AND c.slug = ?";
        $params[] = $categoryFilter;
    }
    $sql .= " ORDER BY p.display_order ASC, p.created_at DESC LIMIT 24";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        echo '<div class="empty-state" style="grid-column: 1 / -1;"><p>No pieces in this category yet.</p></div>';
    } else {
        foreach ($items as $item) {
            echo '<article class="portfolio-item">';
            echo '<a href="' . portfolio_url((int)$item['id'], $item['slug']) . '" class="portfolio-link" data-modal-trigger data-id="' . (int)$item['id'] . '">';
            if ($item['image']) {
                echo '<img src="' . SITE_URL . '/' . e($item['image']) . '" alt="' . e($item['title']) . '" class="portfolio-thumb" loading="lazy">';
            } else {
                echo '<div class="portfolio-thumb" style="background:var(--color-black);display:flex;align-items:center;justify-content:center;color:var(--color-gold);">' . e($item['title']) . '</div>';
            }
            echo '<div class="portfolio-overlay">';
            echo '<span class="portfolio-category">' . e($item['category_name'] ?? 'Original') . '</span>';
            echo '<h3 class="portfolio-item-title">' . e($item['title']) . '</h3>';
            echo '</div>';
            echo '<div class="portfolio-stitch-frame" aria-hidden="true"></div>';
            echo '</a>';
            echo '</article>';
        }
    }
    exit;
}

$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM portfolio_items p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'published'
";
$params = [];
if ($categoryFilter !== 'all') {
    $sql .= " AND c.slug = ?";
    $params[] = $categoryFilter;
}
$sql .= " ORDER BY p.display_order ASC, p.created_at DESC LIMIT 24";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'Portfolio';
$pageDescription = 'Browse custom embroidery work by SmartMade — football tributes, business logos, and original pieces from Scotland.';
$bodyClass = 'portfolio-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="portfolio-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">The work</span>
        <h1 class="page-title reveal" id="portfolio-title">Portfolio</h1>
        <p class="page-intro reveal">A growing collection of football tributes, business logos, and daft-but-brilliant originals. Filter by thread.</p>
    </div>
</section>

<section class="section" aria-label="Portfolio gallery">
    <div class="container">
        <div class="portfolio-filters reveal" role="group" aria-label="Filter portfolio by category">
            <button class="filter-btn <?= $categoryFilter === 'all' ? 'is-active' : '' ?>" data-category="all" aria-pressed="<?= $categoryFilter === 'all' ? 'true' : 'false' ?>">All</button>
            <button class="filter-btn <?= $categoryFilter === 'football' ? 'is-active' : '' ?>" data-category="football" aria-pressed="<?= $categoryFilter === 'football' ? 'true' : 'false' ?>">Football</button>
            <button class="filter-btn <?= $categoryFilter === 'business' ? 'is-active' : '' ?>" data-category="business" aria-pressed="<?= $categoryFilter === 'business' ? 'true' : 'false' ?>">Business</button>
            <button class="filter-btn <?= $categoryFilter === 'originals' ? 'is-active' : '' ?>" data-category="originals" aria-pressed="<?= $categoryFilter === 'originals' ? 'true' : 'false' ?>">Originals</button>
            <span class="stitch-loader" id="portfolio-loader" aria-hidden="true"></span>
        </div>

        <div class="portfolio-grid reveal" id="portfolio-grid">
            <?php if (empty($items)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <p>No pieces in this category yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <article class="portfolio-item">
                        <a href="<?= portfolio_url((int)$item['id'], $item['slug']) ?>" class="portfolio-link" data-modal-trigger data-id="<?= (int)$item['id'] ?>">
                            <?php if ($item['image']): ?>
                                <img src="<?= SITE_URL . '/' . e($item['image']) ?>" alt="<?= e($item['title']) ?>" class="portfolio-thumb" loading="lazy">
                            <?php else: ?>
                                <div class="portfolio-thumb" style="background: var(--color-black); display: flex; align-items: center; justify-content: center; color: var(--color-gold);"><?= e($item['title']) ?></div>
                            <?php endif; ?>
                            <div class="portfolio-overlay">
                                <span class="portfolio-category"><?= e($item['category_name'] ?? 'Original') ?></span>
                                <h3 class="portfolio-item-title"><?= e($item['title']) ?></h3>
                            </div>
                            <div class="portfolio-stitch-frame" aria-hidden="true"></div>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="modal" id="portfolio-modal" role="dialog" aria-modal="true" aria-label="Portfolio item details">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close">&times;</button>
        <div class="modal-body" id="modal-body">
            <p style="padding: 40px; text-align: center;">Loading…</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
