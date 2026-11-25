<?php
/**
 * Security & activity notifications.
 * Every alert is stored for the in-app bell (Admin → Notifications) and emailed according to its
 * category and the user's preferences:
 *   account  — changes to the user's own sign-in/security settings: ALWAYS emailed (primary + verified backup)
 *   signin   — new-device sign-ins, lockouts: emailed unless the user turns it off
 *   admin    — other administrators' sensitive actions (users, settings, backups, exports): admins only
 *   content  — permanent deletions: in-app by default, email optional
 */
declare(strict_types=1);

const NOTIFY_CATEGORIES = [
    'signin' => ['Sign-in alerts', 'New browser or device sign-ins and account lockouts.', true],
    'admin' => ['Administrator activity', 'User accounts, security/email/integration settings, backups and lead exports changed by anyone.', true],
    'system' => ['Site errors & maintenance', 'Unexpected errors visitors run into, and maintenance mode being switched on or off.', true],
    'content' => ['Permanent deletions', 'Pages, posts or files deleted for good (Trash emptied).', false],
];

function notify_prefs(array $user): array
{
    $saved = json_decode((string) ($user['notify_prefs'] ?? ''), true) ?: [];
    $out = [];
    foreach (NOTIFY_CATEGORIES as $k => [, , $default]) $out[$k] = (bool) ($saved[$k] ?? $default);
    return $out;
}

/** Short "Chrome on Windows" style label from a user-agent string. */
function device_label(?string $ua = null): string
{
    $ua = (string) ($ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $browser = match (true) {
        str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
        str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari', $ua === '' => 'Unknown browser', default => 'Browser',
    };
    $os = match (true) {
        str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Mac OS') => 'macOS',
        str_contains($ua, 'Linux') => 'Linux', default => 'an unknown system',
    };
    return "{$browser} on {$os}";
}

/**
 * Record a notification for one user and email it when the category/preferences say so.
 * $opt: severity (info|warning|critical), category (account|signin|admin|content), link, extra_to (address), email (bool force)
 */
function notify_user(array $user, string $event, string $title, string $text, array $opt = []): void
{
    $severity = $opt['severity'] ?? 'info';
    $category = $opt['category'] ?? 'account';
    $prefs = notify_prefs($user);
    $email = $opt['email'] ?? ($category === 'account' || $severity === 'critical' || ($prefs[$category] ?? false));
    if ($email === false && $severity === 'critical') $email = true; // critical alerts are always emailed
    try {
        db_insert('notifications', ['user_id' => (int) $user['id'], 'event' => mb_substr($event, 0, 50), 'severity' => $severity,
            'title' => mb_substr($title, 0, 190), 'body' => mb_substr($text, 0, 2000), 'link' => isset($opt['link']) ? mb_substr((string) $opt['link'], 0, 300) : null,
            'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 'emailed' => (int) (bool) $email]);
        if (random_int(1, 100) === 1) db_query('DELETE FROM notifications WHERE created_at < NOW() - INTERVAL 365 DAY');
    } catch (Throwable $e) {
        error_log('notify: ' . $e->getMessage());
    }
    // Telegram copy: same rules as email (account-security and critical alerts always)
    if ($email || $category === 'account' || $severity === 'critical') {
        try { telegram_alert($user, $severity, $title, $text, $opt['link'] ?? null); } catch (Throwable $e) { error_log('notify telegram: ' . $e->getMessage()); }
    }
    if (!$email || !filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return;

    $to = [$user['email']];
    if (($category === 'account' || $severity === 'critical') && !empty($user['backup_email_verified_at']) && !empty($user['backup_email'])) $to[] = $user['backup_email'];
    if (!empty($opt['extra_to'])) $to[] = $opt['extra_to'];
    $color = ['critical' => '#b91c1c', 'warning' => '#b45309', 'info' => '#174A78'][$severity] ?? '#174A78';
    $inner = '<p style="font-size:14px;line-height:1.6">Hello ' . e((string) $user['name']) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6;border-left:4px solid ' . $color . ';padding-left:12px">' . e($text) . '</p>'
        . '<table cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:13px;color:#374151">'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b7280">When</td><td>' . e(date('j M Y, H:i T')) . '</td></tr>'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b7280">Device</td><td>' . e(device_label()) . '</td></tr>'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b7280">IP address</td><td>' . e(client_ip()) . '</td></tr></table>'
        . (!empty($opt['link']) ? '<p><a href="' . e(abs_url(ltrim((string) $opt['link'], '/'))) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Review</a></p>' : '')
        . ($category === 'admin' || $category === 'content' ? '' : '<p style="font-size:13px;color:#6b7280">If this was not you, reset your password now at ' . e(abs_url('admin/forgot')) . ' and contact your administrator.</p>')
        . '<p style="font-size:12px;color:#9ca3af">Manage these alerts in My profile → Notifications. Security alerts about your own account cannot be turned off.</p>';
    try { send_mail(array_values(array_unique($to)), $title, mail_layout($title, $inner)); } catch (Throwable $e) { error_log('notify mail: ' . $e->getMessage()); }
}

/** Notify every active administrator (optionally excluding the person who acted). */
function notify_admins(string $event, string $title, string $text, array $opt = [], ?int $exceptUserId = null): void
{
    try {
        foreach (db_all("SELECT * FROM users WHERE role = 'admin' AND active = 1") as $admin) {
            if ($exceptUserId !== null && (int) $admin['id'] === $exceptUserId && empty($opt['include_actor'])) continue;
            notify_user($admin, $event, $title, $text, $opt + ['category' => 'admin']);
        }
    } catch (Throwable $e) {
        error_log('notify_admins: ' . $e->getMessage());
    }
}

/** Short "who" string for alert texts. */
function actor_name(): string
{
    $u = current_user();
    return $u ? $u['name'] . ' (' . $u['email'] . ')' : 'The system';
}

/**
 * New-device detection on sign-in: each browser gets a random long-lived cookie (stored hashed).
 * The first browser a user ever signs in from is registered silently; later unknown ones trigger an alert.
 */
function login_device_check(array $user): void
{
    try {
        $uid = (int) $user['id'];
        $hadDevices = (bool) db_val('SELECT COUNT(*) FROM known_devices WHERE user_id = ?', [$uid]);
        [, $isNew] = device_register($uid);
        if ($isNew && $hadDevices) {
            notify_user($user, 'login_new_device', 'New sign-in to your account',
                'Your ' . company('name') . ' control panel account was just signed in to from a browser or device it has not been used on before (' . device_label() . ').',
                ['severity' => 'warning', 'category' => 'signin', 'link' => 'admin/profile#devices']);
        }
    } catch (Throwable $e) {
        error_log('device check: ' . $e->getMessage());
    }
}

function notifications_unread(int $userId): int
{
    try { return (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]); } catch (Throwable) { return 0; }
}
