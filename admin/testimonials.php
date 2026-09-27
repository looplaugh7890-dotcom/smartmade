<?php

require_once __DIR__ . '/../includes/admin_header.php';

$stmt = $pdo->query("
    SELECT * FROM testimonials
    ORDER BY is_featured DESC, display_order ASC, created_at DESC
");
$testimonials = $stmt->fetchAll();

$pageTitle = 'Testimonials';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Testimonials</h1>
    <a href="<?= SITE_URL ?>/admin/testimonial_add.php" class="admin-btn admin-btn-primary">+ Add Testimonial</a>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Author</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($testimonials)): ?>
                    <tr><td colspan="6" class="admin-empty">No testimonials yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td><strong><?= e($t['author_name']) ?></strong></td>
                            <td><?= star_rating((int)$t['rating']) ?></td>
                            <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                            <td><?= $t['is_featured'] ? 'Yes' : 'No' ?></td>
                            <td><?= (int)$t['display_order'] ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/testimonial_edit.php?id=<?= (int)$t['id'] ?>">Edit</a>
                                    <a href="<?= SITE_URL ?>/admin/testimonial_delete.php?id=<?= (int)$t['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="delete" data-confirm="Delete this testimonial?">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
