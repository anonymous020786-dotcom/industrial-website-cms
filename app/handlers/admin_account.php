<?php
/** Admin: step-up confirmation ("confirm it's you"), notifications, profile picture. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

/* ================================================================== */
/* STEP-UP CONFIRMATION                                                */
/* ================================================================== */

function stepup_methods(array $user): array
{
    return [
        'passkey' => (bool) db_val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [(int) $user['id']]),
        'totp' => totp_enabled_for($user),
        'email' => true,
    ];
}

/** What happens after a successful confirmation: resume the interrupted request. */
function stepup_resume(): never
{
    $p = $_SESSION['stepup_pending'] ?? null;
    unset($_SESSION['stepup_pending']);
    if (!$p || time() - (int) $p['at'] > 900) {
        flash('success', 'Identity confirmed for the next 10 minutes.');
        redirect('admin');
    }
    if ($p['method'] === 'GET') redirect((string) $p['path']);
    if ($p['fields'] === null) {
        flash('info', 'Identity confirmed for 10 minutes. For your security, passwords are never stored — please submit the form again.');
        redirect((string) $p['back']);
    }
    // Re-submit the original form with a fresh CSRF token
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/stepup_resume', ['action' => url((string) $p['path']), 'fields' => $p['fields'], 'label' => STEPUP_ACTIONS[$p['action']] ?? 'your action']);
    exit;
}

function admin_confirm(): void
{
    $user = require_login();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    $methods = stepup_methods($row);
    $pending = $_SESSION['stepup_pending'] ?? null;
    $error = null;
    $sent = false;

    if (is_post()) {
        csrf_check();
        $method = (string) input('method', 'email');
        if ($method === 'send') {
            $res = otp_send($row, 'stepup');
            if ($res === true) { $sent = true; $_SESSION['stepup_email_sent'] = time(); } else $error = $res;
        } else {
            $code = (string) input('code', '');
            $ok = $method === 'totp' && $methods['totp'] ? totp_verify_user($row, $code) : (($r = otp_check((int) $row['id'], $code, 'stepup'))['ok'] ? true : $r['error']);
            if ($ok === true) {
                unset($_SESSION['otp_dev_code'], $_SESSION['stepup_email_sent']);
                stepup_grant($row, $method);
                stepup_resume();
            }
            $error = $ok;
            auth_log('stepup_failed', (int) $row['id'], $row['email']);
            $fails = (int) db_val("SELECT COUNT(*) FROM auth_events WHERE user_id = ? AND event = 'stepup_failed' AND created_at >= NOW() - INTERVAL 15 MINUTE", [(int) $row['id']]);
            if ($fails === 3) {
                notify_user($row, 'stepup_failed', 'Several wrong confirmation codes', 'Three wrong codes were entered while confirming a sensitive action on your account. If this was not you, change your password now.', ['severity' => 'warning', 'category' => 'signin']);
            }
        }
    } elseif (!$methods['totp'] && !$methods['passkey'] && time() - (int) ($_SESSION['stepup_email_sent'] ?? 0) > 120) {
        // Email is the only method: send the code straight away
        $res = otp_send($row, 'stepup');
        if ($res === true) { $sent = true; $_SESSION['stepup_email_sent'] = time(); } elseif (!str_starts_with((string) $res, 'Please wait')) $error = $res;
    }
    $emailSent = $sent || time() - (int) ($_SESSION['stepup_email_sent'] ?? 0) < 600;
    admin_render('Confirm it’s you', 'confirm', [
        'methods' => $methods, 'error' => $error, 'emailSent' => $emailSent, 'masked' => mask_email((string) $row['email']),
        'label' => $pending ? (STEPUP_ACTIONS[$pending['action']] ?? 'Sensitive action') : 'Sensitive actions',
        'back' => url((string) ($pending['back'] ?? 'admin')),
        'devCode' => config('debug') ? ($_SESSION['otp_dev_code'] ?? null) : null,
    ]);
}

function admin_confirm_passkey_options(): void
{
    $user = require_login();
    csrf_check();
    json_response(webauthn_stepup_options(db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']])));
}

function admin_confirm_passkey(): void
{
    $user = require_login();
    csrf_check();
    $body = json_decode((string) file_get_contents('php://input', false, null, 0, 65536), true) ?: [];
    try {
        $row = webauthn_login((array) ($body['credential'] ?? []), 'stepup', (int) $user['id']);
    } catch (Throwable $e) {
        auth_log('stepup_failed', (int) $user['id'], $user['email']);
        json_response(['error' => $e->getMessage()], 422);
    }
    stepup_grant($row, 'passkey');
    // Let the page follow the normal resume flow (GET → redirect, POST → re-submit)
    json_response(['ok' => true, 'redirect' => url('admin/confirm/resume')]);
}

function admin_confirm_resume(): void
{
    require_login();
    if (!stepup_valid()) redirect('admin/confirm');
    stepup_resume();
}

/* ================================================================== */
/* NOTIFICATIONS                                                       */
/* ================================================================== */

function admin_notifications(): void
{
    $user = require_login();
    $filter = query('show') === 'unread' ? 'unread' : 'all';
    $rows = db_all('SELECT * FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND read_at IS NULL' : '') . ' ORDER BY id DESC LIMIT 200', [(int) $user['id']]);
    $unread = notifications_unread((int) $user['id']);
    $actions = $unread ? '<form method="post" action="' . e(url('admin/notifications/read')) . '">' . csrf_field() . '<button class="adm-btn adm-btn-light">' . icon('check-check', 'h-4 w-4') . ' Mark all as read</button></form>' : '';
    admin_render('Notifications', 'notifications', compact('rows', 'filter', 'unread'), ['actions' => $actions]);
}

function admin_notifications_read(): void
{
    $user = require_login();
    csrf_check();
    $id = (int) input('id', 0);
    db_query('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL' . ($id ? ' AND id = ?' : ''), $id ? [(int) $user['id'], $id] : [(int) $user['id']]);
    if (wants_json()) json_response(['ok' => true]);
    back('admin/notifications');
}

function admin_notify_prefs(): void
{
    $user = require_login();
    csrf_check();
    $prefs = [];
    foreach (NOTIFY_CATEGORIES as $k => $_) $prefs[$k] = !empty($_POST['notify'][$k]);
    db_update('users', ['notify_prefs' => json_encode($prefs)], (int) $user['id']);
    flash('success', 'Notification preferences saved. Alerts about your own account security are always emailed.');
    redirect('admin/profile#notifications');
}

/* ================================================================== */
/* PROFILE PICTURE                                                     */
/* ================================================================== */

function avatar_delete_file(?string $path): void
{
    if (!$path) return;
    $root = realpath(PUBLIC_DIR . '/uploads/avatars');
    $file = realpath(PUBLIC_DIR . '/' . $path);
    if ($root && $file && str_starts_with($file, $root)) @unlink($file);
}

function admin_avatar_upload(): void
{
    $user = require_login();
    csrf_check();
    $f = $_FILES['avatar'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) { flash('error', 'Choose an image under 5 MB.'); redirect('admin/profile#profile'); }
    if ($f['size'] > 5 * 1024 * 1024) { flash('error', 'The image is larger than 5 MB.'); redirect('admin/profile#profile'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !@getimagesize($f['tmp_name'])) { flash('error', 'Use a JPG, PNG or WebP image.'); redirect('admin/profile#profile'); }
    if (!images_supported()) { flash('error', 'Profile pictures need the PHP GD extension on the server.'); redirect('admin/profile#profile'); }
    $im = image_load($f['tmp_name'], $mime);
    if (!$im) { flash('error', 'That image could not be read.'); redirect('admin/profile#profile'); }
    // Centre-crop to a square and re-encode at 256 px (removes any hidden content and EXIF/GPS data)
    $w = imagesx($im); $h = imagesy($im); $side = min($w, $h);
    $out = imagecreatetruecolor(256, 256);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
    @mkdir(PUBLIC_DIR . '/uploads/avatars', 0775, true);
    $rel = 'uploads/avatars/' . (int) $user['id'] . '-' . bin2hex(random_bytes(6)) . '.webp';
    if (!imagewebp($out, PUBLIC_DIR . '/' . $rel, 82)) { flash('error', 'Could not save the picture. Check folder permissions on public/uploads.'); redirect('admin/profile#profile'); }
    $old = (string) db_val('SELECT avatar FROM users WHERE id = ?', [(int) $user['id']]);
    db_update('users', ['avatar' => $rel], (int) $user['id']);
    avatar_delete_file($old);
    auth_log('avatar_changed', (int) $user['id'], $user['email']);
    flash('success', 'Profile picture updated.');
    redirect('admin/profile#profile');
}

function admin_avatar_remove(): void
{
    $user = require_login();
    csrf_check();
    avatar_delete_file((string) db_val('SELECT avatar FROM users WHERE id = ?', [(int) $user['id']]));
    db_update('users', ['avatar' => null], (int) $user['id']);
    flash('success', 'Profile picture removed.');
    redirect('admin/profile#profile');
}

/* ================================================================== */
/* DEVICES & SESSIONS                                                  */
/* ================================================================== */

/** Stop trusting one of your browsers, or remove it (also ends its "keep me signed in"). */
function admin_device_action(string $id): void
{
    $user = require_login();
    csrf_check();
    $dev = db_one('SELECT * FROM known_devices WHERE id = ? AND user_id = ?', [(int) $id, (int) $user['id']]);
    if (!$dev) abort(404);
    $isCurrent = hash_equals((string) $dev['device_hash'], device_hash());
    if (input('action') === 'untrust') {
        db_query('UPDATE known_devices SET trusted_until = NULL WHERE id = ?', [(int) $dev['id']]);
        auth_log('device_untrusted', (int) $user['id'], $user['email']);
        flash('success', ($isCurrent ? 'This browser' : $dev['label']) . ' will ask for a sign-in code again.');
    } else {
        db_query('DELETE FROM known_devices WHERE id = ?', [(int) $dev['id']]);
        if ($isCurrent) remember_clear_cookie();
        auth_log('device_removed', (int) $user['id'], $user['email']);
        flash('success', ($isCurrent ? 'This browser' : $dev['label']) . ' was removed: it is no longer trusted or kept signed in. To end a session that is open there right now, use “Sign out all other devices”.');
    }
    redirect('admin/profile#devices');
}

function admin_devices_signout_all(): void
{
    $user = require_login();
    csrf_check();
    devices_revoke_all((int) $user['id'], true);
    auth_log('signout_everywhere', (int) $user['id'], $user['email']);
    security_notice(db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]), 'You signed out of all other devices',
        'All other browsers and devices were signed out of your account, and none of them are trusted or kept signed in any more. If you did not do this, change your password now.',
        null, 'signout_everywhere');
    flash('success', 'All other devices were signed out. This browser stays signed in.');
    redirect('admin/profile#devices');
}
