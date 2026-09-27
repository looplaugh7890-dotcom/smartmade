<?php

require_once __DIR__ . '/../includes/admin_header.php';

$adminId = (int)$_SESSION['admin_id'];
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ? LIMIT 1");
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

        if (empty($errors)) {
            $check = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1");
            $check->execute([$email, $adminId]);
            if ($check->fetch()) {
                $errors[] = 'That email is already in use.';
            }
        }

        if (empty($errors) && !empty($newPassword)) {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'New passwords do not match.';
            }
        }

        if (empty($errors)) {
            if (!empty($newPassword)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hash, $adminId]);
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $adminId]);
            }

            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;

            set_flash('success', 'Profile updated.');
            header('Location: ' . SITE_URL . '/admin/profile.php');
            exit;
        }
    }
} else {
    $_POST = $user;
}

$pageTitle = 'Admin Profile';
?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Admin Profile</h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error reveal"><?php foreach ($errors as $e) echo '<p style="margin:0 0 6px;">' . e($e) . '</p>'; ?></div>
<?php endif; ?>

<form action="" method="post" class="admin-form reveal">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="admin-form-group">
            <label for="role">Role</label>
            <input type="text" id="role" value="<?= e(ucfirst($_POST['role'] ?? 'admin')) ?>" disabled>
        </div>
    </div>

    <h3 style="margin-top: 32px; margin-bottom: 16px;">Change Password</h3>
    <div class="admin-form-grid">
        <div class="admin-form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password">
        </div>
        <div class="admin-form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password">
        </div>
        <div class="admin-form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">
        </div>
    </div>

    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Save Profile</button>
    </div>
</form>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
