<?php

$ig = setting('instagram_url');
$fb = setting('facebook_url');
$tt = setting('tiktok_url');
$currentYear = date('Y');
?>
    </main>

    <footer class="site-footer">
        <div class="footer-cta">
            <div class="container">
                <div class="footer-cta-inner">
                    <div class="footer-cta-text">
                        <h2 class="footer-cta-title">Ready for stitches you haven't seen before?</h2>
                        <p class="footer-cta-subtitle">Tell us your idea and we'll turn it into thread.</p>
                    </div>
                    <a href="<?= SITE_URL ?>/quote.php" class="btn btn-light btn-lg">Start Your Design</a>
                </div>
            </div>
        </div>

        <div class="footer-main">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-brand">
                        <a href="<?= SITE_URL ?>/" class="footer-logo">
                            <span class="logo-mark" aria-hidden="true">SM</span>
                            <span class="logo-text">SmartMade</span>
                        </a>
                        <p class="footer-tagline">Original custom embroidery from Scotland. Football tributes, business logos, and one-off pieces stitched with personality.</p>
                        <div class="footer-social">
                            <?php if ($ig): ?>
                                <a href="<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram" class="social-link">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                                </a>
                            <?php endif; ?>
                            <?php if ($fb): ?>
                                <a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" class="social-link">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                                </a>
                            <?php endif; ?>
                            <?php if ($tt): ?>
                                <a href="<?= e($tt) ?>" target="_blank" rel="noopener" aria-label="TikTok" class="social-link">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="footer-nav-group">
                        <h3 class="footer-nav-title">Explore</h3>
                        <ul class="footer-nav-list">
                            <li><a href="<?= SITE_URL ?>/portfolio.php">Portfolio</a></li>
                            <li><a href="<?= SITE_URL ?>/services.php">Services</a></li>
                            <li><a href="<?= SITE_URL ?>/about.php">About</a></li>
                            <li><a href="<?= SITE_URL ?>/blog.php">Journal</a></li>
                            <li><a href="<?= SITE_URL ?>/testimonials.php">Reviews</a></li>
                        </ul>
                    </div>

                    <div class="footer-nav-group">
                        <h3 class="footer-nav-title">Support</h3>
                        <ul class="footer-nav-list">
                            <li><a href="<?= SITE_URL ?>/quote.php">Get a Quote</a></li>
                            <li><a href="<?= SITE_URL ?>/contact.php">Contact</a></li>
                            <li><a href="<?= SITE_URL ?>/faq.php">FAQ</a></li>
                            <li><a href="<?= SITE_URL ?>/shipping.php">Shipping &amp; Returns</a></li>
                        </ul>
                    </div>

                    <div class="footer-nav-group">
                        <h3 class="footer-nav-title">Get in Touch</h3>
                        <ul class="footer-contact-list">
                            <?php if (setting('contact_email')): ?>
                                <li><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
                            <?php endif; ?>
                            <?php if (setting('contact_phone')): ?>
                                <li><a href="tel:<?= e(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li>
                            <?php endif; ?>
                            <?php if (setting('studio_address')): ?>
                                <li><?= nl2br(e(setting('studio_address'))) ?></li>
                            <?php endif; ?>
                            <?php if (setting('business_hours')): ?>
                                <li class="footer-hours"><?= e(setting('business_hours')) ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="footer-newsletter">
                    <form action="<?= SITE_URL ?>/newsletter.php" method="post" class="footer-newsletter-form">
                        <?= csrf_field() ?>
                        <label for="newsletter-email" class="footer-newsletter-label">Get new drops &amp; studio stories</label>
                        <div class="footer-newsletter-row">
                            <input type="email" id="newsletter-email" name="email" class="form-input" placeholder="you@example.com" required>
                            <button type="submit" class="btn btn-primary">Join</button>
                        </div>
                        <?php if (has_flash('success')): ?>
                            <p class="footer-newsletter-msg" style="color: var(--color-gold-light);"><?= e(get_flash('success')) ?></p>
                        <?php endif; ?>
                        <?php if (has_flash('error')): ?>
                            <p class="footer-newsletter-msg" style="color: #F0A0A0;"><?= e(get_flash('error')) ?></p>
                        <?php endif; ?>
                        <?php if (has_flash('info')): ?>
                            <p class="footer-newsletter-msg" style="color: rgba(245,242,236,0.7);"><?= e(get_flash('info')) ?></p>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="footer-legal">
                    <p class="copyright">&copy; <?= $currentYear ?> SmartMade Embroidery. All rights reserved.</p>
                    <ul class="legal-links">
                        <li><a href="<?= SITE_URL ?>/privacy.php">Privacy Policy</a></li>
                        <li><a href="<?= SITE_URL ?>/terms.php">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= SITE_URL ?>/assets/js/main.js" defer></script>
    <script src="<?= SITE_URL ?>/assets/js/store.js" defer></script>
</body>
</html>