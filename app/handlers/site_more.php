<?php
/**
 * Public: part-number finder, downloads centre, distributor network and careers.
 */
declare(strict_types=1);
require_once __DIR__ . '/site_extra.php'; // site_render(), download gate

/* ------------------------------------------------------------------ */
/* Part-number cross-reference                                         */
/* ------------------------------------------------------------------ */

/** Published cross-references matching a part number (exact first, then "starts with"). */
function part_lookup(string $q, int $limit = 20): array
{
    $norm = part_norm($q);
    if (strlen($norm) < 3) return [];
    $rows = db_all("SELECT * FROM part_refs WHERE status = 'published' AND (ref_norm LIKE ? OR UPPER(REPLACE(REPLACE(REPLACE(our_part, '-', ''), ' ', ''), '.', '')) = ?)
        ORDER BY ref_norm = ? DESC, ref_number LIMIT {$limit}", [$norm . '%', $norm, $norm]);
    $products = array_column(products_all(), null, 'id');
    foreach ($rows as &$r) {
        $r['product'] = $products[(int) $r['product_id']] ?? null;
        $r['exact'] = $r['ref_norm'] === $norm || part_norm((string) $r['our_part']) === $norm;
    }
    return $rows;
}

function site_parts(): void
{
    $q = mb_substr(trim((string) query('q', '')), 0, 80);
    $matches = $q !== '' ? part_lookup($q) : [];
    if ($q !== '' && rate_limit(ip_bucket('part-search'), 120, 3600)) db_insert('events', ['type' => $matches ? 'part_found' : 'part_missing', 'page' => mb_substr($q, 0, 500)]);
    $meta = ['title' => $q !== '' ? 'Part number ' . $q : 'Find a part by part number', 'description' => 'Enter a part number from any brand — original equipment, competitor or old drawing — and find our equivalent crane or hydraulic part.',
        'canonical' => abs_url('parts'), 'noindex' => $q !== '', 'crumbs' => [['label' => 'Part finder', 'url' => url('parts')]]];
    site_render($meta, view('site/parts', compact('meta', 'q', 'matches')));
}

/* ------------------------------------------------------------------ */
/* Downloads centre                                                    */
/* ------------------------------------------------------------------ */

function download_url(array $d): string
{
    return url('download/file/' . (int) $d['id']);
}

function site_downloads(): void
{
    $rows = db_all("SELECT * FROM downloads WHERE status = 'published' ORDER BY sort_order, title");
    $groups = [];
    foreach ($rows as $r) $groups[$r['category']][] = $r;
    $order = ['Brochures', 'Datasheets', 'Manuals', 'CAD drawings', 'Certificates', 'Other'];
    uksort($groups, fn($a, $b) => (array_search($a, $order, true) === false ? 99 : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? 99 : array_search($b, $order, true)));
    $sheets = array_values(array_filter(products_all(), fn($p) => !empty($p['datasheet'])));
    $meta = ['title' => 'Downloads — brochures, manuals, CAD and certificates', 'description' => 'Download product brochures, datasheets, installation and maintenance manuals, CAD drawings and quality certificates.',
        'canonical' => abs_url('downloads'), 'crumbs' => [['label' => 'Downloads', 'url' => url('downloads')]]];
    site_render($meta, view('site/downloads', compact('meta', 'groups', 'sheets')));
}

function site_download_file(string $id): void
{
    $d = ctype_digit($id) ? db_one("SELECT * FROM downloads WHERE id = ? AND status = 'published'", [(int) $id]) : null;
    if (!$d) abort(404);
    if ($d['gated'] && !download_access_ok()) redirect('catalogue?next=' . rawurlencode('download/file/' . $d['id']));
    if (rate_limit(ip_bucket('dl-count-' . $d['id']), 5, 3600)) db_query('UPDATE downloads SET downloads = downloads + 1 WHERE id = ?', [(int) $d['id']]);
    download_send((string) $d['file'], (string) $d['title'], 'download', (string) $d['title']);
}

/* ------------------------------------------------------------------ */
/* Distributor network                                                 */
/* ------------------------------------------------------------------ */

function site_distributors(): void
{
    $all = db_all("SELECT * FROM distributors WHERE status = 'published' ORDER BY region, sort_order, name");
    $regions = array_values(array_unique(array_column($all, 'region')));
    $region = in_array($r = (string) query('region', ''), $regions, true) ? $r : '';
    $groups = [];
    foreach ($all as $d) if ($region === '' || $d['region'] === $region) $groups[$d['region']][] = $d;
    $meta = ['title' => 'Distributors & dealers', 'description' => 'Find an authorised distributor near you for crane components, hydraulic cylinders and spare parts — or apply to become a distributor.',
        'canonical' => abs_url('distributors'), 'noindex' => $region !== '', 'crumbs' => [['label' => 'Distributors', 'url' => url('distributors')]]];
    site_render($meta, view('site/distributors', compact('meta', 'groups', 'regions', 'region', 'all')));
}

/* ------------------------------------------------------------------ */
/* Careers                                                             */
/* ------------------------------------------------------------------ */

function site_careers(): void
{
    $jobs = jobs_open();
    $meta = ['title' => 'Careers — jobs in manufacturing, service and engineering', 'description' => 'Join our team. Current openings in production, field service, engineering and sales.',
        'canonical' => abs_url('careers'), 'crumbs' => [['label' => 'Careers', 'url' => url('careers')]]];
    site_render($meta, view('site/careers', compact('meta', 'jobs')));
}

function site_career(string $slug): void
{
    $job = db_one('SELECT * FROM jobs WHERE slug = ?', [$slug]);
    $open = $job && $job['status'] === 'published' && (!$job['closes_at'] || $job['closes_at'] >= date('Y-m-d'));
    // Drafts: preview for the team only. A closed published opening stays reachable (410) so old links explain themselves.
    if (!$job || ($job['status'] === 'draft' && !can_preview())) abort(404);
    $types = ['Full-time' => 'FULL_TIME', 'Part-time' => 'PART_TIME', 'Contract' => 'CONTRACTOR', 'Apprenticeship' => 'OTHER', 'Internship' => 'INTERN'];
    $ld = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $job['title'], 'description' => $job['description'] ?: e((string) $job['summary']),
        'datePosted' => substr((string) $job['created_at'], 0, 10), 'employmentType' => $types[$job['job_type']] ?? 'FULL_TIME',
        'hiringOrganization' => ['@type' => 'Organization', 'name' => company('name'), 'sameAs' => abs_url('')],
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job['location'] ?: trim((string) strtok(company('address'), "
"))]]];
    if ($job['closes_at']) $ld['validThrough'] = $job['closes_at'] . 'T23:59:59';
    if (($job['remote'] ?? '') === 'remote') $ld['jobLocationType'] = 'TELECOMMUTE';
    if (!empty($job['openings'])) $ld['totalJobOpenings'] = (int) $job['openings'];
    if (!empty($job['experience'])) $ld['experienceRequirements'] = (string) $job['experience'];
    $meta = ['title' => $job['title'] . ' — careers', 'description' => (string) ($job['summary'] ?: 'Apply for ' . $job['title'] . ' at ' . company('name') . '.'),
        'canonical' => abs_url('careers/' . $job['slug']), 'noindex' => !$open, 'jsonld' => $open ? [$ld] : [],
        'crumbs' => [['label' => 'Careers', 'url' => url('careers')], ['label' => $job['title'], 'url' => url('careers/' . $job['slug'])]]];
    site_render($meta, view('site/career', compact('meta', 'job', 'open')), $open || $job['status'] === 'draft' ? 200 : 410);
}

/* ------------------------------------------------------------------ */
/* Site search                                                         */
/* ------------------------------------------------------------------ */

function site_search_page(): void
{
    $q = mb_substr(trim((string) query('q', '')), 0, 100);
    $found = $q !== '' ? site_search($q) : ['results' => [], 'parts' => [], 'suggestion' => null];
    if ($q !== '' && rate_limit(ip_bucket('search-log'), 120, 3600)) db_insert('events', ['type' => $found['results'] || $found['parts'] ? 'search' : 'search_empty', 'page' => mb_substr($q, 0, 500)]);
    $meta = ['title' => $q !== '' ? 'Search: ' . $q : 'Search', 'description' => 'Search products, part numbers, articles, projects, downloads and jobs.',
        'canonical' => abs_url('search'), 'noindex' => true, 'crumbs' => [['label' => 'Search', 'url' => url('search')]]];
    site_render($meta, view('site/search', compact('meta', 'q') + $found));
}

/* ------------------------------------------------------------------ */
/* Short links                                                         */
/* ------------------------------------------------------------------ */

function site_short_link(string $code): void
{
    $link = short_code_valid($code) ? db_one('SELECT * FROM short_links WHERE code = ?', [$code]) : null;
    if (!$link) abort(404);
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    // Count people, not link-preview robots (WhatsApp, Slack, LinkedIn… fetch the link once to build a preview)
    if (!preg_match('~bot|crawl|spider|preview|facebookexternalhit|whatsapp|slack|telegram|linkedin|embedly|curl|wget~i', $ua) && rate_limit(ip_bucket('s-' . $link['id']), 3, 3600)) {
        db_query('UPDATE short_links SET clicks = clicks + 1, last_click_at = NOW() WHERE id = ?', [(int) $link['id']]);
    }
    $to = str_starts_with((string) $link['target'], '/') ? abs_url(ltrim((string) $link['target'], '/')) : (string) $link['target'];
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Location: ' . $to, true, 301);
    exit;
}

/* ------------------------------------------------------------------ */
/* Blog comments                                                       */
/* ------------------------------------------------------------------ */

function site_comment_submit(string $slug): void
{
    $post = db_one('SELECT p.* FROM posts p WHERE p.slug = ? AND ' . POST_VISIBLE, [$slug]);
    if (!$post) abort(404);
    $back = fn(string $state, ?int $id = null) => redirect('blog/' . $post['slug'] . '?comment=' . $state . '#' . ($id ? 'comment-' . $id : 'comments'));
    $fail = function (string $msg) use ($post) {
        $meta = ['title' => 'Comment not posted', 'description' => '', 'noindex' => true, 'crumbs' => []];
        site_render($meta, '<section class="section"><div class="mx-auto max-w-2xl px-4"><h1 class="font-display text-4xl font-bold uppercase text-navy">Comment not posted</h1><p class="mt-4 text-charcoal/80">' . e($msg)
            . '</p><a class="btn btn-primary mt-8" href="' . e(url('blog/' . $post['slug']) . '#comment-form') . '">Back to the article</a></div></section>', 422);
        exit;
    };
    if (!comments_open($post)) $fail('Comments are closed for this article.');
    if (!empty($_POST['website'])) $back('pending'); // honeypot: pretend it worked

    $staff = site_admin_user();
    if ($staff) csrf_check();
    else {
        if (!form_token_valid((string) ($_POST['_ft'] ?? ''))) $fail('The form expired or was sent too quickly. Please go back, wait a moment and try again.');
        if (!turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) $fail('The security check failed. Please try again.');
    }
    if (!rate_limit(ip_bucket('comment'), 5, 600)) $fail('Too many comments in a short time. Please wait a few minutes.');

    $body = trim(str_replace("\r\n", "\n", (string) ($_POST['comment'] ?? '')));
    $name = $staff ? (string) $staff['name'] : mb_substr(trim((string) ($_POST['fullName'] ?? '')), 0, 120);
    $email = $staff ? (string) $staff['email'] : mb_strtolower(mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190));
    if (!$staff && mb_strlen($name) < 2) $fail('Please enter your name.');
    if (!$staff && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email address (it is not published).');
    if (!$staff && empty($_POST['consent'])) $fail('Please confirm that your name and comment may be published.');
    if (mb_strlen($body) < 3) $fail('Please write a comment.');
    if (mb_strlen($body) > 5000) $fail('Comments can be up to 5,000 characters.');

    if (!$staff && !form_token_consume((string) ($_POST['_ft'] ?? ''))) $back('pending'); // already sent
    // Replies attach to the top-level comment (one level of threading)
    $parent = null;
    if (ctype_digit((string) ($_POST['parent_id'] ?? ''))) {
        $parent = db_one("SELECT id, parent_id FROM comments WHERE id = ? AND post_id = ? AND status = 'approved'", [(int) $_POST['parent_id'], (int) $post['id']]);
        if ($parent && $parent['parent_id']) $parent['id'] = $parent['parent_id'];
    }
    $spam = $staff ? null : comment_spam_reason($body, $name, $email);
    $status = $staff ? 'approved' : ($spam ? 'spam' : comment_initial_status($email));
    $id = db_insert('comments', ['post_id' => (int) $post['id'], 'parent_id' => $parent['id'] ?? null, 'user_id' => $staff['id'] ?? null, 'name' => $name, 'email' => $email ?: null,
        'body' => $body, 'status' => $status, 'spam_reason' => $spam, 'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'approved_at' => $status === 'approved' ? now() : null, 'approved_by' => $staff['id'] ?? null]);

    if (!$staff && !empty($_POST['newsletter']) && function_exists('newsletter_subscribe')) newsletter_subscribe($email, $name, 'comment');
    if ($status === 'pending' && setting('comments_notify', true)) {
        foreach (db_all("SELECT * FROM users WHERE role IN ('admin', 'editor') AND active = 1") as $u) {
            notify_user($u, 'comment_pending', 'New comment to approve', $name . ' commented on “' . $post['title'] . '”: ' . mb_substr($body, 0, 140), ['link' => 'admin/comments?status=pending', 'category' => 'reviews']);
        }
    }
    $status === 'approved' ? $back('posted', $id) : $back('pending');
}

/* ------------------------------------------------------------------ */
/* Newsletter (public)                                                 */
/* ------------------------------------------------------------------ */

function newsletter_page(string $title, string $html, int $status = 200): never
{
    $meta = ['title' => $title, 'description' => '', 'noindex' => true, 'crumbs' => []];
    header('Referrer-Policy: no-referrer');
    site_render($meta, '<section class="section"><div class="mx-auto max-w-2xl px-4 text-center sm:px-6" data-newsletter-page><span class="mx-auto grid h-16 w-16 place-items-center bg-navy text-safety">' . icon('mail-check', 'h-8 w-8') . '</span>'
        . '<h1 class="mt-6 font-display text-4xl font-bold uppercase tracking-wide text-navy">' . e($title) . '</h1>' . $html . '</div></section>', $status);
    exit;
}

function site_newsletter_subscribe(): void
{
    $json = wants_json();
    $reply = function (bool $ok, string $msg, int $code = 200) use ($json) {
        if ($json) json_response(['ok' => $ok, 'message' => $msg, 'ft' => form_token()], $code); // a fresh token for another sign-up
        newsletter_page($ok ? 'Almost there' : 'Subscription not completed', '<p class="mt-4 text-lg text-charcoal/80">' . e($msg) . '</p><a class="btn btn-primary mt-8" href="' . e(url('')) . '">Back to the website</a>', $code);
    };
    if (!newsletter_enabled()) $reply(false, 'The newsletter is not available right now.', 404);
    if (!empty($_POST['website'])) $reply(true, 'Thank you! Please check your inbox to confirm.'); // honeypot
    if (!form_token_valid((string) ($_POST['_ft'] ?? ''))) $reply(false, 'The form expired. Please refresh the page and try again.', 422);
    if (!turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) $reply(false, 'The security check did not complete. Please try again.', 422);
    if (!rate_limit(ip_bucket('newsletter'), 5, 3600)) $reply(false, 'Too many attempts. Please try again later.', 429);
    if (!filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL)) $reply(false, 'Please enter a valid email address.', 422);
    if (!form_token_consume((string) ($_POST['_ft'] ?? ''))) $reply(true, 'Thank you! Please check your inbox and click the link to confirm your subscription.');
    $src = preg_match('~^[a-z]{2,20}$~', (string) ($_POST['source'] ?? '')) ? (string) $_POST['source'] : 'website';
    $r = newsletter_subscribe((string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''), $src);
    match ($r) {
        'invalid' => $reply(false, 'Please enter a valid email address.', 422),
        'subscribed' => $reply(true, 'Thank you — you are subscribed.'),
        default => $reply(true, 'Thank you! Please check your inbox and click the link to confirm your subscription.'), // also for 'already': no list disclosure
    };
}

function site_newsletter_confirm(string $token): void
{
    $sub = newsletter_by_token($token);
    if (!$sub) newsletter_page('Link not valid', '<p class="mt-4 text-charcoal/80">This confirmation link is not valid. Please subscribe again.</p>', 404);
    if ($sub['status'] !== 'subscribed') {
        db_update('subscribers', ['status' => 'subscribed', 'confirmed_at' => now(), 'unsubscribed_at' => null], (int) $sub['id']);
        newsletter_send_welcome(['status' => 'subscribed'] + $sub);
    }
    newsletter_page('You are subscribed', '<p class="mt-4 text-lg text-charcoal/80">Thank you for confirming. The next issue goes to <strong>' . e((string) $sub['email']) . '</strong>.</p><a class="btn btn-primary mt-8" href="' . e(url('blog')) . '">Read the latest articles</a>');
}

/** GET shows a confirm button (mail scanners follow links); POST unsubscribes — also the RFC 8058 one-click POST from mail apps. */
function site_newsletter_unsubscribe(string $token): void
{
    $sub = newsletter_by_token($token);
    if (!$sub) newsletter_page('Link not valid', '<p class="mt-4 text-charcoal/80">This unsubscribe link is not valid.</p>', 404);
    if (is_post()) {
        if ($sub['status'] !== 'unsubscribed') {
            db_update('subscribers', ['status' => 'unsubscribed', 'unsubscribed_at' => now()], (int) $sub['id']);
            content_log('subscriber', (int) $sub['id'], 'updated', (string) $sub['email'], 'Unsubscribed');
        }
        if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') { http_response_code(200); echo "Unsubscribed\n"; exit; }
        newsletter_page('You are unsubscribed', '<p class="mt-4 text-lg text-charcoal/80"><strong>' . e((string) $sub['email']) . '</strong> will not receive the newsletter any more.</p>'
            . '<p class="mt-6 text-sm text-charcoal/60">Changed your mind? <a class="font-semibold text-steel underline" href="' . e(url('newsletter/confirm/' . $sub['token'])) . '">Subscribe again</a>.</p>');
    }
    if ($sub['status'] === 'unsubscribed') newsletter_page('Already unsubscribed', '<p class="mt-4 text-charcoal/80">' . e((string) $sub['email']) . ' is not on the list.</p>');
    newsletter_page('Unsubscribe?', '<p class="mt-4 text-lg text-charcoal/80">Stop sending the newsletter to <strong>' . e((string) $sub['email']) . '</strong>?</p>'
        . '<form method="post" class="mt-8"><button class="btn btn-primary">' . icon('mail-x', 'h-4 w-4') . ' Yes, unsubscribe me</button></form>');
}

/** Open tracking pixel. */
function site_newsletter_open(string $cid, string $token): void
{
    $sub = newsletter_by_token($token);
    if ($sub && ctype_digit($cid)) db_query('UPDATE campaign_sends SET opened_at = COALESCE(opened_at, NOW()) WHERE campaign_id = ? AND subscriber_id = ?', [(int) $cid, (int) $sub['id']]);
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, private');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

/** Click tracking: only signed links redirect (prevents an open redirect). */
function site_newsletter_click(string $cid, string $token): void
{
    $url = b64url_decode((string) query('u', ''));
    $sig = (string) query('s', '');
    if (!ctype_digit($cid) || !preg_match('~^https?://~i', $url) || !hash_equals(campaign_sig((int) $cid, $token, $url), $sig)) abort(404);
    $sub = newsletter_by_token($token);
    if ($sub) db_query('UPDATE campaign_sends SET clicked_at = COALESCE(clicked_at, NOW()), opened_at = COALESCE(opened_at, NOW()) WHERE campaign_id = ? AND subscriber_id = ?', [(int) $cid, (int) $sub['id']]);
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit;
}

/* ------------------------------------------------------------------ */
/* Customer reviews                                                    */
/* ------------------------------------------------------------------ */

function site_reviews(): void
{
    $s = reviews_stats(null);
    $meta = ['title' => 'Customer reviews', 'description' => 'Reviews from engineers and buyers who ordered crane components and hydraulic products from ' . company('name') . '.',
        'canonical' => abs_url('reviews'), 'crumbs' => [['label' => 'Reviews', 'url' => url('reviews')]]];
    $hero = page_hero(['eyebrow' => 'Reviews', 'title' => 'Customer reviews', 'lead' => $s['count'] ? number_format($s['avg'], 1) . ' out of 5 from ' . $s['count'] . ' verified and published reviews.' : 'Honest feedback from the people who use our parts.', 'crumbs' => $meta['crumbs']]);
    site_render($meta, $hero . view('site/_reviews', ['product' => null]));
}

function site_review_submit(): void
{
    $pid = ctype_digit((string) ($_POST['product_id'] ?? '')) && (int) $_POST['product_id'] > 0 ? (int) $_POST['product_id'] : null;
    $prod = $pid ? (array_column(products_all(), null, 'id')[$pid] ?? null) : null;
    if ($pid && !$prod) abort(404);
    $backUrl = $prod ? 'products/' . $prod['slug'] : 'reviews';
    $fail = function (string $msg) use ($backUrl) {
        $meta = ['title' => 'Review not sent', 'description' => '', 'noindex' => true, 'crumbs' => []];
        site_render($meta, '<section class="section"><div class="mx-auto max-w-2xl px-4"><h1 class="font-display text-4xl font-bold uppercase text-navy">Review not sent</h1><p class="mt-4 text-charcoal/80">' . e($msg)
            . '</p><a class="btn btn-primary mt-8" href="' . e(url($backUrl) . '#write-review') . '">Back</a></div></section>', 422);
        exit;
    };
    if (!reviews_enabled()) $fail('Reviews are not open at the moment.');
    if (!empty($_POST['website'])) redirect($backUrl . '?review=pending#reviews');
    if (!form_token_valid((string) ($_POST['_ft'] ?? ''))) $fail('The form expired or was sent too quickly. Please go back, wait a moment and try again.');
    if (!turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) $fail('The security check failed. Please try again.');
    if (!rate_limit(ip_bucket('review'), 3, 3600)) $fail('Too many reviews from your connection. Please try again later.');
    $rating = (int) ($_POST['rating'] ?? 0);
    $v = fn($k, $max) => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST[$k] ?? ''))), 0, $max);
    $body = $v('body', 3000);
    $email = mb_strtolower($v('email', 190));
    if ($rating < 1 || $rating > 5) $fail('Please choose a star rating.');
    if (mb_strlen($body) < 10) $fail('Please write a few words about your experience (at least 10 characters).');
    if (mb_strlen($v('fullName', 120)) < 2) $fail('Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email address (it is not published).');
    if (empty($_POST['consent'])) $fail('Please confirm that your review may be published.');
    if (db_val("SELECT 1 FROM reviews WHERE email = ? AND COALESCE(product_id, 0) = ? AND created_at >= NOW() - INTERVAL 30 DAY", [$email, (int) $pid])) $fail('You already reviewed this recently — thank you!');
    if (!form_token_consume((string) ($_POST['_ft'] ?? ''))) redirect($backUrl . '?review=pending#reviews'); // already sent
    $spam = comment_spam_reason($body, $v('fullName', 120), $email);
    $verified = review_is_verified($email);
    $auto = setting('reviews_moderation', 'all') === 'verified' && $verified && !$spam;
    $id = db_insert('reviews', ['product_id' => $pid, 'rating' => $rating, 'title' => $v('title', 150) ?: null, 'body' => $body, 'name' => $v('fullName', 120), 'company' => $v('company', 150) ?: null,
        'email' => $email, 'verified' => (int) $verified, 'status' => $spam ? 'rejected' : ($auto ? 'approved' : 'pending'), 'ip' => client_ip(), 'approved_at' => $auto ? now() : null]);
    if (!$spam && !$auto) {
        foreach (db_all("SELECT * FROM users WHERE role IN ('admin', 'editor') AND active = 1") as $u) {
            notify_user($u, 'review_pending', 'New ' . $rating . '-star review to approve', $v('fullName', 120) . ($prod ? ' reviewed ' . $prod['name'] : ' reviewed the company') . ': ' . mb_substr($body, 0, 140),
                ['link' => 'admin/reviews-moderation?status=pending', 'category' => 'reviews', 'severity' => $rating <= 2 ? 'warning' : 'info']);
        }
    }
    redirect($backUrl . '?review=pending#reviews');
}

/* ------------------------------------------------------------------ */
/* Appointment booking                                                 */
/* ------------------------------------------------------------------ */

function appointment_by_token(string $token): ?array
{
    return preg_match('~^[a-f0-9]{32}$~', $token) ? db_one('SELECT a.*, t.name AS type_name, t.slug AS type_slug FROM appointments a LEFT JOIN appointment_types t ON t.id = a.type_id WHERE a.token = ?', [$token]) : null;
}

function site_book(): void
{
    if (!booking_enabled()) abort(404);
    $types = booking_types();
    $type = null;
    foreach ($types as $t) if ($t['slug'] === (string) query('type', '')) $type = $t;
    $resched = query('reschedule') ? appointment_by_token((string) query('reschedule')) : null;
    if ($resched && !in_array($resched['status'], ['pending', 'confirmed'], true)) $resched = null;
    if ($resched && !$type) foreach ($types as $t) if ((int) $t['id'] === (int) $resched['type_id']) $type = $t;
    $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) query('date', '')) ? (string) query('date') : null;
    $days = $type ? booking_days($type) : [];
    if ($type && !$date && $days) $date = (string) array_key_first($days);
    $slots = $type && $date ? booking_slots($type, $date, $resched['id'] ?? null) : [];
    $time = (string) query('time', '');
    $slot = null;
    foreach ($slots as $s) if (date('Hi', $s) === $time) $slot = $s;
    $meta = ['title' => 'Book an appointment — site visit, factory visit or video call', 'description' => 'Book a site inspection, a factory visit or a video call with our engineers. Choose a free time and get a calendar invite straight away.',
        'canonical' => abs_url('book'), 'noindex' => (bool) ($type || $resched), 'crumbs' => [['label' => 'Book an appointment', 'url' => url('book')]]];
    site_render($meta, view('site/book', compact('meta', 'types', 'type', 'days', 'date', 'slots', 'slot', 'resched')));
}

function site_book_submit(): void
{
    if (!booking_enabled()) abort(404);
    $fail = function (string $msg, int $code = 422) {
        $meta = ['title' => 'Booking not completed', 'description' => '', 'noindex' => true, 'crumbs' => []];
        site_render($meta, '<section class="section"><div class="mx-auto max-w-2xl px-4"><h1 class="font-display text-4xl font-bold uppercase text-navy">Booking not completed</h1><p class="mt-4 text-charcoal/80">' . e($msg)
            . '</p><a class="btn btn-primary mt-8" href="' . e(url('book')) . '">Choose another time</a></div></section>', $code);
        exit;
    };
    $type = null;
    foreach (booking_types() as $t) if ((int) $t['id'] === (int) ($_POST['type_id'] ?? 0)) $type = $t;
    if (!$type) $fail('Please choose what you would like to book.');
    $resched = !empty($_POST['reschedule']) ? appointment_by_token((string) $_POST['reschedule']) : null;
    if (!empty($_POST['reschedule']) && (!$resched || !in_array($resched['status'], ['pending', 'confirmed'], true))) $fail('This booking can no longer be changed.');
    $start = ctype_digit((string) ($_POST['start'] ?? '')) ? (int) $_POST['start'] : 0;
    if (!$start || !in_array($start, booking_slots($type, date('Y-m-d', $start), $resched['id'] ?? null), true)) $fail('Sorry — that time was just taken or is no longer available. Please choose another time.', 409);
    $end = $start + max(15, (int) $type['duration_min']) * 60;

    if ($resched) {
        // Rescheduling needs only the private link (already proven by the token)
        if (!rate_limit(ip_bucket('book-change'), 10, 3600)) $fail('Too many changes. Please contact us.', 429);
        db_update('appointments', ['starts_at' => date('Y-m-d H:i:s', $start), 'ends_at' => date('Y-m-d H:i:s', $end), 'type_id' => (int) $type['id'], 'mode' => $type['mode'],
            'sequence' => (int) $resched['sequence'] + 1, 'reminded_at' => null], (int) $resched['id']);
        $a = db_one('SELECT * FROM appointments WHERE id = ?', [(int) $resched['id']]);
        if ($a['lead_id']) lead_note((int) $a['lead_id'], 'system', 'Customer rescheduled ' . appointment_ref($a) . ' to ' . appointment_when($a) . '.');
        appointment_email($a, 'changed');
        appointment_notify_team($a, 'rescheduled');
        redirect('book/manage/' . $a['token'] . '?changed=1');
    }

    if (!empty($_POST['website'])) redirect('book');
    if (!form_token_valid((string) ($_POST['_ft'] ?? ''))) $fail('The form expired or was sent too quickly. Please go back, wait a moment and try again.');
    if (!turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) $fail('The security check failed. Please try again.');
    if (!rate_limit(ip_bucket('book'), 5, 3600)) $fail('Too many bookings from your connection. Please call us instead.', 429);
    $v = fn($k, $max = 190) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $email = mb_strtolower($v('email'));
    if (mb_strlen($v('fullName', 150)) < 2) $fail('Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email address — the calendar invite goes there.');
    if (!preg_match('~^\+?[\d\s\-()]{7,20}$~', $v('phone', 50))) $fail('Please enter a phone number we can reach you on.');
    if ($type['mode'] === 'onsite' && mb_strlen($v('location', 255)) < 3) $fail('Please enter the site address for the visit.');
    if (empty($_POST['consent'])) $fail('Please confirm that we may use your details for this appointment.');
    if (!form_token_consume((string) $_POST['_ft'])) $fail('This booking was already sent. Check your email for the confirmation.');

    $status = setting('booking_auto_confirm', true) ? 'confirmed' : 'pending';
    $notes = $v('notes', 3000);
    $leadId = db_insert('leads', ['type' => 'appointment', 'priority' => $type['mode'] === 'onsite' ? 'high' : 'normal', 'name' => $v('fullName', 150), 'company' => $v('company'), 'email' => $email, 'phone' => $v('phone', 50),
        'product' => mb_substr('Appointment: ' . $type['name'], 0, 190), 'message' => 'Booked ' . $type['name'] . ' for ' . date('l j F Y, H:i', $start) . ($notes !== '' ? "\n\n" . $notes : ''),
        'location' => $v('location', 190) ?: null, 'source_page' => url('book'), 'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), 'consent' => 1]);
    $id = db_insert('appointments', ['type_id' => (int) $type['id'], 'mode' => $type['mode'], 'starts_at' => date('Y-m-d H:i:s', $start), 'ends_at' => date('Y-m-d H:i:s', $end),
        'name' => $v('fullName', 150), 'company' => $v('company') ?: null, 'email' => $email, 'phone' => $v('phone', 50), 'location' => $v('location', 255) ?: null, 'notes' => $notes ?: null,
        'status' => $status, 'token' => bin2hex(random_bytes(16)), 'lead_id' => $leadId, 'ip' => client_ip()]);
    $a = db_one('SELECT * FROM appointments WHERE id = ?', [$id]);
    lead_note($leadId, 'system', 'Booked online: ' . appointment_ref($a) . ', ' . $type['name'] . ', ' . appointment_when($a) . ($status === 'pending' ? ' (waiting for confirmation).' : '.'));
    appointment_email($a, 'booked');
    appointment_notify_team($a, $status === 'pending' ? 'requested' : 'booked');
    redirect('book/manage/' . $a['token'] . '?booked=1');
}

function site_book_manage(string $token): void
{
    $a = appointment_by_token($token);
    if (!$a) abort(404);
    if (is_post()) {
        if (!in_array($a['status'], ['pending', 'confirmed'], true)) redirect('book/manage/' . $a['token']);
        if (!rate_limit(ip_bucket('book-change'), 10, 3600)) abort(429);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        db_update('appointments', ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason ?: 'Cancelled by the customer', 'sequence' => (int) $a['sequence'] + 1], (int) $a['id']);
        $a = db_one('SELECT * FROM appointments WHERE id = ?', [(int) $a['id']]);
        if ($a['lead_id']) lead_note((int) $a['lead_id'], 'system', 'Customer cancelled ' . appointment_ref($a) . ($reason ? ': ' . $reason : '.'));
        appointment_email($a, 'cancelled');
        appointment_notify_team($a, 'cancelled');
        redirect('book/manage/' . $a['token'] . '?cancelled=1');
    }
    $type = db_one('SELECT * FROM appointment_types WHERE id = ?', [(int) $a['type_id']]);
    $meta = ['title' => 'Your appointment ' . appointment_ref($a), 'description' => '', 'noindex' => true, 'crumbs' => []];
    header('Referrer-Policy: no-referrer');
    site_render($meta, view('site/book_manage', compact('a', 'type')));
}

function site_book_ics(string $token): void
{
    $a = appointment_by_token($token);
    if (!$a) abort(404);
    $type = db_one('SELECT * FROM appointment_types WHERE id = ?', [(int) $a['type_id']]) ?: ['name' => 'Appointment', 'location_note' => ''];
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . appointment_ref($a) . '.ics"');
    header('X-Robots-Tag: noindex');
    echo appointment_ics($a, $type, $a['status'] === 'cancelled' ? 'CANCEL' : 'REQUEST');
    exit;
}
