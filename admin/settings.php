<?php

require_once __DIR__ . '/../includes/admin_header.php';

$errors = [];
$tab = $_GET['tab'] ?? 'general';
if (!in_array($tab, ['general', 'store', 'payments', 'email'], true)) {
    $tab = 'general';
}

$tabs = [
    'general' => 'General',
    'store' => 'Store',
    'payments' => 'Payments',
    'email' => 'Email',
];

$settingKeys = [
    'general' => [
        'site_title', 'site_tagline', 'contact_email', 'contact_phone', 'instagram_url',
        'facebook_url', 'tiktok_url', 'studio_address', 'google_maps_embed', 'business_hours',
        'meta_description', 'seo_keywords'
    ],
    'store' => [
        'shop_page_title', 'currency', 'currency_symbol', 'vat_rate', 'vat_included',
        'free_shipping_threshold', 'low_stock_threshold', 'allow_guest_checkout',
        'enable_reviews', 'reviews_require_approval'
    ],
    'payments' => [
        'enable_stripe', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
        'enable_paypal', 'enable_bank_transfer', 'bank_name', 'bank_account_name',
        'bank_sort_code', 'bank_account_number'
    ],
    'email' => [
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
        'order_email_subject'
    ],
];

$boolKeys = ['vat_included', 'allow_guest_checkout', 'enable_reviews', 'reviews_require_approval', 'enable_stripe', 'enable_paypal', 'enable_bank_transfer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $postedTab = in_array($_POST['tab'] ?? '', array_keys($tabs), true) ? $_POST['tab'] : 'general';

        foreach ($settingKeys[$postedTab] as $key) {
            if (in_array($key, $boolKeys, true)) {
                $value = isset($_POST[$key]) ? '1' : '0';
            } else {
                $value = trim($_POST[$key] ?? '');
            }
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }

        activity_log('settings.update', 'settings', null, 'tab: ' . $postedTab);
        set_flash('success', ucfirst($postedTab) . ' settings saved.');
        header('Location: ' . SITE_URL . '/admin/settings.php?tab=' . $postedTab);
        exit;
    }
}

$pageTitle = 'Settings';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Settings</h1>
</div>

<div class="admin-tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= SITE_URL ?>/admin/settings.php?tab=<?= $key ?>" class="<?= $tab === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php foreach ($errors as $err) echo '<p style="margin:0 0 6px;">' . e($err) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" class="admin-form reveal">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="admin-form-grid">

        <?php if ($tab === 'general'): ?>
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

        <?php elseif ($tab === 'store'): ?>
            <div class="admin-form-group full">
                <label for="shop_page_title">Shop page heading</label>
                <input type="text" id="shop_page_title" name="shop_page_title" value="<?= e(setting('shop_page_title')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="currency">Currency code</label>
                <input type="text" id="currency" name="currency" maxlength="3" value="<?= e(setting('currency', 'GBP')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="currency_symbol">Currency symbol</label>
                <input type="text" id="currency_symbol" name="currency_symbol" maxlength="3" value="<?= e(setting('currency_symbol', '£')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="vat_rate">VAT rate (%)</label>
                <input type="number" step="0.01" min="0" id="vat_rate" name="vat_rate" value="<?= e(setting('vat_rate', '20')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="free_shipping_threshold">Free shipping threshold (£)</label>
                <input type="number" step="0.01" min="0" id="free_shipping_threshold" name="free_shipping_threshold" value="<?= e(setting('free_shipping_threshold')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="low_stock_threshold">Low stock warning level</label>
                <input type="number" min="0" id="low_stock_threshold" name="low_stock_threshold" value="<?= e(setting('low_stock_threshold', '5')) ?>">
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="vat_included" value="1" <?= setting('vat_included', '1') === '1' ? 'checked' : '' ?>> Prices include VAT</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="allow_guest_checkout" value="1" <?= setting('allow_guest_checkout', '1') === '1' ? 'checked' : '' ?>> Allow guest checkout</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="enable_reviews" value="1" <?= setting('enable_reviews', '1') === '1' ? 'checked' : '' ?>> Product reviews enabled</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="reviews_require_approval" value="1" <?= setting('reviews_require_approval', '1') === '1' ? 'checked' : '' ?>> Reviews need approval</label>
            </div>

        <?php elseif ($tab === 'payments'): ?>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="enable_stripe" value="1" <?= setting('enable_stripe', '0') === '1' ? 'checked' : '' ?>> Stripe (card payments)</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="enable_paypal" value="1" <?= setting('enable_paypal', '0') === '1' ? 'checked' : '' ?>> PayPal</label>
            </div>
            <div class="admin-form-group">
                <label class="admin-checkbox"><input type="checkbox" name="enable_bank_transfer" value="1" <?= setting('enable_bank_transfer', '1') === '1' ? 'checked' : '' ?>> Bank transfer / invoice</label>
            </div>
            <div class="admin-form-group full"></div>
            <div class="admin-form-group">
                <label for="stripe_public_key">Stripe public key</label>
                <input type="text" id="stripe_public_key" name="stripe_public_key" value="<?= e(setting('stripe_public_key')) ?>" placeholder="pk_live_…">
            </div>
            <div class="admin-form-group">
                <label for="stripe_secret_key">Stripe secret key</label>
                <input type="password" id="stripe_secret_key" name="stripe_secret_key" value="<?= e(setting('stripe_secret_key')) ?>" autocomplete="off" placeholder="sk_live_…">
            </div>
            <div class="admin-form-group full">
                <label for="stripe_webhook_secret">Stripe webhook signing secret</label>
                <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" value="<?= e(setting('stripe_webhook_secret')) ?>" autocomplete="off" placeholder="whsec_…">
                <p class="admin-form-hint">Point the webhook at <?= SITE_URL ?>/webhook.php — used to confirm card payments automatically.</p>
            </div>
            <div class="admin-form-group full"></div>
            <div class="admin-form-group">
                <label for="bank_name">Bank name</label>
                <input type="text" id="bank_name" name="bank_name" value="<?= e(setting('bank_name')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="bank_account_name">Account name</label>
                <input type="text" id="bank_account_name" name="bank_account_name" value="<?= e(setting('bank_account_name')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="bank_sort_code">Sort code</label>
                <input type="text" id="bank_sort_code" name="bank_sort_code" value="<?= e(setting('bank_sort_code')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="bank_account_number">Account number</label>
                <input type="text" id="bank_account_number" name="bank_account_number" value="<?= e(setting('bank_account_number')) ?>">
            </div>

        <?php elseif ($tab === 'email'): ?>
            <div class="admin-form-group">
                <label for="smtp_host">SMTP host</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.example.com">
            </div>
            <div class="admin-form-group">
                <label for="smtp_port">SMTP port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="smtp_user">SMTP user</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?= e(setting('smtp_user')) ?>">
            </div>
            <div class="admin-form-group">
                <label for="smtp_pass">SMTP password</label>
                <input type="password" id="smtp_pass" name="smtp_pass" value="<?= e(setting('smtp_pass')) ?>" autocomplete="off">
            </div>
            <div class="admin-form-group">
                <label for="smtp_encryption">Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
                        <option value="<?= $enc ?>" <?= setting('smtp_encryption', 'tls') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-form-group full">
                <label for="order_email_subject">Order confirmation subject</label>
                <input type="text" id="order_email_subject" name="order_email_subject" value="<?= e(setting('order_email_subject', 'Your SmartMade order {order_number} is confirmed')) ?>">
            </div>
            <div class="admin-form-group full">
                <p class="admin-form-hint">Mail is logged to the email_log table either way — check there if customers report missing emails.</p>
            </div>
        <?php endif; ?>

    </div>
    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save <?= e(ucfirst($tab)) ?> Settings</button>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
