<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$success = false;

$settingKeys = [
    'site_title', 'site_tagline', 'contact_email', 'contact_phone', 'instagram_url',
    'facebook_url', 'tiktok_url', 'studio_address', 'google_maps_embed', 'business_hours',
    'meta_description', 'seo_keywords'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        foreach ($settingKeys as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        set_flash('success', 'Site settings saved.');
        header('Location: ' . SITE_URL . '/admin/settings.php');
        exit;
    }
}

$pageTitle = 'Site Settings';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Site Settings</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error reveal"><?php foreach ($errors as $e) echo '<p style="margin:0 0 6px;">' . e($e) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" class="admin-form reveal">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="site_title">Site Title</label>
            <input type="text" id="site_title" name="site_title" value="<?= e(setting('site_title')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="site_tagline">Hero Tagline</label>
            <input type="text" id="site_tagline" name="site_tagline" value="<?= e(setting('site_tagline')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="contact_email">Contact Email</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= e(setting('contact_email')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="contact_phone">Contact Phone</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?= e(setting('contact_phone')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="instagram_url">Instagram URL</label>
            <input type="url" id="instagram_url" name="instagram_url" value="<?= e(setting('instagram_url')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="facebook_url">Facebook URL</label>
            <input type="url" id="facebook_url" name="facebook_url" value="<?= e(setting('facebook_url')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="tiktok_url">TikTok URL</label>
            <input type="url" id="tiktok_url" name="tiktok_url" value="<?= e(setting('tiktok_url')) ?>">
        </div>
        <div class="admin-form-group">
            <label for="business_hours">Business Hours</label>
            <input type="text" id="business_hours" name="business_hours" value="<?= e(setting('business_hours')) ?>">
        </div>
        <div class="admin-form-group full">
            <label for="studio_address">Studio Address</label>
            <textarea id="studio_address" name="studio_address" rows="3"><?= e(setting('studio_address')) ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="google_maps_embed">Google Maps Embed URL</label>
            <input type="url" id="google_maps_embed" name="google_maps_embed" value="<?= e(setting('google_maps_embed')) ?>">
            <p class="admin-form-hint">Paste the full embed URL from Google Maps (iframe src).</p>
        </div>
        <div class="admin-form-group full">
            <label for="meta_description">Default Meta Description</label>
            <textarea id="meta_description" name="meta_description" rows="3"><?= e(setting('meta_description')) ?></textarea>
        </div>
        <div class="admin-form-group full">
            <label for="seo_keywords">SEO Keywords</label>
            <input type="text" id="seo_keywords" name="seo_keywords" value="<?= e(setting('seo_keywords')) ?>">
        </div>
    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Settings</button>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
