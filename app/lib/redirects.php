<?php
/**
 * 301/302 redirects + 404 log.
 * - Exact rules match the path case-insensitively, ignoring the query string and trailing slash.
 * - Prefix rules end in "/*" (e.g. /old-blog/* → /blog/*): the matched remainder is appended
 *   wherever the target contains "*".
 * - Changing the URL slug of a published page, post, product, industry or project automatically
 *   creates a 301 from the old address.
 */
declare(strict_types=1);

const REDIRECT_CODES = [301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary (keep method)', 308 => '308 Permanent (keep method)'];
const NOT_FOUND_SKIP = '~^/(admin|assets|uploads|install|cron)(/|$)|\.(css|js|map|woff2?|ttf|ico|png|jpe?g|webp|avif|gif|svg)$~i';

/** Normalise a path for matching/storage: leading slash, no query/fragment, no trailing slash, decoded. */
function redirect_normalize(string $path): string
{
    if (preg_match('~^https?://[^/]+(/.*)?$~i', $path, $m)) $path = $m[1] ?? '/';
    $path = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
    $path = '/' . ltrim(rawurldecode($path), '/');
    if ($path !== '/' && !str_ends_with($path, '/*')) $path = rtrim($path, '/');
    return mb_substr($path, 0, 500);
}

function redirect_hash(string $source): string { return sha1(mb_strtolower($source)); }

/** Validate and normalise a redirect target. @return array{0:?string,1:?string} [target, error] */
function redirect_clean_target(string $target, string $source): array
{
    $target = trim($target);
    if ($target === '') return [null, 'Enter where the old address should go.'];
    if (preg_match('~^https?://~i', $target)) {
        if (!filter_var(str_replace('*', 'x', $target), FILTER_VALIDATE_URL)) return [null, 'Enter a valid URL.'];
        return [mb_substr($target, 0, 500), null];
    }
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $target) || str_starts_with($target, '//')) return [null, 'Use a path like /products or a full https:// URL.'];
    $t = '/' . ltrim($target, '/');
    if (redirect_hash(redirect_normalize($t)) === redirect_hash($source)) return [null, 'The target cannot be the same as the old address.'];
    return [mb_substr($t, 0, 500), null];
}

/** Find the rule for a request path (exact first, then the longest prefix rule). */
function redirect_find(string $path): ?array
{
    $path = redirect_normalize($path);
    $rule = db_one('SELECT * FROM redirects WHERE source_hash = ?', [redirect_hash($path)]);
    if ($rule) return $rule;
    static $prefix = null;
    $prefix ??= db_all("SELECT * FROM redirects WHERE source LIKE '%/*' ORDER BY CHAR_LENGTH(source) DESC");
    foreach ($prefix as $r) {
        $base = substr($r['source'], 0, -1); // keep trailing slash: "/old/"
        if (stripos($path . '/', $base) === 0) {
            $rest = (string) substr($path, strlen($base));
            $r['target'] = str_replace('*', $rest, $r['target']);
            return $r;
        }
    }
    return null;
}

/** Called for GET requests that would 404. Sends the redirect and exits if a rule matches. */
function redirect_try(string $path): void
{
    $rule = redirect_find($path);
    if (!$rule) return;
    db_query('UPDATE redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?', [(int) $rule['id']]);
    $to = $rule['target'];
    if (!preg_match('~^https?://~i', $to)) {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $to = url(ltrim($to, '/')) . ($qs !== '' && !str_contains($to, '?') ? '?' . $qs : '');
    }
    $code = (int) $rule['code'];
    header('Location: ' . $to, true, in_array($code, [301, 302, 307, 308], true) ? $code : 301);
    header('Cache-Control: no-cache');
    exit;
}

function not_found_log(string $path): void
{
    $path = redirect_normalize($path);
    if (preg_match(NOT_FOUND_SKIP, $path)) return;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match('~(wp-|xmlrpc|\.php|\.env|phpmyadmin|\.git)~i', $path)) return; // scanner noise
    $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500) ?: null;
    try {
        db_query('INSERT INTO not_found_log (path, path_hash, referrer) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW(), referrer = COALESCE(VALUES(referrer), referrer)', [$path, redirect_hash($path), $ref]);
        if (random_int(1, 200) === 1) db_query('DELETE FROM not_found_log WHERE last_seen < NOW() - INTERVAL 180 DAY');
    } catch (Throwable $e) {
        error_log('404 log: ' . $e->getMessage());
    }
}

/** Create/update a 301 from an old URL to a new one (used when a slug changes). */
function redirect_auto(string $oldPath, string $newPath, string $note): void
{
    $src = redirect_normalize($oldPath);
    $dst = redirect_normalize($newPath);
    if ($src === $dst || $src === '/') return;
    // Point existing rules that targeted the old URL at the new one (avoid chains)
    db_query('UPDATE redirects SET target = ? WHERE target = ?', [$dst, $src]);
    // If the new URL itself was a redirect source, drop that rule (it would loop)
    db_query('DELETE FROM redirects WHERE source_hash = ?', [redirect_hash($dst)]);
    db_query('INSERT INTO redirects (source, source_hash, target, code, note) VALUES (?, ?, ?, 301, ?)
        ON DUPLICATE KEY UPDATE target = VALUES(target), code = 301, note = VALUES(note)', [$src, redirect_hash($src), $dst, mb_substr($note, 0, 190)]);
    db_query('DELETE FROM not_found_log WHERE path_hash = ?', [redirect_hash($src)]);
}

/** @return string|null error when the rule set would create a loop */
function redirect_loop_error(string $source, string $target): ?string
{
    if (preg_match('~^https?://~i', $target)) return null;
    $seen = [redirect_hash($source) => true];
    $cur = $target;
    for ($i = 0; $i < 10; $i++) {
        $h = redirect_hash(redirect_normalize($cur));
        if (isset($seen[$h])) return 'This would create a redirect loop.';
        $seen[$h] = true;
        $next = db_one('SELECT target FROM redirects WHERE source_hash = ?', [$h]);
        if (!$next || preg_match('~^https?://~i', $next['target'])) return null;
        $cur = $next['target'];
    }
    return 'This would create a long redirect chain. Point it directly at the final page.';
}
