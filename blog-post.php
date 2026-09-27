<?php

require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT * FROM blog_posts
    WHERE id = ? AND status = 'published' AND published_at <= NOW()
    LIMIT 1
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    $pageDescription = 'This journal post could not be found.';
    $bodyClass = 'error-page';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container" style="text-align:center;"><h1>Post not found</h1><p><a href="' . SITE_URL . '/blog.php">Back to journal</a></p></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $post['meta_title'] ?: $post['title'];
$pageDescription = $post['meta_description'] ?: excerpt($post['excerpt'] ?? $post['content'], 160);
$ogImage = $post['featured_image'] ? SITE_URL . '/' . $post['featured_image'] : null;
$bodyClass = 'blog-single-page';

include __DIR__ . '/includes/header.php';
?>

<article class="section blog-single" aria-labelledby="post-title">
    <div class="container">
        <header class="blog-single-header reveal">
            <a href="<?= SITE_URL ?>/blog.php" class="section-label" style="margin-bottom: 16px;">← Journal</a>
            <div class="blog-date"><?= format_date($post['published_at']) ?> · by <?= e($post['author_name']) ?></div>
            <h1 class="section-title" id="post-title" style="margin-top: 12px;"><?= e($post['title']) ?></h1>
        </header>

        <?php if ($post['featured_image']): ?>
            <img src="<?= SITE_URL . '/' . e($post['featured_image']) ?>" alt="" class="blog-single-image reveal" loading="eager">
        <?php endif; ?>

        <div class="blog-single-body reveal">
            <?php if ($post['excerpt']): ?>
                <p style="font-size: 1.25rem; font-weight: 500; color: var(--color-black);"><?= e($post['excerpt']) ?></p>
            <?php endif; ?>
            <?= nl2br(e($post['content'])) ?>
        </div>

        <div class="reveal" style="margin-top: 48px; padding-top: 32px; border-top: 2px dashed var(--color-border);">
            <a href="<?= SITE_URL ?>/blog.php" class="btn btn-secondary">← Back to journal</a>
        </div>
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>
