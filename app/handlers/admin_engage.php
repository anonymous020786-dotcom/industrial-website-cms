<?php
/**
 * Admin: short links & sharing stats, comment moderation, newsletter (subscribers and campaigns).
 */
declare(strict_types=1);
require_once __DIR__ . '/admin.php'; // admin_render(), layout

/* ------------------------------------------------------------------ */
/* Short links                                                         */
/* ------------------------------------------------------------------ */

function admin_links(): void
{
    require_login('content');
    $where = ['1=1'];
    $params = [];
    if (in_array($kind = (string) query('kind', ''), ['auto', 'manual'], true)) $where[] = $kind === 'auto' ? 'auto = 1' : 'auto = 0';
    if (($q = trim((string) query('q', ''))) !== '') { $where[] = '(code LIKE ? OR target LIKE ? OR title LIKE ?)'; array_push($params, ...array_fill(0, 3, '%' . addcslashes($q, '%_\\') . '%')); }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM short_links WHERE {$w}", $params);
    $pager = paginate($total, 50, (int) query('page', 1));
    $rows = db_all("SELECT * FROM short_links WHERE {$w} ORDER BY clicks DESC, id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    // Shares in the last 30 days by network, and the most shared pages
    $byNet = [];
    $byPage = [];
    foreach (db_all("SELECT page FROM events WHERE type = 'share' AND created_at >= NOW() - INTERVAL 30 DAY") as $e) {
        [$net, $path] = array_pad(explode('|', (string) $e['page'], 2), 2, '');
        $byNet[$net] = ($byNet[$net] ?? 0) + 1;
        if ($path !== '') $byPage[$path] = ($byPage[$path] ?? 0) + 1;
    }
    arsort($byNet);
    arsort($byPage);
    $byPage = array_slice($byPage, 0, 8, true);
    admin_render('Short links & sharing', 'engage/links', compact('rows', 'pager', 'byNet', 'byPage', 'total'), ['wide' => true]);
}

function admin_link_save(): void
{
    $me = require_login('content');
    csrf_check();
    $target = trim((string) input('target', ''));
    $code = trim((string) input('code', ''));
    $title = mb_substr(trim((string) input('title', '')), 0, 255);
    // A full URL to this site is stored as a path, so it keeps working if the domain changes
    $site = rtrim(abs_url(''), '/');
    if ($site !== '' && str_starts_with($target, $site)) $target = substr($target, strlen($site)) ?: '/';
    if (!short_target_valid($target) || mb_strlen($target) > 1000) { flash('error', 'Enter a page on this site (e.g. /products/hydraulic-cylinders) or a full https:// address.'); redirect('admin/links'); }
    if ($code === '') {
        do { $code = short_code_random(); } while (db_val('SELECT 1 FROM short_links WHERE code = ?', [$code]));
    } elseif (!short_code_valid($code)) {
        flash('error', 'Short codes use letters, numbers, - and _ (2–32 characters).'); redirect('admin/links');
    } elseif (db_val('SELECT 1 FROM short_links WHERE code = ?', [$code])) {
        flash('error', 'The code “' . $code . '” is already used. Choose another.'); redirect('admin/links');
    }
    $id = db_insert('short_links', ['code' => $code, 'target' => $target, 'title' => $title ?: null, 'auto' => 0, 'created_by' => (int) $me['id']]);
    content_log('short_link', $id, 'created', $title ?: $code, abs_url('s/' . $code) . ' → ' . $target);
    flash('success', 'Short link created: ' . abs_url('s/' . $code));
    redirect('admin/links');
}

function admin_link_delete(string $id): void
{
    require_login('content');
    csrf_check();
    $row = db_one('SELECT * FROM short_links WHERE id = ?', [(int) $id]);
    if (!$row) abort(404);
    db_delete('short_links', (int) $row['id']);
    content_log('short_link', (int) $row['id'], 'deleted', $row['title'] ?: $row['code'], abs_url('s/' . $row['code']));
    flash('success', 'Short link deleted. ' . ($row['auto'] ? 'A new one is created the next time the page is shared.' : ''));
    redirect('admin/links');
}

/* ------------------------------------------------------------------ */
/* Comments: moderation                                                */
/* ------------------------------------------------------------------ */

function admin_comments(): void
{
    require_login('content');
    $status = array_key_exists($s = (string) query('status', 'pending'), COMMENT_STATUSES) ? $s : 'pending';
    $where = ['c.status = ?'];
    $params = [$status];
    if (($q = trim((string) query('q', ''))) !== '') { $where[] = '(c.body LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR c.ip = ?)'; array_push($params, ...array_fill(0, 3, '%' . addcslashes($q, '%_\\') . '%')); $params[] = $q; }
    if (ctype_digit((string) query('post', ''))) { $where[] = 'c.post_id = ?'; $params[] = (int) query('post'); }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM comments c WHERE {$w}", $params);
    $pager = paginate($total, 30, (int) query('page', 1));
    $rows = db_all("SELECT c.*, p.title AS post_title, p.slug AS post_slug, pc.name AS parent_name, u.name AS staff_name,
        (SELECT COUNT(*) FROM comments x WHERE x.email = c.email AND x.status = 'approved') AS approved_before
        FROM comments c LEFT JOIN posts p ON p.id = c.post_id LEFT JOIN comments pc ON pc.id = c.parent_id LEFT JOIN users u ON u.id = c.user_id
        WHERE {$w} ORDER BY c.created_at " . ($status === 'pending' ? 'ASC' : 'DESC') . " LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    $counts = array_column(db_all('SELECT status, COUNT(*) n FROM comments GROUP BY status'), 'n', 'status');
    $actions = can('settings') ? '<a class="adm-btn adm-btn-light" href="' . e(url('admin/settings/community')) . '">' . icon('settings', 'h-4 w-4') . ' Comment settings</a>' : '';
    admin_render('Comments', 'engage/comments', compact('rows', 'pager', 'counts', 'status', 'total'), ['actions' => $actions, 'wide' => true]);
}

/** Apply a moderation action to comments. Returns how many changed. */
function comments_moderate(array $ids, string $action, array $me): int
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all("SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON p.id = c.post_id WHERE c.id IN ({$in})", $ids);
    $labels = ['approve' => ['approved', 'Approved'], 'spam' => ['rejected', 'Marked as spam'], 'trash' => ['deleted', 'Moved to trash'], 'delete' => ['deleted', 'Deleted permanently'], 'pending' => ['updated', 'Back to pending']];
    foreach ($rows as $c) {
        if ($action === 'approve') db_update('comments', ['status' => 'approved', 'approved_at' => now(), 'approved_by' => (int) $me['id'], 'spam_reason' => null], (int) $c['id']);
        elseif ($action === 'delete') db_query('DELETE FROM comments WHERE id = ? OR parent_id = ?', [(int) $c['id'], (int) $c['id']]);
        elseif (in_array($action, ['spam', 'trash', 'pending'], true)) db_update('comments', ['status' => $action], (int) $c['id']);
        else continue;
        content_log('comment', (int) $c['id'], $labels[$action][0], $c['name'] . ' on “' . $c['post_title'] . '”', $labels[$action][1]);
    }
    return count($rows);
}

function admin_comment_action(string $id): void
{
    $me = require_login('content');
    csrf_check();
    $action = (string) input('action', '');
    if (!in_array($action, ['approve', 'spam', 'trash', 'pending', 'delete'], true)) abort(400);
    comments_moderate([(int) $id], $action, $me);
    $done = ['approve' => 'Comment approved and published.', 'spam' => 'Marked as spam. Future comments from this sender are held.', 'trash' => 'Comment moved to trash.', 'pending' => 'Comment moved back to pending.', 'delete' => 'Comment deleted permanently.'];
    flash('success', $done[$action]);
    back('admin/comments');
}

function admin_comments_bulk(): void
{
    $me = require_login('content');
    csrf_check();
    $action = (string) input('bulk_action', '');
    if (!in_array($action, ['approve', 'spam', 'trash', 'pending', 'delete'], true)) { flash('error', 'Choose an action.'); back('admin/comments'); }
    $n = comments_moderate((array) ($_POST['ids'] ?? []), $action, $me);
    flash($n ? 'success' : 'error', $n ? $n . ' comment(s) updated.' : 'Select at least one comment.');
    back('admin/comments');
}

/** Reply as the team from the moderation screen (published at once; approves the comment it answers). */
function admin_comment_reply(string $id): void
{
    $me = require_login('content');
    csrf_check();
    $c = db_one('SELECT * FROM comments WHERE id = ?', [(int) $id]);
    if (!$c) abort(404);
    $body = trim(str_replace("\r\n", "\n", (string) input('reply', '')));
    if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) { flash('error', 'Write a reply (up to 5,000 characters).'); back('admin/comments'); }
    if ($c['status'] !== 'approved') comments_moderate([(int) $c['id']], 'approve', $me);
    $rid = db_insert('comments', ['post_id' => (int) $c['post_id'], 'parent_id' => (int) ($c['parent_id'] ?: $c['id']), 'user_id' => (int) $me['id'], 'name' => (string) $me['name'], 'email' => (string) $me['email'],
        'body' => $body, 'status' => 'approved', 'ip' => client_ip(), 'approved_at' => now(), 'approved_by' => (int) $me['id']]);
    // Let the commenter know (one email, only for visitor comments with an address)
    if (!$c['user_id'] && filter_var((string) $c['email'], FILTER_VALIDATE_EMAIL) && setting('comments_reply_email', true)) {
        $post = db_one('SELECT title, slug FROM posts WHERE id = ?', [(int) $c['post_id']]);
        if ($post) send_mail((string) $c['email'], 'Reply to your comment on “' . $post['title'] . '”', mail_layout('We replied to your comment',
            '<p style="font-size:14px;line-height:1.6">Hello ' . e((string) $c['name']) . ',</p><p style="font-size:14px;line-height:1.6">' . e((string) $me['name']) . ' from ' . e(company('name')) . ' replied to your comment:</p>'
            . '<blockquote style="margin:12px 0;padding:10px 14px;border-left:4px solid #F28C28;background:#f8fafc;font-size:14px">' . nl2br(e($body)) . '</blockquote>'
            . '<p><a href="' . e(abs_url('blog/' . $post['slug']) . '#comment-' . $rid) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Read the conversation</a></p>'));
    }
    content_log('comment', $rid, 'created', (string) $me['name'] . ' replied', mb_substr($body, 0, 200));
    flash('success', 'Reply published.');
    back('admin/comments');
}

/* ------------------------------------------------------------------ */
/* Newsletter: campaigns                                               */
/* ------------------------------------------------------------------ */

function campaign_or_404(string $id): array
{
    $c = ctype_digit($id) ? db_one('SELECT * FROM campaigns WHERE id = ?', [(int) $id]) : null;
    if (!$c) abort(404);
    return $c;
}

function admin_newsletter(): void
{
    require_login('content');
    $rows = db_all('SELECT c.*, u.name AS author FROM campaigns c LEFT JOIN users u ON u.id = c.created_by ORDER BY COALESCE(c.sent_at, c.scheduled_at, c.updated_at) DESC LIMIT 100');
    foreach ($rows as &$r) $r['stats'] = in_array($r['status'], ['sending', 'sent', 'cancelled'], true) ? campaign_stats($r) : null;
    unset($r);
    $subs = array_column(db_all('SELECT status, COUNT(*) n FROM subscribers GROUP BY status'), 'n', 'status');
    $growth = (int) db_val("SELECT COUNT(*) FROM subscribers WHERE status = 'subscribed' AND COALESCE(confirmed_at, created_at) >= NOW() - INTERVAL 30 DAY");
    $lost = (int) db_val("SELECT COUNT(*) FROM subscribers WHERE status = 'unsubscribed' AND unsubscribed_at >= NOW() - INTERVAL 30 DAY");
    $actions = '<a class="adm-btn adm-btn-light" href="' . e(url('admin/subscribers')) . '">' . icon('users-round', 'h-4 w-4') . ' Subscribers</a>'
        . '<a class="adm-btn adm-btn-primary" href="' . e(url('admin/newsletter/new')) . '">' . icon('plus', 'h-4 w-4') . ' New newsletter</a>';
    admin_render('Newsletter', 'engage/newsletter', compact('rows', 'subs', 'growth', 'lost'), ['actions' => $actions, 'wide' => true]);
}

function admin_campaign_edit(?string $id = null): void
{
    $me = require_login('content');
    $c = $id ? campaign_or_404($id) : null;
    $locked = $c && in_array($c['status'], ['sending', 'sent', 'cancelled'], true);
    $values = $c ?? ['subject' => '', 'preheader' => '', 'body' => '', 'latest_posts' => 3, 'status' => 'draft', 'scheduled_at' => null];
    $errors = [];
    if (is_post() && !$locked) {
        csrf_check();
        $v = ['subject' => mb_substr(trim((string) input('subject', '')), 0, 190), 'preheader' => mb_substr(trim((string) input('preheader', '')), 0, 190) ?: null,
            'body' => sanitize_html((string) ($_POST['body'] ?? '')), 'latest_posts' => max(0, min(10, (int) input('latest_posts', 0)))];
        if (mb_strlen($v['subject']) < 3) $errors['subject'] = 'Write a subject line.';
        if (trim(strip_tags((string) $v['body'])) === '' && !$v['latest_posts']) $errors['body'] = 'Write the newsletter, or include the latest articles.';
        if (!$errors) {
            if ($c) db_update('campaigns', $v, (int) $c['id']);
            else $c = ['id' => db_insert('campaigns', $v + ['created_by' => (int) $me['id'], 'status' => 'draft'])];
            content_log('campaign', (int) $c['id'], $id ? 'updated' : 'created', $v['subject']);
            $then = (string) input('then', '');
            if ($then === 'test') { admin_campaign_test((string) $c['id'], true); }
            flash('success', 'Newsletter saved.');
            redirect('admin/newsletter/' . $c['id'] . '/edit');
        }
        $values = array_merge($values, $v);
        flash('error', 'Please fix the errors below.');
    }
    $stats = $c && $locked ? campaign_stats($c) : null;
    $audience = (int) db_val("SELECT COUNT(*) FROM subscribers WHERE status = 'subscribed'");
    $preview = $c ? campaign_html(db_one('SELECT * FROM campaigns WHERE id = ?', [(int) $c['id']])) : null;
    $links = $c && $locked ? db_all("SELECT s.email, cs.sent_at, cs.opened_at, cs.clicked_at, cs.error FROM campaign_sends cs JOIN subscribers s ON s.id = cs.subscriber_id WHERE cs.campaign_id = ? ORDER BY cs.clicked_at IS NULL, cs.opened_at IS NULL, s.email LIMIT 200", [(int) $c['id']]) : [];
    admin_render($c ? ($locked ? 'Newsletter: ' . $c['subject'] : 'Edit newsletter') : 'New newsletter', 'engage/campaign', compact('c', 'values', 'errors', 'locked', 'stats', 'audience', 'preview', 'links'),
        ['crumbs' => [['Newsletter', 'admin/newsletter']], 'wide' => true]);
}

/** Send a test copy to the signed-in user. */
function admin_campaign_test(string $id, bool $fromSave = false): void
{
    $me = require_login('content');
    if (!$fromSave) csrf_check();
    $c = campaign_or_404($id);
    $fake = ['id' => 0, 'email' => (string) $me['email'], 'name' => (string) $me['name'], 'token' => str_repeat('0', 40), 'status' => 'subscribed'];
    $r = send_mail((string) $me['email'], '[Test] ' . $c['subject'], campaign_html($c, $fake), null, [], newsletter_unsub_headers($fake));
    flash($r === true ? 'success' : 'error', $r === true ? 'Test sent to ' . $me['email'] . '.' : 'Test not sent: ' . $r);
    redirect('admin/newsletter/' . $c['id'] . '/edit');
}

/** Send now (first batch goes out immediately; cron sends the rest) or schedule. */
function admin_campaign_send(string $id): void
{
    require_login('content');
    csrf_check();
    $c = campaign_or_404($id);
    if (!in_array($c['status'], ['draft', 'scheduled'], true)) { flash('error', 'This newsletter was already sent.'); redirect('admin/newsletter/' . $c['id'] . '/edit'); }
    $audience = (int) db_val("SELECT COUNT(*) FROM subscribers WHERE status = 'subscribed'");
    if (!$audience) { flash('error', 'There are no confirmed subscribers yet.'); redirect('admin/newsletter/' . $c['id'] . '/edit'); }
    $when = trim((string) input('scheduled_at', ''));
    if (input('mode') === 'schedule') {
        $t = $when !== '' ? strtotime($when) : false;
        if (!$t || $t <= time() + 60) { flash('error', 'Choose a time in the future.'); redirect('admin/newsletter/' . $c['id'] . '/edit'); }
        db_update('campaigns', ['status' => 'scheduled', 'scheduled_at' => date('Y-m-d H:i:s', $t)], (int) $c['id']);
        content_log('campaign', (int) $c['id'], 'updated', (string) $c['subject'], 'Scheduled for ' . date('j M Y H:i', $t));
        flash('success', 'Scheduled for ' . date('j M Y, H:i', $t) . ' — to about ' . $audience . ' subscriber(s).');
        redirect('admin/newsletter');
    }
    $n = campaign_start($c);
    $r = campaign_send_batch();
    content_log('campaign', (int) $c['id'], 'sent', (string) $c['subject'], 'To ' . $n . ' subscriber(s)');
    flash('success', 'Sending to ' . $n . ' subscriber(s): ' . $r['sent'] . ' sent now' . ($n > $r['sent'] + $r['failed'] ? ', the rest go out automatically every few minutes.' : '.'));
    redirect('admin/newsletter/' . $c['id'] . '/edit');
}

function admin_campaign_cancel(string $id): void
{
    require_login('content');
    csrf_check();
    $c = campaign_or_404($id);
    if ($c['status'] === 'scheduled') { db_update('campaigns', ['status' => 'draft', 'scheduled_at' => null], (int) $c['id']); flash('success', 'Schedule cancelled — back to draft.'); }
    elseif ($c['status'] === 'sending') { db_update('campaigns', ['status' => 'cancelled'], (int) $c['id']); flash('success', 'Sending stopped. Emails already sent cannot be recalled.'); }
    content_log('campaign', (int) $c['id'], 'updated', (string) $c['subject'], 'Stopped / unscheduled');
    redirect('admin/newsletter/' . $c['id'] . '/edit');
}

function admin_campaign_duplicate(string $id): void
{
    $me = require_login('content');
    csrf_check();
    $c = campaign_or_404($id);
    $nid = db_insert('campaigns', ['subject' => $c['subject'] . ' (copy)', 'preheader' => $c['preheader'], 'body' => $c['body'], 'latest_posts' => $c['latest_posts'], 'status' => 'draft', 'created_by' => (int) $me['id']]);
    flash('success', 'Copied as a new draft.');
    redirect('admin/newsletter/' . $nid . '/edit');
}

function admin_campaign_delete(string $id): void
{
    require_login('content');
    csrf_check();
    $c = campaign_or_404($id);
    if (!in_array($c['status'], ['draft', 'cancelled', 'sent'], true)) { flash('error', 'Stop or unschedule it first.'); redirect('admin/newsletter/' . $c['id'] . '/edit'); }
    db_query('DELETE FROM campaign_sends WHERE campaign_id = ?', [(int) $c['id']]);
    db_delete('campaigns', (int) $c['id']);
    content_log('campaign', (int) $c['id'], 'deleted', (string) $c['subject']);
    flash('success', 'Newsletter deleted.');
    redirect('admin/newsletter');
}

/* ------------------------------------------------------------------ */
/* Newsletter: subscribers                                             */
/* ------------------------------------------------------------------ */

function admin_subscribers(): void
{
    require_login('content');
    $where = ['1=1'];
    $params = [];
    if (array_key_exists($st = (string) query('status', ''), SUBSCRIBER_STATUSES)) { $where[] = 'status = ?'; $params[] = $st; }
    if (($q = trim((string) query('q', ''))) !== '') { $where[] = '(email LIKE ? OR name LIKE ?)'; array_push($params, '%' . addcslashes($q, '%_\\') . '%', '%' . addcslashes($q, '%_\\') . '%'); }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM subscribers WHERE {$w}", $params);
    $pager = paginate($total, 50, (int) query('page', 1));
    $rows = db_all("SELECT * FROM subscribers WHERE {$w} ORDER BY created_at DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    $counts = array_column(db_all('SELECT status, COUNT(*) n FROM subscribers GROUP BY status'), 'n', 'status');
    $actions = can('settings') ? '<a class="adm-btn adm-btn-light" href="' . e(url('admin/subscribers/export') . qs()) . '">' . icon('download', 'h-4 w-4') . ' Export CSV</a>' : '';
    admin_render('Subscribers', 'engage/subscribers', compact('rows', 'pager', 'counts', 'total'), ['actions' => $actions, 'crumbs' => [['Newsletter', 'admin/newsletter']], 'wide' => true]);
}

function admin_subscriber_add(): void
{
    require_login('content');
    csrf_check();
    $email = (string) input('email', '');
    if (empty($_POST['consent'])) { flash('error', 'Only add people who agreed to receive the newsletter — tick the box to confirm.'); redirect('admin/subscribers'); }
    $r = newsletter_subscribe($email, (string) input('name', ''), 'admin', true);
    flash($r === 'invalid' ? 'error' : 'success', ['invalid' => 'Enter a valid email address.', 'already' => $email . ' is already subscribed.', 'subscribed' => $email . ' added.'][$r] ?? 'Done.');
    if ($r === 'subscribed') content_log('subscriber', null, 'created', $email, 'Added by hand');
    redirect('admin/subscribers');
}

/** CSV import: one address per line or a column named email (and optionally name). */
function admin_subscribers_import(): void
{
    require_login('settings');
    csrf_check();
    if (empty($_POST['consent'])) { flash('error', 'Confirm that everyone in the file agreed to receive the newsletter.'); redirect('admin/subscribers'); }
    $f = $_FILES['csv'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) { flash('error', 'Choose a CSV file (up to 5 MB).'); redirect('admin/subscribers'); }
    $fh = fopen($f['tmp_name'], 'r');
    $head = null; $added = 0; $skipped = 0; $n = 0;
    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false && $n++ < 20000) {
        $row = array_map(fn($x) => trim((string) $x, " \t\xEF\xBB\xBF"), $row);
        if ($head === null && preg_grep('~^e-?mail$~i', $row)) { $head = array_map('strtolower', $row); continue; }
        $email = $head ? ($row[array_search('email', $head, true) ?: array_search('e-mail', $head, true)] ?? '') : ($row[0] ?? '');
        $name = $head && ($i = array_search('name', $head, true)) !== false ? ($row[$i] ?? '') : ($head ? '' : ($row[1] ?? ''));
        $r = newsletter_subscribe((string) $email, (string) $name, 'import', true);
        $r === 'subscribed' ? $added++ : $skipped++;
    }
    fclose($fh);
    content_log('subscriber', null, 'created', 'CSV import', "{$added} added, {$skipped} skipped");
    flash('success', "Import finished: {$added} added, {$skipped} skipped (invalid or already subscribed).");
    redirect('admin/subscribers');
}

function admin_subscribers_export(): void
{
    $me = require_login('settings');
    require_stepup('export');
    $rows = db_all('SELECT email, name, status, source, created_at, confirmed_at, unsubscribed_at FROM subscribers ORDER BY created_at');
    auth_log('subscribers_exported', (int) $me['id'], $me['email']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['email', 'name', 'status', 'source', 'created_at', 'confirmed_at', 'unsubscribed_at'], ',', '"', '');
    foreach ($rows as $r) fputcsv($out, array_values($r), ',', '"', '');
    exit;
}

function admin_subscriber_action(string $id): void
{
    require_login('content');
    csrf_check();
    $s = db_one('SELECT * FROM subscribers WHERE id = ?', [(int) $id]);
    if (!$s) abort(404);
    $a = (string) input('action', '');
    if ($a === 'unsubscribe') { db_update('subscribers', ['status' => 'unsubscribed', 'unsubscribed_at' => now()], (int) $s['id']); flash('success', $s['email'] . ' unsubscribed.'); }
    elseif ($a === 'resend' && $s['status'] === 'pending') { newsletter_send_confirm($s); flash('success', 'Confirmation email sent again to ' . $s['email'] . '.'); }
    elseif ($a === 'delete') { db_query('DELETE FROM campaign_sends WHERE subscriber_id = ?', [(int) $s['id']]); db_delete('subscribers', (int) $s['id']); flash('success', $s['email'] . ' deleted (all data removed).'); }
    else abort(400);
    content_log('subscriber', (int) $s['id'], $a === 'delete' ? 'deleted' : 'updated', (string) $s['email'], ucfirst($a));
    back('admin/subscribers');
}


/* ------------------------------------------------------------------ */
/* Command palette search (Ctrl+K)                                     */
/* ------------------------------------------------------------------ */

function admin_palette_json(): void
{
    require_login();
    $q = trim((string) query('q', ''));
    $out = [];
    if (mb_strlen($q) >= 2) {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $add = function (string $group, string $icon, array $rows, callable $title, callable $sub, callable $link) use (&$out) {
            foreach ($rows as $r) $out[] = ['group' => $group, 'icon' => icon($icon, 'h-4 w-4'), 'title' => $title($r), 'sub' => $sub($r), 'url' => url($link($r))];
        };
        if (can('leads')) {
            $add('Leads', 'inbox', db_all("SELECT id, name, company, email, product, type FROM leads WHERE type <> 'job' AND status <> 'spam' AND (name LIKE ? OR company LIKE ? OR email LIKE ? OR phone LIKE ? OR product LIKE ?) ORDER BY id DESC LIMIT 6", array_fill(0, 5, $like)),
                fn($r) => (string) $r['name'], fn($r) => trim(($r['company'] ?: $r['email']) . ' · ' . $r['product'], ' ·'), fn($r) => 'admin/leads/' . $r['id']);
            $add('Quotes', 'file-text', db_all('SELECT id, number, customer_name, company FROM quotes WHERE number LIKE ? OR customer_name LIKE ? OR company LIKE ? ORDER BY id DESC LIMIT 5', array_fill(0, 3, $like)),
                fn($r) => (string) $r['number'], fn($r) => trim($r['customer_name'] . ' · ' . $r['company'], ' ·'), fn($r) => 'admin/quotes/' . $r['id'] . '/edit');
        }
        if (can('content')) {
            $add('Posts', 'newspaper', db_all('SELECT id, title, status FROM posts WHERE deleted_at IS NULL AND title LIKE ? ORDER BY updated_at DESC LIMIT 5', [$like]), fn($r) => (string) $r['title'], fn($r) => ucfirst((string) $r['status']), fn($r) => 'admin/posts/' . $r['id'] . '/edit');
            $add('Pages', 'file-text', db_all('SELECT id, title, slug FROM pages WHERE deleted_at IS NULL AND title LIKE ? LIMIT 5', [$like]), fn($r) => (string) $r['title'], fn($r) => '/' . $r['slug'], fn($r) => 'admin/pages/' . $r['id'] . '/edit');
            $add('Products', 'package', db_all('SELECT id, name FROM products WHERE name LIKE ? LIMIT 5', [$like]), fn($r) => (string) $r['name'], fn($r) => 'Product', fn($r) => 'admin/r/products/' . $r['id'] . '/edit');
        }
        if (can('users')) {
            $add('Applications', 'user-round-check', db_all("SELECT l.id, l.name, j.title FROM leads l LEFT JOIN jobs j ON j.id = l.job_id WHERE l.type = 'job' AND (l.name LIKE ? OR l.email LIKE ?) ORDER BY l.id DESC LIMIT 4", [$like, $like]),
                fn($r) => (string) $r['name'], fn($r) => (string) ($r['title'] ?: 'Application'), fn($r) => 'admin/leads/' . $r['id']);
        }
    }
    json_response(['results' => $out]);
}
