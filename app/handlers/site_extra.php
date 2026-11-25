<?php
/** Public: projects (case studies), datasheet & catalogue downloads, URL cron. */
declare(strict_types=1);
require_once __DIR__ . '/site.php';

/* ================================================================== */
/* PROJECTS                                                            */
/* ================================================================== */

function site_projects(): void
{
    $industries = db_all("SELECT DISTINCT i.id, i.name, i.slug FROM industries i JOIN projects p ON p.industry_id = i.id AND p.status = 'published' ORDER BY i.sort_order, i.name");
    $active = null;
    if ($slug = (string) query('industry', '')) {
        foreach ($industries as $i) if ($i['slug'] === $slug) $active = $i;
    }
    $projects = projects_published($active ? (int) $active['id'] : null);
    if (!$projects && !$active) abort(404); // section appears once the first project is published
    $meta = [
        'title' => 'Projects & Case Studies' . ($active ? ' — ' . $active['name'] : ''),
        'description' => 'Real projects: crane components and hydraulic systems we have engineered, manufactured and delivered' . ($active ? ' for ' . strtolower($active['name']) : '') . ' — the challenge, our solution and the results.',
        'canonical' => abs_url('projects' . ($active ? '?industry=' . $active['slug'] : '')),
        'section' => 'projects',
        'crumbs' => [['label' => 'Projects', 'url' => url('projects')]],
    ];
    $meta['edit'] = ['Manage projects', 'admin/r/projects', 'content'];
    site_render($meta, view('site/projects', compact('projects', 'industries', 'active', 'meta')));
}

function site_project(string $slug): void
{
    $p = db_one('SELECT p.*, i.name AS industry, i.slug AS industry_slug FROM projects p LEFT JOIN industries i ON i.id = p.industry_id WHERE p.slug = ?', [$slug]);
    if (!$p || ($p['status'] !== 'published' && !can_preview())) abort(404);
    $ids = array_map('intval', json_decode((string) $p['product_ids'], true) ?: []);
    $products = array_values(array_filter(products_all(), fn($x) => in_array((int) $x['id'], $ids, true)));
    $results = array_values(array_filter(json_decode((string) $p['results'], true) ?: [], fn($r) => trim((string) ($r['value'] ?? '')) !== ''));
    $more = array_values(array_filter(projects_published(null, 4), fn($x) => (int) $x['id'] !== (int) $p['id']));
    $meta = [
        'title' => $p['meta_title'] ?: $p['title'],
        'description' => $p['meta_description'] ?: str_limit((string) $p['summary'], 160),
        'canonical' => abs_url('projects/' . $p['slug']),
        'og_image' => $p['image'],
        'og_type' => 'article',
        'noindex' => $p['status'] !== 'published',
        'section' => 'projects',
        'crumbs' => [['label' => 'Projects', 'url' => url('projects')], ['label' => $p['title'], 'url' => url('projects/' . $p['slug'])]],
        'jsonld' => [array_filter(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $p['title'], 'description' => (string) $p['summary'],
            'image' => $p['image'] ? image_abs_url($p['image']) : null, 'datePublished' => $p['created_at'] ? date('c', strtotime($p['created_at'])) : null,
            'dateModified' => $p['updated_at'] ? date('c', strtotime($p['updated_at'])) : null, 'about' => $p['industry'] ?: null,
            'author' => ['@type' => 'Organization', 'name' => company('name')], 'publisher' => ['@type' => 'Organization', 'name' => company('name')],
            'mainEntityOfPage' => abs_url('projects/' . $p['slug'])])],
    ];
    $meta['edit'] = ['Edit project', 'admin/r/projects/' . $p['id'] . '/edit', 'content'];
    site_render($meta, view('site/project', ['p' => $p, 'products' => $products, 'results' => array_slice($results, 0, 4), 'more' => array_slice($more, 0, 3), 'meta' => $meta]));
}

/* ================================================================== */
/* DOWNLOADS (datasheets + catalogue)                                  */
/* ================================================================== */

const DOWNLOAD_ACCESS_DAYS = 30;

function download_access_ok(): bool
{
    $raw = (string) ($_COOKIE['dl_access'] ?? '');
    [$exp, $sig] = array_pad(explode('.', $raw, 2), 2, '');
    return ctype_digit($exp) && (int) $exp > time() && hash_equals(hash_hmac('sha256', 'dl|' . $exp, (string) config('app_key')), $sig);
}

function download_grant_access(): void
{
    $exp = time() + DOWNLOAD_ACCESS_DAYS * 86400;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    setcookie('dl_access', $exp . '.' . hash_hmac('sha256', 'dl|' . $exp, (string) config('app_key')), ['expires' => $exp, 'path' => base_path() ?: '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}

/** Stream a stored PDF (local uploads only) or redirect to an external URL. */
function download_send(string $value, string $downloadName, string $event, string $page): never
{
    if (rate_limit(ip_bucket('dl-' . $event), 60, 3600)) db_insert('events', ['type' => $event, 'page' => mb_substr($page, 0, 500)]);
    if (preg_match('~^https?://~i', $value)) { header('Location: ' . $value, true, 302); exit; }
    $root = realpath(PUBLIC_DIR . '/uploads');
    $file = realpath(PUBLIC_DIR . '/' . ltrim($value, '/'));
    if (!$root || !$file || !str_starts_with($file, $root) || !is_file($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') abort(404);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . (slugify($downloadName) ?: 'document') . '.pdf"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($file);
    exit;
}

function site_datasheet(string $slug): void
{
    $p = null;
    foreach (products_all() as $row) if ($row['slug'] === $slug) $p = $row;
    if (!$p || empty($p['datasheet'])) abort(404);
    if (setting('datasheets_gated', false) && !download_access_ok()) redirect('catalogue?next=' . rawurlencode('download/datasheet/' . $slug));
    download_send((string) $p['datasheet'], $p['name'] . ' datasheet', 'datasheet', $p['name']);
}

/** Catalogue: instant download, or a short form first when lead capture is on. */
function site_catalogue(): void
{
    $pdf = (string) setting('catalogue_pdf', '');
    $title = (string) setting('catalogue_title', 'Product catalogue') ?: 'Product catalogue';
    $next = (string) (query('next') ?? input('next', ''));
    if (!preg_match('~^download/datasheet/[a-z0-9-]+$~', $next)) $next = '';
    if ($pdf === '' && $next === '') abort(404);
    $gated = $next !== '' ? (bool) setting('datasheets_gated', false) : (bool) setting('catalogue_gated', true);
    if (!$gated || download_access_ok()) {
        if ($next !== '') redirect($next);
        download_send($pdf, $title, 'catalogue', $title);
    }
    $errors = [];
    $message = null;
    $old = [];
    if (is_post()) {
        $old = $_POST;
        $res = download_lead_submit($_POST, $next !== '' ? 'Datasheet: ' . basename($next) : $title);
        if ($res === true) {
            download_grant_access();
            redirect($next !== '' ? $next : 'catalogue');
        }
        [$message, $errors] = $res;
    }
    $meta = ['title' => $next !== '' ? 'Download datasheet' : 'Download the ' . $title, 'description' => 'Download our ' . strtolower($title) . ' with specifications for crane components and hydraulic products.',
        'canonical' => abs_url('catalogue'), 'noindex' => $next !== '', 'section' => 'products', 'crumbs' => [['label' => 'Products', 'url' => url('products')], ['label' => $title, 'url' => url('catalogue')]]];
    site_render($meta, view('site/catalogue', compact('title', 'errors', 'message', 'old', 'next', 'meta')), $errors || $message ? 422 : 200);
}

/** @return true|array{0:?string,1:array} */
function download_lead_submit(array $in, string $what): bool|array
{
    if (!empty($in['website'])) return true; // honeypot: pretend success
    if (!form_token_valid((string) ($in['_ft'] ?? ''))) return ['The form expired. Please refresh the page and try again.', []];
    if (!turnstile_verify((string) ($in['cf-turnstile-response'] ?? ''))) return ['Security check failed. Please complete the verification and try again.', []];
    if (!rate_limit(ip_bucket('download-lead'), 8, 3600)) return ['Too many requests. Please try again later.', []];
    $v = fn($k, $max = 190) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);
    $errors = [];
    if (mb_strlen($v('fullName')) < 2) $errors['fullName'] = 'Please enter your name.';
    if (!filter_var($v('email'), FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if ($v('company') === '') $errors['company'] = 'Please enter your company.';
    if (empty($in['consent'])) $errors['consent'] = 'Please confirm to continue.';
    if ($errors) return ['Please correct the highlighted fields.', $errors];
    // One lead per email + document per day (repeat downloads don't create duplicates)
    $dup = db_val("SELECT id FROM leads WHERE type = 'download' AND email = ? AND product = ? AND created_at >= NOW() - INTERVAL 1 DAY", [$v('email'), mb_substr($what, 0, 190)]);
    if (!$dup) {
        $utm = [];
        parse_str((string) parse_url((string) ($in['_landing'] ?? ''), PHP_URL_QUERY), $utm);
        $id = db_insert('leads', ['type' => 'download', 'priority' => 'low', 'name' => $v('fullName', 150), 'company' => $v('company'), 'email' => $v('email'), 'phone' => $v('phone', 50),
            'product' => mb_substr($what, 0, 190), 'message' => 'Downloaded: ' . $what, 'source_page' => mb_substr((string) ($in['_page'] ?? ''), 0, 500),
            'referrer' => mb_substr((string) ($in['_referrer'] ?? ''), 0, 500) ?: null, 'utm_source' => mb_substr((string) ($utm['utm_source'] ?? ''), 0, 120) ?: null,
            'utm_medium' => mb_substr((string) ($utm['utm_medium'] ?? ''), 0, 120) ?: null, 'utm_campaign' => mb_substr((string) ($utm['utm_campaign'] ?? ''), 0, 120) ?: null,
            'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), 'consent' => 1]);
        lead_note($id, 'system', 'Download lead: ' . $what . '. Low priority — follow up to offer help with selection or a quotation.');
    }
    return true;
}

/* ================================================================== */
/* URL CRON                                                            */
/* ================================================================== */

function site_cron(string $key): void
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $expected = (string) setting('cron_key', '');
    if (strlen($expected) < 32 || !hash_equals($expected, $key)) { http_response_code(404); echo "Not found\n"; exit; }
    if (!rate_limit('cron-url', 12, 300)) { http_response_code(429); echo "Too many requests\n"; exit; }
    foreach (cron_run() as $task => $result) echo "{$task}: {$result}\n";
    exit;
}

/* ------------------------------------------------------------------ */
/* Quotations: the customer's private link                             */
/* ------------------------------------------------------------------ */

function quote_by_token(string $token): array
{
    $q = preg_match('~^[a-f0-9]{40}$~', $token) ? db_one('SELECT * FROM quotes WHERE token = ?', [$token]) : null;
    if (!$q || $q['status'] === 'draft' && !site_admin_user()) abort(404); // drafts are visible to the team only
    return $q;
}

function site_quote_view(string $token): void
{
    $q = quote_by_token($token);
    $error = null;
    if (is_post()) {
        if (!rate_limit(ip_bucket('quote-decide'), 10, 3600)) abort(429, '', ['retry_after' => 3600]);
        $status = quote_status($q);
        $name = trim((string) input('name', ''));
        if ($status !== 'sent') {
            $error = $status === 'expired' ? 'This quotation has expired. Please contact us for an updated one.' : 'This quotation has already been ' . $status . '.';
        } elseif (input('decision') === 'accept') {
            if (mb_strlen($name) < 2) $error = 'Please type your full name to accept.';
            elseif (empty($_POST['agree'])) $error = 'Please confirm that you accept the quotation and its terms.';
            else { quote_decide($q, true, $name); redirect('q/' . $token . '?done=accepted'); }
        } elseif (input('decision') === 'decline') {
            quote_decide($q, false, $name, mb_substr(trim((string) input('reason', '')), 0, 2000));
            redirect('q/' . $token . '?done=declined');
        }
        $q = quote_by_token($token);
    } elseif (!$q['viewed_at'] && !site_admin_user()) {
        db_update('quotes', ['viewed_at' => now()], (int) $q['id']); // first time the customer opened it
    }
    $meta = ['title' => 'Quotation ' . $q['number'], 'description' => '', 'noindex' => true, 'crumbs' => []];
    header('Referrer-Policy: no-referrer'); // the link itself is the key
    site_render($meta, view('site/quote_view', ['q' => $q, 'items' => json_decode((string) $q['items'], true) ?: [], 'error' => $error, 'done' => (string) query('done', '')]));
}

function site_quote_pdf(string $token): void
{
    $q = quote_by_token($token);
    $pdf = quote_pdf($q);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Quotation-' . $q['number'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    echo $pdf;
    exit;
}


/* ------------------------------------------------------------------ */
/* Service / breakdown requests                                        */
/* ------------------------------------------------------------------ */

function site_service_request(): void
{
    $meta = ['title' => 'Breakdown & service request', 'description' => 'Report a crane or hydraulic breakdown or book a service visit. Get a reference number and follow your request online.',
        'canonical' => abs_url('service-request'), 'crumbs' => [['label' => 'Service request', 'url' => url('service-request')]]];
    site_render($meta, view('site/service_request', ['meta' => $meta]));
}

function site_service_track(string $token): void
{
    $lead = preg_match('~^[a-f0-9]{32}$~', $token) ? db_one("SELECT * FROM leads WHERE track_token = ? AND type = 'service'", [$token]) : null;
    if (!$lead) abort(404);
    $updates = db_all("SELECT body, created_at FROM lead_notes WHERE lead_id = ? AND kind = 'service' ORDER BY id DESC", [(int) $lead['id']]);
    $meta = ['title' => 'Service request ' . service_ref($lead), 'description' => '', 'noindex' => true, 'crumbs' => []];
    header('Referrer-Policy: no-referrer');
    site_render($meta, view('site/service_track', compact('lead', 'updates')));
}


/* ------------------------------------------------------------------ */
/* Engineering calculators                                             */
/* ------------------------------------------------------------------ */

function site_calculators(): void
{
    $calcs = calculators();
    $active = isset($calcs[$c = (string) query('c', '')]) ? $c : '';
    $values = [];
    if ($active) foreach ($calcs[$active]['inputs'] as $k => $def) $values[$k] = is_numeric(query($k)) ? (float) query($k) : $def[2];
    $results = $active ? calc_run($active, $values) : [];
    if ($active && wants_json()) {
        $prod = array_column(products_all(), null, 'slug')[$calcs[$active]['product']] ?? null;
        json_response(['results' => $results, 'quote' => url('quote') . '?' . http_build_query(['product' => $prod['name'] ?? '', 'specs' => calc_summary($active, $values, $results)])]);
    }
    $meta = ['title' => 'Hydraulic calculators — cylinder force, speed and pump power', 'description' => 'Free hydraulic calculators: cylinder push and pull force, extend and retract speed, pump motor power and cylinder bore sizing.',
        'canonical' => abs_url('calculators'), 'noindex' => $active !== '', 'crumbs' => [['label' => 'Calculators', 'url' => url('calculators')]]];
    site_render($meta, view('site/calculators', compact('meta', 'active', 'values', 'results')));
}
