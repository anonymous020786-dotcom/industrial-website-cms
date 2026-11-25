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
const LEAD_CV_EXT = ['pdf', 'doc', 'docx'];
/** Public forms that create a lead (type => label). Job applications are kept apart from sales leads. */
const LEAD_FORMS = ['contact' => 'Contact', 'quote' => 'Quote request', 'service' => 'Service / breakdown', 'part' => 'Part request', 'distributor' => 'Distributor application', 'job' => 'Job application'];
const SERVICE_STATUSES = [
    'received' => ['Received', 'We have your request and are reviewing it.'],
    'scheduled' => ['Visit scheduled', 'An engineer visit or call-back is scheduled.'],
    'in_progress' => ['In progress', 'Our engineers are working on it.'],
    'awaiting_parts' => ['Awaiting parts', 'Parts are being manufactured or sourced.'],
    'resolved' => ['Resolved', 'The issue has been fixed.'],
    'closed' => ['Closed', 'This request is closed.'],
];
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
 * Normalise the multi-item quote list posted as items[n][slug|name|part|qty|notes].
 * Product names are taken from the catalogue (not the browser) when a known slug is given.
 * @return array{0: array, 1: ?string} [items, error]
 */
function lead_items_from_input(mixed $raw): array
{
    if (!is_array($raw)) return [[], null];
    $catalogue = array_column(products_all(), 'name', 'slug');
    $items = [];
    foreach (array_slice($raw, 0, 30) as $it) {
        if (!is_array($it)) continue;
        $slug = (string) ($it['slug'] ?? '');
        $name = isset($catalogue[$slug]) ? $catalogue[$slug] : mb_substr(trim((string) ($it['name'] ?? '')), 0, 190);
        $part = mb_substr(trim((string) ($it['part'] ?? '')), 0, 120);
        $notes = mb_substr(trim((string) ($it['notes'] ?? '')), 0, 300);
        if ($name === '' && $part === '') continue;
        $qtyRaw = trim((string) ($it['qty'] ?? '1'));
        if (!ctype_digit($qtyRaw) || (int) $qtyRaw < 1 || (int) $qtyRaw > 1000000) return [[], 'Each item needs a whole-number quantity between 1 and 1,000,000.'];
        $items[] = ['slug' => isset($catalogue[$slug]) ? $slug : '', 'name' => $name !== '' ? $name : 'Part ' . $part, 'part' => $part, 'qty' => (int) $qtyRaw, 'notes' => $notes];
    }
    return [$items, null];
}

/** HTML table of quote items (used in emails and the admin). */
function lead_items_table(array $items, bool $email = true): string
{
    if (!$items) return '';
    $th = $email ? 'style="text-align:left;padding:6px 8px;background:#101D32;color:#fff;font-size:12px"' : 'class="text-left"';
    $td = $email ? 'style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:13px;vertical-align:top"' : '';
    $h = '<table cellpadding="0" cellspacing="0" ' . ($email ? 'style="width:100%;border-collapse:collapse;margin:8px 0 4px"' : 'class="adm-table"') . '><thead><tr><th ' . $th . '>#</th><th ' . $th . '>Item</th><th ' . $th . '>Part / drawing no.</th><th ' . $th . '>Qty</th><th ' . $th . '>Notes</th></tr></thead><tbody>';
    foreach ($items as $i => $it) {
        $h .= '<tr><td ' . $td . '>' . ($i + 1) . '</td><td ' . $td . '><b>' . e($it['name']) . '</b></td><td ' . $td . '>' . e($it['part'] ?: '—') . '</td><td ' . $td . '>' . (int) $it['qty'] . '</td><td ' . $td . '>' . e($it['notes'] ?: '') . '</td></tr>';
    }
    return $h . '</tbody></table>';
}

/**
 * Validate and store a public form submission.
 * @return array{ok: bool, errors?: array, message?: string, id?: int}
 */
function lead_submit(array $in, array $files): array
{
    $type = isset(LEAD_FORMS[$t = (string) ($in['form'] ?? '')]) ? $t : 'contact';

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
    [$items, $itemsError] = $type === 'quote' ? lead_items_from_input($in['items'] ?? null) : [[], null];
    if ($itemsError) $errors['items'] = $itemsError;
    if ($items && $data['product'] === '') $data['product'] = mb_substr(count($items) === 1 ? $items[0]['name'] : 'Multiple items (' . count($items) . ')', 0, 190);
    $data['location'] = $v('location') ?: null;
    $job = null;
    // Form-specific details
    if ($type === 'service') {
        $data['product'] = $v('equipment') ?: $data['product'];
        if ($data['product'] === '') $errors['equipment'] = 'Please tell us which equipment is affected.';
        if (!$data['location']) $errors['location'] = 'Please enter the site location.';
        $data['priority'] = in_array($in['urgency'] ?? '', ['urgent', 'high'], true) ? 'high' : 'normal';
    } elseif ($type === 'part') {
        if ($data['part_number'] === '') $errors['partNumber'] = 'Please enter the part number you are looking for.';
        $data['product'] = mb_substr('Part request: ' . $data['part_number'], 0, 190);
    } elseif ($type === 'distributor') {
        $data['product'] = 'Distributor application';
        if (!$data['location']) $errors['location'] = 'Please enter your city / region.';
    } elseif ($type === 'job') {
        $job = ctype_digit((string) ($in['job_id'] ?? '')) ? db_one("SELECT * FROM jobs WHERE id = ? AND status = 'published'", [(int) $in['job_id']]) : null;
        if (!$job) $errors['job_id'] = 'Please choose a position.';
        $data['product'] = mb_substr('Application: ' . ($job['title'] ?? 'Open application'), 0, 190);
    }
    if (mb_strlen($data['name']) < 2) $errors['fullName'] = 'Please enter your full name.';
    if ($type === 'service') {
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if ($data['phone'] !== '' && !preg_match('~^\+?[\d\s\-()]{7,20}$~', $data['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if (in_array($type, ['quote', 'service', 'distributor', 'job'], true) && $data['phone'] === '') $errors['phone'] = 'Please enter your phone number.';
    if (in_array($type, ['quote', 'distributor'], true) && $data['company'] === '') $errors['company'] = 'Please enter your company name.';
    if (in_array($type, ['contact', 'quote'], true) && $data['product'] === '') $errors['product'] = 'Please select an option.';
    if ($type !== 'job' && mb_strlen($data['message']) < 10) $errors['message'] = 'Please add a little more detail (at least 10 characters).';
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
            if (!in_array($ext, $type === 'job' ? LEAD_CV_EXT : LEAD_FILE_EXT, true)) { $errors['attachments'] = 'Unsupported file type: ' . $origName; continue; }
            if ($f['size'][$i] > LEAD_FILE_MAX) { $errors['attachments'] = $origName . ' is larger than 10 MB.'; continue; }
            $uploads[] = ['tmp' => $f['tmp_name'][$i], 'name' => mb_substr(basename((string) $origName), 0, 200), 'ext' => $ext, 'size' => (int) $f['size'][$i]];
        }
    }

    if ($type === 'job' && !$uploads && !isset($errors['attachments'])) $errors['attachments'] = 'Please attach your CV (PDF or Word).';
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
        'items' => $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null,
        'job_id' => $job['id'] ?? null,
        'track_token' => $type === 'service' ? bin2hex(random_bytes(16)) : null,
        'service_status' => $type === 'service' ? 'received' : null,
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
    lead_note($id, 'system', 'Received via the ' . strtolower(LEAD_FORMS[$type]) . ' form.');
    if ($type === 'job') { job_application_notify($id); return ['ok' => true, 'id' => $id]; }
    lead_notify($id);
    if ($type === 'service') return ['ok' => true, 'id' => $id] + service_after_submit($id);
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
        $items = json_decode((string) $lead['items'], true) ?: [];
        $inner = '<table cellpadding="0" cellspacing="0" style="font-size:14px">' . $rows . '</table>'
            . ($items ? '<p style="margin:16px 0 0;color:#6b7280;font-size:13px">Items requested (' . count($items) . ')</p>' . lead_items_table($items) : '')
            . '<p style="margin:16px 0 6px;color:#6b7280;font-size:13px">Message</p><div style="padding:12px;background:#F3F5F7;font-size:14px;white-space:pre-wrap">' . e($lead['message']) . '</div>'
            . ($files ? '<p style="font-size:13px">' . $files . ' attachment(s) — download them in the admin panel.</p>' : '')
            . '<p style="margin-top:20px"><a href="' . e(abs_url('admin/leads/' . $id)) . '" style="background:#F28C28;color:#fff;padding:10px 18px;text-decoration:none;font-weight:bold">Open lead #' . $id . '</a></p>';
        $res = send_mail($recipients, 'New ' . $lead['type'] . ' enquiry from ' . $lead['name'], mail_layout('New ' . ($lead['type'] === 'quote' ? 'quote request' : 'enquiry'), $inner), $lead['email']);
        lead_note($id, 'system', $res === true ? 'Notification email sent to ' . $recipients . '.' : 'Notification email failed: ' . $res);
    }
    try { telegram_lead_alert($lead); } catch (Throwable $e) { error_log('telegram lead alert: ' . $e->getMessage()); }
    if (setting('lead_autoreply_enabled') && $lead['email']) {
        $msg = (string) setting('lead_autoreply_text', "Thank you for contacting us. We have received your enquiry and will respond within one business day.");
        $inner = '<p style="font-size:14px;line-height:1.6">Dear ' . e($lead['name']) . ',</p><p style="font-size:14px;line-height:1.6;white-space:pre-wrap">' . e($msg) . '</p>';
        send_mail($lead['email'], (string) setting('lead_autoreply_subject', 'We received your enquiry'), mail_layout('Thank you for your enquiry', $inner));
    }
}


/* ------------------------------------------------------------------ */
/* Service / breakdown requests                                        */
/* ------------------------------------------------------------------ */

function service_ref(array|int $lead): string
{
    return 'SR-' . str_pad((string) (is_array($lead) ? $lead['id'] : $lead), 5, '0', STR_PAD_LEFT);
}

function service_track_url(array $lead): string
{
    return abs_url('service-request/track/' . $lead['track_token']);
}

/** After a service request: urgent ones alert the whole sales/service team at once; the customer gets their tracking link. */
function service_after_submit(int $id): array
{
    $lead = db_one('SELECT * FROM leads WHERE id = ?', [$id]);
    $ref = service_ref($lead);
    $url = service_track_url($lead);
    if ($lead['priority'] === 'high') {
        $roles = PERMISSIONS['leads'] ?? ['admin'];
        $in = implode(',', array_fill(0, count($roles), '?'));
        foreach (db_all("SELECT * FROM users WHERE active = 1 AND role IN ({$in})", $roles) as $u) {
            notify_user($u, 'service_urgent', 'URGENT breakdown ' . $ref . ': ' . $lead['product'],
                $lead['name'] . ($lead['company'] ? ' (' . $lead['company'] . ')' : '') . ' at ' . $lead['location'] . ' — ' . $lead['phone'] . '. ' . mb_strimwidth((string) $lead['message'], 0, 300, '…'),
                ['severity' => 'critical', 'category' => 'admin', 'link' => 'admin/leads/' . $id, 'email' => true]);
        }
    }
    if ($lead['email']) {
        $inner = '<p style="font-size:14px;line-height:1.6">Dear ' . e($lead['name']) . ',</p>'
            . '<p style="font-size:14px;line-height:1.6">We have received your service request <b>' . e($ref) . '</b> for <b>' . e($lead['product']) . '</b>' . ($lead['priority'] === 'high' ? ' and marked it urgent' : '') . '. An engineer will contact you shortly.</p>'
            . '<p style="margin:22px 0"><a href="' . e($url) . '" style="background:#F28C28;color:#101D32;padding:12px 22px;text-decoration:none;font-weight:bold">Track your request</a></p>'
            . '<p style="font-size:13px;color:#6b7280">Keep this link — it shows the current status and our updates. For emergencies call ' . e(company('phone')) . '.</p>';
        send_mail((string) $lead['email'], 'Service request ' . $ref . ' received', mail_layout('Service request ' . $ref, $inner));
    }
    return ['ref' => $ref, 'track' => $url];
}

/** Update a ticket's status with a message the customer can see (tracking page + email). */
function service_update(array $lead, string $status, string $message, ?int $userId): void
{
    if (!isset(SERVICE_STATUSES[$status])) return;
    db_update('leads', ['service_status' => $status] + (in_array($status, ['resolved', 'closed'], true) && in_array($lead['status'], LEAD_OPEN, true) ? ['status' => 'won'] : []), (int) $lead['id']);
    $text = SERVICE_STATUSES[$status][0] . ($message !== '' ? ' — ' . $message : '');
    lead_note((int) $lead['id'], 'service', $text, $userId); // 'service' notes are shown on the public tracking page
    if ($lead['email'] && $lead['track_token']) {
        $inner = '<p style="font-size:14px;line-height:1.6">Dear ' . e((string) $lead['name']) . ',</p>'
            . '<p style="font-size:14px;line-height:1.6">Update on service request <b>' . e(service_ref($lead)) . '</b>: <b>' . e(SERVICE_STATUSES[$status][0]) . '</b>.</p>'
            . ($message !== '' ? '<p style="font-size:14px;line-height:1.6;white-space:pre-wrap;border-left:4px solid #F28C28;padding-left:12px">' . e($message) . '</p>' : '')
            . '<p style="margin:22px 0"><a href="' . e(service_track_url($lead)) . '" style="background:#F28C28;color:#101D32;padding:12px 22px;text-decoration:none;font-weight:bold">View your request</a></p>';
        send_mail((string) $lead['email'], 'Service request ' . service_ref($lead) . ': ' . SERVICE_STATUSES[$status][0], mail_layout('Service request update', $inner));
    }
}

/** Job applications go to the careers contact (or administrators), never to the sales alert list. */
function job_application_notify(int $id): void
{
    $lead = db_one('SELECT l.*, j.title AS job_title FROM leads l LEFT JOIN jobs j ON j.id = l.job_id WHERE l.id = ?', [$id]);
    if (!$lead) return;
    $text = $lead['name'] . ' applied for ' . ($lead['job_title'] ?: 'an open position') . ' (' . $lead['email'] . ', ' . $lead['phone'] . ').';
    $to = trim((string) setting('careers_email', ''));
    if ($to !== '') {
        send_mail($to, 'New application: ' . ($lead['job_title'] ?: 'Careers'), mail_layout('New job application',
            '<p style="font-size:14px">' . e($text) . '</p><p><a href="' . e(abs_url('admin/careers/applications/' . $id)) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Open application</a></p>'), (string) $lead['email']);
    }
    notify_admins('job_application', 'New job application', $text, ['severity' => 'info', 'category' => 'admin', 'link' => 'admin/careers/applications/' . $id, 'email' => $to === '' ? null : false]);
}
