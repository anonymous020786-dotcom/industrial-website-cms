<?php
/**
 * Built-in, cookie-free visitor analytics.
 *
 * The browser sends one small beacon per page view. A visitor is counted with a hash of
 * IP address + browser + a random salt that changes every day, so nobody can be followed
 * across days and no personal data is stored. Bots, the team and Do-Not-Track are skipped.
 */
declare(strict_types=1);

const ANALYTICS_BOTS = '~bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|java/|go-http|httpclient|facebookexternalhit|whatsapp|bingpreview~i';

function analytics_enabled(): bool
{
    return (bool) setting('analytics_enabled', true);
}

/** Today's salt (rotated daily; yesterday's is deleted, so hashes can't be linked across days). */
function analytics_salt(): string
{
    $day = date('Y-m-d');
    $saved = (array) setting('analytics_salt', []);
    if (($saved['day'] ?? '') !== $day) {
        $saved = ['day' => $day, 'salt' => bin2hex(random_bytes(16))];
        setting_set('analytics_salt', $saved);
    }
    return (string) $saved['salt'];
}

function analytics_device(string $ua, int $width): string
{
    if (preg_match('~ipad|tablet|kindle|silk|(android(?!.*mobile))~i', $ua)) return 'tablet';
    if (preg_match('~mobi|iphone|ipod|android.*mobile|windows phone~i', $ua) || ($width > 0 && $width < 640)) return 'mobile';
    return 'desktop';
}

/** Where the visit came from: utm_source, else the referring site (search engines grouped). */
function analytics_source(string $referrer, string $query): string
{
    parse_str($query, $q);
    if (!empty($q['utm_source'])) return mb_strtolower(mb_substr((string) $q['utm_source'], 0, 60));
    if ($referrer === '') return '';
    $host = mb_strtolower((string) parse_url($referrer, PHP_URL_HOST));
    $host = preg_replace('~^(www\.|m\.|l\.|lm\.)~', '', $host) ?? $host;
    if ($host === '' || $host === mb_strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) return '';
    foreach (['google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'yahoo' => 'Yahoo', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 't.co' => 'X', 'twitter' => 'X', 'youtube' => 'YouTube', 'chatgpt' => 'ChatGPT', 'perplexity' => 'Perplexity'] as $k => $name) {
        if (str_contains($host, $k)) return $name;
    }
    return mb_substr($host, 0, 120);
}

/** Record a page view from the beacon. Returns true when it was counted. */
function analytics_record(array $in): bool
{
    if (!analytics_enabled()) return false;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match(ANALYTICS_BOTS, $ua)) return false;
    if (setting('analytics_respect_dnt', true) && (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1')) return false;
    if (!empty($_COOKIE['ivadmin'])) return false; // the team's own visits don't count
    $path = (string) parse_url((string) ($in['p'] ?? ''), PHP_URL_PATH);
    if ($path === '' || $path[0] !== '/' || mb_strlen($path) > 255 || preg_match('~^/(admin|s/|newsletter/|cron/|q/|download/)~', $path)) return false;
    $query = (string) parse_url((string) ($in['p'] ?? ''), PHP_URL_QUERY);
    if (!rate_limit(ip_bucket('hit'), 120, 600)) return false;
    $visitor = substr(hash('sha256', analytics_salt() . '|' . client_ip() . '|' . $ua), 0, 16);
    db_insert('page_hits', ['day' => date('Y-m-d'), 'path' => mb_strtolower(rtrim($path, '/') ?: '/'), 'source' => analytics_source(mb_substr((string) ($in['r'] ?? ''), 0, 500), $query) ?: null,
        'device' => analytics_device($ua, (int) ($in['w'] ?? 0)), 'visitor' => $visitor]);
    return true;
}

/** Summary for a period (inclusive dates). */
function analytics_summary(string $from, string $to): array
{
    $w = [$from, $to];
    $tot = db_one('SELECT COUNT(*) views, COUNT(DISTINCT day, visitor) visitors FROM page_hits WHERE day BETWEEN ? AND ?', $w) ?: ['views' => 0, 'visitors' => 0];
    $days = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) $days[date('Y-m-d', $t)] = ['views' => 0, 'visitors' => 0];
    foreach (db_all('SELECT day, COUNT(*) views, COUNT(DISTINCT visitor) visitors FROM page_hits WHERE day BETWEEN ? AND ? GROUP BY day', $w) as $r) $days[$r['day']] = ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors']];
    $len = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
    $pf = date('Y-m-d', strtotime($from) - $len * 86400);
    $pt = date('Y-m-d', strtotime($from) - 86400);
    $prev = db_one('SELECT COUNT(*) views, COUNT(DISTINCT day, visitor) visitors FROM page_hits WHERE day BETWEEN ? AND ?', [$pf, $pt]) ?: ['views' => 0, 'visitors' => 0];
    $leads = (int) db_val("SELECT COUNT(*) FROM leads WHERE DATE(created_at) BETWEEN ? AND ? AND status <> 'spam' AND type <> 'job'", $w);
    return [
        'views' => (int) $tot['views'], 'visitors' => (int) $tot['visitors'], 'prev_views' => (int) $prev['views'], 'prev_visitors' => (int) $prev['visitors'],
        'leads' => $leads, 'conversion' => (int) $tot['visitors'] ? round($leads / (int) $tot['visitors'] * 100, 2) : 0.0,
        'days' => $days,
        'pages' => db_all('SELECT path, COUNT(*) views, COUNT(DISTINCT day, visitor) visitors FROM page_hits WHERE day BETWEEN ? AND ? GROUP BY path ORDER BY views DESC LIMIT 15', $w),
        'sources' => db_all("SELECT COALESCE(source, '') source, COUNT(DISTINCT day, visitor) visitors FROM page_hits WHERE day BETWEEN ? AND ? GROUP BY source ORDER BY visitors DESC LIMIT 12", $w),
        'devices' => array_column(db_all('SELECT device, COUNT(DISTINCT day, visitor) n FROM page_hits WHERE day BETWEEN ? AND ? GROUP BY device', $w), 'n', 'device'),
        'live' => (int) db_val('SELECT COUNT(DISTINCT visitor) FROM page_hits WHERE created_at >= NOW() - INTERVAL 5 MINUTE'),
    ];
}

function analytics_cleanup(): int
{
    $days = max(30, (int) setting('analytics_keep_days', 395));
    return db_query('DELETE FROM page_hits WHERE day < CURDATE() - INTERVAL ' . $days . ' DAY')->rowCount();
}
