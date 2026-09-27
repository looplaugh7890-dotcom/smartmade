<?php

require_once __DIR__ . '/../includes/admin_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$total = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$pagination = paginate($total, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM blog_posts
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $pagination['offset']]);
$posts = $stmt->fetchAll();

$pageTitle = 'Journal Manager';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Journal</h1>
    <a href="<?= SITE_URL ?>/admin/blog_add.php" class="admin-btn admin-btn-primary">+ Add Post</a>
</div>

<div class="admin-card reveal">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="5" class="admin-empty">No posts yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><strong><?= e($post['title']) ?></strong></td>
                            <td><?= e($post['author_name']) ?></td>
                            <td><span class="badge badge-<?= e($post['status']) ?>"><?= e(ucfirst($post['status'])) ?></span></td>
                            <td><?= $post['published_at'] ? format_date($post['published_at']) : '—' ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= SITE_URL ?>/admin/blog_edit.php?id=<?= (int)$post['id'] ?>">Edit</a>
                                    <a href="<?= SITE_URL ?>/admin/blog_delete.php?id=<?= (int)$post['id'] ?>&csrf=<?= urlencode(csrf_token()) ?>" class="delete" data-confirm="Delete this post?">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['totalPages'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['hasPrev']): ?>
                <a href="?page=<?= $pagination['page'] - 1 ?>">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagination['hasNext']): ?>
                <a href="?page=<?= $pagination['page'] + 1 ?>">→</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
