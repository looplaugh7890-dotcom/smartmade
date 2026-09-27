<?php

require_once __DIR__ . '/includes/functions.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;

$countStmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published' AND published_at <= NOW()");
$total = (int) $countStmt->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM blog_posts
    WHERE status = 'published' AND published_at <= NOW()
    ORDER BY published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$posts = $stmt->fetchAll();

$pageTitle = 'Journal';
$pageDescription = 'Behind-the-scenes stories, new embroidery drops, and process notes from SmartMade in Scotland.';
$bodyClass = 'blog-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="blog-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Behind the stitches</span>
        <h1 class="page-title reveal" id="blog-title">Journal</h1>
        <p class="page-intro reveal">Process stories, new drops, football tributes, and the occasional ramble about thread.</p>
    </div>
</section>

<section class="section" aria-label="Journal posts">
    <div class="container">
        <?php if (empty($posts)): ?>
            <div class="empty-state reveal">
                <p>Journal posts coming soon.</p>
            </div>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($posts as $post): ?>
                    <article class="blog-card reveal">
                        <a href="<?= blog_url((int)$post['id'], $post['slug']) ?>">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= SITE_URL . '/' . e($post['featured_image']) ?>" alt="" class="blog-thumb" loading="lazy">
                            <?php else: ?>
                                <div class="blog-thumb" style="background: var(--color-black); display: flex; align-items: center; justify-content: center; color: var(--color-gold); font-family: var(--font-display); font-size: 2rem; font-weight: 900;">SM</div>
                            <?php endif; ?>
                        </a>
                        <div class="blog-content">
                            <div class="blog-date"><?= format_date($post['published_at']) ?></div>
                            <h2 class="blog-title"><a href="<?= blog_url((int)$post['id'], $post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                            <p class="blog-excerpt"><?= e(excerpt($post['excerpt'] ?? $post['content'], 140)) ?></p>
                            <a href="<?= blog_url((int)$post['id'], $post['slug']) ?>" class="pillar-link">Read more →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pagination['totalPages'] > 1): ?>
                <nav class="pagination" aria-label="Journal pagination">
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
