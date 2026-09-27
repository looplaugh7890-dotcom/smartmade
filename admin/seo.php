<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $pageKey = slugify(trim($_POST['page_key'] ?? ''));
            $path = trim($_POST['path'] ?? '');
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDescription = trim($_POST['meta_description'] ?? '');
            $ogImage = trim($_POST['og_image'] ?? '');
            $canonical = trim($_POST['canonical'] ?? '');
            $robots = trim($_POST['robots'] ?? 'index,follow');

            if ($pageKey === '') $errors[] = 'Page key is required.';
            if ($path === '') $errors[] = 'Path is required.';
            if (!in_array($robots, ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], true)) {
                $robots = 'index,follow';
            }

            if (empty($errors)) {
                $upd = $pdo->prepare("
                    INSERT INTO page_seo (page_key, path, meta_title, meta_description, og_image, canonical, robots)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        path = VALUES(path),
                        meta_title = VALUES(meta_title),
                        meta_description = VALUES(meta_description),
                        og_image = VALUES(og_image),
                        canonical = VALUES(canonical),
                        robots = VALUES(robots)
                ");
                $upd->execute([
                    $pageKey, $path, $metaTitle ?: null, $metaDescription ?: null,
                    $ogImage ?: null, $canonical ?: null, $robots
                ]);

                activity_log('seo.save', 'page_seo', null, $pageKey);
                set_flash('success', 'SEO settings saved for “' . $pageKey . '”.');
                header('Location: ' . SITE_URL . '/admin/seo.php');
                exit;
            }
        } elseif ($action === 'delete') {
            $seoId = (int)($_POST['seo_id'] ?? 0);
            $pdo->prepare("DELETE FROM page_seo WHERE id = ?")->execute([$seoId]);
            activity_log('seo.delete', 'page_seo', $seoId);
            set_flash('success', 'SEO entry deleted.');
            header('Location: ' . SITE_URL . '/admin/seo.php');
            exit;
        }
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $sel = $pdo->prepare("SELECT * FROM page_seo WHERE page_key = ?");
    $sel->execute([$_GET['edit']]);
    $editing = $sel->fetch() ?: null;
}

$rows = $pdo->query("SELECT * FROM page_seo ORDER BY page_key ASC")->fetchAll();

$defaults = [
    ['home', '/', 'Home'],
    ['shop', '/shop', 'Shop'],
    ['cart', '/cart', 'Basket'],
    ['checkout', '/checkout', 'Checkout'],
    ['gallery', '/gallery', 'Gallery'],
    ['reviews', '/reviews', 'Reviews'],
    ['portfolio', '/portfolio', 'Portfolio'],
    ['services', '/services', 'Services'],
    ['about', '/about', 'About'],
    ['contact', '/contact', 'Contact'],
    ['faq', '/faq', 'FAQ'],
    ['quote', '/quote', 'Get a Quote'],
    ['blog', '/blog', 'Journal'],
    ['testimonials', '/testimonials', 'Testimonials'],
    ['shipping', '/shipping', 'Shipping & Returns'],
];

$pageTitle = 'SEO';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">SEO &amp; Meta</h1>
    <a href="<?= SITE_URL ?>/sitemap.xml" target="_blank" class="admin-btn admin-btn-secondary">View sitemap ↗</a>
</div>

<p class="admin-form-hint" style="margin-bottom:18px;">Overrides the default meta title/description for each page key. Pages without an entry fall back to the site defaults in Settings.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<div class="admin-card reveal" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title"><?= $editing ? 'Edit: ' . e($editing['page_key']) : 'Add / update entry' ?></h2>
        <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/seo.php" class="admin-btn admin-btn-secondary admin-btn-sm">Cancel edit</a><?php endif; ?>
    </div>
    <form action="" method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="admin-form-grid">
            <div class="admin-form-group">
                <label for="page_key">Page key *</label>
                <input type="text" id="page_key" name="page_key" value="<?= e($editing['page_key'] ?? '') ?>" placeholder="shop" required>
                <p class="admin-form-hint">e.g. home, shop, about — or a product key like product-my-item.</p>
            </div>
            <div class="admin-form-group">
                <label for="path">Path *</label>
                <input type="text" id="path" name="path" value="<?= e($editing['path'] ?? '') ?>" placeholder="/shop" required>
            </div>
            <div class="admin-form-group full">
                <label for="meta_title">Meta title</label>
                <input type="text" id="meta_title" name="meta_title" maxlength="60" value="<?= e($editing['meta_title'] ?? '') ?>">
            </div>
            <div class="admin-form-group full">
                <label for="meta_description">Meta description</label>
                <textarea id="meta_description" name="meta_description" rows="3" maxlength="160"><?= e($editing['meta_description'] ?? '') ?></textarea>
            </div>
            <div class="admin-form-group">
                <label for="og_image">Social share image</label>
                <input type="text" id="og_image" name="og_image" value="<?= e($editing['og_image'] ?? '') ?>" placeholder="uploads/...">
            </div>
            <div class="admin-form-group">
                <label for="canonical">Canonical URL</label>
                <input type="text" id="canonical" name="canonical" value="<?= e($editing['canonical'] ?? '') ?>" placeholder="leave blank for auto">
            </div>
            <div class="admin-form-group">
                <label for="robots">Robots</label>
                <select id="robots" name="robots">
                    <?php foreach (['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'] as $r): ?>
                        <option value="<?= $r ?>" <?= (($editing['robots'] ?? 'index,follow') === $r) ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">Save SEO</button>
        </div>
    </form>
</div>

<div class="admin-card reveal">
    <div class="admin-card-header"><h2 class="admin-card-title">Existing entries (<?= count($rows) ?>)</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Key</th><th>Path</th><th>Meta title</th><th>Description</th><th>Robots</th><th>Updated</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="admin-empty">No SEO overrides yet — the site uses default titles. Quick-start keys: <?= e(implode(', ', array_column($defaults, 0))) ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><code><?= e($row['page_key']) ?></code></td>
                            <td><?= e($row['path']) ?></td>
                            <td><?= e($row['meta_title'] ?? '—') ?></td>
                            <td style="max-width:280px;"><?= e(excerpt($row['meta_description'] ?? '', 90)) ?></td>
                            <td><span class="badge badge-<?= $row['robots'] === 'index,follow' ? 'success' : 'error' ?>"><?= e($row['robots']) ?></span></td>
                            <td><?= format_date($row['updated_at']) ?></td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="?edit=<?= urlencode($row['page_key']) ?>">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this SEO entry?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="seo_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="link-btn link-btn-danger" style="font-size:inherit;">Delete</button>
                                    </form>
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
