<?php
/** Admin: layout, auth, dashboard. */
declare(strict_types=1);

function admin_nav(): array
{
    $newLeads = can('leads') ? (int) db_val("SELECT COUNT(*) FROM leads WHERE status = 'new'") : 0;
    return [
        ['Dashboard', 'admin', 'layout-dashboard', 'dashboard'],
        ['Leads', 'admin/leads', 'inbox', 'leads', $newLeads],
        ['_', 'Content'],
        ['Pages', 'admin/pages', 'file-text', 'content'],
        ['Blog Posts', 'admin/posts', 'newspaper', 'content'],
        ['Categories', 'admin/r/categories', 'folder-tree', 'content'],
        ['Products', 'admin/r/products', 'package', 'content'],
        ['Product Categories', 'admin/r/product_categories', 'layers', 'content'],
        ['Industries', 'admin/r/industries', 'factory', 'content'],
        ['Projects', 'admin/r/projects', 'briefcase-business', 'content'],
        ['Certificates', 'admin/r/certificates', 'award', 'content'],
        ['Testimonials', 'admin/r/testimonials', 'message-square-quote', 'content'],
        ['Media Library', 'admin/media', 'images', 'content'],
        ['_', 'Insights'],
        ['Reports', 'admin/reports', 'chart-column', 'leads'],
        ['Redirects & 404s', 'admin/redirects', 'route', 'settings'],
        ['_', 'Configuration'],
        ['Settings', 'admin/settings/general', 'settings', 'settings'],
        ['Integrations', 'admin/settings/integrations', 'plug', 'settings'],
        ['Backups & automation', 'admin/backups', 'database-backup', 'settings'],
        ['Users', 'admin/r/users', 'users', 'users'],
    ];
}

function admin_layout(string $title, string $content, array $opt = []): string
{
    return view('admin/layout', ['title' => $title, 'content' => $content, 'actions' => $opt['actions'] ?? '', 'crumbs' => $opt['crumbs'] ?? [], 'wide' => $opt['wide'] ?? false]);
}

function admin_render(string $title, string $view, array $data = [], array $opt = []): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo minify_html(admin_layout($title, view('admin/' . $view, $data), $opt));
}

/* ------------------------------------------------------------------ */
/* Auth                                                                */
/* ------------------------------------------------------------------ */

function login_turnstile_on(): bool
{
    return turnstile_enabled() && (bool) setting('turnstile_login', true);
}

function login_redirect_after(): never
{
    $to = $_SESSION['intended'] ?? 'admin';
    unset($_SESSION['intended']);
    redirect(ltrim((string) $to, '/'));
}

/** Step 1: email + password (+ Cloudflare Turnstile when configured). */
function admin_login(): void
{
    session_boot();
    ensure_auth_tables();
    if (current_user()) redirect('admin');
    $error = null;
    $email = '';
    if (is_post()) {
        csrf_check();
        $email = strtolower((string) (input('login') ?? input('email', ''))); // email or username
        if (login_turnstile_on() && !turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) {
            $error = 'Security check failed. Please complete the verification and try again.';
            auth_log('login_captcha_failed', null, $email);
        } else {
            $user = verify_credentials($email, (string) ($_POST['password'] ?? ''));
            if (is_string($user)) {
                $error = $user;
            } elseif (device_trusted((int) $user['id']) || (!otp_enabled() && !totp_enabled_for($user))) {
                complete_login($user);
                login_redirect_after();
            } elseif (totp_enabled_for($user)) {
                // Authenticator app takes priority over emailed codes
                session_regenerate_id(true);
                $_SESSION['mfa_uid'] = (int) $user['id'];
                $_SESSION['mfa_started'] = time();
                redirect('admin/login/2fa');
            } else {
                session_regenerate_id(true);
                $_SESSION['otp_uid'] = (int) $user['id'];
                $_SESSION['otp_started'] = time();
                $sent = otp_send($user);
                if ($sent !== true) {
                    unset($_SESSION['otp_uid']);
                    $error = $sent . ' Contact your administrator, or run `php app/cli.php otp --off` on the server if email is misconfigured.';
                } else {
                    redirect('admin/login/verify');
                }
            }
        }
    }
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/login', ['error' => $error, 'email' => $email, 'step' => 'password', 'turnstile' => login_turnstile_on()]);
}

/** Step 2: 6-digit code sent by email. */
function admin_login_verify(): void
{
    session_boot();
    if (current_user()) redirect('admin');
    $user = otp_pending_user();
    if (!$user) {
        flash('info', 'Your sign-in session expired. Please enter your password again.');
        redirect('admin/login');
    }
    $error = null;
    if (is_post()) {
        csrf_check();
        $res = otp_verify((int) $user['id'], (string) input('code', ''));
        if ($res === true) {
            complete_login($user, !empty($_POST['trust']));
            login_redirect_after();
        }
        $error = $res;
    }
    header('Content-Type: text/html; charset=utf-8');
    $hasBackup = $user['backup_email'] && $user['backup_email_verified_at'];
    $sentTo = ($_SESSION['otp_to'] ?? 'primary') === 'backup' && $hasBackup ? $user['backup_email'] : $user['email'];
    $masked = mask_email((string) $sentTo);
    echo view('admin/login', ['error' => $error, 'step' => 'otp', 'maskedEmail' => $masked, 'trustDays' => (int) setting('otp_trust_days', 30),
        'hasBackup' => $hasBackup, 'sentToBackup' => $sentTo !== $user['email'], 'maskedBackup' => $hasBackup ? mask_email((string) $user['backup_email']) : '',
        'devCode' => config('debug') ? ($_SESSION['otp_dev_code'] ?? null) : null]);
}

function admin_login_resend(): void
{
    csrf_check();
    $user = otp_pending_user();
    if (!$user) redirect('admin/login');
    // Optionally send to the verified backup address instead of the primary
    $useBackup = input('to') === 'backup' && $user['backup_email'] && $user['backup_email_verified_at'];
    $res = otp_send($user, 'login', $useBackup ? $user['backup_email'] : null);
    if ($res === true) $_SESSION['otp_to'] = $useBackup ? 'backup' : 'primary';
    flash($res === true ? 'success' : 'error', $res === true ? 'A new code has been sent' . ($useBackup ? ' to your backup email.' : '.') : (string) $res);
    redirect('admin/login/verify');
}

/* ------------------------------------------------------------------ */
/* Forgot / reset password                                             */
/* ------------------------------------------------------------------ */

function admin_forgot(): void
{
    session_boot();
    ensure_auth_tables();
    if (current_user()) redirect('admin/profile');
    $error = null;
    $identifier = '';
    if (is_post()) {
        csrf_check();
        $identifier = strtolower(trim((string) input('login', '')));
        if ($identifier === '') {
            $error = 'Enter your email address or username.';
        } elseif (login_turnstile_on() && !turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))) {
            $error = 'Security check failed. Please complete the verification and try again.';
        } elseif ($err = password_reset_request($identifier)) {
            $error = $err;
        } else {
            redirect('admin/reset');
        }
    }
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/login', ['error' => $error, 'step' => 'forgot', 'email' => $identifier, 'turnstile' => login_turnstile_on()]);
}

function admin_reset(): void
{
    session_boot();
    if (current_user()) redirect('admin/profile');
    if (!isset($_SESSION['reset_uid'])) redirect('admin/forgot');
    $error = null;
    if (is_post()) {
        csrf_check();
        $res = login_turnstile_on() && !turnstile_verify((string) ($_POST['cf-turnstile-response'] ?? ''))
            ? 'Security check failed. Please complete the verification and try again.'
            : password_reset_complete((string) input('code', ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        if ($res === true) {
            flash('success', 'Your password has been reset. Please sign in with your new password.');
            redirect('admin/login');
        }
        $error = $res;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/login', ['error' => $error, 'step' => 'reset', 'turnstile' => login_turnstile_on(), 'devCode' => config('debug') ? ($_SESSION['otp_dev_code'] ?? null) : null]);
}

function admin_logout(): void
{
    csrf_check();
    logout_user();
    flash('success', 'You have been signed out.');
    redirect('admin/login');
}

/* ------------------------------------------------------------------ */
/* Dashboard                                                           */
/* ------------------------------------------------------------------ */

function admin_dashboard(): void
{
    $user = require_login('dashboard');
    $d = [];
    if (can('leads')) {
        $d['leads_new'] = (int) db_val("SELECT COUNT(*) FROM leads WHERE status = 'new'");
        $d['leads_open'] = (int) db_val("SELECT COUNT(*) FROM leads WHERE status IN ('" . implode("','", LEAD_OPEN) . "')");
        $d['leads_30'] = (int) db_val('SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 30 DAY AND status <> \'spam\'');
        $d['leads_prev30'] = (int) db_val('SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 60 DAY AND created_at < NOW() - INTERVAL 30 DAY AND status <> \'spam\'');
        $won = (int) db_val("SELECT COUNT(*) FROM leads WHERE status = 'won'");
        $closed = (int) db_val("SELECT COUNT(*) FROM leads WHERE status IN ('won','lost')");
        $d['win_rate'] = $closed ? round($won / $closed * 100) : null;
        $d['pipeline_value'] = (float) db_val("SELECT COALESCE(SUM(value),0) FROM leads WHERE status IN ('" . implode("','", LEAD_OPEN) . "')");
        $d['followups'] = db_all("SELECT id, name, company, follow_up_at, status FROM leads WHERE follow_up_at IS NOT NULL AND follow_up_at <= CURDATE() + INTERVAL 7 DAY AND status IN ('" . implode("','", LEAD_OPEN) . "') ORDER BY follow_up_at LIMIT 8");
        $d['recent'] = db_all('SELECT id, name, company, product, type, status, created_at FROM leads ORDER BY created_at DESC LIMIT 8');
        $d['by_status'] = array_column(db_all('SELECT status, COUNT(*) n FROM leads GROUP BY status'), 'n', 'status');
        $days = db_all("SELECT DATE(created_at) d, COUNT(*) n FROM leads WHERE created_at >= CURDATE() - INTERVAL 13 DAY AND status <> 'spam' GROUP BY DATE(created_at)");
        $map = array_column($days, 'n', 'd');
        $d['daily'] = [];
        for ($i = 13; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-{$i} day")); $d['daily'][$day] = (int) ($map[$day] ?? 0); }
        $d['clicks'] = array_column(db_all('SELECT type, COUNT(*) n FROM events WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY type'), 'n', 'type');
        $d['sources'] = db_all("SELECT COALESCE(NULLIF(utm_source,''), CASE WHEN referrer IS NULL OR referrer = '' THEN 'Direct' ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referrer,'https://',''),'http://',''),'/',1),'?',1) END) src, COUNT(*) n
            FROM leads WHERE created_at >= NOW() - INTERVAL 90 DAY AND status <> 'spam' GROUP BY src ORDER BY n DESC LIMIT 6");
    }
    if (can('content')) {
        $d['posts_published'] = (int) db_val('SELECT COUNT(*) FROM posts p WHERE ' . POST_VISIBLE);
        $d['posts_draft'] = (int) db_val("SELECT COUNT(*) FROM posts WHERE status = 'draft' AND deleted_at IS NULL");
        $d['posts_scheduled'] = (int) db_val("SELECT COUNT(*) FROM posts WHERE status = 'scheduled' AND published_at > NOW()");
        $d['top_posts'] = db_all('SELECT p.id, p.title, p.views FROM posts p WHERE ' . POST_VISIBLE . ' ORDER BY p.views DESC LIMIT 5');
    }
    $d['checklist'] = admin_launch_checklist();
    admin_render('Dashboard', 'dashboard', ['d' => $d, 'user' => $user]);
}

/** Go-live checklist shown on the dashboard until everything is configured. */
function admin_launch_checklist(): array
{
    if (!can('settings')) return [];
    $c = [
        ['Company details & address', !str_contains(company('phone'), '00000') && company('email') !== 'sales@example.com', 'admin/settings/general'],
        ['Production site URL', setting('site_url') && !preg_match('~example\.com|localhost|127\.0\.0\.1~', (string) setting('site_url')), 'admin/settings/seo'],
        ['Lead notification email', (bool) setting('lead_notify_emails'), 'admin/settings/email'],
        ['SMTP email delivery', (bool) setting('smtp_host'), 'admin/settings/email'],
        ['Google Analytics or Tag Manager', setting('ga4_id') || setting('gtm_id'), 'admin/settings/integrations'],
        ['Spam protection (Cloudflare Turnstile)', turnstile_enabled(), 'admin/settings/integrations'],
        ['Legal pages reviewed (template notice off)', !setting('legal_template_notice', true), 'admin/settings/legal'],
        ['Demo statistics replaced', !setting('stats_note') || !str_contains((string) setting('stats_note'), 'placeholder'), 'admin/settings/content'],
    ];
    return array_map(fn($r) => ['label' => $r[0], 'done' => (bool) $r[1], 'url' => $r[2]], $c);
}
