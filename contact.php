<?php

require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || mb_strlen($name) < 2) {
            $errors[] = 'Please enter your name.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($message) || mb_strlen($message) < 10) {
            $errors[] = 'Please enter a message of at least 10 characters.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);

            $notifySubject = 'New SmartMade contact message from ' . $name;
            $body = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message";
            send_notification(ADMIN_EMAIL, $notifySubject, $body);

            $success = true;
        }
    }
}

$pageTitle = 'Contact';
$pageDescription = 'Get in touch with SmartMade Embroidery in Scotland. Custom quotes, general enquiries, and studio visits by appointment.';
$bodyClass = 'contact-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="contact-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Say hello</span>
        <h1 class="page-title reveal" id="contact-title">Contact</h1>
        <p class="page-intro reveal">Questions, ideas, or just fancy a blether about embroidery? Drop us a message.</p>
    </div>
</section>

<section class="section form-section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 64px; align-items: start;">
            <div class="reveal">
                <?php if ($success): ?>
                    <div class="form-card" style="text-align: center; padding: 64px 40px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: var(--color-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-family: var(--font-display); font-size: 2rem; font-weight: 900; border: 3px dashed var(--color-black);">✓</div>
                        <h2 style="font-family: var(--font-display); font-size: 2rem; margin-bottom: 16px;">Message sent!</h2>
                        <p style="color: var(--color-text-muted);">We'll get back to you as soon as possible.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Please fix the following:</strong>
                            <ul style="margin: 8px 0 0 20px; padding: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?= SITE_URL ?>/contact.php" method="post" class="form-card" data-validate>
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" id="name" name="name" class="form-input" required value="<?= e($_POST['name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" id="email" name="email" class="form-input" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="form-group form-group-full">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" id="subject" name="subject" class="form-input" value="<?= e($_POST['subject'] ?? '') ?>">
                            </div>
                            <div class="form-group form-group-full">
                                <label for="message" class="form-label">Message *</label>
                                <textarea id="message" name="message" class="form-textarea" required placeholder="What's on your mind?"><?= e($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group form-group-full form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <aside class="reveal" style="transition-delay: 0.15s;">
                <div style="background: var(--color-black); color: var(--color-cream); padding: 36px; border-radius: var(--radius-lg); margin-bottom: 24px;">
                    <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin-bottom: 20px;">Direct details</h2>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php if (setting('contact_email')): ?>
                            <li style="margin-bottom: 14px;">
                                <strong style="display: block; color: var(--color-gold); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Email</strong>
                                <a href="mailto:<?= e(setting('contact_email')) ?>" style="color: var(--color-cream);"><?= e(setting('contact_email')) ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if (setting('contact_phone')): ?>
                            <li style="margin-bottom: 14px;">
                                <strong style="display: block; color: var(--color-gold); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Phone</strong>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', setting('contact_phone'))) ?>" style="color: var(--color-cream);"><?= e(setting('contact_phone')) ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if (setting('studio_address')): ?>
                            <li style="margin-bottom: 14px;">
                                <strong style="display: block; color: var(--color-gold); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Studio</strong>
                                <span style="color: var(--color-cream);"><?= nl2br(e(setting('studio_address'))) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if (setting('business_hours')): ?>
                            <li>
                                <strong style="display: block; color: var(--color-gold); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Hours</strong>
                                <span style="color: var(--color-cream);"><?= e(setting('business_hours')) ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (setting('instagram_url')): ?>
                    <a href="<?= e(setting('instagram_url')) ?>" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 12px; background: var(--color-white); padding: 20px 24px; border-radius: var(--radius-lg); border: 2px dashed var(--color-border); color: var(--color-black); font-weight: 600;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        Follow us on Instagram
                    </a>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php if (setting('google_maps_embed')): ?>
    <section class="section" style="padding-top: 0;">
        <div class="container">
            <iframe src="<?= e(setting('google_maps_embed')) ?>" width="100%" height="400" style="border:0; border-radius: var(--radius-lg); filter: grayscale(100%) contrast(1.1);" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Studio location map"></iframe>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
