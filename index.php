<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/store.php';

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM portfolio_items p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'published' AND p.is_featured = 1
    ORDER BY p.display_order ASC, p.created_at DESC
    LIMIT 6
");
$stmt->execute();
$featuredItems = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM testimonials
    WHERE status = 'published' AND is_featured = 1
    ORDER BY display_order ASC, created_at DESC
    LIMIT 3
");
$stmt->execute();
$testimonials = $stmt->fetchAll();

$featuredProducts = fetch_products(['featured' => true, 'per_page' => 4, 'sort' => 'popular'])['rows'];

$stmt = $pdo->prepare("
    SELECT r.*, p.name AS product_name, p.slug AS product_slug
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    WHERE r.status = 'approved'
    ORDER BY r.is_featured DESC, r.created_at DESC
    LIMIT 3
");
$stmt->execute();
$publicReviews = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM blog_posts
    WHERE status = 'published' AND published_at <= NOW()
    ORDER BY published_at DESC
    LIMIT 3
");
$stmt->execute();
$blogPosts = $stmt->fetchAll();

$pageTitle = 'Embroidery people haven\'t seen before';
$pageDescription = setting('meta_description');
$bodyClass = 'home-page';

include __DIR__ . '/includes/header.php';
?>

<section class="hero" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="container hero-inner">
        <div class="hero-content">
            <span class="hero-badge reveal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><path d="M2 12h20"></path></svg>
                Custom embroidery from Scotland
            </span>
            <h1 class="hero-title" id="hero-title">
                <span class="hero-title-line reveal">Embroidery</span>
                <span class="hero-title-line reveal" style="transition-delay: 0.1s;">people haven't</span>
                <span class="hero-title-line hero-title-accent reveal" style="transition-delay: 0.2s;">
                    seen before
                    <svg class="hero-stitch-line" viewBox="0 0 400 12" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M2,8 C80,2 120,14 200,6 S320,-2 398,8"></path>
                    </svg>
                </span>
            </h1>
            <p class="hero-text reveal" style="transition-delay: 0.3s;">
                Football tributes, business logos, and one-off original pieces — stitched with personality, humour, and a wee bit of Scottish swagger.
            </p>
            <div class="hero-actions reveal" style="transition-delay: 0.4s;">
                <a href="<?= SITE_URL ?>/quote.php" class="btn btn-primary btn-lg">Start Your Design</a>
                <a href="<?= SITE_URL ?>/portfolio.php" class="btn btn-secondary btn-lg">View Portfolio</a>
            </div>
        </div>
        <div class="hero-visual reveal" style="transition-delay: 0.3s;">
            <div class="hero-image-wrap">
                <img src="<?= SITE_URL ?>/assets/images/heroimage.png" alt="Hand-stitched embroidered piece by SmartMade" class="hero-image" width="1536" height="1024" loading="eager" decoding="async">
                <div class="hero-image-frame" aria-hidden="true"></div>
            </div>
            <div class="hero-float-card">
                <strong>100% original</strong>
                <span>Hand-digitised and machine-stitched in Scotland</span>
            </div>
        </div>
    </div>
</section>

<section class="section pillars-section" aria-labelledby="pillars-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">What we do</span>
            <h2 class="section-title reveal" id="pillars-title">Three threads, endless possibilities</h2>
            <p class="section-subtitle reveal">Whether it's a player portrait, a start-up logo, or a daft in-joke for your best mate — if it can be drawn, it can be stitched.</p>
        </div>
        <div class="pillars-grid">
            <article class="pillar-card reveal">
                <svg class="pillar-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="32" cy="32" r="28"></circle>
                    <path d="M32 8v48M8 32h48"></path>
                    <path d="M16 16l32 32M16 48l32-32" opacity="0.4"></path>
                </svg>
                <h3 class="pillar-title">Football</h3>
                <p class="pillar-text">Club badges, player caricatures, Scotland colours, and matchday memes stitched for fans who get it.</p>
                <a href="<?= category_url('football') ?>" class="pillar-link">See football work →</a>
            </article>
            <article class="pillar-card reveal" style="transition-delay: 0.1s;">
                <svg class="pillar-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="8" y="16" width="48" height="32" rx="4"></rect>
                    <path d="M20 28h24M20 36h16"></path>
                </svg>
                <h3 class="pillar-title">Business</h3>
                <p class="pillar-text">Crisp, professional logo digitising and embroidery on apparel, workwear, and branded merchandise.</p>
                <a href="<?= category_url('business') ?>" class="pillar-link">See business work →</a>
            </article>
            <article class="pillar-card reveal" style="transition-delay: 0.2s;">
                <svg class="pillar-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 52l8-24 12 16 8-24 12 16"></path>
                    <circle cx="32" cy="12" r="6"></circle>
                </svg>
                <h3 class="pillar-title">Originals</h3>
                <p class="pillar-text">Portraits, caricatures, gifts, and ideas so weirdly brilliant we can't wait to stitch them.</p>
                <a href="<?= category_url('originals') ?>" class="pillar-link">See original work →</a>
            </article>
        </div>
    </div>
</section>

<section class="section portfolio-section" aria-labelledby="featured-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">Recent work</span>
            <h2 class="section-title reveal" id="featured-title">Fresh off the hoop</h2>
            <p class="section-subtitle reveal">A selection of recent pieces. Click through for the story behind each stitch.</p>
        </div>

        <?php if (empty($featuredItems)): ?>
            <div class="empty-state reveal">
                <p>Portfolio pieces are being stitched right now — check back soon.</p>
            </div>
        <?php else: ?>
            <div class="portfolio-grid">
                <?php foreach ($featuredItems as $item): ?>
                    <article class="portfolio-item reveal">
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
            </div>
            <div class="text-center" style="margin-top: 48px; text-align: center;">
                <a href="<?= SITE_URL ?>/portfolio.php" class="btn btn-secondary btn-lg reveal">View Full Portfolio</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($featuredProducts)): ?>
<section class="section shop-section" aria-labelledby="shop-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">Ready to stitch</span>
            <h2 class="section-title reveal" id="shop-title">Shop the collection</h2>
            <p class="section-subtitle reveal">Custom embroidery you can order straight from the site — pick your size, add your text, we'll stitch it.</p>
        </div>

        <div class="product-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <?= product_card_html($product) ?>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 48px;">
            <a href="<?= SITE_URL ?>/shop.php" class="btn btn-primary btn-lg reveal">Visit the Shop</a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section process-section" aria-labelledby="process-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal" style="color: var(--color-gold-light);">How it works</span>
            <h2 class="section-title reveal" id="process-title" style="color: var(--color-cream);">From idea to stitched finish</h2>
            <p class="section-subtitle reveal" style="color: rgba(245, 242, 236, 0.7);">No mystery, no mass production — just a clear process and a lot of care.</p>
        </div>
        <div class="process-steps">
            <div class="process-step reveal">
                <h3 class="process-step-title">Send your idea</h3>
                <p class="process-step-text">Fill in the quote form with your image, logo, or half-baked concept. Rough sketches are welcome.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.1s;">
                <h3 class="process-step-title">Digitise &amp; proof</h3>
                <p class="process-step-text">We convert your design into stitches, send a digital proof, and tweak until you're happy.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.2s;">
                <h3 class="process-step-title">Stitch it</h3>
                <p class="process-step-text">The machine gets to work. We share behind-the-scenes clips if you want to see the magic happen.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.3s;">
                <h3 class="process-step-title">Delivery</h3>
                <p class="process-step-text">Collect from the studio or we post it out. Then you get to show it off.</p>
            </div>
        </div>
    </div>
</section>

<section class="section testimonials-section" aria-labelledby="reviews-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">Kind words</span>
            <h2 class="section-title reveal" id="reviews-title">Stitched with love, reviewed with enthusiasm</h2>
        </div>
        <?php if (empty($testimonials) && empty($publicReviews)): ?>
            <div class="empty-state reveal">
                <p>No reviews yet — be the first to get a piece stitched.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($publicReviews)): ?>
                <div class="product-grid" style="margin-bottom: 32px;">
                    <?php foreach ($publicReviews as $r): ?>
                        <article class="review-card reveal">
                            <div class="review-card-head">
                                <?= star_rating((int)$r['rating']) ?>
                                <strong><?= e($r['author_name']) ?></strong>
                                <time datetime="<?= e($r['created_at']) ?>"><?= format_date($r['created_at']) ?></time>
                            </div>
                            <?php if ($r['title']): ?><h3 class="review-title"><?= e($r['title']) ?></h3><?php endif; ?>
                            <p><?= e(excerpt($r['content'], 240)) ?></p>
                            <?php if ($r['product_name']): ?>
                                <p class="review-product">On <a href="<?= SITE_URL ?>/product/<?= e($r['product_slug']) ?>" style="color: var(--color-gold-dark);"><?= e($r['product_name']) ?></a></p>
                            <?php endif; ?>
                            <?php if ($r['admin_reply']): ?>
                                <div class="review-reply"><strong>Our reply:</strong> <?= e(excerpt($r['admin_reply'], 180)) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($testimonials)): ?>
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
            <?php endif; ?>

            <div style="text-align: center; margin-top: 48px;">
                <a href="<?= SITE_URL ?>/reviews.php" class="btn btn-secondary btn-lg reveal">Read All Reviews</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section instagram-section" aria-labelledby="instagram-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">On the 'Gram</span>
            <h2 class="section-title reveal" id="instagram-title">Follow the stitches</h2>
            <p class="section-subtitle reveal">Behind-the-scenes reels, work-in-progress shots, and the occasional daft caption. <?= setting('instagram_url') ? '<a href="' . e(setting('instagram_url')) . '" target="_blank" rel="noopener" style="color: var(--color-gold-dark); text-decoration: underline;">@smartmade</a>' : '' ?></p>
        </div>
        <div class="instagram-grid">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <a href="<?= e(setting('instagram_url', '#')) ?>" target="_blank" rel="noopener" class="instagram-item reveal" aria-label="Instagram post <?= $i ?>">
                    <img src="<?= SITE_URL ?>/assets/images/insta-<?= $i ?>.svg" alt="Instagram post preview <?= $i ?>" loading="lazy">
                    <span class="instagram-overlay">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </span>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section class="section journal-section" aria-labelledby="journal-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal">Behind the stitches</span>
            <h2 class="section-title reveal" id="journal-title">From the journal</h2>
            <p class="section-subtitle reveal">Process notes, new drops, and the stories behind the pieces.</p>
        </div>
        <?php if (empty($blogPosts)): ?>
            <div class="empty-state reveal">
                <p>Journal posts coming soon.</p>
            </div>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($blogPosts as $post): ?>
                    <article class="blog-card reveal">
                        <a href="<?= blog_url((int)$post['id'], $post['slug']) ?>">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= SITE_URL . '/' . e($post['featured_image']) ?>" alt="" class="blog-thumb" loading="lazy">
                            <?php else: ?>
                                <div class="blog-thumb" style="background: var(--color-black); display: flex; align-items: center; justify-content: center; color: var(--color-gold); font-family: var(--font-display); font-size: 2rem; font-weight: 900;">SM</div>
                            <?php endif; ?>
                        </a>
                        <div class="blog-content">
                            <div class="blog-date"><?= format_date($post['published_at'] ?? $post['created_at']) ?></div>
                            <h3 class="blog-title"><a href="<?= blog_url((int)$post['id'], $post['slug']) ?>"><?= e($post['title']) ?></a></h3>
                            <p class="blog-excerpt"><?= e(excerpt($post['excerpt'] ?? $post['content'], 130)) ?></p>
                            <a href="<?= blog_url((int)$post['id'], $post['slug']) ?>" class="pillar-link">Read more →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
