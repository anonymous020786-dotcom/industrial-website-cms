<?php
/**
 * Devices, trusted browsers and "Keep me signed in".
 *
 * Each browser gets a random device cookie (ivdev, stored only as an HMAC). Per user + device we keep:
 *   trusted_until   — skip the sign-in code (email OTP / authenticator app) on this browser until then
 *   remember_*      — "Keep me signed in": a rotating token (cookie ivremember, stored hashed) that starts a
 *                     new session automatically; bound to the device cookie, so a copied token alone fails
 * Everything is revocable per device from My profile → Devices & sessions, and is cleared when the password
 * changes or the user signs out everywhere. Critical actions still need step-up confirmation.
 */
declare(strict_types=1);

const DEVICE_COOKIE = 'ivdev';
const REMEMBER_COOKIE = 'ivremember';

function admin_cookie_opts(int $expires): array
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ['expires' => $expires, 'path' => (base_path() ?: '') . '/admin', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
}

/** The browser's device id (created on first use). */
function device_cookie_raw(): string
{
    static $raw = null;
    if ($raw !== null) return $raw;
    $raw = (string) ($_COOKIE[DEVICE_COOKIE] ?? '');
    if (!preg_match('~^[a-f0-9]{48}$~', $raw)) {
        $raw = bin2hex(random_bytes(24));
        $_COOKIE[DEVICE_COOKIE] = $raw;
        if (!headers_sent()) setcookie(DEVICE_COOKIE, $raw, admin_cookie_opts(time() + 400 * 86400));
    }
    return $raw;
}

function device_hash(?string $raw = null): string
{
    return hash_hmac('sha256', $raw ?? device_cookie_raw(), (string) config('app_key'));
}

function current_device(int $userId): ?array
{
    return db_one('SELECT * FROM known_devices WHERE user_id = ? AND device_hash = ?', [$userId, device_hash()]);
}

/** Make sure this browser is registered for the user; returns [row, isNew]. */
function device_register(int $userId): array
{
    $row = current_device($userId);
    if ($row) {
        db_query('UPDATE known_devices SET last_seen = NOW(), ip = ?, label = ? WHERE id = ?', [client_ip(), mb_substr(device_label(), 0, 120), (int) $row['id']]);
        return [$row, false];
    }
    // Forget browsers unused for 6 months that are neither trusted nor kept signed in
    db_query('DELETE FROM known_devices WHERE user_id = ? AND last_seen < NOW() - INTERVAL 180 DAY AND (trusted_until IS NULL OR trusted_until < NOW()) AND (remember_until IS NULL OR remember_until < NOW())', [$userId]);
    db_insert('known_devices', ['user_id' => $userId, 'device_hash' => device_hash(), 'label' => mb_substr(device_label(), 0, 120), 'ip' => client_ip()]);
    return [current_device($userId), true];
}

/* ---------------- trusted browser: skip sign-in codes ---------------- */

function trust_device(int $userId): void
{
    $days = (int) setting('otp_trust_days', 30);
    if ($days < 1) return;
    [$row] = device_register($userId);
    db_query('UPDATE known_devices SET trusted_until = NOW() + INTERVAL ? DAY WHERE id = ?', [$days, (int) $row['id']]);
    auth_log('device_trusted', $userId);
}

function device_trusted(int $userId): bool
{
    if ((int) setting('otp_trust_days', 30) < 1 || empty($_COOKIE[DEVICE_COOKIE])) return false;
    $row = current_device($userId);
    return $row && $row['trusted_until'] && strtotime((string) $row['trusted_until']) > time();
}

/* ---------------- keep me signed in ---------------- */

function remember_enabled(): bool
{
    return (bool) setting('remember_enabled', true) && (int) setting('remember_days', 30) > 0;
}

function remember_issue(array $user): void
{
    if (!remember_enabled()) return;
    $days = max(1, min(90, (int) setting('remember_days', 30)));
    [$row] = device_register((int) $user['id']);
    $token = bin2hex(random_bytes(32));
    db_query('UPDATE known_devices SET remember_hash = ?, remember_until = NOW() + INTERVAL ? DAY WHERE id = ?', [hash('sha256', $token), $days, (int) $row['id']]);
    $cookie = (int) $user['id'] . '.' . $token;
    $_COOKIE[REMEMBER_COOKIE] = $cookie;
    if (!headers_sent()) setcookie(REMEMBER_COOKIE, $cookie, admin_cookie_opts(time() + $days * 86400));
}

function remember_clear_cookie(): void
{
    unset($_COOKIE[REMEMBER_COOKIE]);
    if (!headers_sent()) setcookie(REMEMBER_COOKIE, '', admin_cookie_opts(time() - 3600));
}

/**
 * Start a session from a valid "keep me signed in" token. The token is single-use: it is rotated
 * every time it is used, so a stolen old cookie stops working. @return array|null user row
 */
function remember_login(): ?array
{
    $raw = (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (!remember_enabled() || !preg_match('~^(\d+)\.([a-f0-9]{64})$~', $raw, $m) || empty($_COOKIE[DEVICE_COOKIE])) return null;
    $uid = (int) $m[1];
    $row = db_one('SELECT d.*, u.active FROM known_devices d JOIN users u ON u.id = d.user_id WHERE d.user_id = ? AND d.device_hash = ?', [$uid, device_hash()]);
    $valid = $row && $row['active'] && $row['remember_hash'] && hash_equals((string) $row['remember_hash'], hash('sha256', $m[2]))
        && $row['remember_until'] && strtotime((string) $row['remember_until']) > time();
    if (!$valid) {
        if ($row && $row['remember_hash'] && !hash_equals((string) $row['remember_hash'], hash('sha256', $m[2]))) {
            // A wrong token for a known device: possible cookie theft — revoke it
            db_query('UPDATE known_devices SET remember_hash = NULL, remember_until = NULL WHERE id = ?', [(int) $row['id']]);
            auth_log('remember_token_mismatch', $uid);
            if ($owner = db_one('SELECT * FROM users WHERE id = ?', [$uid])) {
                notify_user($owner, 'remember_token_mismatch', 'A sign-in token was reused',
                    'An old “keep me signed in” token for ' . $row['label'] . ' was used again, which can mean the browser cookie was copied. That browser was signed out and will need your password. If this was not you, change your password and use “Sign out all other devices”.',
                    ['severity' => 'warning', 'category' => 'signin', 'link' => 'admin/profile#devices']);
            }
        }
        remember_clear_cookie();
        return null;
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['ver'] = session_fingerprint($uid);
    $_SESSION['last'] = time();
    $_SESSION['remembered'] = true;
    db_query('UPDATE known_devices SET last_seen = NOW(), ip = ? WHERE id = ?', [client_ip(), (int) $row['id']]);
    remember_issue($user); // rotate
    auth_log('login_remembered', $uid, $user['email']);
    return $user;
}

function remember_forget_current(int $userId): void
{
    if (!empty($_COOKIE[DEVICE_COOKIE])) db_query('UPDATE known_devices SET remember_hash = NULL, remember_until = NULL WHERE user_id = ? AND device_hash = ?', [$userId, device_hash()]);
    remember_clear_cookie();
}

/**
 * Sign out other browsers: clears trust and "keep me signed in" on all of the user's devices
 * (optionally except this one) and rotates the session salt so every other open session ends.
 */
function devices_revoke_all(int $userId, bool $keepCurrent = true): void
{
    $keep = $keepCurrent && !empty($_COOKIE[DEVICE_COOKIE]) ? device_hash() : '';
    db_query('UPDATE known_devices SET trusted_until = NULL, remember_hash = NULL, remember_until = NULL WHERE user_id = ? AND device_hash <> ?', [$userId, $keep]);
    db_update('users', ['session_salt' => bin2hex(random_bytes(16))], $userId);
    if (isset($_SESSION['uid']) && (int) $_SESSION['uid'] === $userId) $_SESSION['ver'] = session_fingerprint($userId);
}
