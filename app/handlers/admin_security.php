<?php
/** Admin: passkeys (WebAuthn) and authenticator-app (TOTP) two-factor sign-in. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/admin_settings.php'; // profile_password_ok()

function json_body(): array
{
    $raw = (string) file_get_contents('php://input', false, null, 0, 65536);
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function json_error(string $msg, int $code = 422): never { json_response(['error' => $msg], $code); }

/* ================================================================== */
/* PASSKEYS — registration (signed-in users)                           */
/* ================================================================== */

/** Step 1: confirm the password, return creation options. */
function admin_passkey_options(): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) (json_body()['password'] ?? ''));
    if ($ok !== true) json_error($ok);
    if ((int) db_val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [(int) $user['id']]) >= 10) json_error('You can register up to 10 passkeys. Remove one first.');
    json_response(webauthn_registration_options($row));
}

/** Step 2: verify the authenticator response and save the passkey. */
function admin_passkey_register(): void
{
    $user = require_login();
    csrf_check();
    $body = json_body();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    try {
        webauthn_register($row, (array) ($body['credential'] ?? []), (string) ($body['name'] ?? ''));
    } catch (Throwable $e) {
        auth_log('passkey_add_failed', (int) $user['id'], $user['email']);
        json_error($e->getMessage());
    }
    flash('success', 'Passkey added. Next time, choose “Sign in with a passkey” on the login page.');
    json_response(['ok' => true]);
}

function admin_passkey_rename(string $id): void
{
    $user = require_login();
    csrf_check();
    $name = mb_substr(trim((string) input('name', '')), 0, 120);
    if ($name === '') { flash('error', 'Enter a name.'); redirect('admin/profile#passkeys'); }
    db_query('UPDATE passkeys SET name = ? WHERE id = ? AND user_id = ?', [$name, (int) $id, (int) $user['id']]);
    flash('success', 'Passkey renamed.');
    redirect('admin/profile#passkeys');
}

function admin_passkey_delete(string $id): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true) { flash('error', $ok); redirect('admin/profile#passkeys'); }
    $pk = db_one('SELECT * FROM passkeys WHERE id = ? AND user_id = ?', [(int) $id, (int) $user['id']]);
    if ($pk) {
        db_delete('passkeys', (int) $pk['id']);
        auth_log('passkey_removed', (int) $user['id'], $user['email']);
        security_notice($row, 'A passkey was removed', 'The passkey “' . $pk['name'] . '” was removed from your account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '.');
        flash('success', 'Passkey removed. Also delete it from your device’s password manager.');
    }
    redirect('admin/profile#passkeys');
}

/* ================================================================== */
/* PASSKEYS — sign-in (public)                                         */
/* ================================================================== */

function admin_login_passkey_options(): void
{
    session_boot();
    ensure_auth_tables();
    csrf_check();
    if (!rate_limit(ip_bucket('passkey-options'), 30, 900)) json_error('Too many attempts. Please wait a few minutes.', 429);
    json_response(webauthn_login_options());
}

function admin_login_passkey(): void
{
    session_boot();
    csrf_check();
    if (!rate_limit(ip_bucket('passkey-login'), 15, 900)) json_error('Too many attempts. Please wait a few minutes.', 429);
    try {
        $user = webauthn_login((array) (json_body()['credential'] ?? []));
    } catch (Throwable $e) {
        auth_log('passkey_login_failed', null, null);
        json_error($e->getMessage());
    }
    complete_login($user);
    auth_log('passkey_login', (int) $user['id'], $user['email']);
    $to = $_SESSION['intended'] ?? 'admin';
    unset($_SESSION['intended']);
    json_response(['ok' => true, 'redirect' => url(ltrim((string) $to, '/'))]);
}

/* ================================================================== */
/* AUTHENTICATOR APP (TOTP)                                            */
/* ================================================================== */

/** Start setup: confirm password, create a pending secret (shown as QR code on the profile page). */
function admin_totp_setup(): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true) { flash('error', $ok); redirect('admin/profile#two-factor'); }
    $_SESSION['totp_pending'] = ['secret' => totp_new_secret(), 'at' => time()];
    redirect('admin/profile#two-factor');
}

function admin_totp_confirm(): void
{
    $user = require_login();
    csrf_check();
    $pending = $_SESSION['totp_pending'] ?? null;
    if (!$pending || time() - (int) $pending['at'] > 900) { unset($_SESSION['totp_pending']); flash('error', 'Setup expired. Please start again.'); redirect('admin/profile#two-factor'); }
    $step = totp_match($pending['secret'], (string) input('code', ''));
    if ($step === null) { flash('error', 'That code is not correct. Scan the QR code again and enter the newest 6-digit code.'); redirect('admin/profile#two-factor'); }
    db_update('users', ['totp_secret' => secret_encrypt($pending['secret']), 'totp_enabled_at' => now()], (int) $user['id']);
    db_insert('rate_limits', ['bucket' => "totp-used:{$user['id']}:{$step}"]);
    unset($_SESSION['totp_pending']);
    $_SESSION['recovery_codes_once'] = recovery_codes_generate((int) $user['id']);
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    auth_log('totp_enabled', (int) $user['id'], $user['email']);
    security_notice($row, 'Authenticator app enabled', 'Two-factor sign-in with an authenticator app was turned on for your account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '.');
    flash('success', 'Authenticator app enabled. Save your recovery codes now — they are shown only once.');
    redirect('admin/profile#two-factor');
}

function admin_totp_disable(): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true) { flash('error', $ok); redirect('admin/profile#two-factor'); }
    db_update('users', ['totp_secret' => null, 'totp_enabled_at' => null], (int) $user['id']);
    db_query('DELETE FROM recovery_codes WHERE user_id = ?', [(int) $user['id']]);
    auth_log('totp_disabled', (int) $user['id'], $user['email']);
    security_notice($row, 'Authenticator app turned off', 'Two-factor sign-in with an authenticator app was turned off for your account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '. If this was not you, change your password immediately.');
    flash('success', 'Authenticator app turned off.');
    redirect('admin/profile#two-factor');
}

function admin_recovery_regenerate(): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true || !totp_enabled_for($row)) { flash('error', $ok === true ? 'Turn on the authenticator app first.' : $ok); redirect('admin/profile#two-factor'); }
    $_SESSION['recovery_codes_once'] = recovery_codes_generate((int) $user['id']);
    auth_log('recovery_codes_regenerated', (int) $user['id'], $user['email']);
    flash('success', 'New recovery codes created. The old ones no longer work.');
    redirect('admin/profile#two-factor');
}

/** Sign-in step 2 for users with an authenticator app: TOTP or recovery code. */
function admin_login_2fa(): void
{
    session_boot();
    if (current_user()) redirect('admin');
    $uid = (int) ($_SESSION['mfa_uid'] ?? 0);
    $user = $uid && time() - (int) ($_SESSION['mfa_started'] ?? 0) <= 900 ? db_one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]) : null;
    if (!$user) { unset($_SESSION['mfa_uid']); flash('info', 'Your sign-in session expired. Please enter your password again.'); redirect('admin/login'); }
    $error = null;
    $mode = input('mode', query('mode', 'app')) === 'recovery' ? 'recovery' : 'app';
    if (is_post()) {
        csrf_check();
        $res = $mode === 'recovery' ? recovery_code_use($user, (string) input('code', '')) : totp_verify_user($user, (string) input('code', ''));
        if ($res === true) {
            unset($_SESSION['mfa_uid'], $_SESSION['mfa_started']);
            complete_login($user, !empty($_POST['trust']));
            login_redirect_after();
        }
        $error = $res;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/login', ['error' => $error, 'step' => 'totp', 'mode' => $mode, 'trustDays' => (int) setting('otp_trust_days', 30), 'recoveryLeft' => recovery_codes_left((int) $user['id'])]);
}
