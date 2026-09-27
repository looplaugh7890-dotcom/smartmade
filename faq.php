<?php

require_once __DIR__ . '/includes/functions.php';

$stmt = $pdo->prepare("
    SELECT * FROM faq_items
    WHERE status = 'published'
    ORDER BY sort_order ASC, created_at ASC
");
$stmt->execute();
$faqs = $stmt->fetchAll();

$pageTitle = 'FAQ';
$pageDescription = 'Frequently asked questions about SmartMade custom embroidery — turnaround, pricing, designs, shipping, and more.';
$bodyClass = 'faq-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="faq-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Questions</span>
        <h1 class="page-title reveal" id="faq-title">FAQ</h1>
        <p class="page-intro reveal">The questions we get asked most often, answered honestly. Can't find what you're after? Just ask.</p>
    </div>
</section>

<section class="section" aria-label="Frequently asked questions">
    <div class="container">
        <?php if (empty($faqs)): ?>
            <div class="empty-state reveal">
                <p>No FAQs added yet.</p>
            </div>
        <?php else: ?>
            <div class="faq-list reveal">
                <?php foreach ($faqs as $faq): ?>
                    <div class="faq-item">
                        <button class="faq-question" aria-expanded="false">
                            <?= e($faq['question']) ?>
                            <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        </button>
                        <div class="faq-answer">
                            <p><?= nl2br(e($faq['answer'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="reveal" style="text-align: center; margin-top: 64px;">
            <p style="color: var(--color-text-muted); margin-bottom: 20px;">Still have a question?</p>
            <a href="<?= SITE_URL ?>/contact.php" class="btn btn-primary">Get in touch</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
