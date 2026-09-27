<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'item';
}

function csrf_token(): string {
    $sessionId = session_id();
    if (!$sessionId) {
        session_start();
        $sessionId = session_id();
    }

    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
    $stmt->execute();

    $stmt = $pdo->prepare("SELECT token FROM csrf_tokens WHERE session_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['token'];
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));

    $stmt = $pdo->prepare("INSERT INTO csrf_tokens (session_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$sessionId, $token, $expires]);

    return $token;
}

function verify_csrf(string $token = ''): bool {
    if (empty($token) && isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    if (empty($token)) {
        return false;
    }

    $sessionId = session_id();
    if (!$sessionId) {
        return false;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM csrf_tokens WHERE session_id = ? AND token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$sessionId, $token]);
    return (bool) $stmt->fetch();
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function setting(string $key, string $default = ''): string {
    static $settings = null;

    if ($settings === null) {
        global $pdo;
        $settings = [];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log('Failed to load settings: ' . $e->getMessage());
        }
    }

    return $settings[$key] ?? $default;
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function has_flash(string $type): bool {
    return !empty($_SESSION['flash'][$type]);
}

function category_url(string $slug): string {
    return SITE_URL . '/portfolio.php?category=' . urlencode($slug);
}

function portfolio_url(int $id, string $slug): string {
    return SITE_URL . '/portfolio.php?view=' . $id . '&slug=' . urlencode($slug);
}

function blog_url(int $id, string $slug): string {
    return SITE_URL . '/blog-post.php?id=' . $id . '&slug=' . urlencode($slug);
}

function upload_image(array $file, string $subfolder): string|false {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        return false;
    }

    $base = uniqid(date('Ymd') . '_', true);
    $filename = $base . '.' . $ext;
    $destDir = UPLOAD_PATH . '/' . $subfolder;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $destPath = $destDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }

    if (function_exists('getimagesize')) {
        smartmade_strip_metadata($destPath, $mime);
    }

    return 'uploads/' . $subfolder . '/' . $filename;
}

function smartmade_strip_metadata(string $path, string $mime): void {
    $createFn = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
        default      => null,
    };

    if (!$createFn || !function_exists($createFn)) {
        return;
    }

    $img = @$createFn($path);
    if (!$img) {
        return;
    }

    match ($mime) {
        'image/jpeg' => imagejpeg($img, $path, 90),
        'image/png'  => imagepng($img, $path, 6),
        'image/gif'  => imagegif($img, $path),
        'image/webp' => imagewebp($img, $path, 90),
        default      => null,
    };

    imagedestroy($img);
}

function delete_upload(string $relativePath): bool {
    if (empty($relativePath)) {
        return false;
    }
    $fullPath = SITE_PATH . '/' . ltrim($relativePath, '/');
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

function send_notification(string $to, string $subject, string $body): bool {
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM_ADDRESS . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to, $subject, $body, $headers);
}

function excerpt(string $text, int $length = 160): string {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '…';
}

function format_date(string $date, string $format = 'j M Y'): string {
    return date($format, strtotime($date));
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

function paginate(int $total, int $page, int $perPage): array {
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'page'        => $page,
        'perPage'     => $perPage,
        'totalPages'  => $totalPages,
        'offset'      => $offset,
        'total'       => $total,
        'hasPrev'     => $page > 1,
        'hasNext'     => $page < $totalPages,
    ];
}

function pagination_url(int $page, array $keepParams = []): string {
    $params = array_merge($keepParams, ['page' => $page]);
    return '?' . http_build_query($params);
}

function star_rating(int $rating): string {
    $html = '<span class="star-rating" aria-label="Rating: ' . $rating . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    $html .= '</span>';
    return $html;
}
