<?php
/**
 * Admin authentication:
 *  - sign in with email OR username + password (rate limited, audited)
 *  - optional email one-time passcode (OTP) step, to primary or verified backup email
 *  - forgot / reset password by emailed code
 *  - purpose-bound OTP codes for email changes and backup email verification
 */
declare(strict_types=1);

const ROLES = [
    'admin' => 'Administrator — full access',
    'editor' => 'Editor — content, blog, products, media',
    'sales' => 'Sales — leads & enquiries',
];

const PERMISSIONS = [
    'dashboard' => ['admin', 'editor', 'sales'],
    'leads' => ['admin', 'sales'],
    'content' => ['admin', 'editor'],   // pages, posts, categories, products, industries, media
    'settings' => ['admin'],
    'users' => ['admin'],
];

const OTP_TTL = 600;          // code valid for 10 minutes
const OTP_MAX_ATTEMPTS = 5;   // wrong guesses per code
const OTP_RESEND_AFTER = 60;  // seconds between sends
const OTP_MAX_SENDS = 5;      // codes per user per 15 minutes (all purposes)

const OTP_PURPOSES = [
    'login' => ['Your sign-in code', 'to finish signing in to the %s control panel'],
    'reset' => ['Reset your password', 'to reset your password for the %s control panel'],
    'email_change' => ['Confirm your new email address', 'to confirm this address as the new sign-in email for the %s control panel'],
    'backup_email' => ['Confirm your backup email', 'to confirm this address as the backup email for your %s control panel account'],
    'stepup' => ['Confirm it’s you', 'to confirm a sensitive action in the %s control panel'],
];

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    session_boot();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        // "Keep me signed in": start a new session from the rotating device-bound token
        $rem = remember_login();
        if (!$rem) return $user = null;
        $id = (int) $rem['id'];
    }
    $row = db_one('SELECT id, name, email, username, backup_email, backup_email_verified_at, role, active, password_changed_at, avatar, notify_prefs, totp_enabled_at FROM users WHERE id = ?', [(int) $id]);
    if (!$row || !$row['active'] || ($_SESSION['ver'] ?? '') !== session_fingerprint((int) $id)) {
        unset($_SESSION['uid']);
        return $user = null;
    }
    return $user = $row;
}

/** Changes when the password changes, so other sessions and trusted devices are invalidated. */
function session_fingerprint(int $userId): string
{
    $row = db_one('SELECT password_hash, session_salt FROM users WHERE id = ?', [$userId]);
    // Changes when the password changes or the user signs out everywhere (salt rotated)
    return substr(hash('sha256', (string) ($row['password_hash'] ?? '') . (string) ($row['session_salt'] ?? '') . config('app_key')), 0, 24);
}

function can(string $permission): bool
{
    $u = current_user();
    return $u && in_array($u['role'], PERMISSIONS[$permission] ?? [], true);
}

function require_login(?string $permission = null): array
{
    $u = current_user();
    if (!$u) {
        if (wants_json()) json_response(['error' => 'Unauthenticated'], 401);
        $_SESSION['intended'] = request_path() . (empty($_GET) ? '' : '?' . http_build_query($_GET));
        redirect('admin/login');
    }
    if ($permission && !can($permission)) abort(403, 'You do not have permission to access this area.');
    $idle = max(15, (int) setting('admin_idle_minutes', 240)) * 60;
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $idle) {
        auth_log('session_timeout', (int) $u['id'], $u['email']);
        // End the idle session; "keep me signed in" (if set) quietly starts a fresh one
        $_SESSION = [];
        session_regenerate_id(true);
        if (!remember_login()) {
            flash('info', 'You were signed out after a period of inactivity.');
            redirect('admin/login');
        }
    }
    $_SESSION['last'] = time();
    return $u;
}

function auth_log(string $event, ?int $userId = null, ?string $email = null): void
{
    try {
        db_insert('auth_events', ['user_id' => $userId, 'email' => $email ? mb_substr($email, 0, 190) : null, 'event' => $event,
            'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
        if (random_int(1, 50) === 1) db_query('DELETE FROM auth_events WHERE created_at < NOW() - INTERVAL 180 DAY');
    } catch (Throwable $e) {
        error_log('auth_log: ' . $e->getMessage());
    }
}

/** j•••@example.com — hides most of the local part of an address. */
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($domain === '') return $email;
    return mb_substr($local, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($local) - 1))) . '@' . $domain;
}

/** Find an active user by email or username (case-insensitive). */
function find_user_by_identifier(string $identifier): ?array
{
    $id = strtolower(trim($identifier));
    if ($id === '') return null;
    return db_one('SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1', [$id, $id]);
}

function username_problems(string $username, ?int $ignoreId = null): ?string
{
    if ($username === '') return null;
    if (!preg_match('~^[a-z0-9][a-z0-9._-]{2,59}$~', $username)) return 'Username must be 3–60 characters: lowercase letters, numbers, dot, dash or underscore.';
    if (str_contains($username, '@')) return 'Username cannot contain “@”.';
    if (db_val('SELECT id FROM users WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND id <> ?', [$username, $username, $ignoreId ?? 0])) return 'This username is already taken.';
    return null;
}

function email_in_use(string $email, ?int $ignoreId = null): bool
{
    $email = strtolower($email);
    return (bool) db_val('SELECT id FROM users WHERE (LOWER(email) = ? OR LOWER(username) = ?) AND id <> ?', [$email, $email, $ignoreId ?? 0]);
}

/* ------------------------------------------------------------------ */
/* Step 1 — password                                                   */
/* ------------------------------------------------------------------ */

/** @return array|string user row on success, error message on failure */
function verify_credentials(string $identifier, string $password): array|string
{
    $identifier = strtolower(trim($identifier));
    $bucket = 'login:' . substr(hash('sha256', $identifier . client_ip()), 0, 40);
    $ipBucket = ip_bucket('login-ip');
    $recent = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)', [$bucket]);
    $recentIp = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)', [$ipBucket]);
    if ($recent >= 5 || $recentIp >= 20) {
        auth_log('login_locked', null, $identifier);
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }
    $user = find_user_by_identifier($identifier);
    // Always run password_verify so unknown accounts take similar time
    $ok = password_verify($password, $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ3nN2u4Gm0zA8C6Q7cY1oJvKpP8eG9W');
    if (!$user || !$user['active'] || !$ok) {
        db_insert('rate_limits', ['bucket' => $bucket]);
        db_insert('rate_limits', ['bucket' => $ipBucket]);
        auth_log('login_failed', $user['id'] ?? null, $identifier);
        if ($user && $recent + 1 === 5) {
            notify_user($user, 'login_locked', 'Sign-in locked after failed attempts',
                'Someone entered a wrong password for your account 5 times, so sign-in is locked for 15 minutes. If this was not you, consider changing your password.',
                ['severity' => 'warning', 'category' => 'signin']);
        }
        return 'Invalid email/username or password.';
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], (int) $user['id']);
    }
    db_query('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
    return $user;
}

function complete_login(array $user, bool $trustDevice = false, ?bool $remember = null): void
{
    $remember ??= !empty($_SESSION['login_remember']);
    session_regenerate_id(true);
    unset($_SESSION['otp_uid'], $_SESSION['otp_started'], $_SESSION['otp_dev_code'], $_SESSION['otp_to'], $_SESSION['login_remember']);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['ver'] = session_fingerprint((int) $user['id']);
    $_SESSION['last'] = time();
    db_update('users', ['last_login_at' => now()], (int) $user['id']);
    auth_log('login_success', (int) $user['id'], $user['email']);
    login_device_check($user);
    if ($trustDevice) trust_device((int) $user['id']);
    if ($remember) remember_issue($user);
}

/** Single-step login used when OTP is disabled. */
function attempt_login(string $identifier, string $password): string|bool
{
    $res = verify_credentials($identifier, $password);
    if (is_string($res)) return $res;
    complete_login($res);
    return true;
}

function logout_user(): void
{
    session_boot();
    if (!empty($_SESSION['uid'])) remember_forget_current((int) $_SESSION['uid']);
    $_SESSION = [];
    session_regenerate_id(true);
}

function password_problems(string $password): ?string
{
    if (strlen($password) < 10) return 'Password must be at least 10 characters.';
    if (!preg_match('~[A-Za-z]~', $password) || !preg_match('~\d~', $password)) return 'Password must contain letters and numbers.';
    return null;
}

/**
 * Change a password. Signs out all other sessions and trusted browsers (the session
 * fingerprint depends on the hash) and emails a security notice to the account.
 */
function set_password(array $user, string $newPassword, string $reason = 'changed'): void
{
    db_update('users', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'password_changed_at' => now()], (int) $user['id']);
    db_query('UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
    // Other browsers lose "trusted" and "keep me signed in"; a reset (possible compromise) revokes this one too
    devices_revoke_all((int) $user['id'], $reason !== 'reset');
    auth_log($reason === 'reset' ? 'password_reset' : 'password_changed', (int) $user['id'], $user['email']);
    security_notice($user, $reason === 'reset' ? 'Your password was reset' : 'Your password was changed',
        'The password for your ' . company('name') . ' control panel account was ' . ($reason === 'reset' ? 'reset using an emailed code' : 'changed') . ' on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '. All other signed-in sessions and trusted browsers have been signed out.');
}

/** Email a security notice to the primary (and verified backup) address. Never blocks the action. */
function security_notice(array $user, string $title, string $text, ?string $extraTo = null, string $event = 'account_security'): void
{
    // Account-security alerts are always emailed (primary + verified backup) and logged in-app
    notify_user($user, $event, $title, $text, ['severity' => 'critical', 'category' => 'account', 'extra_to' => $extraTo, 'link' => 'admin/profile']);
}

/* ------------------------------------------------------------------ */
/* One-time passcodes (login, reset, email change, backup email)       */
/* ------------------------------------------------------------------ */

function otp_enabled(): bool
{
    return (bool) setting('admin_otp_enabled', false);
}

/**
 * Generate, store (hashed) and email a 6-digit code for a purpose.
 * @param string|string[]|null $to recipient(s); defaults to the primary email
 * @return true|string
 */
function otp_send(array $user, string $purpose = 'login', string|array|null $to = null, ?string $target = null): bool|string
{
    $uid = (int) $user['id'];
    $last = db_val('SELECT created_at FROM login_otps WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$uid, $purpose]);
    if ($last && time() - strtotime((string) $last) < OTP_RESEND_AFTER) {
        return 'Please wait ' . (OTP_RESEND_AFTER - (time() - strtotime((string) $last))) . ' seconds before requesting another code.';
    }
    $sent = (int) db_val('SELECT COUNT(*) FROM login_otps WHERE user_id = ? AND created_at >= NOW() - INTERVAL 15 MINUTE', [$uid]);
    if ($sent >= OTP_MAX_SENDS) return 'Too many codes requested. Please wait 15 minutes.';

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_query('UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$uid, $purpose]);
    db_insert('login_otps', ['user_id' => $uid, 'purpose' => $purpose, 'target' => $target, 'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires_at' => date('Y-m-d H:i:s', time() + OTP_TTL)]);

    [$subject, $why] = OTP_PURPOSES[$purpose] ?? OTP_PURPOSES['login'];
    $inner = '<p style="font-size:14px;line-height:1.6">Hello ' . e($user['name']) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6">Use this code ' . e(sprintf($why, company('name'))) . ':</p>'
        . '<p style="margin:24px 0;font-size:34px;font-weight:bold;letter-spacing:10px;color:#101D32">' . $code . '</p>'
        . '<p style="font-size:13px;color:#6b7280">The code expires in ' . (OTP_TTL / 60) . ' minutes. Requested from IP ' . e(client_ip()) . ' at ' . e(date('j M Y H:i')) . '. Never share this code — our team will never ask for it.</p>'
        . '<p style="font-size:13px;color:#6b7280">If you did not request this, you can ignore this email' . ($purpose === 'login' ? ', but consider changing your password because someone entered it correctly' : '') . '.</p>';
    $res = send_mail($to ?? $user['email'], $code . ' — ' . $subject, mail_layout($subject, $inner));
    if ($res !== true) {
        auth_log('otp_send_failed', $uid, $user['email']);
        return 'We could not email the code (' . $res . ').';
    }
    if (config('debug')) $_SESSION['otp_dev_code'] = $code; // local development only — shown on screen
    auth_log($purpose === 'login' ? 'otp_sent' : $purpose . '_code_sent', $uid, $user['email']);
    return true;
}

/**
 * Check a code for a purpose. On success returns the stored target (may be '' for none).
 * @return array{ok: bool, error?: string, target?: string}
 */
function otp_check(int $userId, string $code, string $purpose = 'login'): array
{
    $code = preg_replace('~\D~', '', $code) ?? '';
    $row = db_one('SELECT * FROM login_otps WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$userId, $purpose]);
    if (!$row || strtotime((string) $row['expires_at']) < time()) return ['ok' => false, 'error' => 'This code has expired. Request a new one.'];
    if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) return ['ok' => false, 'error' => 'Too many incorrect attempts. Request a new code.'];
    if (strlen($code) !== 6 || !password_verify($code, $row['code_hash'])) {
        db_query('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        auth_log('otp_failed', $userId);
        $left = OTP_MAX_ATTEMPTS - (int) $row['attempts'] - 1;
        return ['ok' => false, 'error' => $left > 0 ? "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' left.' : 'Too many incorrect attempts. Request a new code.'];
    }
    db_query('UPDATE login_otps SET used_at = NOW() WHERE id = ?', [$row['id']]);
    return ['ok' => true, 'target' => (string) $row['target']];
}

/** Backwards-compatible login check. @return true|string */
function otp_verify(int $userId, string $code): bool|string
{
    $r = otp_check($userId, $code, 'login');
    return $r['ok'] ? true : $r['error'];
}

/** Pending user waiting for login OTP (expires 15 minutes after password step). */
function otp_pending_user(): ?array
{
    session_boot();
    $uid = (int) ($_SESSION['otp_uid'] ?? 0);
    if (!$uid || time() - (int) ($_SESSION['otp_started'] ?? 0) > 900) return null;
    return db_one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]);
}

/* ------------------------------------------------------------------ */
/* Forgot / reset password                                             */
/* ------------------------------------------------------------------ */

/**
 * Start a password reset. Always behaves the same whether or not the account exists
 * (no account enumeration). Code goes to the primary and verified backup email.
 */
function password_reset_request(string $identifier): ?string
{
    session_boot();
    if (!rate_limit(ip_bucket('reset'), 5, 900)) return 'Too many reset requests. Please wait 15 minutes.';
    $user = find_user_by_identifier($identifier);
    $_SESSION['reset_uid'] = ($user && $user['active']) ? (int) $user['id'] : 0;
    $_SESSION['reset_started'] = time();
    unset($_SESSION['otp_dev_code']);
    if ($user && $user['active']) {
        $to = array_values(array_filter([$user['email'], $user['backup_email_verified_at'] ? $user['backup_email'] : null]));
        $res = otp_send($user, 'reset', $to);
        if ($res !== true && str_starts_with((string) $res, 'Please wait')) return $res;
        if ($res !== true) error_log('Password reset email failed for user ' . $user['id'] . ': ' . $res);
    } else {
        auth_log('reset_unknown_account', null, $identifier);
    }
    return null;
}

/** @return true|string */
function password_reset_complete(string $code, string $password, string $confirm): bool|string
{
    session_boot();
    $uid = (int) ($_SESSION['reset_uid'] ?? 0);
    if (time() - (int) ($_SESSION['reset_started'] ?? 0) > 900) return 'This reset session expired. Please start again.';
    if ($p = password_problems($password)) return $p;
    if ($password !== $confirm) return 'Passwords do not match.';
    if (!$uid) { usleep(300000); return 'Incorrect or expired code.'; }
    $r = otp_check($uid, $code, 'reset');
    if (!$r['ok']) return $r['error'];
    $user = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
    set_password($user, $password, 'reset');
    unset($_SESSION['reset_uid'], $_SESSION['reset_started'], $_SESSION['otp_dev_code']);
    db_query('DELETE FROM rate_limits WHERE bucket LIKE ?', ['login:%']);
    return true;
}

/** Make sure newer tables/columns exist on sites installed before they were added. */
function ensure_auth_tables(): void
{
    require_once APP_DIR . '/lib/seed.php';
    if ((int) setting('schema_version', 0) >= SCHEMA_VERSION) return;
    run_migrations();
}

/** Round avatar: the uploaded profile picture, or the first letter of the name. */
function user_avatar_html(array $u, string $class = 'adm-avatar'): string
{
    if (!empty($u['avatar']) && is_file(PUBLIC_DIR . '/' . ltrim((string) $u['avatar'], '/'))) {
        return '<span class="' . e($class) . ' overflow-hidden"><img src="' . e(url((string) $u['avatar'])) . '" alt="" class="h-full w-full object-cover" width="64" height="64"></span>';
    }
    return '<span class="' . e($class) . '" aria-hidden="true">' . e(mb_strtoupper(mb_substr((string) ($u['name'] ?? '?'), 0, 1))) . '</span>';
}
