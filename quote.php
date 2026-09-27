<?php

require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = false;

$orderType = $_GET['type'] ?? 'custom_original';
$orderType = in_array($orderType, ['business_logo', 'custom_original', 'team_bulk', 'other'], true) ? $orderType : 'custom_original';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid. Please refresh the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $orderTypePost = $_POST['order_type'] ?? 'custom_original';
        $service = trim($_POST['service'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $deadline = $_POST['deadline'] ?? null;
        $budget = trim($_POST['budget'] ?? '');

        if (empty($name) || mb_strlen($name) < 2) {
            $errors[] = 'Please enter your full name.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($description) || mb_strlen($description) < 10) {
            $errors[] = 'Please describe your idea in at least 10 characters.';
        }
        if (!in_array($orderTypePost, ['business_logo', 'custom_original', 'team_bulk', 'other'], true)) {
            $orderTypePost = 'custom_original';
        }
        if ($deadline && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $deadline = null;
        }

        $referenceImage = null;
        if (!empty($_FILES['reference_image']['tmp_name'])) {
            $upload = upload_image($_FILES['reference_image'], 'quotes');
            if ($upload === false) {
                $errors[] = 'Reference image upload failed. Please use JPG, PNG, WebP or GIF under 5MB.';
            } else {
                $referenceImage = $upload;
            }
        }

        $attachmentPaths = [];
        if (!empty($_FILES['attachments']['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $destDir = UPLOAD_PATH . '/quotes';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            foreach ((array)$_FILES['attachments']['tmp_name'] as $i => $tmp) {
                $file = [
                    'name' => $_FILES['attachments']['name'][$i] ?? '',
                    'tmp_name' => $tmp,
                    'error' => $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['attachments']['size'][$i] ?? 0,
                ];

                if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
                    continue;
                }
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > MAX_ARTWORK_SIZE || count($attachmentPaths) >= 3) {
                    $errors[] = 'An attachment could not be saved (max 3 files, ' . (int)(MAX_ARTWORK_SIZE / 1048576) . 'MB each).';
                    continue;
                }

                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($mime, ALLOWED_ARTWORK_TYPES, true)) {
                    $errors[] = 'Attachments must be JPG, PNG, WebP, GIF or PDF.';
                    continue;
                }

                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                    'application/pdf' => 'pdf',
                ];
                $ext = $extMap[$mime] ?? 'bin';
                $filename = uniqid(date('Ymd') . '_', true) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
                    $attachmentPaths[] = [
                        'path' => 'uploads/quotes/' . $filename,
                        'name' => mb_substr($file['name'], 0, 200),
                        'type' => $mime,
                        'size' => (int)$file['size'],
                    ];
                } else {
                    $errors[] = 'An attachment could not be saved.';
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO quote_requests
                (name, email, phone, order_type, service, description, reference_image, quantity, deadline, budget_range)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $email, $phone, $orderTypePost, $service ?: null, $description, $referenceImage, $quantity,
                $deadline ?: null, $budget
            ]);
            $quoteId = (int) $pdo->lastInsertId();

            if ($attachmentPaths) {
                $att = $pdo->prepare("
                    INSERT INTO quote_attachments (quote_id, file_path, original_name, file_type, file_size)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($attachmentPaths as $file) {
                    $att->execute([$quoteId, $file['path'], $file['name'], $file['type'], $file['size']]);
                }
            }

            $subject = 'New SmartMade quote request from ' . $name;
            $body = "Name: $name\nEmail: $email\nPhone: $phone\nType: $orderTypePost\nService: $service\nQuantity: $quantity\nDeadline: $deadline\nBudget: $budget\nAttachments: " . count($attachmentPaths) . "\n\nIdea:\n$description";
            send_notification(ADMIN_EMAIL, $subject, $body);

            require_once __DIR__ . '/includes/mail.php';
            $qStmt = $pdo->prepare("SELECT * FROM quote_requests WHERE id = ?");
            $qStmt->execute([$quoteId]);
            if ($qRow = $qStmt->fetch()) {
                send_quote_received($qRow);
            }

            $success = true;
        }
    }
}

$pageTitle = 'Get a Quote';
$pageDescription = 'Request a custom embroidery quote from SmartMade. Original art, business logos, team orders, and more.';
$bodyClass = 'quote-page';

include __DIR__ . '/includes/header.php';
?>

<section class="page-header" aria-labelledby="quote-title">
    <div class="container page-header-inner">
        <span class="section-label reveal" style="color: var(--color-gold-light);">Start your design</span>
        <h1 class="page-title reveal" id="quote-title">Get a Quote</h1>
        <p class="page-intro reveal">Tell us what you're after. Attach a reference image if you have one. We'll get back to you with a price and turnaround.</p>
    </div>
</section>

<section class="section form-section">
    <div class="container">
        <?php if ($success): ?>
            <div class="form-card reveal" style="text-align: center; padding: 64px 40px;">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: var(--color-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-family: var(--font-display); font-size: 2rem; font-weight: 900; border: 3px dashed var(--color-black);">✓</div>
                <h2 style="font-family: var(--font-display); font-size: 2rem; margin-bottom: 16px;">Quote request received!</h2>
                <p style="color: var(--color-text-muted); max-width: 520px; margin: 0 auto 28px;">Thanks for getting in touch. I'll have a look at your idea and reply within 1–2 working days with a price, turnaround, and any questions. Keep an eye on your inbox (and spam folder, just in case).</p>
                <a href="<?= SITE_URL ?>/portfolio.php" class="btn btn-primary btn-lg">Browse more work</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error reveal">
                    <strong>Please fix the following:</strong>
                    <ul style="margin: 8px 0 0 20px; padding: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= SITE_URL ?>/quote.php" method="post" enctype="multipart/form-data" class="form-card reveal" data-validate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name" class="form-label">Full name *</label>
                        <input type="text" id="name" name="name" class="form-input" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email address *</label>
                        <input type="email" id="email" name="email" class="form-input" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone <span>(optional)</span></label>
                        <input type="tel" id="phone" name="phone" class="form-input" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="order_type" class="form-label">Order type *</label>
                        <select id="order_type" name="order_type" class="form-select" required>
                            <option value="custom_original" <?= $orderType === 'custom_original' ? 'selected' : '' ?>>Custom original piece (portrait, gift, art)</option>
                            <option value="business_logo" <?= $orderType === 'business_logo' ? 'selected' : '' ?>>Business logo embroidery</option>
                            <option value="team_bulk" <?= $orderType === 'team_bulk' ? 'selected' : '' ?>>Team / bulk order</option>
                            <option value="other" <?= $orderType === 'other' ? 'selected' : '' ?>>Something else</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="service" class="form-label">Service *</label>
                        <select id="service" name="service" class="form-select" required>
                            <option value="" <?= ($service ?? '') === '' ? 'selected' : '' ?>>Choose a service…</option>
                            <?php foreach (['Embroidery', 'Printing', 'Promotional products', 'Artwork & digitising', 'Not sure yet'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= (($_POST['service'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label for="description" class="form-label">Tell us about your idea *</label>
                        <textarea id="description" name="description" class="form-textarea" required placeholder="e.g. A caricature of my mate in a Dunfermline top, or my barbershop logo on 4 black polo shirts..."><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group form-group-full">
                        <label for="reference_image" class="form-label">Reference image <span>(optional, max 5MB)</span></label>
                        <input type="file" id="reference_image" name="reference_image" class="form-input form-file" accept="image/jpeg,image/png,image/webp,image/gif">
                        <p class="form-hint">Upload a logo, photo, sketch, or inspiration image. We'll handle the digitising.</p>
                    </div>
                    <div class="form-group form-group-full">
                        <label for="attachments" class="form-label">Extra files <span>(optional — up to 3 files, JPG/PNG/WebP/GIF/PDF)</span></label>
                        <input type="file" id="attachments" name="attachments[]" class="form-input form-file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple>
                        <p class="form-hint">Logos, sketches, reference sheets or a PDF brief — attach them all in one go.</p>
                    </div>
                    <div class="form-group">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" class="form-input" min="1" value="<?= e($_POST['quantity'] ?? '1') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="deadline" class="form-label">Deadline <span>(optional)</span></label>
                        <input type="date" id="deadline" name="deadline" class="form-input" value="<?= e($_POST['deadline'] ?? '') ?>">
                    </div>
                    <div class="form-group form-group-full">
                        <label for="budget" class="form-label">Budget range <span>(optional)</span></label>
                        <select id="budget" name="budget" class="form-select">
                            <option value="">I'd rather discuss</option>
                            <option value="Under £50" <?= (($_POST['budget'] ?? '') === 'Under £50') ? 'selected' : '' ?>>Under £50</option>
                            <option value="£50–£100" <?= (($_POST['budget'] ?? '') === '£50–£100') ? 'selected' : '' ?>>£50–£100</option>
                            <option value="£100–£250" <?= (($_POST['budget'] ?? '') === '£100–£250') ? 'selected' : '' ?>>£100–£250</option>
                            <option value="£250–£500" <?= (($_POST['budget'] ?? '') === '£250–£500') ? 'selected' : '' ?>>£250–£500</option>
                            <option value="£500+" <?= (($_POST['budget'] ?? '') === '£500+') ? 'selected' : '' ?>>£500+</option>
                        </select>
                    </div>
                    <div class="form-group form-group-full form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Send Quote Request</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
