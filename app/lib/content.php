<?php
/** Content queries used by public templates (cached per request). */
declare(strict_types=1);

function page_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    return db_one('SELECT * FROM pages WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : ''), [$slug]);
}

function page_url(array|string $page): string
{
    $slug = is_array($page) ? $page['slug'] : $page;
    return $slug === 'home' ? url('') : url($slug);
}

function product_categories(): array
{
    static $c = null;
    return $c ??= db_all('SELECT * FROM product_categories ORDER BY sort_order, name');
}

function product_category_name(?int $id): string
{
    foreach (product_categories() as $c) if ((int) $c['id'] === (int) $id) return $c['name'];
    return '';
}

function products_all(): array
{
    static $p = null;
    if ($p !== null) return $p;
    $p = db_all("SELECT * FROM products WHERE status = 'published' ORDER BY sort_order, name");
    foreach ($p as &$row) {
        foreach (['features', 'applications', 'specs'] as $k) $row[$k] = json_decode_array($row[$k]);
    }
    return $p;
}

function product_url(array $p): string { return url('products/' . $p['slug']); }

function industries_all(): array
{
    static $i = null;
    if ($i !== null) return $i;
    $i = db_all("SELECT * FROM industries WHERE status = 'published' ORDER BY sort_order, name");
    foreach ($i as &$row) $row['product_ids'] = array_map('intval', json_decode_array($row['product_ids']));
    return $i;
}

function post_categories(bool $withCounts = false): array
{
    if (!$withCounts) return db_all("SELECT * FROM categories WHERE type = 'post' ORDER BY sort_order, name");
    return db_all("SELECT c.*, COUNT(p.id) AS post_count FROM categories c
        LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' AND p.published_at <= NOW()
        WHERE c.type = 'post' GROUP BY c.id ORDER BY c.sort_order, c.name");
}

/** SQL fragment for publicly visible posts (published, or scheduled whose time has come). */
const POST_VISIBLE = "p.status IN ('published','scheduled') AND p.published_at IS NOT NULL AND p.published_at <= NOW()";

function posts_query(array $opt = []): array
{
    $where = [POST_VISIBLE];
    $params = [];
    if (!empty($opt['category_id'])) { $where[] = 'p.category_id = ?'; $params[] = (int) $opt['category_id']; }
    if (!empty($opt['exclude'])) { $where[] = 'p.id <> ?'; $params[] = (int) $opt['exclude']; }
    if (!empty($opt['q'])) {
        $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.tags LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $opt['q']) . '%';
        array_push($params, $like, $like, $like);
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM posts p WHERE {$sqlWhere}", $params);
    $limit = (int) ($opt['limit'] ?? 9);
    $offset = (int) ($opt['offset'] ?? 0);
    $rows = db_all("SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.featured_alt, p.published_at, p.reading_minutes, p.category_id,
            c.name AS category_name, c.slug AS category_slug, u.name AS author_name
        FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id
        WHERE {$sqlWhere} ORDER BY p.published_at DESC, p.id DESC LIMIT {$limit} OFFSET {$offset}", $params);
    return ['rows' => $rows, 'total' => $total];
}

function post_url(array $p): string { return url('blog/' . $p['slug']); }

function reading_minutes(string $html): int
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int) round($words / 220));
}

/* ------------------------------------------------------------------ */
/* Navigation                                                          */
/* ------------------------------------------------------------------ */

/** Header menu from settings: [{label, url, mega}] */
function header_menu(): array
{
    return setting('menu_header', []) ?: [];
}

function footer_menu(): array
{
    return setting('menu_footer', []) ?: [];
}

function legal_pages(): array
{
    return db_all("SELECT slug, title FROM pages WHERE template = 'legal' AND status = 'published' ORDER BY id");
}

function menu_href(string $u): string
{
    $u = trim($u);
    if ($u === '' || $u === '/') return url('');
    return url($u);
}

function menu_active(string $u): bool
{
    if (preg_match('~^(https?:|mailto:|tel:|#)~', $u)) return false;
    $path = '/' . trim(parse_url($u, PHP_URL_PATH) ?? '', '/');
    return current_url_is($path);
}


/** "AB-12.345 / c" and "ab12345c" are the same part number. */
function part_norm(string $s): string
{
    return strtoupper(preg_replace('~[^A-Za-z0-9]+~', '', $s) ?? '');
}

/** Published job openings that are still open. */
function jobs_open(): array
{
    return db_all("SELECT * FROM jobs WHERE status = 'published' AND (closes_at IS NULL OR closes_at >= CURDATE()) ORDER BY sort_order, id DESC");
}
