<?php
/** Public website handlers. */
declare(strict_types=1);

function site_render(array $meta, string $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo minify_html(view('site/layout', ['meta' => $meta, 'body' => $body]));
}

/** Admins may preview drafts with ?preview=1 */
function can_preview(): bool
{
    if (empty($_GET['preview']) || empty($_COOKIE['ivadmin'])) return false;
    return (bool) current_user();
}

function page_meta(array $page, array $extra = []): array
{
    return array_merge([
        'title' => $page['meta_title'] ?: $page['title'],
        'description' => $page['meta_description'] ?: str_limit($page['hero_lead'] ?: strip_tags((string) $page['content']), 160),
        'canonical' => abs_url($page['slug'] === 'home' ? '' : $page['slug']),
        'og_image' => $page['og_image'] ?: $page['hero_image'],
        'noindex' => (bool) $page['noindex'] || $page['status'] !== 'published',
        'is_home' => $page['slug'] === 'home',
        'section' => $page['slug'],
        'crumbs' => $page['slug'] === 'home' ? [] : [['label' => $page['title'], 'url' => page_url($page)]],
    ], $extra);
}

function site_page(string $slug = 'home'): void
{
    $slug = strtolower($slug);
    if ($slug === 'home' && request_path() !== '/') redirect('', 301);
    $page = page_by_slug($slug, !can_preview());
    if (!$page) abort(404);
    $tpl = $page['template'];
    $file = APP_DIR . '/views/site/t_' . $tpl . '.php';
    if (!is_file($file)) $tpl = 'default';
    $meta = page_meta($page);
    $data = page_data($page);
    $vars = ['page' => $page, 'data' => $data, 'meta' => &$meta];

    if ($tpl === 'blog') {
        $vars += blog_index_vars($data, null);
        if ($vars['pager']['page'] > 1 || $vars['q'] !== '') { $meta['noindex'] = $vars['q'] !== ''; $meta['canonical'] = abs_url('blog' . ($vars['pager']['page'] > 1 ? '?page=' . $vars['pager']['page'] : '')); }
    }
    $body = view('site/t_' . $tpl, $vars);
    $meta['edit'] = ['Edit page', 'admin/pages/' . $page['id'] . '/edit', 'content'];
    site_render($meta, $body);
}

function blog_index_vars(array $data, ?array $category): array
{
    $per = max(3, (int) ($data['per_page'] ?? 9));
    $q = mb_substr(trim((string) query('q', '')), 0, 100);
    $pageNo = max(1, (int) query('page', 1));
    $count = posts_query(['category_id' => $category['id'] ?? null, 'q' => $q, 'limit' => 1]);
    $pager = paginate($count['total'], $per, $pageNo);
    $res = posts_query(['category_id' => $category['id'] ?? null, 'q' => $q, 'limit' => $per, 'offset' => $pager['offset']]);
    return ['posts' => $res['rows'], 'pager' => $pager, 'q' => $q, 'category' => $category, 'categories' => post_categories(true)];
}

function site_blog_category(string $slug): void
{
    $cat = db_one("SELECT * FROM categories WHERE type = 'post' AND slug = ?", [$slug]);
    $page = page_by_slug('blog');
    if (!$cat || !$page) abort(404);
    $data = page_data($page);
    $vars = ['page' => $page, 'data' => $data] + blog_index_vars($data, $cat);
    $meta = page_meta($page, [
        'title' => $cat['meta_title'] ?: $cat['name'] . ' Articles',
        'description' => $cat['meta_description'] ?: ($cat['description'] ?: 'Articles about ' . $cat['name'] . ' from ' . company('name') . '.'),
        'canonical' => abs_url('blog/category/' . $cat['slug']),
        'section' => 'blog',
        'crumbs' => [['label' => $page['title'], 'url' => url('blog')], ['label' => $cat['name'], 'url' => url('blog/category/' . $cat['slug'])]],
    ]);
    $vars['meta'] = &$meta;
    $meta['edit'] = ['Manage posts', 'admin/posts', 'content'];
    site_render($meta, view('site/t_blog', $vars));
}

function site_blog_post(string $slug): void
{
    $preview = can_preview();
    $post = db_one('SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name FROM posts p
        LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id
        WHERE p.slug = ?' . ($preview ? '' : ' AND ' . POST_VISIBLE), [$slug]);
    if (!$post) abort(404);
    if (!$preview) db_query('UPDATE posts SET views = views + 1 WHERE id = ?', [$post['id']]);

    [$content, $toc] = add_heading_ids(fix_internal_links(render_shortcodes((string) $post['content'])));
    $related = posts_query(['category_id' => $post['category_id'], 'exclude' => $post['id'], 'limit' => 3])['rows'];
    if (count($related) < 3) {
        $more = posts_query(['exclude' => $post['id'], 'limit' => 6])['rows'];
        foreach ($more as $m) if (count($related) < 3 && !in_array($m['id'], array_column($related, 'id'))) $related[] = $m;
    }
    $prev = db_one('SELECT p.title, p.slug FROM posts p WHERE ' . POST_VISIBLE . ' AND p.published_at < ? ORDER BY p.published_at DESC LIMIT 1', [$post['published_at'] ?? now()]);
    $next = db_one('SELECT p.title, p.slug FROM posts p WHERE ' . POST_VISIBLE . ' AND p.published_at > ? ORDER BY p.published_at ASC LIMIT 1', [$post['published_at'] ?? now()]);

    $url = abs_url('blog/' . $post['slug']);
    // FAQPage structured data from the article's "Frequently Asked Questions" section (H3 question + paragraph answer)
    $faqLd = null;
    if (preg_match('~<h2[^>]*>\s*Frequently Asked Questions\s*</h2>(.*?)(?=<h2|$)~is', $content, $fm)
        && preg_match_all('~<h3[^>]*>(.*?)</h3>\s*<p>(.*?)</p>~is', $fm[1], $qa, PREG_SET_ORDER)) {
        $faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($m) => [
            '@type' => 'Question', 'name' => trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES)),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES))],
        ], $qa)];
    }
    $crumbs = [['label' => 'Blog', 'url' => url('blog')]];
    if ($post['category_name']) $crumbs[] = ['label' => $post['category_name'], 'url' => url('blog/category/' . $post['category_slug'])];
    $crumbs[] = ['label' => $post['title'], 'url' => post_url($post)];
    $meta = [
        'title' => $post['meta_title'] ?: $post['title'],
        'description' => $post['meta_description'] ?: str_limit($post['excerpt'] ?: $post['content'], 160),
        'canonical' => $post['canonical_url'] ?: $url,
        'og_image' => $post['featured_image'],
        'og_type' => 'article',
        'noindex' => (bool) $post['noindex'] || $preview && $post['status'] === 'draft',
        'section' => 'blog',
        'crumbs' => $crumbs,
        'jsonld' => [[
            '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'],
            'description' => $post['meta_description'] ?: str_limit($post['excerpt'], 200),
            'image' => $post['featured_image'] ? image_abs_url($post['featured_image']) : null,
            'datePublished' => $post['published_at'] ? date('c', strtotime($post['published_at'])) : null,
            'dateModified' => date('c', strtotime($post['updated_at'])),
            'author' => ['@type' => 'Organization', 'name' => company('name')],
            'publisher' => ['@type' => 'Organization', 'name' => company('name')],
            'mainEntityOfPage' => $url,
            'commentCount' => comment_count((int) $post['id']),
        ]],
    ];
    if ($faqLd) $meta['jsonld'][] = $faqLd;
    $meta['edit'] = ['Edit post', 'admin/posts/' . $post['id'] . '/edit', 'content'];
    site_render($meta, view('site/post', compact('post', 'content', 'toc', 'related', 'prev', 'next', 'url')));
}

function site_product(string $slug): void
{
    $p = null;
    foreach (products_all() as $row) if ($row['slug'] === $slug) $p = $row;
    if (!$p) abort(404);
    $cat = product_category_name((int) $p['category_id']);
    $meta = [
        'title' => $p['meta_title'] ?: $p['name'],
        'description' => $p['meta_description'] ?: str_limit($p['summary'], 160),
        'canonical' => abs_url('products/' . $p['slug']),
        'og_image' => $p['image'],
        'section' => 'products',
        'crumbs' => [['label' => 'Products', 'url' => url('products')], ['label' => $p['name'], 'url' => product_url($p)]],
        'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'description' => $p['summary'],
            'image' => image_abs_url($p['image']), 'category' => $cat, 'brand' => ['@type' => 'Brand', 'name' => company('name')]] + reviews_jsonld((int) $p['id'])],
    ];
    $meta['edit'] = ['Edit product', 'admin/r/products/' . $p['id'] . '/edit', 'content'];
    site_render($meta, view('site/product', ['p' => $p, 'cat' => $cat]));
}

function site_error(int $status, string $message = ''): never
{
    error_respond($status, $message); // app/lib/errors.php
}

/* ------------------------------------------------------------------ */
/* Lead form + click tracking                                          */
/* ------------------------------------------------------------------ */

function site_lead_submit(): void
{
    $res = lead_submit($_POST, $_FILES);
    if (wants_json()) json_response($res, $res['ok'] ? 200 : 422);
    // No-JS fallback
    $back = ($_POST['form'] ?? '') === 'quote' ? 'quote' : 'contact';
    if ($res['ok']) redirect($back . '?sent=1#form');
    $meta = ['title' => 'Please check your enquiry', 'description' => '', 'noindex' => true, 'crumbs' => []];
    $msg = $res['message'] ?? 'Please check the form.';
    $list = '';
    foreach ($res['errors'] ?? [] as $err) $list .= '<li>' . e($err) . '</li>';
    site_render($meta, '<section class="section"><div class="mx-auto max-w-2xl px-4"><h1 class="section-title">Please check your enquiry</h1><p class="mt-4">' . e($msg) . '</p><ul class="mt-4 list-disc pl-6 text-red-700">' . $list . '</ul><a class="btn btn-primary mt-8" href="' . e(url($back)) . '">Go back to the form</a></div></section>', 422);
}

/** Page-view beacon for the built-in analytics (always answers 204). */
function site_hit(): void
{
    try { analytics_record($_POST); } catch (Throwable $e) { error_log('analytics: ' . $e->getMessage()); }
    header('Cache-Control: no-store');
    http_response_code(204);
}

function site_track(): void
{
    $type = (string) ($_POST['type'] ?? '');
    if (in_array($type, ['whatsapp', 'phone', 'email', 'share'], true) && rate_limit(ip_bucket('track'), 30, 600)) {
        db_insert('events', ['type' => $type, 'page' => mb_substr((string) ($_POST['page'] ?? ''), 0, 500)]);
    }
    http_response_code(204);
}

/* ------------------------------------------------------------------ */
/* Feeds                                                               */
/* ------------------------------------------------------------------ */

function site_sitemap_xml(): void
{
    $urls = [];
    foreach (db_all("SELECT slug, updated_at FROM pages WHERE status = 'published' AND noindex = 0") as $p) {
        $urls[] = [abs_url($p['slug'] === 'home' ? '' : $p['slug']), $p['updated_at'], $p['slug'] === 'home' ? '1.0' : '0.8'];
    }
    foreach (db_all("SELECT slug, updated_at FROM products WHERE status = 'published'") as $p) $urls[] = [abs_url('products/' . $p['slug']), $p['updated_at'], '0.8'];
    $projects = db_all("SELECT slug, updated_at FROM projects WHERE status = 'published'");
    if ($projects) $urls[] = [abs_url('projects'), null, '0.7'];
    foreach ($projects as $p) $urls[] = [abs_url('projects/' . $p['slug']), $p['updated_at'], '0.7'];
    foreach (['service-request' => '0.6', 'parts' => '0.6', 'calculators' => '0.6', 'downloads' => '0.5', 'reviews' => '0.4'] as $u => $prio) $urls[] = [abs_url($u), null, $prio];
    if (db_val("SELECT 1 FROM distributors WHERE status = 'published' LIMIT 1")) $urls[] = [abs_url('distributors'), null, '0.5'];
    $jobs = jobs_open();
    if ($jobs) $urls[] = [abs_url('careers'), null, '0.5'];
    foreach ($jobs as $j) $urls[] = [abs_url('careers/' . $j['slug']), $j['updated_at'], '0.5'];
    foreach (db_all("SELECT c.slug FROM categories c WHERE c.type = 'post' AND EXISTS (SELECT 1 FROM posts p WHERE p.category_id = c.id AND " . POST_VISIBLE . ')') as $c) $urls[] = [abs_url('blog/category/' . $c['slug']), null, '0.5'];
    foreach (db_all('SELECT p.slug, p.updated_at FROM posts p WHERE ' . POST_VISIBLE . ' AND p.noindex = 0 ORDER BY p.published_at DESC') as $p) $urls[] = [abs_url('blog/' . $p['slug']), $p['updated_at'], '0.6'];

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $mod, $prio]) {
        echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . '<priority>' . $prio . "</priority></url>\n";
    }
    echo "</urlset>\n";
}

function site_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    if (!setting('search_indexing', true)) { echo "User-agent: *\nDisallow: /\n"; return; }
    echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /install\nDisallow: /lead\n\nSitemap: " . abs_url('sitemap.xml') . "\n";
}

function site_manifest(): void
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    $icon = fn($f, $s, $purpose = 'any') => ['src' => url('assets/icons/' . $f), 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => $purpose];
    echo json_encode([
        'name' => company('name'),
        'short_name' => company('short_name', company('name')),
        'description' => company('description'),
        'start_url' => url(''),
        'scope' => url(''),
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#101D32',
        'icons' => [$icon('android-chrome-192x192.png', 192), $icon('android-chrome-512x512.png', 512), $icon('maskable-512x512.png', 512, 'maskable')],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function site_browserconfig(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo '<?xml version="1.0" encoding="utf-8"?><browserconfig><msapplication><tile><square150x150logo src="'
        . e(url('assets/icons/mstile-150x150.png')) . '"/><TileColor>#101D32</TileColor></tile></msapplication></browserconfig>';
}

function site_feed(): void
{
    $posts = db_all('SELECT p.title, p.slug, p.excerpt, p.published_at FROM posts p WHERE ' . POST_VISIBLE . ' ORDER BY p.published_at DESC LIMIT 20');
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
    echo '<title>' . e(company('name')) . ' Blog</title><link>' . e(abs_url('blog')) . '</link><description>' . e(company('description')) . '</description>';
    echo '<atom:link href="' . e(abs_url('feed.xml')) . '" rel="self" type="application/rss+xml"/>';
    foreach ($posts as $p) {
        echo '<item><title>' . e($p['title']) . '</title><link>' . e(abs_url('blog/' . $p['slug'])) . '</link><guid>' . e(abs_url('blog/' . $p['slug'])) . '</guid>'
            . '<pubDate>' . date(DATE_RSS, strtotime($p['published_at'])) . '</pubDate><description>' . e($p['excerpt']) . '</description></item>';
    }
    echo '</channel></rss>';
}

/** /login (and /admin/): straight to the dashboard when signed in, otherwise the sign-in page. */
function site_login_shortcut(): void
{
    redirect(site_admin_user() ? 'admin' : 'admin/login');
}
