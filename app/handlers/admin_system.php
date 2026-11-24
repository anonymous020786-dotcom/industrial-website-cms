<?php
/** Admin: maintenance mode quick switch & preview link, and the error log. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

/* ================================================================== */
/* MAINTENANCE MODE                                                    */
/* ================================================================== */

/** Quick on/off from the banner or the settings status card. */
function admin_maintenance_switch(): void
{
    require_login('settings');
    csrf_check();
    require_stepup('maintenance');
    $on = input('action') === 'on';
    $was = maintenance_active();
    $until = null;
    if ($on && ($h = (int) input('hours', 0)) > 0) $until = date('Y-m-d H:i:s', time() + min(72, $h) * 3600);
    maintenance_switch($on, $until);
    $now = maintenance_state(true)['active'];
    if ($now !== $was) maintenance_notify($now);
    flash($now ? 'info' : 'success', $now
        ? 'Maintenance mode is on. Visitors now see the maintenance page; you still see the normal site because you are signed in.'
        : 'Maintenance mode is off. The website is live again.');
    $to = stepup_safe_path((string) input('return', ''), 'admin/settings/maintenance');
    redirect(str_starts_with($to, 'admin') ? $to : 'admin/settings/maintenance');
}

/** Replace the secret preview link (anyone with the old one loses access). */
function admin_maintenance_link(): void
{
    require_login('settings');
    csrf_check();
    require_stepup('maintenance');
    maintenance_token(true);
    flash('success', 'A new preview link was created. The old link no longer works.');
    redirect('admin/settings/maintenance');
}

/* ================================================================== */
/* ERROR LOG                                                           */
/* ================================================================== */

function admin_errors(): void
{
    require_login('settings');
    $ref = strtoupper(trim((string) query('ref', '')));
    if ($ref !== '') {
        if (!str_starts_with($ref, 'E-')) $ref = 'E-' . $ref;
        $id = db_val('SELECT id FROM app_errors WHERE ref = ?', [$ref]);
        if ($id) redirect('admin/errors/' . $id);
        flash('error', 'No error with reference ' . $ref . ' was found. Errors are kept for 60 days.');
    }
    $show = query('show') === 'resolved' ? 'resolved' : 'open';
    $groups = db_all('SELECT e.*, g.n, g.first_seen, g.visitors FROM (
            SELECT fingerprint, COUNT(*) n, MIN(created_at) first_seen, MAX(id) last_id, COUNT(DISTINCT ip) visitors
            FROM app_errors WHERE resolved_at IS ' . ($show === 'open' ? 'NULL' : 'NOT NULL') . ' GROUP BY fingerprint
        ) g JOIN app_errors e ON e.id = g.last_id ORDER BY e.created_at DESC LIMIT 200');
    $stats = [
        '24h' => (int) db_val('SELECT COUNT(*) FROM app_errors WHERE created_at >= NOW() - INTERVAL 1 DAY'),
        '7d' => (int) db_val('SELECT COUNT(*) FROM app_errors WHERE created_at >= NOW() - INTERVAL 7 DAY'),
        'open' => (int) db_val('SELECT COUNT(DISTINCT fingerprint) FROM app_errors WHERE resolved_at IS NULL'),
        '404' => (int) db_val("SELECT COUNT(*) FROM not_found_log WHERE last_seen >= NOW() - INTERVAL 7 DAY"),
    ];
    $actions = '<form method="post" action="' . e(url('admin/errors/test')) . '">' . csrf_field() . '<button class="adm-btn adm-btn-light" title="Shows the error page, records it here and sends the alert">' . icon('flask-conical', 'h-4 w-4') . ' Trigger a test error</button></form>'
        . ($show === 'open' && $groups
        ? '<form method="post" action="' . e(url('admin/errors/resolve')) . '">' . csrf_field() . '<input type="hidden" name="all" value="1"><button class="adm-btn adm-btn-light" data-confirm="Mark every open error as resolved? They reopen automatically if they happen again.">' . icon('check-check', 'h-4 w-4') . ' Resolve all</button></form>'
        : '');
    admin_render('Error log', 'errors', compact('groups', 'show', 'stats', 'ref'), ['actions' => $actions]);
}

function admin_error_show(string $id): void
{
    require_login('settings');
    $row = db_one('SELECT e.*, u.name AS user_name, u.email AS user_email FROM app_errors e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = ?', [(int) $id]);
    if (!$row) abort(404);
    $similar = db_all('SELECT id, ref, path, ip, created_at, resolved_at FROM app_errors WHERE fingerprint = ? ORDER BY id DESC LIMIT 25', [$row['fingerprint']]);
    $total = (int) db_val('SELECT COUNT(*) FROM app_errors WHERE fingerprint = ?', [$row['fingerprint']]);
    admin_render('Error ' . $row['ref'], 'error_show', compact('row', 'similar', 'total'), ['crumbs' => [['Error log', 'admin/errors']]]);
}

function admin_errors_resolve(): void
{
    require_login('settings');
    csrf_check();
    if (input('all')) {
        $n = db_query('UPDATE app_errors SET resolved_at = NOW() WHERE resolved_at IS NULL')->rowCount();
        flash('success', $n ? 'All errors marked as resolved. Any that happen again will reappear here.' : 'There were no open errors.');
        redirect('admin/errors');
    }
    $fp = (string) input('fingerprint', '');
    $reopen = input('reopen') === '1';
    db_query('UPDATE app_errors SET resolved_at = ' . ($reopen ? 'NULL' : 'NOW()') . ' WHERE fingerprint = ?', [$fp]);
    flash('success', $reopen ? 'Error reopened.' : 'Marked as resolved. If it happens again it will reappear in the open list.');
    redirect('admin/errors');
}

/** Deliberately fail, to check that the error page, the log and the alerts all work. */
function admin_errors_test(): void
{
    $user = require_login('settings');
    csrf_check();
    throw new RuntimeException('Test error triggered by ' . $user['name'] . ' from the error log (this is expected).');
}
