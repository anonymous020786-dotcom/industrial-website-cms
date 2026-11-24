<?php
/** Front controller routing. */
declare(strict_types=1);

function routes(): array
{
    return [
        // Installer
        ['GET|POST', '/install', 'install_handler', 'install'],

        // Public
        ['POST', '/lead', 'site_lead_submit', 'site'],
        ['POST', '/track', 'site_track', 'site'],
        ['GET', '/sitemap.xml', 'site_sitemap_xml', 'site'],
        ['GET', '/robots.txt', 'site_robots', 'site'],
        ['GET', '/feed.xml', 'site_feed', 'site'],
        ['GET', '/site.webmanifest', 'site_manifest', 'site'],
        ['GET', '/browserconfig.xml', 'site_browserconfig', 'site'],
        ['GET', '/products/{slug}', 'site_product', 'site'],
        ['GET', '/blog/category/{slug}', 'site_blog_category', 'site'],
        ['GET', '/blog/{slug}', 'site_blog_post', 'site'],

        // Admin — auth
        ['GET|POST', '/admin/login', 'admin_login', 'admin'],
        ['GET|POST', '/admin/login/verify', 'admin_login_verify', 'admin'],
        ['POST', '/admin/login/resend', 'admin_login_resend', 'admin'],
        ['GET|POST', '/admin/forgot', 'admin_forgot', 'admin'],
        ['GET|POST', '/admin/reset', 'admin_reset', 'admin'],
        ['GET', '/admin/dev-mail', 'admin_dev_mail', 'admin_settings'],
        ['POST', '/admin/logout', 'admin_logout', 'admin'],
        ['GET', '/admin', 'admin_dashboard', 'admin'],

        // Leads
        ['GET', '/admin/leads', 'admin_leads', 'admin_leads'],
        ['GET', '/admin/leads/export', 'admin_leads_export', 'admin_leads'],
        ['POST', '/admin/leads/bulk', 'admin_leads_bulk', 'admin_leads'],
        ['GET|POST', '/admin/leads/new', 'admin_lead_new', 'admin_leads'],
        ['GET', '/admin/leads/{id}', 'admin_lead_show', 'admin_leads'],
        ['POST', '/admin/leads/{id}', 'admin_lead_update', 'admin_leads'],
        ['POST', '/admin/leads/{id}/note', 'admin_lead_note', 'admin_leads'],
        ['POST', '/admin/leads/{id}/delete', 'admin_lead_delete', 'admin_leads'],
        ['GET', '/admin/leads/file/{id}', 'admin_lead_file', 'admin_leads'],

        // Posts & pages: trash, restore, duplicate, revisions (shared handlers)
        ['POST', '/admin/{type}/{id}/restore', 'admin_content_restore', 'admin_content'],
        ['POST', '/admin/{type}/{id}/destroy', 'admin_content_destroy', 'admin_content'],
        ['POST', '/admin/{type}/{id}/duplicate', 'admin_content_duplicate', 'admin_content'],
        ['POST', '/admin/{type}/{id}/revisions/{rid}/restore', 'admin_revision_restore', 'admin_content'],
        ['POST', '/admin/{type}/empty-trash', 'admin_content_empty_trash', 'admin_content'],

        // Posts
        ['GET', '/admin/posts', 'admin_posts', 'admin_content'],
        ['GET|POST', '/admin/posts/new', 'admin_post_edit', 'admin_content'],
        ['GET|POST', '/admin/posts/{id}/edit', 'admin_post_edit', 'admin_content'],
        ['POST', '/admin/posts/{id}/delete', 'admin_post_delete', 'admin_content'],
        ['POST', '/admin/posts/bulk', 'admin_posts_bulk', 'admin_content'],

        // Pages
        ['GET', '/admin/pages', 'admin_pages', 'admin_content'],
        ['GET|POST', '/admin/pages/new', 'admin_page_edit', 'admin_content'],
        ['GET|POST', '/admin/pages/{id}/edit', 'admin_page_edit', 'admin_content'],
        ['POST', '/admin/pages/{id}/delete', 'admin_page_delete', 'admin_content'],
        ['POST', '/admin/pages/bulk', 'admin_pages_bulk', 'admin_content'],

        // Generic resources: products, industries, categories, product categories, users
        ['GET', '/admin/r/{res}', 'admin_resource_list', 'admin_content'],
        ['GET|POST', '/admin/r/{res}/new', 'admin_resource_edit', 'admin_content'],
        ['GET|POST', '/admin/r/{res}/{id}/edit', 'admin_resource_edit', 'admin_content'],
        ['POST', '/admin/r/{res}/{id}/delete', 'admin_resource_delete', 'admin_content'],
        ['POST', '/admin/r/{res}/{id}/duplicate', 'admin_resource_duplicate', 'admin_content'],

        // Media
        ['GET', '/admin/media', 'admin_media', 'admin_content'],
        ['GET', '/admin/media.json', 'admin_media_json', 'admin_content'],
        ['POST', '/admin/media/upload', 'admin_media_upload', 'admin_content'],
        ['POST', '/admin/media/{id}/update', 'admin_media_update', 'admin_content'],
        ['POST', '/admin/media/{id}/delete', 'admin_media_delete', 'admin_content'],
        ['GET', '/admin/icon.svg', 'admin_icon_svg', 'admin_content'],

        // Settings, users, profile
        ['GET|POST', '/admin/settings/{tab}', 'admin_settings', 'admin_settings'],
        ['POST', '/admin/settings-test-email', 'admin_settings_test_email', 'admin_settings'],
        ['GET|POST', '/admin/profile', 'admin_profile', 'admin_settings'],

        // Catch-all CMS pages (home, about, legal pages, custom pages…)
        ['GET', '/', 'site_page', 'site'],
        ['GET', '/{slug}', 'site_page', 'site'],
    ];
}

function app_run(): void
{
    $path = request_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'HEAD') $method = 'GET';

    if (!is_installed() && $path !== '/install') redirect('install');

    try {
        foreach (routes() as [$methods, $pattern, $handler, $file]) {
            $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[^/]+)', $pattern) . '$~';
            if (!preg_match($regex, $path, $m)) continue;
            if (!in_array($method, explode('|', $methods), true)) continue;
            require_once APP_DIR . '/handlers/' . $file . '.php';
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            send_security_headers(str_starts_with($path, '/admin'));
            $handler(...array_values($params));
            return;
        }
        abort(404);
    } catch (HttpException $e) {
        http_response_code($e->status);
        if (str_starts_with($path, '/admin') && $e->status !== 404) {
            require_once APP_DIR . '/handlers/admin.php';
            echo admin_layout('Error', '<div class="adm-card p-10 text-center"><p class="text-5xl font-bold text-navy">' . $e->status . '</p><p class="mt-3 text-charcoal/70">' . e($e->getMessage()) . '</p><a class="adm-btn adm-btn-primary mt-6" href="' . e(url('admin')) . '">Back to dashboard</a></div>');
            return;
        }
        require_once APP_DIR . '/handlers/site.php';
        site_error($e->status, $e->getMessage());
    }
}
