<?php
/** Lead capture (public forms) and lead pipeline helpers. */
declare(strict_types=1);

const LEAD_STATUSES = [
    'new' => ['New', 'bg-blue-100 text-blue-800'],
    'contacted' => ['Contacted', 'bg-indigo-100 text-indigo-800'],
    'qualified' => ['Qualified', 'bg-violet-100 text-violet-800'],
    'quoted' => ['Quoted', 'bg-amber-100 text-amber-800'],
    'negotiation' => ['Negotiation', 'bg-orange-100 text-orange-800'],
    'won' => ['Won', 'bg-emerald-100 text-emerald-800'],
    'lost' => ['Lost', 'bg-gray-200 text-gray-700'],
    'spam' => ['Spam', 'bg-red-100 text-red-700'],
];
const LEAD_OPEN = ['new', 'contacted', 'qualified', 'quoted', 'negotiation'];
const LEAD_FILE_EXT = ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png'];
const LEAD_FILE_MAX = 10 * 1024 * 1024;
const LEAD_FILE_COUNT = 5;

function lead_status_badge(string $status): string
{
    [$label, $cls] = LEAD_STATUSES[$status] ?? [ucfirst($status), 'bg-gray-100 text-gray-700'];
    return '<span class="adm-badge ' . $cls . '">' . e($label) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Cookie-free anti-spam token: signed timestamp                        */
/* ------------------------------------------------------------------ */
function form_token(): string
{
    $ts = (string) time();
    return $ts . '.' . substr(hash_hmac('sha256', $ts, (string) config('app_key')), 0, 32);
}

function form_token_valid(string $token): bool
{
    [$ts, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if (!ctype_digit($ts) || !hash_equals(substr(hash_hmac('sha256', $ts, (string) config('app_key')), 0, 32), $sig)) return false;
    $age = time() - (int) $ts;
    return $age >= 3 && $age <= 7200; // humans take >3 s; tokens expire after 2 h
}

function turnstile_enabled(): bool
{
    return setting('turnstile_site_key') && setting('turnstile_secret');
}

function turnstile_verify(string $response): bool
{
    if (!turnstile_enabled()) return true;
    if ($response === '') return false;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 8,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => setting('turnstile_secret'), 'response' => $response, 'remoteip' => client_ip()]),
    ]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    $json = $res ? json_decode($res, true) : null;
    return !empty($json['success']);
}

/**
 * Validate and store a public form submission.
 * @return array{ok: bool, errors?: array, message?: string, id?: int}
 */
function lead_submit(array $in, array $files): array
{
    $type = ($in['form'] ?? '') === 'quote' ? 'quote' : 'contact';

    // Spam checks: honeypot, signed time token, Turnstile, rate limit.
    if (!empty($in['website'])) return ['ok' => true, 'id' => 0]; // silently accept bots
    if (!form_token_valid((string) ($in['_ft'] ?? ''))) {
        return ['ok' => false, 'message' => 'The form expired or was submitted too quickly. Please refresh the page and try again.'];
    }
    if (!turnstile_verify((string) ($in['cf-turnstile-response'] ?? ''))) {
        return ['ok' => false, 'message' => 'Security check failed. Please complete the verification and try again.'];
    }
    if (!rate_limit(ip_bucket('lead'), 5, 600)) {
        return ['ok' => false, 'message' => 'Too many submissions. Please wait a few minutes or contact us by phone.'];
    }

    $v = fn($k, $max = 190) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
    $data = [
        'type' => $type,
        'name' => $v('fullName', 150), 'company' => $v('company'), 'email' => $v('email'), 'phone' => $v('phone', 50),
        'product' => $v('product'), 'part_number' => $v('partNumber'), 'message' => $v('message', 5000),
    ];
    $errors = [];
    if (mb_strlen($data['name']) < 2) $errors['fullName'] = 'Please enter your full name.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if ($data['phone'] !== '' && !preg_match('~^\+?[\d\s\-()]{7,20}$~', $data['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if ($type === 'quote' && $data['phone'] === '') $errors['phone'] = 'Please enter your phone number.';
    if ($type === 'quote' && $data['company'] === '') $errors['company'] = 'Please enter your company name.';
    if ($data['product'] === '') $errors['product'] = 'Please select an option.';
    if (mb_strlen($data['message']) < 10) $errors['message'] = 'Please add a little more detail (at least 10 characters).';
    if (empty($in['consent'])) $errors['consent'] = 'Please confirm to continue.';
    $qty = trim((string) ($in['quantity'] ?? ''));
    if ($qty !== '' && (!ctype_digit($qty) || (int) $qty < 1)) $errors['quantity'] = 'Please enter a whole number of 1 or more.';
    $req = trim((string) ($in['requiredBy'] ?? ''));
    if ($req !== '' && (!($d = DateTime::createFromFormat('Y-m-d', $req)) || $d < new DateTime('today'))) $errors['requiredBy'] = 'Please choose a date in the future.';

    // Attachments (quote form)
    $uploads = [];
    if (!empty($files['attachments']['name']) && is_array($files['attachments']['name'])) {
        $f = $files['attachments'];
        $count = 0;
        foreach ($f['name'] as $i => $origName) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if (++$count > LEAD_FILE_COUNT) { $errors['attachments'] = 'You can attach up to ' . LEAD_FILE_COUNT . ' files.'; break; }
            $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
            if ($f['error'][$i] !== UPLOAD_ERR_OK) { $errors['attachments'] = 'A file failed to upload. Files must be under 10 MB.'; continue; }
            if (!in_array($ext, LEAD_FILE_EXT, true)) { $errors['attachments'] = 'Unsupported file type: ' . $origName; continue; }
            if ($f['size'][$i] > LEAD_FILE_MAX) { $errors['attachments'] = $origName . ' is larger than 10 MB.'; continue; }
            $uploads[] = ['tmp' => $f['tmp_name'][$i], 'name' => mb_substr(basename((string) $origName), 0, 200), 'ext' => $ext, 'size' => (int) $f['size'][$i]];
        }
    }

    if ($errors) return ['ok' => false, 'errors' => $errors, 'message' => 'Please correct the highlighted fields.'];

    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $utm = [];
    parse_str((string) parse_url((string) ($in['_landing'] ?? ''), PHP_URL_QUERY), $utm);
    $id = db_insert('leads', $data + [
        'quantity' => $qty !== '' ? (int) $qty : null,
        'required_by' => $req !== '' ? $req : null,
        'source_page' => mb_substr((string) ($in['_page'] ?? $ref), 0, 500),
        'referrer' => mb_substr((string) ($in['_referrer'] ?? ''), 0, 500),
        'utm_source' => mb_substr((string) ($utm['utm_source'] ?? ''), 0, 120) ?: null,
        'utm_medium' => mb_substr((string) ($utm['utm_medium'] ?? ''), 0, 120) ?: null,
        'utm_campaign' => mb_substr((string) ($utm['utm_campaign'] ?? ''), 0, 120) ?: null,
        'ip' => client_ip(),
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'consent' => 1,
    ]);

    $dir = STORAGE_DIR . '/leads';
    @mkdir($dir, 0775, true);
    foreach ($uploads as $u) {
        $stored = bin2hex(random_bytes(16)) . '.' . $u['ext'];
        if (move_uploaded_file($u['tmp'], $dir . '/' . $stored) || (PHP_SAPI === 'cli' && rename($u['tmp'], $dir . '/' . $stored))) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($dir . '/' . $stored) ?: 'application/octet-stream';
            db_insert('lead_files', ['lead_id' => $id, 'stored_name' => $stored, 'original_name' => $u['name'], 'mime' => $mime, 'size' => $u['size']]);
        }
    }
    lead_note($id, 'system', 'Lead received via the ' . ($type === 'quote' ? 'quote request' : 'contact') . ' form.');
    lead_notify($id);
    return ['ok' => true, 'id' => $id];
}

function lead_note(int $leadId, string $kind, string $body, ?int $userId = null): void
{
    db_insert('lead_notes', ['lead_id' => $leadId, 'user_id' => $userId, 'kind' => $kind, 'body' => mb_substr($body, 0, 5000)]);
}

function lead_notify(int $id): void
{
    $lead = db_one('SELECT * FROM leads WHERE id = ?', [$id]);
    if (!$lead) return;
    $recipients = (string) setting('lead_notify_emails', '');
    if ($recipients !== '' && setting('lead_notify_enabled', true)) {
        $rows = '';
        foreach (['Type' => ucfirst($lead['type']), 'Name' => $lead['name'], 'Company' => $lead['company'], 'Email' => $lead['email'], 'Phone' => $lead['phone'],
                  'Product / service' => $lead['product'], 'Quantity' => $lead['quantity'], 'Required by' => $lead['required_by'], 'Part / drawing no.' => $lead['part_number'], 'Source page' => $lead['source_page']] as $k => $val) {
            if ($val === null || $val === '') continue;
            $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;white-space:nowrap">' . e($k) . '</td><td style="padding:6px 0">' . e($val) . '</td></tr>';
        }
        $files = (int) db_val('SELECT COUNT(*) FROM lead_files WHERE lead_id = ?', [$id]);
        $inner = '<table cellpadding="0" cellspacing="0" style="font-size:14px">' . $rows . '</table>'
            . '<p style="margin:16px 0 6px;color:#6b7280;font-size:13px">Message</p><div style="padding:12px;background:#F3F5F7;font-size:14px;white-space:pre-wrap">' . e($lead['message']) . '</div>'
            . ($files ? '<p style="font-size:13px">' . $files . ' attachment(s) — download them in the admin panel.</p>' : '')
            . '<p style="margin-top:20px"><a href="' . e(abs_url('admin/leads/' . $id)) . '" style="background:#F28C28;color:#fff;padding:10px 18px;text-decoration:none;font-weight:bold">Open lead #' . $id . '</a></p>';
        $res = send_mail($recipients, 'New ' . $lead['type'] . ' enquiry from ' . $lead['name'], mail_layout('New ' . ($lead['type'] === 'quote' ? 'quote request' : 'enquiry'), $inner), $lead['email']);
        lead_note($id, 'system', $res === true ? 'Notification email sent to ' . $recipients . '.' : 'Notification email failed: ' . $res);
    }
    if (setting('lead_autoreply_enabled') && $lead['email']) {
        $msg = (string) setting('lead_autoreply_text', "Thank you for contacting us. We have received your enquiry and will respond within one business day.");
        $inner = '<p style="font-size:14px;line-height:1.6">Dear ' . e($lead['name']) . ',</p><p style="font-size:14px;line-height:1.6;white-space:pre-wrap">' . e($msg) . '</p>';
        send_mail($lead['email'], (string) setting('lead_autoreply_subject', 'We received your enquiry'), mail_layout('Thank you for your enquiry', $inner));
    }
}
