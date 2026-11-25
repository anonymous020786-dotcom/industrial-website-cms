<?php
/**
 * Newsletter: double opt-in subscriptions, campaigns sent in batches by cron,
 * open/click tracking, and one-click unsubscribe (RFC 8058).
 */
declare(strict_types=1);

const SUBSCRIBER_STATUSES = ['pending' => ['Waiting for confirmation', 'bg-amber-100 text-amber-800'], 'subscribed' => ['Subscribed', 'bg-emerald-100 text-emerald-800'],
    'unsubscribed' => ['Unsubscribed', 'bg-gray-200 text-gray-700'], 'bounced' => ['Bounced', 'bg-red-100 text-red-700']];
const CAMPAIGN_STATUSES = ['draft' => ['Draft', 'bg-gray-100 text-gray-700'], 'scheduled' => ['Scheduled', 'bg-blue-100 text-blue-800'],
    'sending' => ['Sending', 'bg-amber-100 text-amber-800'], 'sent' => ['Sent', 'bg-emerald-100 text-emerald-800'], 'cancelled' => ['Stopped', 'bg-red-100 text-red-700']];

function newsletter_enabled(): bool
{
    return (bool) setting('newsletter_enabled', true);
}

/**
 * Add or re-activate a subscriber. Returns 'confirm' (email sent), 'subscribed', 'already' or 'invalid'.
 * Never reveals to the visitor whether an address was already on the list beyond "check your inbox".
 */
function newsletter_subscribe(string $email, string $name = '', string $source = 'website', bool $consentGiven = false): string
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) return 'invalid';
    $name = mb_substr(trim($name), 0, 150);
    $row = db_one('SELECT * FROM subscribers WHERE email = ?', [$email]);
    if ($row && $row['status'] === 'subscribed') return 'already';
    $double = (bool) setting('newsletter_double_optin', true) && !$consentGiven;
    $status = $double ? 'pending' : 'subscribed';
    if ($row) {
        db_update('subscribers', ['status' => $status, 'name' => $name ?: $row['name'], 'source' => $source, 'ip' => client_ip(), 'unsubscribed_at' => null,
            'confirmed_at' => $status === 'subscribed' ? now() : $row['confirmed_at']], (int) $row['id']);
        $id = (int) $row['id'];
    } else {
        $id = db_insert('subscribers', ['email' => $email, 'name' => $name ?: null, 'status' => $status, 'token' => bin2hex(random_bytes(20)), 'source' => $source,
            'ip' => client_ip(), 'confirmed_at' => $status === 'subscribed' ? now() : null]);
    }
    $sub = db_one('SELECT * FROM subscribers WHERE id = ?', [$id]);
    if ($double) {
        // At most one confirmation email per address every 10 minutes (stops someone flooding an inbox)
        if (rate_limit('nl-confirm:' . sha1($email), 1, 600)) newsletter_send_confirm($sub);
        return 'confirm';
    }
    if ($source !== 'import' && $source !== 'admin') newsletter_send_welcome($sub);
    return 'subscribed';
}

function newsletter_url(string $action, array $sub): string
{
    return abs_url('newsletter/' . $action . '/' . $sub['token']);
}

function newsletter_footer_html(array $sub): string
{
    return '<p style="margin-top:28px;font-size:12px;line-height:1.6;color:#6b7280">You receive this because ' . e((string) $sub['email']) . ' subscribed to news from '
        . e(company('name')) . '. <a href="' . e(newsletter_url('unsubscribe', $sub)) . '" style="color:#6b7280">Unsubscribe</a>'
        . (company('address') ? '<br>' . e(str_replace("\n", ', ', company('address'))) : '') . '</p>';
}

function newsletter_send_confirm(array $sub): void
{
    $title = 'Please confirm your subscription';
    send_mail((string) $sub['email'], 'Confirm your subscription to ' . company('name'), mail_layout($title,
        '<p style="font-size:14px;line-height:1.6">Hello' . ($sub['name'] ? ' ' . e((string) $sub['name']) : '') . ',</p>'
        . '<p style="font-size:14px;line-height:1.6">Please confirm that you want to receive ' . e((string) setting('newsletter_title', 'our newsletter')) . ' at this address.</p>'
        . '<p style="margin:22px 0"><a href="' . e(newsletter_url('confirm', $sub)) . '" style="background:#F28C28;color:#101D32;padding:12px 20px;text-decoration:none;font-weight:bold">Yes, subscribe me</a></p>'
        . '<p style="font-size:12px;color:#6b7280">If you did not ask for this, ignore this email — you will not be subscribed.</p>'));
}

function newsletter_send_welcome(array $sub): void
{
    if (!setting('newsletter_welcome', true)) return;
    $text = (string) setting('newsletter_welcome_text', 'Thank you for subscribing. We send practical engineering articles, product news and maintenance tips — usually once a month, never spam.');
    send_mail((string) $sub['email'], 'Welcome to ' . (string) setting('newsletter_title', company('name') . ' news'), mail_layout('You are subscribed',
        '<p style="font-size:14px;line-height:1.6">' . nl2br(e($text)) . '</p>' . newsletter_footer_html($sub)),
        null, [], newsletter_unsub_headers($sub));
}

function newsletter_unsub_headers(array $sub): array
{
    return ['List-Unsubscribe' => '<' . newsletter_url('unsubscribe', $sub) . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'];
}

function newsletter_by_token(string $token): ?array
{
    return preg_match('~^[a-f0-9]{40}$~', $token) ? db_one('SELECT * FROM subscribers WHERE token = ?', [$token]) : null;
}

/* ------------------------------------------------------------------ */
/* Campaigns                                                           */
/* ------------------------------------------------------------------ */

function campaign_sig(int $cid, string $token, string $url): string
{
    return substr(hash_hmac('sha256', $cid . '|' . $token . '|' . $url, (string) config('app_key')), 0, 24);
}


/** The campaign as the subscriber gets it (or as a preview, without tracking, when $sub is null). */
function campaign_html(array $c, ?array $sub = null): string
{
    $body = sanitize_html((string) $c['body']);
    if ((int) $c['latest_posts'] > 0) {
        $posts = db_all('SELECT p.* FROM posts p WHERE ' . POST_VISIBLE . ' ORDER BY p.published_at DESC LIMIT ' . min(10, (int) $c['latest_posts']));
        if ($posts) {
            $body .= '<h2 style="margin:28px 0 10px;font-size:17px;color:#101D32">Latest articles</h2>';
            foreach ($posts as $p) {
                $body .= '<div style="margin:0 0 16px;padding:0 0 16px;border-bottom:1px solid #e5e7eb"><a href="' . e(abs_url('blog/' . $p['slug'])) . '" style="font-size:15px;font-weight:bold;color:#174A78;text-decoration:none">' . e($p['title']) . '</a>'
                    . ($p['excerpt'] ? '<div style="margin-top:4px;font-size:13px;line-height:1.6;color:#374151">' . e(str_limit((string) $p['excerpt'], 180)) . '</div>' : '') . '</div>';
            }
        }
    }
    // Absolute links (the editor stores site paths)
    $body = preg_replace_callback('~href="(/[^"]*)"~', fn($m) => 'href="' . e(abs_url(ltrim(html_entity_decode($m[1]), '/'))) . '"', $body) ?? $body;
    $pre = $c['preheader'] ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e((string) $c['preheader']) . '</div>' : '';
    if ($sub) {
        // Click tracking: every web link goes through a signed redirect (no open redirect)
        $body = preg_replace_callback('~href="(https?://[^"]+)"~i', function ($m) use ($c, $sub) {
            $u = html_entity_decode($m[1]);
            return 'href="' . e(abs_url('newsletter/c/' . (int) $c['id'] . '/' . $sub['token']) . '?u=' . b64url_encode($u) . '&s=' . campaign_sig((int) $c['id'], (string) $sub['token'], $u)) . '"';
        }, $body) ?? $body;
        $body .= newsletter_footer_html($sub) . '<img src="' . e(abs_url('newsletter/o/' . (int) $c['id'] . '/' . $sub['token'])) . '" width="1" height="1" alt="" style="display:block;border:0">';
    } else {
        $body .= '<p style="margin-top:28px;font-size:12px;color:#6b7280">Footer with the unsubscribe link and your company address is added for each subscriber.</p>';
    }
    return mail_layout((string) $c['subject'], $pre . $body);
}

function campaign_send_one(array $c, array $sub): bool|string
{
    return send_mail((string) $sub['email'], (string) $c['subject'], campaign_html($c, $sub), (string) (setting('newsletter_reply_to') ?: '') ?: null, [], newsletter_unsub_headers($sub));
}

/** Queue every current subscriber and mark the campaign as sending. Returns the number of recipients. */
function campaign_start(array $c): int
{
    db_query("INSERT IGNORE INTO campaign_sends (campaign_id, subscriber_id) SELECT ?, id FROM subscribers WHERE status = 'subscribed'", [(int) $c['id']]);
    $n = (int) db_val('SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ?', [(int) $c['id']]);
    db_update('campaigns', ['status' => 'sending', 'started_at' => now(), 'recipients' => $n], (int) $c['id']);
    return $n;
}

/** Send the next batch for every campaign that is sending (and start scheduled ones that are due). */
function campaign_send_batch(?int $limit = null): array
{
    $limit ??= max(1, min(1000, (int) setting('newsletter_batch', 100)));
    $out = ['sent' => 0, 'failed' => 0, 'finished' => 0];
    foreach (db_all("SELECT * FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW()") as $c) campaign_start($c);
    foreach (db_all("SELECT * FROM campaigns WHERE status = 'sending' ORDER BY started_at") as $c) {
        $rows = db_all("SELECT s.* FROM campaign_sends cs JOIN subscribers s ON s.id = cs.subscriber_id WHERE cs.campaign_id = ? AND cs.sent_at IS NULL ORDER BY cs.subscriber_id LIMIT {$limit}", [(int) $c['id']]);
        foreach ($rows as $sub) {
            if ($sub['status'] !== 'subscribed') { db_query('UPDATE campaign_sends SET sent_at = NOW(), error = ? WHERE campaign_id = ? AND subscriber_id = ?', ['skipped: ' . $sub['status'], (int) $c['id'], (int) $sub['id']]); continue; }
            $r = campaign_send_one($c, $sub);
            db_query('UPDATE campaign_sends SET sent_at = NOW(), error = ? WHERE campaign_id = ? AND subscriber_id = ?', [$r === true ? null : mb_substr((string) $r, 0, 255), (int) $c['id'], (int) $sub['id']]);
            $r === true ? $out['sent']++ : $out['failed']++;
        }
        $limit -= count($rows);
        if (!db_val('SELECT 1 FROM campaign_sends WHERE campaign_id = ? AND sent_at IS NULL LIMIT 1', [(int) $c['id']])) {
            db_update('campaigns', ['status' => 'sent', 'sent_at' => now()], (int) $c['id']);
            $out['finished']++;
        }
        if ($limit <= 0) break;
    }
    return $out;
}

function campaign_stats(array $c): array
{
    $s = db_one('SELECT COUNT(*) total, SUM(sent_at IS NOT NULL AND error IS NULL) sent, SUM(error IS NOT NULL) failed, SUM(opened_at IS NOT NULL) opened, SUM(clicked_at IS NOT NULL) clicked FROM campaign_sends WHERE campaign_id = ?', [(int) $c['id']]) ?: [];
    $sent = (int) ($s['sent'] ?? 0);
    $unsub = $c['started_at'] ? (int) db_val("SELECT COUNT(*) FROM campaign_sends cs JOIN subscribers s ON s.id = cs.subscriber_id WHERE cs.campaign_id = ? AND s.status = 'unsubscribed' AND s.unsubscribed_at >= ?", [(int) $c['id'], $c['started_at']]) : 0;
    return ['total' => (int) ($s['total'] ?? 0), 'sent' => $sent, 'failed' => (int) ($s['failed'] ?? 0), 'opened' => (int) ($s['opened'] ?? 0), 'clicked' => (int) ($s['clicked'] ?? 0), 'unsubscribed' => $unsub,
        'open_rate' => $sent ? round((int) $s['opened'] / $sent * 100, 1) : 0.0, 'click_rate' => $sent ? round((int) $s['clicked'] / $sent * 100, 1) : 0.0];
}

/** Cron: send queued newsletters. */
function cron_newsletter(bool $force): ?string
{
    if (!db_val("SELECT 1 FROM campaigns WHERE status = 'sending' OR (status = 'scheduled' AND scheduled_at <= NOW()) LIMIT 1")) return null;
    $r = campaign_send_batch();
    return "newsletter: {$r['sent']} sent, {$r['failed']} failed, {$r['finished']} campaign(s) finished";
}

/** Subscribe form (footer, blog sidebar). $dark for the navy footer. */
function newsletter_form(string $variant = 'footer'): string
{
    if (!newsletter_enabled()) return '';
    $dark = $variant === 'footer';
    $id = 'nl-' . $variant;
    return '<form method="post" action="' . e(url('newsletter/subscribe')) . '" class="newsletter-form' . ($dark ? ' is-dark' : '') . '" data-newsletter novalidate>'
        . '<input type="hidden" name="_ft" value="' . e(form_token()) . '"><input type="hidden" name="source" value="' . e($variant) . '">'
        . '<div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<label for="' . $id . '" class="sr-only">Email address</label>'
        . '<div class="newsletter-row"><input id="' . $id . '" type="email" name="email" required placeholder="Your work email" autocomplete="email">'
        . '<button type="submit" class="btn btn-primary">' . e((string) setting('newsletter_button', 'Subscribe')) . '</button></div>'
        . '<p class="newsletter-note">' . e((string) setting('newsletter_note', 'Monthly engineering notes. No spam — unsubscribe any time.')) . '</p>'
        . '<p class="newsletter-msg" data-newsletter-msg role="status" hidden></p></form>';
}
