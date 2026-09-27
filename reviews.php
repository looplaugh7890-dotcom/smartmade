<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';

$errors = [];
$submitted = false;

global $pdo;

if (setting('enable_reviews', '1') !== '1') {
    $pageTitle = 'Reviews';
    $pageDescription = 'Customer reviews.';
    include __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty-state"><h1>Reviews are currently closed</h1></div></div></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $name = trim($_POST['author_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $productId = (int)($_POST['product_id'] ?? 0);
        $website = trim($_POST['website'] ?? ''); // honeypot

        if (mb_strlen($name) < 2) {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($content) < 10) {
            $errors[] = 'Please write at least a sentence or two.';
        }
        if ($productId) {
            $check = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
            $check->execute([$productId]);
            if (!$check->fetch()) {
                $productId = 0;
            }
        }

        if ($website !== '') {
            // honeypot tripped — silently accept
            $submitted = true;
            $errors = [];
        } elseif (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, customer_id, author_name, email, rating, title, content, status, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $productId ?: null,
                customer_id(),
                $name,
                $email,
                $rating,
                $title ?: null,
                $content,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            activity_log('review_submitted', 'review', (int)$pdo->lastInsertId(), $name);
            $submitted = true;
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$total = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT r.*, p.name AS product_name, p.slug AS product_slug
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    WHERE r.status = 'approved'
    ORDER BY r.is_featured DESC, r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$reviews = $stmt->fetchAll();

$avg = (float)$pdo->query("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved'")->fetchColumn();

$reviewProducts = $pdo->query("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$pageTitle = 'Customer Reviews';
$pageDescription = 'What customers say about SmartMade embroidery, printing and promotional work.';
$canonicalPath = 'reviews';
$bodyClass = 'reviews-page';

$jsonLd = [org_jsonld(), [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'SmartMade Customer Reviews',
    'description' => $pageDescription,
    'url' => canonical_url('reviews'),
    'aggregateRating' => $total > 0 ? [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($avg, 1, '.', ''),
        'reviewCount' => $total,
        'bestRating' => 5,
    ] : null,
]];
$jsonLd[] = array_filter($jsonLd[1]);
$jsonLd = array_values(array_filter($jsonLd));

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="reviews-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Kind words</span>
        <h1 class="page-title reveal" id="reviews-title">Customer Reviews</h1>
        <p class="page-intro reveal">
            <?php if ($total > 0): ?>
                Rated <strong><?= number_format($avg, 1) ?> / 5</strong> across <?= (int)$total ?> review<?= $total === 1 ? '' : 's' ?>.
            <?php else: ?>
                Be the first to tell us how we did.
            <?php endif; ?>
        </p>
    </div>
</section>

<?= breadcrumb_html([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Reviews', 'url' => canonical_url('reviews')],
]) ?>

<section class="section reviews-section">
    <div class="container">
        <div class="reviews-layout">
            <div class="reviews-list-col">
                <?php if ($submitted): ?>
                    <div class="alert alert-success">
                        Thanks! Your review has been sent for approval and will appear here shortly.
                    </div>
                <?php endif; ?>

                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <h2>No reviews yet</h2>
                        <p>If we have worked with you, we would love to hear how it went.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="review-card<?= $review['is_featured'] ? ' is-featured' : '' ?>">
                                <div class="review-card-head">
                                    <span class="star-rating" aria-label="<?= (int)$review['rating'] ?> out of 5"><?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                                    <strong><?= e($review['author_name']) ?></strong>
                                    <time datetime="<?= e($review['created_at']) ?>"><?= e(format_date($review['created_at'])) ?></time>
                                </div>
                                <?php if ($review['title']): ?><h2 class="review-title"><?= e($review['title']) ?></h2><?php endif; ?>
                                <p><?= nl2br(e($review['content'])) ?></p>
                                <?php if ($review['product_name']): ?>
                                    <p class="review-product">On: <a href="<?= canonical_url('product/' . $review['product_slug']) ?>"><?= e($review['product_name']) ?></a></p>
                                <?php endif; ?>
                                <?php if ($review['admin_reply']): ?>
                                    <div class="review-reply"><strong>SmartMade replied:</strong> <?= nl2br(e($review['admin_reply'])) ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($pagination['totalPages'] > 1): ?>
                        <nav class="pagination" aria-label="Review pages">
                            <?php if ($pagination['hasPrev']): ?>
                                <a href="?page=<?= $pagination['page'] - 1 ?>">← Previous</a>
                            <?php endif; ?>
                            <span class="page-current">Page <?= $pagination['page'] ?> of <?= $pagination['totalPages'] ?></span>
                            <?php if ($pagination['hasNext']): ?>
                                <a href="?page=<?= $pagination['page'] + 1 ?>">Next →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="review-form-col">
                <div class="form-card reveal">
                    <h2 style="margin-top:0;">Write a review</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul style="margin:0 0 0 18px;padding:0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="review-form">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="visually-hidden" aria-hidden="true">
                        </div>

                        <div class="form-group">
                            <span class="form-label">Your rating *</span>
                            <div class="star-input" role="radiogroup" aria-label="Rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                                    <label for="star-<?= $i ?>" title="<?= $i ?> stars">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="rev-name">Your name *</label>
                            <input type="text" id="rev-name" name="author_name" class="form-input" required value="<?= e($_POST['author_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rev-email">Email <span>(never shown publicly)</span></label>
                            <input type="email" id="rev-email" name="email" class="form-input" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rev-product">Which product? <span>(optional)</span></label>
                            <select id="rev-product" name="product_id" class="form-select">
                                <option value="">General feedback</option>
                                <?php foreach ($reviewProducts as $rp): ?>
                                    <option value="<?= (int)$rp['id'] ?>" <?= (($_POST['product_id'] ?? '') == $rp['id']) ? 'selected' : '' ?>><?= e($rp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rev-title">Headline <span>(optional)</span></label>
                            <input type="text" id="rev-title" name="title" class="form-input" value="<?= e($_POST['title'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rev-content">Your review *</label>
                            <textarea id="rev-content" name="content" class="form-textarea" rows="5" required><?= e($_POST['content'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Submit review</button>
                        <p class="form-hint">Reviews are checked before they go live — usually within a day.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
