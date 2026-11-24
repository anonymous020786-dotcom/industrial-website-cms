<?php
/** General helpers: escaping, URLs, requests, responses, views, CSRF, flash messages. */
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* URLs                                                                */
/* ------------------------------------------------------------------ */

/** Base path when installed in a sub-folder (e.g. /my-site). Empty at domain root. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $configured = config('base_path');
    if (is_string($configured) && $configured !== 'auto') return $base = rtrim($configured, '/');
    if (PHP_SAPI === 'cli') return $base = '';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script = rtrim($script, '/');
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // When the root .htaccess rewrites into /public, the public segment is hidden from URLs.
    if (str_ends_with($script, '/public') && !str_starts_with($uri, $script . '/') && $uri !== $script) {
        $script = substr($script, 0, -7);
    }
    return $base = $script;
}

function url(string $path = ''): string
{
    if (preg_match('~^(https?:|mailto:|tel:|#|//)~i', $path)) return $path;
    $path = ltrim($path, '/');
    return base_path() . '/' . $path;
}

/** Absolute URL using the configured site URL (for canonical, sitemap, OG). */
function abs_url(string $path = ''): string
{
    if (preg_match('~^https?://~i', $path)) return $path;
    $site = rtrim((string) setting('site_url', ''), '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    return $site . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . $path;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

/** Media values may be absolute URLs or site-relative paths like uploads/2026/11/x.jpg */
function media_url(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') return '';
    return preg_match('~^https?://~i', $value) ? $value : url($value);
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
    if (str_starts_with($uri, '/index.php')) $uri = substr($uri, 10);
    $uri = '/' . trim($uri, '/');
    return $uri;
}

function current_url_is(string $path): bool
{
    $p = '/' . trim($path, '/');
    $cur = request_path();
    return $cur === $p || ($p !== '/' && str_starts_with($cur, $p . '/'));
}

/* ------------------------------------------------------------------ */
/* Request / response                                                  */
/* ------------------------------------------------------------------ */

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function query(string $key, mixed $default = null): mixed
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . (preg_match('~^https?://~', $to) ? $to : url($to)), true, $code);
    exit;
}

function back(string $fallback = 'admin'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function abort(int $code, string $message = ''): never
{
    throw new HttpException($code, $message);
}

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message ?: ($status === 404 ? 'Not found' : 'Error'), $status);
    }
}

function client_ip(): string
{
    if (setting('cf_trust_proxy') && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function send_security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if ($admin) {
        header('Cache-Control: no-store');
        // Admin pages load only first-party code (plus Cloudflare Turnstile on the login page).
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://challenges.cloudflare.com; frame-src 'self' https://challenges.cloudflare.com; "
            . "img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'");
    } else {
        // Public site: scripts stay open for analytics/chat integrations, but block framing by other
        // sites, <base> hijacking, plugins and form posts to foreign origins.
        header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'" . ($https ? '; upgrade-insecure-requests' : ''));
        header('Cache-Control: no-cache'); // HTML is always revalidated; versioned assets are cached long-term
    }
}

/* ------------------------------------------------------------------ */
/* Views                                                               */
/* ------------------------------------------------------------------ */

/** Render a view file from app/views and return the HTML. */
function view(string $__name, array $__vars = []): string
{
    $__file = APP_DIR . '/views/' . $__name . '.php';
    if (!is_file($__file)) throw new RuntimeException("View not found: {$__name}");
    extract($__vars, EXTR_SKIP);
    unset($__vars);
    ob_start();
    try {
        include $__file;
    } catch (Throwable $t) {
        ob_end_clean();
        throw $t;
    }
    return (string) ob_get_clean();
}

/**
 * Minify HTML output: strips comments and collapses whitespace. Content inside
 * pre/textarea/script/style/code is preserved exactly. Disable with 'minify_html' => false in config.
 */
function minify_html(string $html): string
{
    if (config('minify_html', true) === false) return $html;
    $keep = [];
    $out = preg_replace_callback('~<(pre|textarea|script|style|code)\b[^>]*>.*?</\1>~is', function ($m) use (&$keep) {
        $key = "\x02" . count($keep) . "\x03";
        $keep[$key] = $m[0];
        return $key;
    }, $html);
    if ($out === null) return $html;
    $out = preg_replace('~<!--(?!\s*\[if)[\s\S]*?-->~', '', $out) ?? $out;   // comments (keep IE conditionals)
    $out = preg_replace('~\s+~u', ' ', $out) ?? $out;                        // collapse whitespace to one space
    $out = preg_replace('~\s*(</?(?:html|head|body|meta|link|title|main|header|footer|nav|section|article|aside|div|ul|ol|li|dl|dt|dd|table|thead|tbody|tr|th|td|form|fieldset|legend|figure|figcaption|h[1-6]|p|br|hr|option|select|svg|path|template|dialog|details|summary)\b[^>]*>)\s*~i', '$1', $out) ?? $out; // no spaces around block tags
    return trim(strtr($out, $keep));
}

/* ------------------------------------------------------------------ */
/* Sessions, CSRF, flash                                               */
/* ------------------------------------------------------------------ */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('ivadmin');
    session_set_cookie_params(['lifetime' => 0, 'path' => (base_path() ?: '') . '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
        abort(419, 'Your session expired. Please go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    session_boot();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    session_boot();
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Keep submitted form values after a validation error (admin forms). */
function old_input(?array $set = null): array
{
    session_boot();
    if ($set !== null) { $_SESSION['_old'] = $set; return $set; }
    $o = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return $o;
}

/* ------------------------------------------------------------------ */
/* Formatting & misc                                                   */
/* ------------------------------------------------------------------ */

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = strtr($text, ['&' => ' and ', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ñ' => 'n', 'ç' => 'c']);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text) ?? '';
    return trim(substr($text, 0, 150), '-') ?: 'item';
}

function now(): string { return date('Y-m-d H:i:s'); }

function format_date(?string $date, string $format = 'j M Y'): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function time_ago(?string $date): string
{
    if (!$date) return '';
    $diff = time() - (int) strtotime($date);
    if ($diff < 60) return 'just now';
    foreach ([[31536000, 'year'], [2592000, 'month'], [604800, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'minute']] as [$secs, $label]) {
        if ($diff >= $secs) {
            $n = intdiv($diff, $secs);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return format_date($date);
}

function str_limit(?string $text, int $limit = 160): string
{
    $text = trim(preg_replace('~\s+~', ' ', strip_tags((string) $text)) ?? '');
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

function json_decode_array(?string $json): array
{
    if ($json === null || $json === '') return [];
    $v = json_decode($json, true);
    return is_array($v) ? $v : [];
}

function pad2(int $n): string { return str_pad((string) $n, 2, '0', STR_PAD_LEFT); }

/** Simple pagination data */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Build a query string preserving current GET params */
function qs(array $changes = []): string
{
    $params = array_filter(array_merge($_GET, $changes), fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
}

/** Widths generated by `npm run images` for each mirrored stock photo. */
const STOCK_WIDTHS = [480, 800, 1200, 1600, 2400];

/**
 * Local copy of an Unsplash photo (public/assets/stock/{photo-id}-{w}.webp), or null when it
 * has not been mirrored yet — callers then fall back to the Unsplash URL.
 */
function stock_local(string $url, int $width): ?string
{
    $path = stock_path($url, $width);
    return $path === null ? null : url($path);
}

/** Absolute image URL for meta tags / JSON-LD / feeds — the mirrored copy when available. */
function image_abs_url(?string $value, int $width = 1200): string
{
    $url = media_url($value);
    if ($url === '') return '';
    $local = stock_path($url, $width);
    return $local !== null ? abs_url($local) : (preg_match('~^https?://~i', $url) ? $url : abs_url(ltrim((string) $value, '/')));
}

/** Relative path (assets/stock/…webp) of the mirrored copy, or null. */
function stock_path(string $url, int $width): ?string
{
    static $have = [];
    if (!preg_match('~images\.unsplash\.com/(photo-[\w-]+)~', $url, $m)) return null;
    $id = $m[1];
    $have[$id] ??= is_file(PUBLIC_DIR . "/assets/stock/{$id}-" . STOCK_WIDTHS[0] . '.webp');
    if (!$have[$id]) return null;
    $pick = STOCK_WIDTHS[count(STOCK_WIDTHS) - 1];
    foreach (STOCK_WIDTHS as $w) if ($w >= $width) { $pick = $w; break; }
    return "assets/stock/{$id}-{$pick}.webp";
}

/** Responsive <img>: mirrored stock photos (local WebP), Unsplash URLs (srcset) or plain local images. */
function img_tag(?string $src, string $alt = '', array $opt = []): string
{
    $src = media_url($src);
    $cls = $opt['class'] ?? 'h-full w-full object-cover';
    $lazy = $opt['lazy'] ?? true;
    $widths = $opt['widths'] ?? [640, 1024, 1600];
    $sizes = $opt['sizes'] ?? '100vw';
    if ($src === '') return '';
    $attrs = '';
    $rel = preg_match('~^' . preg_quote(rtrim(url(''), '/'), '~') . '/(uploads/[^?#]+\.(?:jpe?g|png|webp))$~i', $src, $um) ? $um[1] : null;
    if ($rel && ($vars = media_variants($rel))) {
        // Optimised upload: serve the generated WebP sizes
        $base = preg_replace('~\.[a-z0-9]+$~i', '', $rel);
        $attrs .= ' srcset="' . implode(', ', array_map(fn($w) => e(url("{$base}-{$w}.webp")) . " {$w}w", $vars)) . '" sizes="' . e($sizes) . '"';
        $pick = end($vars);
        foreach ($vars as $vw) if ($vw >= ($widths[1] ?? $widths[0])) { $pick = $vw; break; }
        $src = url("{$base}-{$pick}.webp");
    } elseif (stock_local($src, $widths[0]) !== null) {
        $set = [];
        foreach ($widths as $w) {
            $local = (string) stock_local($src, $w);
            preg_match('~-(\d+)\.webp$~', $local, $mm);
            $set[$local] = e($local) . ' ' . $mm[1] . 'w';
        }
        $attrs .= ' srcset="' . implode(', ', $set) . '" sizes="' . e($sizes) . '"';
        $src = (string) stock_local($src, $widths[1] ?? $widths[0]);
    } elseif (str_contains($src, 'images.unsplash.com')) {
        $set = array_map(fn($w) => e(preg_replace('~([?&])w=\d+~', '${1}w=' . $w, $src)) . " {$w}w", $widths);
        $attrs .= ' srcset="' . implode(', ', $set) . '" sizes="' . e($sizes) . '"';
        $src = preg_replace('~([?&])w=\d+~', '${1}w=' . ($widths[1] ?? $widths[0]), $src);
    }
    $attrs .= $lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"';
    return '<img src="' . e($src) . '"' . $attrs . ' alt="' . e($alt) . '" class="' . e($cls) . '">';
}
