<?php
/**
 * Telegram: sign-in and confirmation codes, one-tap sign-in approval, security alerts and new-lead
 * alerts, delivered by your own Telegram bot (create one with @BotFather, paste its token in
 * Settings → Telegram).
 *
 * Updates (people pressing Start, Approve/Deny buttons) arrive by webhook on a public HTTPS site, or
 * are fetched on demand ("polling") while someone is waiting on a page, so it also works on a
 * laptop or behind a firewall. No external library is used.
 */
declare(strict_types=1);

const TELEGRAM_LINK_TTL = 600; // a "Connect Telegram" link works for 10 minutes

function telegram_enabled(): bool
{
    return (bool) setting('telegram_enabled', false) && (string) setting('telegram_bot_token', '') !== '';
}

/** Only debug installs may point at a different API server (the test suite runs a fake one). */
function telegram_api_base(): string
{
    $o = (string) setting('telegram_api_base', '');
    return config('debug') && $o !== '' ? rtrim($o, '/') : 'https://api.telegram.org';
}

/**
 * Call a Bot API method. Returns the `result` on success, or null (the reason is in
 * $GLOBALS['telegram_error']). Never throws.
 */
function telegram_api(string $method, array $params = [], ?string $token = null): mixed
{
    $token ??= (string) setting('telegram_bot_token', '');
    $GLOBALS['telegram_error'] = null;
    if ($token === '' || !preg_match('~^\d+:[\w-]{20,}$~', $token)) { $GLOBALS['telegram_error'] = 'The bot token is missing or not in the right format.'; return null; }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 8, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]]);
    $raw = @file_get_contents(telegram_api_base() . '/bot' . $token . '/' . $method, false, $ctx);
    if ($raw === false) { $GLOBALS['telegram_error'] = 'Could not reach Telegram. Check the server’s internet connection.'; return null; }
    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['ok'])) {
        $GLOBALS['telegram_error'] = 'Telegram said: ' . (string) ($json['description'] ?? 'unknown error');
        if (str_contains((string) ($json['description'] ?? ''), 'Unauthorized')) $GLOBALS['telegram_error'] = 'Telegram rejected the bot token. Copy it again from @BotFather.';
        return null;
    }
    return $json['result'] ?? true;
}

/** Links in buttons must be public https addresses; local sites get the link in the text instead. */
function telegram_can_link(): bool
{
    return str_starts_with(abs_url(''), 'https://');
}

/**
 * Send a message (HTML formatting). $buttons: rows of ['text' => …, 'data' => …] or
 * ['text' => …, 'url' => …]. Returns the message id, or null.
 */
function telegram_send(string $chatId, string $html, array $buttons = []): ?int
{
    if (!telegram_enabled() || $chatId === '') return null;
    $params = ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    $rows = [];
    foreach ($buttons as $row) {
        $r = [];
        foreach ($row as $b) {
            if (isset($b['url']) && !telegram_can_link()) continue;
            $r[] = isset($b['url']) ? ['text' => $b['text'], 'url' => $b['url']] : ['text' => $b['text'], 'callback_data' => $b['data']];
        }
        if ($r) $rows[] = $r;
    }
    if ($rows) $params['reply_markup'] = ['inline_keyboard' => $rows];
    $res = telegram_api('sendMessage', $params);
    if ($res === null) { error_log('telegram sendMessage: ' . $GLOBALS['telegram_error']); return null; }
    return (int) ($res['message_id'] ?? 0) ?: null;
}

/* ---------------- per-user connection & preferences ---------------- */

function telegram_linked(array $u): bool
{
    return !empty($u['telegram_chat_id']);
}

/** codes: send sign-in/confirmation codes here · approve: one-tap approval · alerts · leads · require: code at every sign-in */
function telegram_prefs(array $u): array
{
    $saved = json_decode((string) ($u['telegram_prefs'] ?? ''), true) ?: [];
    return array_merge(['codes' => true, 'approve' => true, 'alerts' => true, 'leads' => false, 'require' => false], array_intersect_key($saved, array_flip(['codes', 'approve', 'alerts', 'leads', 'require'])));
}

/** Codes for this user go to Telegram (instead of email). */
function telegram_for_codes(array $u): bool
{
    return telegram_enabled() && telegram_linked($u) && telegram_prefs($u)['codes'];
}

/** The user asked for a Telegram code at every sign-in (trusted devices still skip it). */
function telegram_requires_code(array $u): bool
{
    return telegram_for_codes($u) && telegram_prefs($u)['require'];
}

/** Start connecting: returns the t.me deep link (the code inside is single-use and expires). */
function telegram_link_start(array $u): string
{
    $code = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    db_update('users', ['telegram_link_hash' => hash('sha256', $code), 'telegram_link_expires' => date('Y-m-d H:i:s', time() + TELEGRAM_LINK_TTL)], (int) $u['id']);
    return 'https://t.me/' . setting('telegram_bot_username', '') . '?start=' . $code;
}

function telegram_unlink(int $userId): void
{
    db_update('users', ['telegram_chat_id' => null, 'telegram_username' => null, 'telegram_linked_at' => null, 'telegram_link_hash' => null, 'telegram_link_expires' => null], $userId);
}

/* ---------------- codes & sign-in approval ---------------- */

/**
 * Deliver a one-time code by Telegram. For sign-ins, adds Approve / Deny buttons.
 * Returns true when Telegram accepted the message.
 */
function telegram_send_code(array $u, string $code, string $purpose, int $otpId): bool
{
    $company = company('name', 'the website');
    $where = e(device_label()) . ' · IP ' . e(client_ip());
    if ($purpose === 'login') {
        $html = "🔐 <b>Sign-in code: <code>{$code}</code></b>\n\nSomeone is signing in to the <b>" . e($company) . "</b> control panel as " . e((string) $u['name']) . ".\n"
            . "🖥 {$where}\n🕒 " . e(date('j M Y, H:i')) . "\n\nThe code expires in " . (OTP_TTL / 60) . " minutes. Never share it.";
    } else {
        $label = STEPUP_ACTIONS[$_SESSION['stepup_pending']['action'] ?? ''] ?? 'a sensitive action';
        $html = "🛡 <b>Confirmation code: <code>{$code}</code></b>\n\nTo confirm: <b>" . e($label) . "</b>\n🖥 {$where}\n\nThe code expires in " . (OTP_TTL / 60) . ' minutes. If this was not you, change your password now.';
    }
    $buttons = [];
    if ($purpose === 'login' && setting('telegram_login_approval', true) && telegram_prefs($u)['approve']) {
        $nonce = bin2hex(random_bytes(12));
        db_query('UPDATE login_otps SET approval_nonce = ? WHERE id = ?', [$nonce, $otpId]);
        $html .= "\n\nOr just tap <b>Approve</b> — the browser continues by itself.";
        $buttons[] = [['text' => '✅ Approve sign-in', 'data' => "ap:{$otpId}:{$nonce}"], ['text' => '⛔ Deny', 'data' => "dn:{$otpId}:{$nonce}"]];
    }
    $mid = telegram_send((string) $u['telegram_chat_id'], $html, $buttons);
    if ($mid) db_query('UPDATE login_otps SET channel = ?, tg_message_id = ? WHERE id = ?', ['telegram', $mid, $otpId]);
    return (bool) $mid;
}

/** State of the newest pending sign-in for this user: approved | denied | pending | expired. */
function telegram_login_state(int $userId): string
{
    $row = db_one("SELECT * FROM login_otps WHERE user_id = ? AND purpose = 'login' ORDER BY id DESC LIMIT 1", [$userId]);
    if (!$row || strtotime((string) $row['expires_at']) < time()) return 'expired';
    if ($row['approval'] === 'denied') return 'denied';
    if ($row['approval'] === 'approved' && $row['used_at'] === null) return 'approved';
    return $row['used_at'] === null ? 'pending' : 'expired';
}

/** Mark the approved sign-in as used (single use). */
function telegram_login_consume(int $userId): bool
{
    return db_query("UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND purpose = 'login' AND approval = 'approved' AND used_at IS NULL AND expires_at > NOW()", [$userId])->rowCount() > 0;
}

/* ---------------- alerts ---------------- */

function telegram_alert(array $u, string $severity, string $title, string $text, ?string $link = null): void
{
    if (!telegram_enabled() || !telegram_linked($u) || !telegram_prefs($u)['alerts']) return;
    $icon = ['critical' => '🚨', 'warning' => '⚠️', 'info' => 'ℹ️'][$severity] ?? 'ℹ️';
    $url = $link ? abs_url(ltrim($link, '/')) : null;
    $html = "{$icon} <b>" . e($title) . "</b>\n\n" . e($text) . ($url && !telegram_can_link() ? "\n\n" . e($url) : '');
    telegram_send((string) $u['telegram_chat_id'], $html, $url ? [[['text' => 'Open in admin', 'url' => $url]]] : []);
}

/** New enquiry → everyone with access to leads who turned on lead alerts. */
function telegram_lead_alert(array $lead): void
{
    if (!telegram_enabled()) return;
    $roles = PERMISSIONS['leads'] ?? ['admin'];
    $in = implode(',', array_fill(0, count($roles), '?'));
    $url = abs_url('admin/leads/' . (int) $lead['id']);
    $lines = ["📥 <b>New " . e($lead['type'] === 'quote' ? 'quote request' : 'enquiry') . "</b> #" . (int) $lead['id'], ''];
    foreach (['Name' => $lead['name'], 'Company' => $lead['company'], 'Phone' => $lead['phone'], 'Email' => $lead['email'], 'Product' => $lead['product'], 'Quantity' => $lead['quantity']] as $k => $v) {
        if ($v !== null && $v !== '') $lines[] = '<b>' . $k . ':</b> ' . e((string) $v);
    }
    if (trim((string) $lead['message']) !== '') $lines[] = "\n" . e(mb_strimwidth((string) $lead['message'], 0, 600, '…'));
    if (!telegram_can_link()) $lines[] = "\n" . e($url);
    foreach (db_all("SELECT * FROM users WHERE active = 1 AND telegram_chat_id IS NOT NULL AND role IN ({$in})", $roles) as $u) {
        if (!telegram_prefs($u)['leads']) continue;
        telegram_send((string) $u['telegram_chat_id'], implode("\n", $lines), [[['text' => 'Open lead #' . (int) $lead['id'], 'url' => $url]]]);
    }
}

/* ---------------- receiving updates ---------------- */

/**
 * Fetch and handle pending updates when the site is not using a webhook. Throttled (one poll at a
 * time, at most every 2 seconds) so waiting pages can call it freely.
 */
function telegram_poll(): void
{
    if (!telegram_enabled() || setting('telegram_webhook', false)) return;
    $stamp = STORAGE_DIR . '/telegram.poll';
    if (is_file($stamp) && microtime(true) - (float) @file_get_contents($stamp) < 2) return;
    $fh = @fopen(STORAGE_DIR . '/telegram.lock', 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return;
    try {
        @file_put_contents($stamp, (string) microtime(true));
        $offset = (int) setting('telegram_update_offset', 0);
        $updates = telegram_api('getUpdates', ['offset' => $offset + 1, 'timeout' => 0, 'limit' => 50, 'allowed_updates' => ['message', 'callback_query']]);
        foreach ((array) $updates as $up) {
            setting_set('telegram_update_offset', (int) $up['update_id']);
            try { telegram_handle_update($up); } catch (Throwable $e) { error_log('telegram update: ' . $e); }
        }
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function telegram_handle_update(array $up): void
{
    if (isset($up['callback_query'])) { telegram_handle_button($up['callback_query']); return; }
    $msg = $up['message'] ?? null;
    if (!$msg || ($msg['chat']['type'] ?? '') !== 'private') return;
    $chat = (string) $msg['chat']['id'];
    $text = trim((string) ($msg['text'] ?? ''));
    $company = e(company('name', 'our website'));

    if (preg_match('~^/start\s+([\w-]{16,64})$~', $text, $m)) {
        $u = db_one('SELECT * FROM users WHERE telegram_link_hash = ? AND telegram_link_expires > NOW() AND active = 1', [hash('sha256', $m[1])]);
        if (!$u) { telegram_send($chat, "⌛ This connect link has expired or was already used. Open <b>My profile → Telegram</b> in the {$company} admin and press <b>Connect Telegram</b> again."); return; }
        $username = (string) ($msg['from']['username'] ?? '');
        db_update('users', ['telegram_chat_id' => $chat, 'telegram_username' => $username !== '' ? mb_substr($username, 0, 64) : null, 'telegram_linked_at' => now(),
            'telegram_link_hash' => null, 'telegram_link_expires' => null], (int) $u['id']);
        auth_log('telegram_linked', (int) $u['id'], $u['email']);
        telegram_send($chat, "✅ <b>Connected.</b> This chat now receives sign-in codes and alerts for <b>" . e((string) $u['name']) . "</b> at {$company}.\n\nYou can change what is sent in My profile → Telegram. Send /stop to disconnect.");
        $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $u['id']]);
        security_notice($u, 'Telegram connected to your account', 'A Telegram account' . ($username !== '' ? ' (@' . $username . ')' : '') . ' was connected to your control panel account. It will receive sign-in codes and security alerts. If this was not you, disconnect it in My profile and change your password.', null, 'telegram_linked');
        return;
    }
    if ($text === '/stop') {
        $users = db_all('SELECT * FROM users WHERE telegram_chat_id = ?', [$chat]);
        foreach ($users as $u) {
            telegram_unlink((int) $u['id']);
            auth_log('telegram_unlinked', (int) $u['id'], $u['email']);
        }
        telegram_send($chat, $users ? '👋 Disconnected. You will no longer receive codes or alerts here.' : 'This chat is not connected to any account.');
        return;
    }
    telegram_send($chat, "👋 This bot sends sign-in codes and alerts for the <b>{$company}</b> control panel.\n\nTo connect, sign in to the admin, open <b>My profile → Telegram</b> and press <b>Connect Telegram</b>.");
}

function telegram_handle_button(array $cb): void
{
    $answer = fn(string $t) => telegram_api('answerCallbackQuery', ['callback_query_id' => (string) $cb['id'], 'text' => $t]);
    if (!preg_match('~^(ap|dn):(\d+):([a-f0-9]{24})$~', (string) ($cb['data'] ?? ''), $m)) { $answer('Unknown action.'); return; }
    $row = db_one("SELECT o.*, u.telegram_chat_id, u.name, u.email FROM login_otps o JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.purpose = 'login'", [(int) $m[2]]);
    // Only the account owner's own chat can answer, only once, only before it expires
    if (!$row || !hash_equals((string) $row['approval_nonce'], $m[3]) || (string) $row['telegram_chat_id'] !== (string) ($cb['from']['id'] ?? '')) { $answer('This request is not valid.'); return; }
    $chat = (string) $row['telegram_chat_id'];
    $mid = (int) ($cb['message']['message_id'] ?? 0);
    if ($row['used_at'] !== null || $row['approval'] !== null || strtotime((string) $row['expires_at']) < time()) {
        $answer('This sign-in request has expired.');
        return;
    }
    if ($m[1] === 'ap') {
        db_query("UPDATE login_otps SET approval = 'approved' WHERE id = ? AND approval IS NULL", [(int) $row['id']]);
        auth_log('telegram_approved', (int) $row['user_id'], $row['email']);
        $answer('Sign-in approved');
        if ($mid) telegram_api('editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'parse_mode' => 'HTML', 'text' => '✅ <b>Sign-in approved</b> at ' . e(date('H:i')) . '. The browser continues by itself.']);
    } else {
        db_query("UPDATE login_otps SET approval = 'denied', used_at = NOW() WHERE id = ?", [(int) $row['id']]);
        auth_log('telegram_denied', (int) $row['user_id'], $row['email']);
        $answer('Sign-in blocked');
        if ($mid) telegram_api('editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'parse_mode' => 'HTML', 'text' => '⛔ <b>Sign-in blocked.</b> Someone knows your password — change it now in My profile.']);
        $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $row['user_id']]);
        if ($u) security_notice($u, 'Sign-in blocked from Telegram', 'You denied a sign-in to your account from Telegram. The person entered your correct password, so change it now and use “Sign out all other devices”.', null, 'telegram_denied');
    }
}

/** Point Telegram at our webhook (public HTTPS only), or switch back to polling. */
function telegram_configure_webhook(bool $on, ?string $token = null): ?string
{
    if ($on) {
        if (!telegram_can_link()) return 'Webhooks need the site’s public HTTPS address (Settings → SEO → Production site URL). Leave the webhook off to use polling.';
        $secret = bin2hex(random_bytes(24));
        if (telegram_api('setWebhook', ['url' => abs_url('telegram/webhook'), 'secret_token' => $secret, 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true], $token) === null) return $GLOBALS['telegram_error'];
        setting_set('telegram_webhook_secret', $secret);
        return null;
    }
    telegram_api('deleteWebhook', ['drop_pending_updates' => false], $token);
    return null;
}
