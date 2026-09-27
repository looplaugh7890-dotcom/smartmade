<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$service = in_array($_GET['service'] ?? '', ['embroidery', 'printing', 'promotional', 'other'], true) ? $_GET['service'] : '';

global $pdo;
$where = "status = 'published'";
$params = [];
if ($service) {
    $where .= " AND service_type = ?";
    $params[] = $service;
}
$stmt = $pdo->prepare("SELECT * FROM gallery_items WHERE $where ORDER BY is_featured DESC, display_order ASC, created_at DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

$services = [
    '' => 'All work',
    'embroidery' => 'Embroidery',
    'printing' => 'Printing',
    'promotional' => 'Promotional',
    'other' => 'Other',
];

$pageTitle = 'Gallery';
$pageDescription = 'Previous embroidery, printing and promotional work from the SmartMade studio in Fife, Scotland.';
$canonicalPath = 'gallery' . ($service ? '?service=' . $service : '');
$bodyClass = 'gallery-page';

$jsonLd = [org_jsonld(), [
    '@context' => 'https://schema.org',
    '@type' => 'ImageGallery',
    'name' => 'SmartMade Gallery',
    'description' => $pageDescription,
    'url' => canonical_url('gallery'),
]];

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="gallery-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Fresh off the machine</span>
        <h1 class="page-title reveal" id="gallery-title">The Gallery</h1>
        <p class="page-intro reveal">Embroidery, printing and promotional pieces we have made for folk across Scotland and beyond.</p>
    </div>
</section>

<?= breadcrumb_html([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Gallery', 'url' => canonical_url('gallery')],
]) ?>

<section class="section gallery-section">
    <div class="container">
        <div class="filter-bar" role="group" aria-label="Filter gallery">
            <?php foreach ($services as $key => $label): ?>
                <a href="<?= SITE_URL ?>/gallery.php<?= $key ? '?service=' . $key : '' ?>"
                   class="filter-btn<?= $service === $key ? ' is-active' : '' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <h2>Nothing here yet</h2>
                <p>We are still uploading the archive. Meanwhile, the <a href="<?= SITE_URL ?>/portfolio.php">portfolio</a> is full of pieces.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($items as $item): ?>
                    <figure class="gallery-item reveal<?= $item['is_featured'] ? ' is-featured' : '' ?>">
                        <a href="<?= SITE_URL . '/' . e($item['image']) ?>"
                           class="gallery-item-link"
                           data-lightbox
                           data-caption="<?= e($item['title'] . ($item['description'] ? ' — ' . $item['description'] : '')) ?>">
                            <img src="<?= SITE_URL . '/' . e($item['image']) ?>"
                                 alt="<?= e($item['title']) ?>"
                                 loading="lazy" decoding="async" width="800" height="800">
                        </a>
                        <figcaption>
                            <span class="gallery-item-service"><?= e(ucfirst($item['service_type'])) ?></span>
                            <h2 class="gallery-item-title"><?= e($item['title']) ?></h2>
                            <?php if ($item['client_name']): ?>
                                <span class="gallery-item-client"><?= e($item['client_name']) ?></span>
                            <?php endif; ?>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="gallery-cta">
            <p>Fancy something like these?</p>
            <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary">Start your design</a>
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-secondary">Shop ready-made</a>
        </div>
    </div>
</section>

<div class="lightbox" id="lightbox" hidden>
    <button type="button" class="lightbox-close" aria-label="Close">&times;</button>
    <img src="" alt="" class="lightbox-image">
    <p class="lightbox-caption"></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
