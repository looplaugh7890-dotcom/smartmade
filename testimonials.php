<?php

require_once __DIR__ . '/includes/functions.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;

$countStmt = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'published'");
$total = (int) $countStmt->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM testimonials
    WHERE status = 'published'
    ORDER BY is_featured DESC, display_order ASC, created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$testimonials = $stmt->fetchAll();

$pageTitle = 'Reviews';
$pageDescription = 'Read reviews from SmartMade customers — custom embroidery portraits, business logos, and gifts from Scotland.';
$bodyClass = 'testimonials-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="reviews-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Reviews</span>
        <h1 class="page-title reveal" id="reviews-title">Kind Words</h1>
        <p class="page-intro reveal">Real feedback from real people who trusted us with their ideas.</p>
    </div>
</section>

<section class="section" aria-label="Customer reviews">
    <div class="container">
        <?php if (empty($testimonials)): ?>
            <div class="empty-state reveal">
                <p>No reviews published yet.</p>
            </div>
        <?php else: ?>
            <div class="testimonial-grid">
                <?php foreach ($testimonials as $t): ?>
                    <article class="testimonial-card reveal">
                        <span class="testimonial-seal">SM</span>
                        <?= star_rating((int)$t['rating']) ?>
                        <p class="testimonial-text">"<?= e($t['content']) ?>"</p>
                        <div class="testimonial-author">
                            <?php if ($t['image']): ?>
                                <img src="<?= SITE_URL . '/' . e($t['image']) ?>" alt="" class="testimonial-avatar" loading="lazy">
                            <?php else: ?>
                                <div class="testimonial-avatar" style="display: flex; align-items: center; justify-content: center; background: var(--color-black); color: var(--color-gold); font-weight: 700;"><?= e(strtoupper(mb_substr($t['author_name'], 0, 1))) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="testimonial-name"><?= e($t['author_name']) ?></div>
                                <?php if ($t['author_title']): ?>
                                    <div class="testimonial-title"><?= e($t['author_title']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pagination['totalPages'] > 1): ?>
                <nav class="pagination" aria-label="Reviews pagination">
                    <?php if ($pagination['hasPrev']): ?>
                        <a href="<?= pagination_url($pagination['page'] - 1) ?>">← Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <?php if ($i === $pagination['page']): ?>
                            <span class="current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= pagination_url($i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($pagination['hasNext']): ?>
                        <a href="<?= pagination_url($pagination['page'] + 1) ?>">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
