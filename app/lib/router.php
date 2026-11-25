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
        ['GET', '/projects', 'site_projects', 'site_extra'],
        ['GET', '/projects/{slug}', 'site_project', 'site_extra'],
        ['GET', '/download/datasheet/{slug}', 'site_datasheet', 'site_extra'],
        ['GET|POST', '/catalogue', 'site_catalogue', 'site_extra'],
        ['GET', '/cron/{key}', 'site_cron', 'site_extra'],
        ['POST', '/telegram/webhook', 'telegram_webhook', 'admin_telegram'],
        ['GET', '/blog/category/{slug}', 'site_blog_category', 'site'],
        ['GET', '/blog/{slug}', 'site_blog_post', 'site'],

        // Admin — auth (short, easy-to-remember addresses for the team)
        ['GET', '/login', 'site_login_shortcut', 'site'],
        ['GET', '/admin/', 'site_login_shortcut', 'site'],
        ['GET|POST', '/admin/login', 'admin_login', 'admin'],
        ['GET|POST', '/admin/login/verify', 'admin_login_verify', 'admin'],
        ['POST', '/admin/login/resend', 'admin_login_resend', 'admin'],
        ['POST', '/admin/login/verify/status', 'admin_login_verify_status', 'admin'],
        ['POST', '/admin/telegram/link', 'admin_telegram_link', 'admin_telegram'],
        ['POST', '/admin/telegram/status', 'admin_telegram_status', 'admin_telegram'],
        ['POST', '/admin/telegram/prefs', 'admin_telegram_prefs', 'admin_telegram'],
        ['POST', '/admin/telegram/test', 'admin_telegram_test', 'admin_telegram'],
        ['POST', '/admin/telegram/unlink', 'admin_telegram_unlink', 'admin_telegram'],
        ['GET|POST', '/admin/login/2fa', 'admin_login_2fa', 'admin_security'],
        ['POST', '/admin/login/passkey/options', 'admin_login_passkey_options', 'admin_security'],
        ['POST', '/admin/login/passkey', 'admin_login_passkey', 'admin_security'],

        // Step-up confirmation, notifications, profile picture
        ['GET|POST', '/admin/confirm', 'admin_confirm', 'admin_account'],
        ['POST', '/admin/confirm/passkey/options', 'admin_confirm_passkey_options', 'admin_account'],
        ['POST', '/admin/confirm/passkey', 'admin_confirm_passkey', 'admin_account'],
        ['GET', '/admin/confirm/resume', 'admin_confirm_resume', 'admin_account'],
        ['GET', '/admin/notifications', 'admin_notifications', 'admin_account'],
        ['POST', '/admin/notifications/read', 'admin_notifications_read', 'admin_account'],
        ['POST', '/admin/notify-prefs', 'admin_notify_prefs', 'admin_account'],
        ['POST', '/admin/avatar', 'admin_avatar_upload', 'admin_account'],
        ['POST', '/admin/avatar/remove', 'admin_avatar_remove', 'admin_account'],
        ['POST', '/admin/devices/signout-all', 'admin_devices_signout_all', 'admin_account'],
        ['POST', '/admin/devices/{id}', 'admin_device_action', 'admin_account'],

        // Passkeys & authenticator app (signed-in user)
        ['POST', '/admin/passkeys/options', 'admin_passkey_options', 'admin_security'],
        ['POST', '/admin/passkeys/register', 'admin_passkey_register', 'admin_security'],
        ['POST', '/admin/passkeys/{id}/rename', 'admin_passkey_rename', 'admin_security'],
        ['POST', '/admin/passkeys/{id}/delete', 'admin_passkey_delete', 'admin_security'],
        ['POST', '/admin/2fa/setup', 'admin_totp_setup', 'admin_security'],
        ['POST', '/admin/2fa/confirm', 'admin_totp_confirm', 'admin_security'],
        ['POST', '/admin/2fa/disable', 'admin_totp_disable', 'admin_security'],
        ['POST', '/admin/2fa/recovery', 'admin_recovery_regenerate', 'admin_security'],
        ['POST', '/admin/2fa/recovery/download', 'admin_recovery_download', 'admin_security'],
        ['POST', '/admin/2fa/recovery/forget', 'admin_recovery_forget', 'admin_security'],

        // Tools: redirects, reports, backups, cron
        ['GET', '/admin/redirects', 'admin_redirects', 'admin_tools'],
        ['POST', '/admin/redirects/save', 'admin_redirect_save', 'admin_tools'],
        ['GET', '/admin/redirects/export', 'admin_redirects_export', 'admin_tools'],
        ['POST', '/admin/redirects/import', 'admin_redirects_import', 'admin_tools'],
        ['POST', '/admin/redirects/404', 'admin_404_action', 'admin_tools'],
        ['POST', '/admin/redirects/{id}/delete', 'admin_redirect_delete', 'admin_tools'],
        ['GET', '/admin/reports', 'admin_reports', 'admin_tools'],
        ['GET', '/admin/backups', 'admin_backups', 'admin_tools'],
        ['POST', '/admin/backups/create', 'admin_backup_create', 'admin_tools'],
        ['POST', '/admin/backups/download', 'admin_backup_download', 'admin_tools'],
        ['POST', '/admin/backups/delete', 'admin_backup_delete', 'admin_tools'],
        ['POST', '/admin/cron/run', 'admin_cron_run', 'admin_tools'],
        ['POST', '/admin/maintenance', 'admin_maintenance_switch', 'admin_system'],
        ['POST', '/admin/maintenance/link', 'admin_maintenance_link', 'admin_system'],
        ['GET', '/admin/errors', 'admin_errors', 'admin_system'],
        ['POST', '/admin/errors/resolve', 'admin_errors_resolve', 'admin_system'],
        ['POST', '/admin/errors/test', 'admin_errors_test', 'admin_system'],
        ['GET', '/admin/errors/{id}', 'admin_error_show', 'admin_system'],
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
        ['POST', '/admin/media/optimize', 'admin_media_optimize', 'admin_content'],
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

    // Maintenance mode: visitors get the maintenance page (admins and allowed IPs pass through)
    if (is_installed()) maintenance_gate($path, $method);

    try {
        $allowed = [];
        foreach (routes() as [$methods, $pattern, $handler, $file]) {
            $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[^/]+)', $pattern) . '$~';
            if (!preg_match($regex, $path, $m)) continue;
            if (!in_array($method, explode('|', $methods), true)) { array_push($allowed, ...explode('|', $methods)); continue; }
            require_once APP_DIR . '/handlers/' . $file . '.php';
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            send_security_headers(str_starts_with($path, '/admin'));
            $handler(...array_values($params));
            return;
        }
        if ($allowed) abort(405, '', ['allow' => array_values(array_unique(array_merge($allowed, in_array('GET', $allowed, true) ? ['HEAD'] : [])))]);
        abort(404);
    } catch (HttpException $e) {
        // Before showing a public 404: apply a matching redirect, otherwise record the broken address
        if ($e->status === 404 && $method === 'GET' && !str_starts_with($path, '/admin')) {
            try { redirect_try($path); not_found_log($path); } catch (Throwable $t) { error_log('redirects: ' . $t->getMessage()); }
        }
        send_security_headers(str_starts_with($path, '/admin'));
        error_respond($e->status, $e->getMessage(), $e->options);
    }
}
