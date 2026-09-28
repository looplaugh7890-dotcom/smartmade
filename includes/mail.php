<?php

require_once __DIR__ . '/functions.php';

/**
 * Transactional email. Uses direct SMTP when smtp_host is configured in
 * site_settings, otherwise falls back to PHP mail(). Every attempt is
 * recorded in `email_log`.
 */
function send_mail(string $to, string $subject, string $html, string $plain = '', ?string $template = null, ?string $type = null, ?int $id = null): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_email($to, $subject, $template, $type, $id, 'failed', 'Invalid recipient address');
        return false;
    }

    $plain = $plain ?: trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));
    $fromName = EMAIL_FROM_NAME;
    $fromAddress = EMAIL_FROM_ADDRESS;

    $ok = false;
    $error = null;

    $host = setting('smtp_host');
    if ($host) {
        try {
            $ok = smtp_send_mail($to, $subject, $html, $plain, $fromName, $fromAddress);
            if (!$ok) {
                $error = 'SMTP send failed';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $ok = false;
        }
    }

    if (!$ok && !$host) {
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . sprintf('"%s" <%s>', $fromName, $fromAddress) . "\r\n";
        $headers .= 'Reply-To: ' . setting('contact_email', $fromAddress) . "\r\n";

        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
        if (!$ok) {
            $error = 'mail() returned false';
        }
    }

    log_email($to, $subject, $template, $type, $id, $ok ? 'sent' : 'failed', $error);
    return $ok;
}

function smtp_send_mail(string $to, string $subject, string $html, string $plain, string $fromName, string $fromAddress): bool {
    $host = setting('smtp_host');
    $port = (int)(setting('smtp_port', '587') ?: 587);
    $user = setting('smtp_user');
    $pass = setting('smtp_pass');
    $encryption = strtolower(setting('smtp_encryption', 'tls'));

    $timeout = 15;
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        throw new RuntimeException('SMTP connect failed: ' . $errstr);
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $expect = function (string $response, string $codes) {
        if (!in_array(substr($response, 0, 3), explode(',', $codes), true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    };

    $expect($read(), '220');
    fwrite($fp, 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . "\r\n");
    $expect($read(), '250');

    if ($encryption === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        $expect($read(), '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP TLS negotiation failed');
        }
        fwrite($fp, 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . "\r\n");
        $expect($read(), '250');
    }

    if ($user !== '') {
        fwrite($fp, 'AUTH LOGIN' . "\r\n");
        $expect($read(), '334');
        fwrite($fp, base64_encode($user) . "\r\n");
        $expect($read(), '334');
        fwrite($fp, base64_encode($pass) . "\r\n");
        $expect($read(), '235');
    }

    fwrite($fp, 'MAIL FROM:<' . $fromAddress . ">\r\n");
    $expect($read(), '250');
    fwrite($fp, 'RCPT TO:<' . $to . ">\r\n");
    $expect($read(), '250,251');
    fwrite($fp, 'DATA' . "\r\n");
    $expect($read(), '354');

    $headers = 'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromName), $fromAddress) . "\r\n";
    $headers .= 'Reply-To: <' . setting('contact_email', $fromAddress) . ">\r\n";
    $headers .= 'To: <' . $to . ">\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="smartmade-mail-boundary"' . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost') . ">\r\n";
    $headers .= 'X-Mailer: SmartMade Mailer' . "\r\n";

    $body = '--smartmade-mail-boundary' . "\r\n";
    $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n\r\n";
    $body .= preg_replace('/^\./m', '..', $plain) . "\r\n";
    $body .= '--smartmade-mail-boundary' . "\r\n";
    $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n";
    $body .= preg_replace('/^\./m', '..', $html) . "\r\n";
    $body .= '--smartmade-mail-boundary--' . "\r\n";

    $data = $headers . "\r\n" . $body . "\r\n.\r\n";
    fwrite($fp, $data);
    $expect($read(), '250');
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return true;
}

function email_layout(string $heading, string $contentHtml): string {
    $site = htmlspecialchars(setting('site_title', 'SmartMade'), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars(rtrim(SITE_URL, '/'), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(setting('studio_address'), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#F5F1E8;font-family:Arial,Helvetica,sans-serif;color:#111;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F1E8;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border:1px solid #E4DCC8;">
        <tr><td style="background:#0A0A0A;padding:20px 28px;">
          <span style="font-size:20px;font-weight:bold;color:#C9A227;letter-spacing:1px;">$site</span>
        </td></tr>
        <tr><td style="padding:28px;">
          <h1 style="font-size:22px;margin:0 0 16px;color:#0A0A0A;">$heading</h1>
          $contentHtml
        </td></tr>
        <tr><td style="background:#0A0A0A;padding:18px 28px;color:#B9B2A3;font-size:12px;line-height:1.6;">
          <a href="$url" style="color:#C9A227;text-decoration:none;">$url</a><br>
          $address
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function email_order_rows(array $items): string {
    $rows = '';
    foreach ($items as $item) {
        $name = htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8');
        $variant = $item['variant_label'] ? ' <span style="color:#777;">(' . htmlspecialchars($item['variant_label'], ENT_QUOTES, 'UTF-8') . ')</span>' : '';
        $qty = (int)$item['quantity'];
        $line = htmlspecialchars(money($item['line_total']), ENT_QUOTES, 'UTF-8');
        $pers = '';
        if (!empty($item['personalisation_json'])) {
            $data = json_decode($item['personalisation_json'], true) ?: [];
            foreach ($data as $k => $v) {
                $pers .= '<br><span style="color:#777;font-size:12px;">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</span>';
            }
        }
        $rows .= "<tr><td style='padding:8px 0;border-bottom:1px solid #EEE;'>$name$variant$pers</td>"
            . "<td style='padding:8px 0;border-bottom:1px solid #EEE;text-align:center;'>$qty</td>"
            . "<td style='padding:8px 0;border-bottom:1px solid #EEE;text-align:right;'>$line</td></tr>";
    }
    return $rows;
}

function send_order_confirmation(array $order, array $items): bool {
    $name = htmlspecialchars($order['name'], ENT_QUOTES, 'UTF-8');
    $number = htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8');
    $total = htmlspecialchars(money($order['grand_total']), ENT_QUOTES, 'UTF-8');

    $rows = email_order_rows($items);
    $content = "
        <p>Hi $name,</p>
        <p>Thanks for your order. We've received it and it's now with the studio.</p>
        <p><strong>Order number:</strong> $number</p>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin:20px 0;'>
          <tr style='background:#0A0A0A;color:#fff;'>
            <td style='padding:8px;'><strong>Item</strong></td>
            <td style='padding:8px;text-align:center;'><strong>Qty</strong></td>
            <td style='padding:8px;text-align:right;'><strong>Total</strong></td>
          </tr>
          $rows
          <tr><td colspan='2' style='padding:12px 8px 0;text-align:right;'><strong>Order total</strong></td>
              <td style='padding:12px 8px 0;text-align:right;'><strong>$total</strong></td></tr>
        </table>
        <p>We'll email you again when your order ships. Questions? Just reply to this email.</p>
    ";

    $subject = setting('order_email_subject', 'We have received your SmartMade order') . ' — ' . $number;
    return send_mail($order['email'], $subject, email_layout('Order received — ' . $number, $content), '', 'order_confirmation', 'order', (int)$order['id']);
}

function send_admin_new_order(array $order, array $items): bool {
    $to = setting('contact_email', EMAIL_FROM_ADDRESS);
    $number = htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($order['name'] . ' <' . $order['email'] . '>', ENT_QUOTES, 'UTF-8');
    $total = htmlspecialchars(money($order['grand_total']), ENT_QUOTES, 'UTF-8');
    $rows = email_order_rows($items);

    $content = "
        <p>A new order has been placed on the website.</p>
        <p><strong>Order:</strong> $number<br><strong>Customer:</strong> $name<br><strong>Total:</strong> $total</p>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin:20px 0;'>$rows</table>
        <p><a href='" . SITE_URL . "/admin/order_view.php?id=" . (int)$order['id'] . "' style='background:#C9A227;color:#0A0A0A;padding:10px 18px;text-decoration:none;font-weight:bold;'>View order in admin</a></p>
    ";

    return send_mail($to, 'New order ' . $number, email_layout('New order ' . $number, $content), '', 'admin_new_order', 'order', (int)$order['id']);
}

function send_quote_received(array $quote): bool {
    $name = htmlspecialchars($quote['name'], ENT_QUOTES, 'UTF-8');
    $content = "
        <p>Hi $name,</p>
        <p>Thanks for your quote request — it's landed safely. We'll come back to you within 1–2 working days with a price and a turnaround.</p>
        <p>If it's urgent, drop us a line at " . htmlspecialchars(setting('contact_email'), ENT_QUOTES, 'UTF-8') . ".</p>
    ";
    return send_mail($quote['email'], 'Your SmartMade quote request', email_layout('Quote request received', $content), '', 'quote_received', 'quote', (int)$quote['id']);
}

function send_password_reset_email(string $to, string $name, string $resetUrl): bool {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $content = "
        <p>Hi $safeName,</p>
        <p>Someone asked to reset the password for your SmartMade account.</p>
        <p><a href='$safeUrl' style='background:#C9A227;color:#0A0A0A;padding:12px 22px;text-decoration:none;font-weight:bold;display:inline-block;'>Reset your password</a></p>
        <p>This link expires in 1 hour. If you didn't request this, you can ignore this email.</p>
    ";
    return send_mail($to, 'Reset your SmartMade password', email_layout('Reset your password', $content), '', 'password_reset', 'customer');
}

function send_review_approved(array $review): bool {
    $name = htmlspecialchars($review['author_name'], ENT_QUOTES, 'UTF-8');
    $content = "
        <p>Hi $name,</p>
        <p>Thanks for your review — it's now live on the site and genuinely appreciated.</p>
        <p><a href='" . SITE_URL . "/reviews.php' style='color:#8A6D0F;'>See it on SmartMade</a></p>
    ";
    return send_mail($review['email'], 'Your SmartMade review is live', email_layout('Your review is live', $content), '', 'review_approved', 'review', (int)$review['id']);
}
