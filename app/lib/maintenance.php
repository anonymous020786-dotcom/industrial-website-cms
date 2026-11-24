<?php
/**
 * Maintenance mode. While it is on, visitors get a branded "we'll be back" page with HTTP 503 and
 * Retry-After (so search engines keep rankings and simply come back later). These still see the
 * normal site: signed-in team members, allowed IP addresses, and anyone who opened the secret
 * preview link. The admin, scheduled tasks and robots.txt keep working.
 *
 * It can be switched on by hand, or scheduled for a window (start/end) and turns itself off at the
 * end. Settings → Maintenance mode, the banner in the admin, or `php app/cli.php maintenance`.
 */
declare(strict_types=1);

const MAINT_COOKIE = 'ivmaint';

/** @return array{active: bool, source: ?string, until: ?int, starts: ?int} */
function maintenance_state(bool $fresh = false): array
{
    static $state = null;
    if ($state !== null && !$fresh) return $state;
    $now = time();
    $start = ($s = (string) setting('maintenance_start', '')) !== '' ? strtotime($s) : null;
    $end = ($s = (string) setting('maintenance_end', '')) !== '' ? strtotime($s) : null;
    $until = ($s = (string) setting('maintenance_until', '')) !== '' ? strtotime($s) : null;
    if (setting('maintenance_enabled', false)) {
        return $state = ['active' => true, 'source' => 'manual', 'until' => $until ?: ($end && $end > $now ? $end : null), 'starts' => null];
    }
    if ($start && $start <= $now && (!$end || $end > $now)) {
        return $state = ['active' => true, 'source' => 'scheduled', 'until' => $end ?: $until, 'starts' => $start];
    }
    return $state = ['active' => false, 'source' => null, 'until' => null, 'starts' => $start && $start > $now ? $start : null];
}

function maintenance_active(): bool
{
    return maintenance_state()['active'];
}

/** The secret for the preview link; created on first use, replaced with $regenerate. */
function maintenance_token(bool $regenerate = false): string
{
    $t = (string) setting('maintenance_bypass_token', '');
    if ($regenerate || strlen($t) < 32) {
        $t = bin2hex(random_bytes(20));
        setting_set('maintenance_bypass_token', $t);
    }
    return $t;
}

function maintenance_preview_url(): string
{
    return abs_url('') . '?maintenance_access=' . maintenance_token();
}

/** Cookie value proving the browser opened the current preview link (changes when it is regenerated). */
function maintenance_cookie_value(): string
{
    return hash_hmac('sha256', 'maintenance|' . maintenance_token(), (string) config('app_key'));
}

/** IPv4/IPv6 address or CIDR range match. */
function ip_matches(string $ip, string $rule): bool
{
    $rule = trim($rule);
    if ($rule === '') return false;
    if (!str_contains($rule, '/')) return @inet_pton($ip) !== false && @inet_pton($ip) === @inet_pton($rule);
    [$net, $bits] = explode('/', $rule, 2);
    $a = @inet_pton($ip); $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b) || !ctype_digit($bits)) return false;
    $bits = (int) $bits;
    if ($bits > strlen($a) * 8) return false;
    $bytes = intdiv($bits, 8); $rest = $bits % 8;
    if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
    if ($rest === 0) return true;
    $mask = chr((0xFF << (8 - $rest)) & 0xFF);
    return (($a[$bytes] & $mask) === ($b[$bytes] & $mask));
}

function ip_rule_valid(string $rule): bool
{
    if (!str_contains($rule, '/')) return filter_var($rule, FILTER_VALIDATE_IP) !== false;
    [$net, $bits] = explode('/', $rule, 2);
    if (filter_var($net, FILTER_VALIDATE_IP) === false || !ctype_digit($bits)) return false;
    return (int) $bits <= (str_contains($net, ':') ? 128 : 32);
}

/** Who sees the real site during maintenance. */
function maintenance_can_bypass(): bool
{
    $cookie = (string) ($_COOKIE[MAINT_COOKIE] ?? '');
    if ($cookie !== '' && hash_equals(maintenance_cookie_value(), $cookie)) return true;
    $ip = client_ip();
    foreach ((array) setting('maintenance_allow_ips', []) as $rule) if (ip_matches($ip, (string) $rule)) return true;
    // Signed-in team members (the session cookie is site-wide)
    if (!empty($_COOKIE['ivadmin'])) {
        try { if (current_user()) return true; } catch (Throwable) {}
    }
    return false;
}

function maintenance_set_cookie(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    setcookie(MAINT_COOKIE, maintenance_cookie_value(), ['expires' => time() + 7 * 86400, 'path' => (base_path() ?: '') . '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}

/** Seconds a client should wait before retrying (for the Retry-After header). */
function maintenance_retry_after(): int
{
    $until = maintenance_state()['until'];
    return $until && $until > time() ? max(60, min(86400, $until - time())) : 1800;
}

/** Runs before routing on every request. */
function maintenance_gate(string $path, string $method): void
{
    try {
        $state = maintenance_state();
    } catch (Throwable $e) {
        return; // settings unreadable (database down): the error handler deals with that
    }
    // The secret preview link works even when maintenance is off (so it can be tested beforehand)
    if (isset($_GET['maintenance_access']) && is_string($_GET['maintenance_access'])) {
        if (hash_equals(maintenance_token(), $_GET['maintenance_access'])) {
            maintenance_set_cookie();
            $_COOKIE[MAINT_COOKIE] = maintenance_cookie_value();
            $qs = $_GET; unset($qs['maintenance_access']);
            redirect(ltrim($path, '/') . ($qs ? '?' . http_build_query($qs) : ''));
        }
    }
    if (str_starts_with($path, '/admin') || $path === '/install' || str_starts_with($path, '/cron/')) return;
    if (in_array($path, ['/robots.txt', '/site.webmanifest', '/browserconfig.xml'], true)) return;

    // Team members can see exactly what visitors see (even before switching it on)
    if (isset($_GET['maintenance_preview']) && $method === 'GET' && maintenance_can_bypass()) maintenance_render(true);
    if (!$state['active'] || maintenance_can_bypass()) return;

    if ($path === '/track') { http_response_code(204); exit; }
    if (wants_json()) {
        http_response_code(503);
        header('Retry-After: ' . maintenance_retry_after());
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'message' => 'The website is down for maintenance, so your message was not sent. Please try again later' . (company('email') ? ' or email ' . company('email') : '') . '.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    maintenance_render(false);
}

function maintenance_render(bool $preview): never
{
    $state = maintenance_state();
    http_response_code($preview && !$state['active'] ? 200 : 503);
    if ($state['active']) header('Retry-After: ' . maintenance_retry_after());
    send_security_headers(false);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo view('site/maintenance', ['state' => $state, 'preview' => $preview]);
    exit;
}

/** Turn manual maintenance on/off; turning it off also ends a running scheduled window. */
function maintenance_switch(bool $on, ?string $until = null): void
{
    setting_set('maintenance_enabled', $on);
    if ($until !== null) setting_set('maintenance_until', $until);
    if (!$on) {
        $end = (string) setting('maintenance_end', '');
        $start = (string) setting('maintenance_start', '');
        if ($start !== '' && strtotime($start) <= time() && ($end === '' || strtotime($end) > time())) {
            setting_set('maintenance_start', null);
            setting_set('maintenance_end', null);
        }
        setting_set('maintenance_until', null);
    }
    setting_set('maintenance_changed_at', now());
    maintenance_state(true);
}

/** "Tue 25 Nov, 14:00" in the site's timezone. */
function maintenance_time(int $ts): string
{
    return date(date('Y', $ts) === date('Y') ? 'D j M, H:i' : 'D j M Y, H:i', $ts);
}

/** Tell every administrator the site went offline or came back (in-app + email). */
function maintenance_notify(bool $on, string $via = ''): void
{
    $state = maintenance_state(true);
    $who = $via !== '' ? $via : actor_name();
    if ($on) {
        $text = $who . ' switched on maintenance mode. Visitors now see the maintenance page'
            . ($state['until'] ? ' until about ' . maintenance_time($state['until']) : '') . '. Signed-in team members still see the normal site.';
        notify_admins('maintenance_on', 'Website is in maintenance mode', $text, ['severity' => 'warning', 'category' => 'system', 'link' => 'admin/settings/maintenance', 'include_actor' => true]);
    } else {
        notify_admins('maintenance_off', 'Website is back online', $who . ' switched off maintenance mode. Visitors see the normal site again.', ['severity' => 'info', 'category' => 'system', 'link' => 'admin/settings/maintenance', 'include_actor' => true]);
    }
    try {
        $u = current_user();
        auth_log($on ? 'maintenance_on' : 'maintenance_off', $u ? (int) $u['id'] : null, $u['email'] ?? null);
    } catch (Throwable) {}
}
