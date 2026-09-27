<?php

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Shipping & Returns';
$pageDescription = 'SmartMade Embroidery shipping, delivery, and returns policy for custom embroidery orders.';
$bodyClass = 'legal-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="shipping-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Legal</span>
        <h1 class="page-title reveal" id="shipping-title">Shipping &amp; Returns</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="content-block" style="margin: 0 auto;">
            <h2 class="reveal">Delivery options</h2>
            <p class="reveal">We offer local collection from Fife, Scotland, and tracked UK postage. International shipping may be possible on request — just ask when you get your quote.</p>

            <h2 class="reveal">Shipping costs</h2>
            <p class="reveal">Postage costs are calculated based on size, weight, and destination. We'll confirm the exact cost in your quote before you confirm the order.</p>

            <h2 class="reveal">Turnaround</h2>
            <p class="reveal">Most one-off original pieces take 7–14 days from design approval. Business and bulk orders depend on complexity and quantity — we'll give you a clear estimate with your quote.</p>

            <h2 class="reveal">Returns &amp; refunds</h2>
            <p class="reveal">Because each piece is made to order, we cannot accept returns for change of mind. If your item arrives damaged or materially different from the approved proof, please contact us within 7 days with photos and we'll make it right.</p>

            <h2 class="reveal">Lost post</h2>
            <p class="reveal">If your tracked parcel goes missing, we'll work with the courier to resolve it. Untracked post is at the customer's risk.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
