<?php

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Services';
$pageDescription = 'Custom embroidery services: original art, business logo digitising & embroidery, and bulk team orders from SmartMade Scotland.';
$bodyClass = 'services-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="services-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">What we offer</span>
        <h1 class="page-title reveal" id="services-title">Services</h1>
        <p class="page-intro reveal">Three ways we turn your idea into stitches. No minimum order guilt, no corporate waffle — just honest craft.</p>
    </div>
</section>

<section class="section" aria-label="Our services">
    <div class="container">
        <div class="services-grid">
            <article class="service-card reveal">
                <div class="service-media">
                    <span class="service-placeholder">01</span>
                </div>
                <div class="service-content">
                    <span class="portfolio-category">For fans, gift-givers &amp; creatives</span>
                    <h2 class="service-title">Custom Original Embroidery</h2>
                    <p class="service-text">Portraits, caricatures, football tributes, in-jokes, pets, song lyrics — if it means something to you, we'll stitch it. Every piece is digitised by hand and stitched with the kind of attention your daft idea deserves.</p>
                    <div class="service-meta">
                        <span class="service-meta-item">From £35</span>
                        <span class="service-meta-item">7–14 days</span>
                        <span class="service-meta-item">One-off or small runs</span>
                    </div>
                    <a href="<?= SITE_URL ?>/quote.php?type=custom_original" class="btn btn-primary">Get a custom quote</a>
                </div>
            </article>

            <article class="service-card reveal">
                <div class="service-media">
                    <span class="service-placeholder">02</span>
                </div>
                <div class="service-content">
                    <span class="portfolio-category">For brands &amp; small businesses</span>
                    <h2 class="service-title">Business Logo Digitising &amp; Embroidery</h2>
                    <p class="service-text">Your logo, cleanly digitised and embroidered onto polos, hoodies, hats, aprons, tote bags — whatever represents your brand best. Perfect for start-ups, barbershops, cafés, trades, and creative studios.</p>
                    <div class="service-meta">
                        <span class="service-meta-item">Digitising from £25</span>
                        <span class="service-meta-item">Garment-dependent</span>
                        <span class="service-meta-item">Digital proof included</span>
                    </div>
                    <a href="<?= SITE_URL ?>/quote.php?type=business_logo" class="btn btn-primary">Get a logo quote</a>
                </div>
            </article>

            <article class="service-card reveal">
                <div class="service-media">
                    <span class="service-placeholder">03</span>
                </div>
                <div class="service-content">
                    <span class="portfolio-category">For teams, clubs &amp; events</span>
                    <h2 class="service-title">Team &amp; Bulk Orders</h2>
                    <p class="service-text">Football squads, stag dos, staff uniforms, fundraiser merch — we'll keep the look consistent and the price fair. Discounts apply for larger quantities, and we'll talk you through garment options.</p>
                    <div class="service-meta">
                        <span class="service-meta-item">Discounted bulk rates</span>
                        <span class="service-meta-item">Turnaround agreed upfront</span>
                        <span class="service-meta-item">Consistent quality</span>
                    </div>
                    <a href="<?= SITE_URL ?>/quote.php?type=team_bulk" class="btn btn-primary">Plan a bulk order</a>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section process-section" aria-labelledby="process-title">
    <div class="container">
        <div class="section-header">
            <span class="section-label reveal" style="color: var(--color-gold-light);">The process</span>
            <h2 class="section-title reveal" id="process-title" style="color: var(--color-cream);">How it works</h2>
            <p class="section-subtitle reveal" style="color: rgba(245, 242, 236, 0.7);">Straightforward steps from your brain to fabric.</p>
        </div>
        <div class="process-steps">
            <div class="process-step reveal">
                <h3 class="process-step-title">You send the idea</h3>
                <p class="process-step-text">Photo, sketch, logo file, or a paragraph of enthusiastic nonsense. All accepted.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.1s;">
                <h3 class="process-step-title">We digitise &amp; mock up</h3>
                <p class="process-step-text">Your design becomes stitch data. We send a proof so you know exactly what you're getting.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.2s;">
                <h3 class="process-step-title">Needle meets fabric</h3>
                <p class="process-step-text">Machine stitching, hoop wrangling, thread changes, and quality checks.</p>
            </div>
            <div class="process-step reveal" style="transition-delay: 0.3s;">
                <h3 class="process-step-title">You receive the goods</h3>
                <p class="process-step-text">Collection from Fife or tracked UK postage. Then it's brag time.</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
