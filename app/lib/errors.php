<?php
/**
 * Error handling: one place that turns any failure (404, expired form, rate limit, crash, fatal
 * error, database outage) into the right response, whether that is a branded site page, an admin
 * page, JSON for scripts, or a self-contained fallback page when nothing else works.
 *
 * Unexpected errors get a short reference (e.g. E-7K2M9QXA) that is shown to the visitor, written
 * to the PHP error log and stored in the admin Error log, so support can find the exact failure.
 */
declare(strict_types=1);

/** Title, explanation and icon for each status code we show a page for. */
function error_info(int $status): array
{
    $map = [
        400 => ['Bad request', 'The request could not be understood. Please check the address or form and try again.', 'file-warning'],
        401 => ['Sign-in required', 'You need to sign in to see this page.', 'log-in'],
        403 => ['Access denied', 'You don’t have permission to open this page.', 'lock'],
        404 => ['Page not found', 'The page you’re looking for has moved, been renamed or never existed.', 'search-x'],
        405 => ['Not allowed', 'This address can’t be used that way. Please go back and use the links or forms on the page.', 'ban'],
        408 => ['Request timed out', 'The request took too long to arrive. Please try again.', 'timer-off'],
        410 => ['Page removed', 'This page has been removed permanently.', 'archive-x'],
        413 => ['Upload too large', 'The file or form you sent is larger than the server accepts. Please use a smaller file and try again.', 'file-up'],
        419 => ['Your session expired', 'For your security, forms expire after a while. Your information was not sent — go back, refresh the page and submit it again.', 'timer-reset'],
        422 => ['Please check your details', 'Some of the information sent could not be processed.', 'circle-alert'],
        429 => ['Too many requests', 'You’ve made a lot of requests in a short time. Please wait a moment and try again.', 'gauge'],
        500 => ['Something went wrong', 'An unexpected error stopped this page from loading. Our team has been notified. Please try again in a moment.', 'server-crash'],
        502 => ['Temporary connection problem', 'A service this page depends on did not respond. Please try again shortly.', 'unplug'],
        503 => ['Temporarily unavailable', 'The website is briefly unavailable. Please try again in a few minutes.', 'wrench'],
        504 => ['Request timed out', 'A service this page depends on took too long to respond. Please try again shortly.', 'unplug'],
    ];
    $fallback = $status >= 500 ? $map[500] : $map[400];
    [$title, $text, $icon] = $map[$status] ?? $fallback;
    return ['title' => $title, 'text' => $text, 'icon' => $icon];
}

/** A short, unambiguous reference visitors can quote to support (no 0/O/1/I). */
function error_ref(): string
{
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $chars[random_int(0, 31)];
    return 'E-' . $s;
}

/** Stack trace without argument values (they can contain passwords or personal data). */
function error_trace(Throwable $e): string
{
    $root = str_replace('\\', '/', APP_ROOT) . '/';
    $lines = [];
    foreach (array_slice($e->getTrace(), 0, 25) as $i => $f) {
        $where = isset($f['file']) ? str_replace([str_replace('/', '\\', $root), $root], '', $f['file']) . ':' . ($f['line'] ?? '?') : '[internal]';
        $lines[] = '#' . $i . ' ' . $where . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
    }
    return implode("\n", $lines);
}

function error_short_path(string $file): string
{
    $root = str_replace('\\', '/', APP_ROOT) . '/';
    return str_replace($root, '', str_replace('\\', '/', $file));
}

/**
 * Store an unexpected error in the admin Error log and alert administrators the first time a
 * particular error appears in an hour. Never throws: the database itself may be the problem.
 */
function error_record(string $ref, int $status, string $class, string $message, string $file, int $line, string $trace): void
{
    try {
        if (!is_installed()) return;
        $location = error_short_path($file) . ':' . $line;
        $fingerprint = sha1($class . '|' . $location . '|' . preg_replace('~\d+~', '#', mb_substr($message, 0, 200)));
        $recent = (int) db_val('SELECT COUNT(*) FROM app_errors WHERE fingerprint = ? AND created_at >= NOW() - INTERVAL 1 HOUR', [$fingerprint]);
        if ($recent >= 100) return; // a flood of the same failure: the log file still has every one
        $uid = null;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid'])) $uid = (int) $_SESSION['uid'];
        db_insert('app_errors', [
            'ref' => $ref, 'fingerprint' => $fingerprint, 'status' => $status,
            'method' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 10),
            'path' => mb_substr(PHP_SAPI === 'cli' ? 'cli' : (string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
            'class' => mb_substr($class, 0, 120), 'message' => mb_substr($message, 0, 2000), 'location' => mb_substr($location, 0, 300),
            'trace' => mb_substr($trace, 0, 20000), 'user_id' => $uid, 'ip' => PHP_SAPI === 'cli' ? null : client_ip(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
        if ($recent === 0) {
            notify_admins('server_error', 'Site error: ' . mb_substr($message, 0, 80),
                'Someone hit an unexpected error on ' . mb_substr((string) ($_SERVER['REQUEST_URI'] ?? 'the site'), 0, 120) . ' (reference ' . $ref . ', ' . $location . '). '
                . 'Repeats of the same error in the next hour are logged without another alert.',
                ['severity' => 'critical', 'category' => 'system', 'link' => 'admin/errors?ref=' . $ref]);
        }
    } catch (Throwable $t) {
        error_log('error_record failed: ' . $t->getMessage());
    }
}

/** Log an unexpected exception everywhere it should go. Returns its reference. */
function error_report(Throwable $e, int $status = 500): string
{
    $ref = error_ref();
    error_log('[' . $ref . '] ' . $e);
    error_record($ref, $status, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), error_trace($e));
    return $ref;
}

/** True for failures that mean "the database can't be reached" rather than a bug. */
function error_is_db_down(Throwable $e): bool
{
    if (!$e instanceof PDOException) return false;
    $code = (string) $e->getCode();
    return in_array($code, ['2002', '2006', '1040', '1045', '1049', 'HY000'], true)
        && (bool) preg_match('~(connect|gone away|too many connections|access denied|unknown database|refused|timed out)~i', $e->getMessage());
}

/** Same-site address of the previous page, for "Go back" buttons (never an open redirect). */
function error_back_url(): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : '') === $host) {
        return $ref;
    }
    return '';
}

/**
 * "Did you mean…?" for a 404: published pages, products, posts and projects whose address or
 * title is close to the one requested.
 */
function error_suggestions(string $path, int $limit = 4): array
{
    $want = strtolower(trim(preg_replace('~\.(html?|php|aspx?)$~i', '', basename(rtrim($path, '/'))), '-_ '));
    $want = str_replace(['_', ' ', '%20'], '-', $want);
    if (strlen($want) < 3) return [];
    $cands = [];
    try {
        foreach (db_all("SELECT slug, title FROM pages WHERE status = 'published' AND deleted_at IS NULL AND slug <> 'home'") as $r) $cands[] = [$r['title'], page_url($r['slug']), $r['slug'], 'Page'];
        foreach (products_all() as $r) $cands[] = [$r['name'], product_url($r), $r['slug'], 'Product'];
        foreach (db_all("SELECT slug, title FROM posts WHERE status = 'published' AND deleted_at IS NULL AND published_at <= NOW() ORDER BY published_at DESC LIMIT 300") as $r) $cands[] = [$r['title'], post_url($r), $r['slug'], 'Article'];
        foreach (db_all("SELECT slug, title FROM projects WHERE status = 'published'") as $r) $cands[] = [$r['title'], url('projects/' . $r['slug']), $r['slug'], 'Project'];
    } catch (Throwable $t) {
        return [];
    }
    $scored = [];
    foreach ($cands as [$title, $href, $slug, $kind]) {
        $slug = strtolower((string) $slug);
        similar_text($want, $slug, $pct);
        $score = $pct;
        if (strlen($want) >= 4 && (str_contains($slug, $want) || str_contains($want, $slug))) $score = max($score, 80);
        // Shared words ("hydraulic-cylinder" vs "cylinders-hydraulic")
        $a = array_filter(explode('-', $want), fn($w) => strlen($w) > 2);
        $b = array_filter(explode('-', $slug), fn($w) => strlen($w) > 2);
        if ($a && $b) {
            $common = 0;
            foreach ($a as $w) foreach ($b as $v) if ($w === $v || (strlen($w) > 4 && (str_starts_with($v, $w) || str_starts_with($w, $v)))) { $common++; break; }
            $score = max($score, 100 * $common / max(count($a), count($b)));
        }
        if ($score >= 55) $scored[] = ['title' => (string) $title, 'url' => $href, 'kind' => $kind, 'score' => $score];
    }
    usort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($scored, 0, $limit);
}

/** Discard anything a half-finished page already printed. */
function error_clean_output(): void
{
    while (ob_get_level() > 0) @ob_end_clean();
}

/**
 * Send the right error response for this request and stop.
 * $opt: ref (string), retry_after (int seconds), allow (string[] methods), debug (Throwable).
 */
function error_respond(int $status, string $message = '', array $opt = []): never
{
    error_clean_output();
    $info = error_info($status);
    $ref = $opt['ref'] ?? null;
    $text = $message !== '' && !in_array($message, ['Not found', 'Error'], true) ? $message : $info['text'];
    if (!headers_sent()) {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        if (!empty($opt['retry_after'])) header('Retry-After: ' . (int) $opt['retry_after']);
        if (!empty($opt['allow'])) header('Allow: ' . implode(', ', $opt['allow']));
    }
    $path = PHP_SAPI === 'cli' ? '' : request_path();

    if (PHP_SAPI !== 'cli' && wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_filter(['ok' => false, 'status' => $status, 'error' => $info['title'], 'message' => $text, 'ref' => $ref,
            'retry_after' => $opt['retry_after'] ?? null], fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');

    $vars = ['status' => $status, 'title' => $info['title'], 'text' => $text, 'icon' => $info['icon'], 'ref' => $ref,
        'retryAfter' => (int) ($opt['retry_after'] ?? 0), 'back' => error_back_url(), 'path' => $path,
        'retryable' => in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true),
        'debug' => config('debug') && isset($opt['debug']) ? $opt['debug'] : null];

    // Database outages can't render the normal layouts (they read settings and menus from it)
    if (empty($opt['static'])) {
        try {
            if (str_starts_with($path, '/admin')) {
                $user = current_user();
                if ($user || $status !== 404) {
                    require_once APP_DIR . '/handlers/admin.php';
                    if (!$user && in_array($status, [401, 403], true)) redirect('admin/login');
                    $html = $user
                        ? admin_layout($info['title'], view('admin/error', $vars))
                        : view('admin/error_guest', $vars);
                    echo $html;
                    exit;
                }
            }
            require_once APP_DIR . '/handlers/site.php';
            if ($status === 404) $vars['suggestions'] = error_suggestions($path);
            $meta = ['title' => $info['title'], 'description' => '', 'noindex' => true, 'crumbs' => []];
            site_render($meta, view('site/error', $vars), $status);
            exit;
        } catch (Throwable $t) {
            error_log('error page failed: ' . $t);
            error_clean_output();
        }
    }
    echo error_static_page($vars);
    exit;
}

/** A complete page with no dependencies (no database, layout or stylesheet). */
function error_static_page(array $v): string
{
    $name = 'This website';
    try { if (is_installed()) $name = company('name', $name); } catch (Throwable) {}
    $status = (int) $v['status'];
    $ref = $v['ref'] ? '<p class="ref">Reference: <code>' . e((string) $v['ref']) . '</code><br>Quote this if you contact us.</p>' : '';
    $retry = !empty($v['retryAfter']) ? '<p class="small">Please try again in about ' . e((string) max(1, (int) ceil($v['retryAfter'] / 60))) . ' minute(s).</p>' : '';
    $debug = !empty($v['debug']) ? '<pre>' . e((string) $v['debug']) . '</pre>' : '';
    $home = function_exists('url') ? url('') : '/';
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . e($v['title']) . ' · ' . e($name) . '</title><style>'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#101D32;color:#fff;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . 'background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:32px 32px}'
        . 'main{width:min(560px,calc(100% - 32px));padding:48px 0}.code{font:700 96px/1 system-ui,sans-serif;color:#F28C28;letter-spacing:-2px;margin:0}'
        . 'h1{font-size:28px;margin:12px 0 8px}p{color:rgba(255,255,255,.78);margin:0 0 16px}.small{font-size:14px}'
        . '.ref{margin-top:24px;padding:12px 16px;border:1px solid rgba(255,255,255,.18);border-radius:8px;font-size:14px}code{color:#fff;font-weight:700}'
        . 'a.btn{display:inline-block;margin-top:8px;padding:12px 22px;border-radius:6px;background:#F28C28;color:#101D32;font-weight:700;text-decoration:none}'
        . 'pre{white-space:pre-wrap;font-size:12px;background:rgba(0,0,0,.35);padding:12px;border-radius:6px;color:#fecaca;max-height:50vh;overflow:auto}'
        . '.brand{font-weight:700;letter-spacing:.12em;text-transform:uppercase;font-size:13px;color:rgba(255,255,255,.6)}</style></head><body><main>'
        . '<p class="brand">' . e($name) . '</p><p class="code">' . $status . '</p><h1>' . e($v['title']) . '</h1><p>' . e($v['text']) . '</p>' . $retry
        . '<a class="btn" href="' . e($home) . '">Back to home</a>' . $ref . $debug . '</main></body></html>';
}

/** Install the handlers that catch everything the router does not. */
function errors_register(bool $debug): void
{
    set_exception_handler(function (Throwable $e) use ($debug) {
        if (PHP_SAPI === 'cli') {
            $ref = error_report($e);
            fwrite(STDERR, "[{$ref}] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }
        if ($e instanceof HttpException) error_respond($e->status, $e->getMessage(), $e->options);
        if (error_is_db_down($e)) {
            error_log('Database unavailable: ' . $e->getMessage());
            error_respond(503, 'We can’t reach our database right now. This is usually fixed within a few minutes — please try again shortly.', ['retry_after' => 120, 'static' => true, 'debug' => $e]);
        }
        $ref = error_report($e);
        error_respond(500, '', ['ref' => $ref, 'debug' => $e]);
    });

    // Fatal errors (out of memory, timeouts, parse errors in a template) skip the exception handler
    register_shutdown_function(function () {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
        $ref = error_ref();
        error_log("[{$ref}] Fatal: {$err['message']} in {$err['file']}:{$err['line']}");
        if (PHP_SAPI === 'cli') return;
        // Give the database insert a little room even after a timeout
        @set_time_limit(10);
        error_record($ref, 500, 'FatalError', (string) $err['message'], (string) $err['file'], (int) $err['line'], '');
        error_clean_output();
        if (headers_sent()) return;
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $info = error_info(500);
        echo error_static_page(['status' => 500, 'title' => $info['title'], 'text' => $info['text'], 'ref' => $ref, 'retryAfter' => 0,
            'debug' => config('debug') ? $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] : null]);
    });
}
