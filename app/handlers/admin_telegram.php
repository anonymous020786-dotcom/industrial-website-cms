<?php
/** Telegram: webhook endpoint, and connecting / configuring a user's Telegram in My profile. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

/** Telegram → us. Authenticated by the secret token we registered with setWebhook. */
function telegram_webhook(): void
{
    $given = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    $secret = (string) setting('telegram_webhook_secret', '');
    if (!telegram_enabled() || $secret === '' || !hash_equals($secret, $given)) { http_response_code(403); exit; }
    $update = json_decode((string) file_get_contents('php://input', false, null, 0, 65536), true);
    if (is_array($update) && isset($update['update_id'])) {
        // Telegram retries on errors; ignore anything we have already handled
        if ((int) $update['update_id'] > (int) setting('telegram_update_offset', 0)) {
            setting_set('telegram_update_offset', (int) $update['update_id']);
            try { telegram_handle_update($update); } catch (Throwable $e) { error_report($e); }
        }
    }
    json_response(['ok' => true]);
}

function telegram_back(): never
{
    redirect('admin/profile#telegram');
}

/** Create a single-use "Connect Telegram" link (shown on the profile page for 10 minutes). */
function admin_telegram_link(): void
{
    $user = require_login();
    csrf_check();
    if (!telegram_enabled()) { flash('error', 'Telegram is not set up yet. An administrator can add a bot in Settings → Telegram.'); telegram_back(); }
    require_stepup('telegram');
    $_SESSION['telegram_link_url'] = telegram_link_start($user);
    $_SESSION['telegram_link_until'] = time() + TELEGRAM_LINK_TTL;
    telegram_back();
}

/** Polled by the profile page while waiting for the user to press Start in Telegram. */
function admin_telegram_status(): void
{
    $user = require_login();
    csrf_check();
    telegram_poll();
    $row = db_one('SELECT telegram_chat_id, telegram_username FROM users WHERE id = ?', [(int) $user['id']]);
    $linked = !empty($row['telegram_chat_id']);
    if ($linked) unset($_SESSION['telegram_link_url'], $_SESSION['telegram_link_until']);
    json_response(['linked' => $linked, 'username' => $row['telegram_username'] ?? null]);
}

function admin_telegram_prefs(): void
{
    $user = require_login();
    csrf_check();
    $old = telegram_prefs($user);
    $new = [];
    foreach (array_keys($old) as $k) $new[$k] = !empty($_POST['tg'][$k]);
    if (!can('leads')) $new['leads'] = false;
    if ($new['require'] && !$new['codes']) $new['codes'] = true; // a required code has to be delivered somewhere
    // Weakening sign-in protection needs a fresh confirmation
    if (($old['require'] && !$new['require']) || ($old['codes'] && !$new['codes'])) require_stepup('telegram');
    db_update('users', ['telegram_prefs' => json_encode($new)], (int) $user['id']);
    if ($old['require'] !== $new['require']) {
        auth_log($new['require'] ? 'telegram_2fa_on' : 'telegram_2fa_off', (int) $user['id'], $user['email']);
        if (!$new['require']) security_notice(db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]), 'Telegram sign-in code turned off',
            'Your account no longer asks for a Telegram code at sign-in. If you did not do this, change your password now.', null, 'telegram_2fa_off');
    }
    flash('success', 'Telegram preferences saved.');
    telegram_back();
}

function admin_telegram_test(): void
{
    $user = require_login();
    csrf_check();
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    if (!telegram_enabled() || !telegram_linked($row)) { flash('error', 'Connect Telegram first.'); telegram_back(); }
    $ok = telegram_send((string) $row['telegram_chat_id'], "✅ <b>Test message</b>\n\nTelegram alerts for " . e((string) $row['name']) . ' at ' . e(company('name')) . ' are working.');
    flash($ok ? 'success' : 'error', $ok ? 'Test message sent — check Telegram.' : 'Telegram did not accept the message: ' . ($GLOBALS['telegram_error'] ?? 'unknown error') . ' If you blocked the bot, unblock it or connect again.');
    telegram_back();
}

function admin_telegram_unlink(): void
{
    $user = require_login();
    csrf_check();
    require_stepup('telegram');
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    if ($row && telegram_linked($row)) {
        telegram_send((string) $row['telegram_chat_id'], '👋 Disconnected from ' . e(company('name')) . '. You will no longer receive codes or alerts here.');
        telegram_unlink((int) $user['id']);
        auth_log('telegram_unlinked', (int) $user['id'], $user['email']);
        security_notice($row, 'Telegram disconnected from your account', 'Telegram no longer receives sign-in codes or alerts for your account. Codes are emailed again.', null, 'telegram_unlinked');
    }
    unset($_SESSION['telegram_link_url'], $_SESSION['telegram_link_until']);
    flash('success', 'Telegram disconnected. Codes will be sent by email.');
    telegram_back();
}
