<?php
/**
 * Minimal SMTP mailer (SSL/TLS or STARTTLS, AUTH LOGIN) with PHP mail() fallback.
 * Configure in Admin → Settings → Email.
 */
declare(strict_types=1);

/** @return true|string  true on success, error message on failure */
function send_mail(string|array $to, string $subject, string $html, ?string $replyTo = null): bool|string
{
    $to = array_values(array_filter(array_map('trim', is_array($to) ? $to : explode(',', $to)), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if (!$to) return 'No valid recipient address.';
    $fromEmail = (string) setting('mail_from_email', company('email'));
    $fromName = (string) setting('mail_from_name', company('name'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) return 'Set a valid “From” email in Settings → Email.';

    $boundary = 'b' . bin2hex(random_bytes(8));
    $text = trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/h\d|/li|/tr)[^>]*>~i', "\n", $html) ?? ''), ENT_QUOTES));
    $encName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $encName . ' <' . $fromEmail . '>',
        'To: ' . implode(', ', $to),
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--{$boundary}--\r\n";

    // Development mailbox: in debug mode every email is also saved to app/storage/mail (viewable in Admin → Settings → System).
    $host = trim((string) setting('smtp_host', ''));
    if (config('debug') || config('mail_log')) {
        $dir = STORAGE_DIR . '/mail';
        @mkdir($dir, 0775, true);
        $meta = '<!-- to: ' . e(implode(', ', $to)) . ' | subject: ' . e($subject) . ' | ' . date('c') . ' -->';
        @file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html', $meta . "\n" . $html);
        foreach (array_slice(glob($dir . '/*.html') ?: [], 0, -200) as $old) @unlink($old); // keep the newest 200
        if ($host === '' && config('debug')) return true; // no SMTP locally: treat the saved copy as delivered
    }
    if ($host === '') {
        $h = array_filter($headers, fn($l) => !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:'));
        $ok = @mail(implode(', ', $to), '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $h), '-f' . $fromEmail);
        return $ok ? true : 'PHP mail() failed. Configure SMTP in Settings → Email.';
    }

    try {
        smtp_send($host, (int) setting('smtp_port', 587), (string) setting('smtp_encryption', 'tls'), (string) setting('smtp_user', ''), (string) setting('smtp_pass', ''), $fromEmail, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP error: ' . $e->getMessage());
        return 'SMTP error: ' . $e->getMessage();
    }
}

function smtp_send(string $host, int $port, string $enc, string $user, string $pass, string $from, array $to, string $data): void
{
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Cannot connect to {$host}:{$port} ({$errstr})");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = function (?string $c, array $ok) use ($fp, $read): string {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $ok, true)) {
            $msg = ($c !== null && str_starts_with($c, 'AUTH')) ? 'Authentication failed: ' . $resp : ($resp ?: 'No response from server');
            throw new RuntimeException(trim($msg));
        }
        return $resp;
    };

    $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
    $cmd(null, [220]);
    $cmd($ehlo, [250]);
    if ($enc === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('STARTTLS negotiation failed');
        $cmd($ehlo, [250]);
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode($pass), [235]);
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    foreach ($to as $rcpt) $cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
    $cmd('DATA', [354]);
    // Dot-stuffing per RFC 5321
    $data = preg_replace('~^\.~m', '..', $data);
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}

/** Simple branded HTML email wrapper */
function mail_layout(string $title, string $inner): string
{
    $name = e(company('name'));
    return '<!doctype html><html><body style="margin:0;background:#F3F5F7;font-family:Arial,Helvetica,sans-serif;color:#252B35">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-top:4px solid #F28C28">'
        . '<tr><td style="background:#101D32;padding:18px 28px;color:#fff;font-size:18px;font-weight:bold;letter-spacing:1px">' . $name . '</td></tr>'
        . '<tr><td style="padding:28px"><h1 style="margin:0 0 16px;font-size:20px;color:#101D32">' . e($title) . '</h1>' . $inner . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#F3F5F7;font-size:12px;color:#6b7280">' . $name . ' · ' . e(company('phone')) . ' · ' . e(company('email')) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}
