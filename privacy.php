<?php

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Privacy Policy';
$pageDescription = 'SmartMade Embroidery privacy policy. How we collect, use, and protect your personal information.';
$bodyClass = 'legal-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="privacy-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Legal</span>
        <h1 class="page-title reveal" id="privacy-title">Privacy Policy</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="content-block" style="margin: 0 auto;">
            <p class="reveal"><strong>Last updated:</strong> <?= date('F Y') ?></p>

            <h2 class="reveal">1. What information we collect</h2>
            <p class="reveal">When you submit a quote request or contact form, we collect your name, email address, phone number (if provided), and the details of your enquiry. If you upload a reference image, we store that securely too.</p>

            <h2 class="reveal">2. How we use your information</h2>
            <p class="reveal">We use your information to respond to your enquiry, provide a quote, complete your order, and keep you updated on progress. We do not sell your data to third parties.</p>

            <h2 class="reveal">3. How we store your data</h2>
            <p class="reveal">Your data is stored on our secure web hosting environment. Reference images are kept in a non-public uploads directory. We only keep your information for as long as necessary to fulfil your request or comply with legal obligations.</p>

            <h2 class="reveal">4. Your rights</h2>
            <p class="reveal">You have the right to ask what data we hold about you, to request corrections, and to request deletion. Just email us at <?= setting('contact_email') ? '<a href="mailto:' . e(setting('contact_email')) . '">' . e(setting('contact_email')) . '</a>' : 'our contact email' ?>.</p>

            <h2 class="reveal">5. Cookies</h2>
            <p class="reveal">This site uses a session cookie to keep you logged in if you use the admin panel, and to manage security tokens on forms. No tracking cookies are used on the public site.</p>

            <h2 class="reveal">6. Contact</h2>
            <p class="reveal">If you have any questions about this privacy policy, please contact us through the <a href="<?= SITE_URL ?>/contact.php">contact page</a>.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
