<?php

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Terms & Conditions';
$pageDescription = 'SmartMade Embroidery terms and conditions for custom embroidery services.';
$bodyClass = 'legal-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="terms-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Legal</span>
        <h1 class="page-title reveal" id="terms-title">Terms &amp; Conditions</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="content-block" style="margin: 0 auto;">
            <p class="reveal"><strong>Last updated:</strong> <?= date('F Y') ?></p>

            <h2 class="reveal">1. Quotes &amp; Orders</h2>
            <p class="reveal">All quotes are valid for 14 days unless otherwise stated. A quote becomes an order once both the design and price are confirmed by the customer, and any deposit requested has been paid.</p>

            <h2 class="reveal">2. Designs &amp; Copyright</h2>
            <p class="reveal">By submitting an image or idea, you confirm that you have the right to use it. We retain the right to photograph completed work for portfolio and promotional use unless you explicitly request otherwise.</p>

            <h2 class="reveal">3. Proofs &amp; Revisions</h2>
            <p class="reveal">We provide a digital proof before stitching. Minor revisions are included. Major changes after approval may incur additional charges.</p>

            <h2 class="reveal">4. Payment</h2>
            <p class="reveal">Payment terms will be confirmed with your quote. For larger orders, a deposit may be required before work begins.</p>

            <h2 class="reveal">5. Turnaround</h2>
            <p class="reveal">Turnaround times are estimates and begin after design approval and payment (where applicable). Delays caused by supply chain issues or customer approvals will extend the timeline.</p>

            <h2 class="reveal">6. Limitation of Liability</h2>
            <p class="reveal">SmartMade is not liable for any indirect or consequential losses. Our total liability is limited to the value of the order.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
