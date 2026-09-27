<?php

require_once __DIR__ . '/../includes/admin_header.php';

$quoteCount = (int) $pdo->query("SELECT COUNT(*) FROM quote_requests WHERE status != 'archived'")->fetchColumn();
$newQuoteCount = (int) $pdo->query("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'")->fetchColumn();
$messageCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$portfolioCount = (int) $pdo->query("SELECT COUNT(*) FROM portfolio_items WHERE status = 'published'")->fetchColumn();
$testimonialCount = (int) $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'published'")->fetchColumn();
$blogCount = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();

$recentQuotes = $pdo->query("
    SELECT id, name, order_type, status, submitted_at
    FROM quote_requests
    ORDER BY submitted_at DESC
    LIMIT 5
")->fetchAll();

$recentMessages = $pdo->query("
    SELECT id, name, subject, is_read, created_at
    FROM contact_messages
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Dashboard</h1>
    <a href="<?= SITE_URL ?>/admin/portfolio_add.php" class="admin-btn admin-btn-primary">+ Add Portfolio Item</a>
</div>

<div class="admin-stats">
    <div class="admin-stat-card gold reveal">
        <div class="admin-stat-value"><?= $newQuoteCount ?></div>
        <div class="admin-stat-label">New Quote Requests</div>
    </div>
    <div class="admin-stat-card reveal">
        <div class="admin-stat-value"><?= $messageCount ?></div>
        <div class="admin-stat-label">Unread Messages</div>
    </div>
    <div class="admin-stat-card reveal">
        <div class="admin-stat-value"><?= $portfolioCount ?></div>
        <div class="admin-stat-label">Published Portfolio</div>
    </div>
    <div class="admin-stat-card reveal">
        <div class="admin-stat-value"><?= $quoteCount ?></div>
        <div class="admin-stat-label">Total Active Quotes</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;" class="dashboard-grid">
    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Recent Quote Requests</h2>
            <a href="<?= SITE_URL ?>/admin/quotes.php" class="admin-btn admin-btn-secondary admin-btn-sm">View all</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Type</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recentQuotes)): ?>
                        <tr><td colspan="4" class="admin-empty">No quote requests yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentQuotes as $q): ?>
                            <tr>
                                <td><a href="<?= SITE_URL ?>/admin/quote_view.php?id=<?= (int)$q['id'] ?>"><?= e($q['name']) ?></a></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $q['order_type']))) ?></td>
                                <td><span class="badge badge-<?= e(str_replace(['new','in_progress','completed','archived'], ['new','progress','completed','archived'], $q['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $q['status']))) ?></span></td>
                                <td><?= format_date($q['submitted_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card reveal">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Recent Messages</h2>
            <a href="<?= SITE_URL ?>/admin/messages.php" class="admin-btn admin-btn-secondary admin-btn-sm">View all</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Subject</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMessages)): ?>
                        <tr><td colspan="3" class="admin-empty">No messages yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentMessages as $m): ?>
                            <tr>
                                <td>
                                    <a href="<?= SITE_URL ?>/admin/message_view.php?id=<?= (int)$m['id'] ?>">
                                        <?= e($m['name']) ?>
                                        <?php if (!$m['is_read']): ?><span class="badge badge-new">New</span><?php endif; ?>
                                    </a>
                                </td>
                                <td><?= e($m['subject'] ?: '(No subject)') ?></td>
                                <td><?= format_date($m['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
