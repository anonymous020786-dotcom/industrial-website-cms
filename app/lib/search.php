<?php
/**
 * Site-wide search across products, part numbers, pages, posts, projects, downloads and jobs.
 * Plain SQL LIKE matching (fine for a site of this size) with typo-tolerant "did you mean" suggestions.
 */
declare(strict_types=1);

const SEARCH_MAX_WORDS = 6;

/** @return string[] lower-case search words (2+ characters) */
function search_words(string $q): array
{
    preg_match_all('~[\p{L}\p{N}][\p{L}\p{N}.\-/]*~u', mb_strtolower($q), $m);
    $words = array_values(array_unique(array_filter($m[0], fn($w) => mb_strlen($w) >= 2)));
    return array_slice($words, 0, SEARCH_MAX_WORDS);
}

function search_like(string $w): string
{
    return '%' . addcslashes($w, '%_\\') . '%';
}

/** Every word must appear in at least one of the columns. */
function search_where(array $cols, array $words): array
{
    $and = [];
    $params = [];
    foreach ($words as $w) {
        $and[] = '(' . implode(' OR ', array_map(fn($c) => "{$c} LIKE ?", $cols)) . ')';
        foreach ($cols as $_) $params[] = search_like($w);
    }
    return [implode(' AND ', $and), $params];
}

function search_snippet(string $text, array $words, int $len = 180): string
{
    $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if (mb_strlen($text) <= $len) return $text;
    $pos = null;
    foreach ($words as $w) if (($p = mb_stripos($text, $w)) !== false && ($pos === null || $p < $pos)) $pos = $p;
    $start = max(0, (int) $pos - 60);
    $cut = mb_substr($text, $start, $len);
    return ($start > 0 ? '…' : '') . trim($cut) . (mb_strlen($text) > $start + $len ? '…' : '');
}

function search_score(string $title, string $q, array $words): int
{
    $t = mb_strtolower($title);
    $s = str_contains($t, mb_strtolower(trim($q))) ? 20 : 0;
    foreach ($words as $w) $s += str_contains($t, $w) ? 5 : 1;
    return $s;
}

/**
 * @return array{results: array<int, array{type:string,title:string,url:string,snippet:string,score:int}>, parts: array, suggestion: ?string}
 */
function site_search(string $q, int $limit = 40): array
{
    $words = search_words($q);
    if (!$words) return ['results' => [], 'parts' => [], 'suggestion' => null];
    $out = [];
    $add = function (string $type, string $title, string $url, string $body, int $boost = 0) use (&$out, $q, $words) {
        $out[] = ['type' => $type, 'title' => $title, 'url' => $url, 'snippet' => search_snippet($body, $words), 'score' => search_score($title, $q, $words) + $boost];
    };

    [$w, $p] = search_where(['name', 'summary', 'overview', 'features', 'applications', 'meta_description'], $words);
    foreach (db_all("SELECT * FROM products WHERE status = 'published' AND {$w} LIMIT 20", $p) as $r) $add('Product', $r['name'], product_url($r), $r['summary'] ?: (string) $r['overview'], 6);

    [$w, $p] = search_where(['title', 'hero_title', 'hero_lead', 'meta_description', 'content', 'data'], $words);
    foreach (db_all("SELECT * FROM pages WHERE status = 'published' AND noindex = 0 AND deleted_at IS NULL AND {$w} LIMIT 20", $p) as $r) $add('Page', $r['title'], page_url($r), ($r['meta_description'] ?: $r['hero_lead']) ?: (string) $r['content'], 3);

    [$w, $p] = search_where(['p.title', 'p.excerpt', 'p.content', 'p.tags'], $words);
    foreach (db_all('SELECT p.* FROM posts p WHERE ' . POST_VISIBLE . " AND p.deleted_at IS NULL AND {$w} ORDER BY p.published_at DESC LIMIT 20", $p) as $r) $add('Article', $r['title'], post_url($r), $r['excerpt'] ?: (string) $r['content']);

    [$w, $p] = search_where(['title', 'client', 'location', 'summary', 'challenge', 'solution'], $words);
    foreach (db_all("SELECT * FROM projects WHERE status = 'published' AND {$w} LIMIT 20", $p) as $r) $add('Project', $r['title'], url('projects/' . $r['slug']), (string) $r['summary']);

    [$w, $p] = search_where(['title', 'category', 'description'], $words);
    foreach (db_all("SELECT * FROM downloads WHERE status = 'published' AND {$w} LIMIT 20", $p) as $r) $add('Download · ' . $r['category'], $r['title'], url('download/file/' . (int) $r['id']), (string) $r['description']);

    [$w, $p] = search_where(['title', 'department', 'location', 'summary', 'description'], $words);
    foreach (db_all("SELECT * FROM jobs WHERE status = 'published' AND (closes_at IS NULL OR closes_at >= CURDATE()) AND {$w} LIMIT 20", $p) as $r) $add('Job opening', $r['title'], url('careers/' . $r['slug']), (string) ($r['summary'] ?: $r['description']));

    usort($out, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['title'], $b['title']));
    $parts = function_exists('part_lookup') ? part_lookup($q, 6) : [];
    $suggestion = count($out) < 3 ? search_suggest($words) : null;
    return ['results' => array_slice($out, 0, $limit), 'parts' => $parts, 'suggestion' => $suggestion];
}

/** Closest known words for misspelt ones ("hydralic cilinder" → "hydraulic cylinder"). */
function search_suggest(array $words): ?string
{
    $vocab = [];
    $titles = array_merge(
        array_column(db_all("SELECT name t, summary s FROM products WHERE status = 'published'"), 't'),
        array_column(db_all("SELECT name t FROM product_categories"), 't'),
        array_column(db_all("SELECT title t FROM pages WHERE status = 'published' AND deleted_at IS NULL"), 't'),
        array_column(db_all('SELECT p.title t FROM posts p WHERE ' . POST_VISIBLE . ' ORDER BY p.published_at DESC LIMIT 300'), 't'),
        array_column(db_all("SELECT title t FROM projects WHERE status = 'published'"), 't'),
        array_column(db_all("SELECT name t FROM industries"), 't'),
    );
    foreach ($titles as $t) foreach (search_words((string) $t) as $w) if (mb_strlen($w) >= 3 && !ctype_digit($w)) $vocab[$w] = true;
    $changed = false;
    $fixed = [];
    foreach ($words as $w) {
        if (isset($vocab[$w]) || mb_strlen($w) < 3 || preg_match('~\d~', $w)) { $fixed[] = $w; continue; }
        $best = null; $bestD = PHP_INT_MAX;
        $max = mb_strlen($w) <= 4 ? 1 : (mb_strlen($w) <= 8 ? 2 : 3);
        foreach (array_keys($vocab) as $v) {
            if (abs(strlen($v) - strlen($w)) > $max) continue;
            $d = levenshtein($w, $v);
            // Prefer words sharing the first letter on ties ("cilinder" → "cylinder" over "cinder")
            if ($d < $bestD || ($d === $bestD && $best !== null && $v[0] === $w[0] && $best[0] !== $w[0])) { $best = $v; $bestD = $d; }
        }
        if ($best !== null && $bestD <= $max) { $fixed[] = $best; $changed = true; } else $fixed[] = $w;
    }
    return $changed ? implode(' ', $fixed) : null;
}
