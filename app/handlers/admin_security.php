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
    require_stepup('passkey_add');
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
    require_stepup('passkey_remove');
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
    $body = json_body();
    $_SESSION['login_remember'] = !empty($body['remember']);
    try {
        $user = webauthn_login((array) ($body['credential'] ?? []));
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
    require_stepup('totp');
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
    recovery_export_keep($_SESSION['recovery_codes_once']);
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
    require_stepup('totp');
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
    require_stepup('recovery');
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true || !totp_enabled_for($row)) { flash('error', $ok === true ? 'Turn on the authenticator app first.' : $ok); redirect('admin/profile#two-factor'); }
    $_SESSION['recovery_codes_once'] = recovery_codes_generate((int) $user['id']);
    recovery_export_keep($_SESSION['recovery_codes_once']);
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


/* ------------------------------------------------------------------ */
/* Recovery codes: download as TXT, CSV or Excel                       */
/* ------------------------------------------------------------------ */

const RECOVERY_EXPORT_TTL = 900; // downloads stay possible for 15 minutes after the codes are created

/** Stored codes are hashed and can't be read back, so downloads use a short-lived copy in this session only. */
function recovery_export_keep(array $codes): void
{
    $_SESSION['recovery_export'] = ['codes' => array_values($codes), 'until' => time() + RECOVERY_EXPORT_TTL];
}

function recovery_export_codes(): ?array
{
    $x = $_SESSION['recovery_export'] ?? null;
    if (!is_array($x) || (int) $x['until'] < time()) { unset($_SESSION['recovery_export']); return null; }
    return $x['codes'];
}

/** Excel opens text starting with = + - @ as a formula; prefix it so it stays text. */
function csv_safe(string $v): string
{
    return preg_match('~^[=+\-@\t\r]~', $v) ? "'" . $v : $v;
}

/** A minimal, valid .xlsx (Office Open XML) with one sheet of text cells. */
function xlsx_build(array $rows, string $sheet = 'Sheet1'): string
{
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $col = fn(int $i) => chr(65 + $i);
    $xmlRows = '';
    foreach (array_values($rows) as $r => $cells) {
        $xmlRows .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($cells) as $c => $v) {
            $style = $r === 0 ? ' s="1"' : '';
            $xmlRows .= '<c r="' . $col($c) . ($r + 1) . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x((string) $v) . '</t></is></c>';
        }
        $xmlRows .= '</row>';
    }
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $x($sheet) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="5" width="30" customWidth="1"/></cols><sheetData>' . $xmlRows . '</sheetData></worksheet>',
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) $zip->addFromString($name, $content);
    $zip->close();
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

function admin_recovery_download(): void
{
    $user = require_login();
    csrf_check();
    $codes = recovery_export_codes();
    if (!$codes) {
        flash('error', 'For your security, recovery codes can be downloaded only within 15 minutes after they are created. Create new codes to download them.');
        redirect('admin/profile#two-factor');
    }
    $format = in_array(input('format'), ['txt', 'csv', 'xlsx'], true) ? (string) input('format') : 'txt';
    $site = (string) parse_url(abs_url(''), PHP_URL_HOST) ?: company('name', 'site');
    $account = (string) ($user['email'] ?? '');
    $created = date('Y-m-d H:i', (int) $_SESSION['recovery_export']['until'] - RECOVERY_EXPORT_TTL);
    $base = 'recovery-codes-' . preg_replace('~[^a-z0-9.-]+~', '-', strtolower($site)) . '-' . date('Y-m-d');

    if ($format === 'txt') {
        $body = company('name', 'Control panel') . " — authenticator recovery codes\r\n"
            . "Site:    {$site}\r\nAccount: {$account}\r\nCreated: {$created}\r\n\r\n"
            . "Each code works once, if you lose access to your authenticator app.\r\n"
            . "Keep this file private (password manager or printed and locked away).\r\n\r\n";
        foreach ($codes as $i => $c) $body .= str_pad((string) ($i + 1), 2, ' ', STR_PAD_LEFT) . '. ' . $c . "\r\n";
        $type = 'text/plain; charset=utf-8';
    } elseif ($format === 'csv') {
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['#', 'Recovery code', 'Account', 'Site', 'Created'], ',', '"', '');
        foreach ($codes as $i => $c) fputcsv($fh, [$i + 1, csv_safe($c), csv_safe($account), csv_safe($site), $created], ',', '"', '');
        rewind($fh);
        $body = "\xEF\xBB\xBF" . stream_get_contents($fh); // BOM so Excel reads UTF-8
        fclose($fh);
        $type = 'text/csv; charset=utf-8';
    } else {
        $rows = [['#', 'Recovery code', 'Account', 'Site', 'Created']];
        foreach ($codes as $i => $c) $rows[] = [(string) ($i + 1), $c, $account, $site, $created];
        $body = xlsx_build($rows, 'Recovery codes');
        $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
    auth_log('recovery_codes_downloaded', (int) $user['id'], $account);
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $base . '.' . $format . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}

/** "Done, I've saved them": forget the downloadable copy right away. */
function admin_recovery_forget(): void
{
    require_login();
    csrf_check();
    unset($_SESSION['recovery_export']);
    flash('success', 'Recovery codes hidden. They can no longer be downloaded from this session.');
    redirect('admin/profile#two-factor');
}
