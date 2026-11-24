<?php
/** Admin: redirects & 404 log, lead reports, backups, automation (cron). */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

/* ================================================================== */
/* REDIRECTS & 404s                                                    */
/* ================================================================== */

function admin_redirects(): void
{
    require_login('settings');
    $tab = query('tab') === '404' ? '404' : 'rules';
    $q = trim((string) query('q', ''));
    if ($tab === 'rules') {
        $rows = $q !== '' ? db_all('SELECT * FROM redirects WHERE source LIKE ? OR target LIKE ? ORDER BY id DESC LIMIT 500', ["%{$q}%", "%{$q}%"])
            : db_all('SELECT * FROM redirects ORDER BY id DESC LIMIT 500');
    } else {
        $rows = db_all('SELECT * FROM not_found_log WHERE ignored = 0' . ($q !== '' ? ' AND path LIKE ?' : '') . ' ORDER BY hits DESC, last_seen DESC LIMIT 500', $q !== '' ? ["%{$q}%"] : []);
    }
    $counts = ['rules' => (int) db_val('SELECT COUNT(*) FROM redirects'), '404' => (int) db_val('SELECT COUNT(*) FROM not_found_log WHERE ignored = 0')];
    $edit = ($eid = (int) query('edit', 0)) ? db_one('SELECT * FROM redirects WHERE id = ?', [$eid]) : null;
    $prefill = ['source' => (string) query('from', ''), 'target' => '', 'code' => 301, 'note' => ''];
    admin_render('Redirects & 404s', 'redirects', compact('tab', 'rows', 'counts', 'q', 'edit', 'prefill'), ['wide' => true]);
}

function admin_redirect_save(): void
{
    require_login('settings');
    csrf_check();
    $id = (int) input('id', 0);
    $source = redirect_normalize((string) input('source', ''));
    $code = (int) input('code', 301);
    if (!isset(REDIRECT_CODES[$code])) $code = 301;
    $back = 'admin/redirects' . ($id ? '?edit=' . $id : '');
    if ($source === '/' || $source === '') { flash('error', 'Enter the old address, e.g. /old-page.html'); redirect($back); }
    if (preg_match('~^/(admin|assets|install|cron)(/|$)~', $source)) { flash('error', 'That path is reserved by the system.'); redirect($back); }
    [$target, $err] = redirect_clean_target((string) input('target', ''), $source);
    if ($err) { flash('error', $err); redirect($back); }
    if ($loop = redirect_loop_error($source, $target)) { flash('error', $loop); redirect($back); }
    $exists = db_one('SELECT id FROM redirects WHERE source_hash = ?', [redirect_hash($source)]);
    if ($exists && (int) $exists['id'] !== $id) { flash('error', 'A redirect for ' . $source . ' already exists.'); redirect('admin/redirects?edit=' . $exists['id']); }
    $data = ['source' => $source, 'source_hash' => redirect_hash($source), 'target' => $target, 'code' => $code, 'note' => mb_substr(trim((string) input('note', '')), 0, 190) ?: null];
    if ($id) db_update('redirects', $data, $id); else db_insert('redirects', $data);
    db_query('DELETE FROM not_found_log WHERE path_hash = ?', [redirect_hash($source)]);
    flash('success', "Redirect saved: {$source} → {$target}");
    redirect('admin/redirects');
}

function admin_redirect_delete(string $id): void
{
    require_login('settings');
    csrf_check();
    db_delete('redirects', (int) $id);
    flash('success', 'Redirect deleted.');
    back('admin/redirects');
}

function admin_redirects_export(): void
{
    require_login('settings');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirects-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['source', 'target', 'code', 'note', 'hits'], ',', '"', '\\');
    foreach (db_all('SELECT source, target, code, note, hits FROM redirects ORDER BY source') as $r) fputcsv($out, array_values($r), ',', '"', '\\');
    exit;
}

/** Bulk import: CSV with columns source,target[,code][,note] (header row optional). */
function admin_redirects_import(): void
{
    require_login('settings');
    csrf_check();
    $f = $_FILES['csv'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) { flash('error', 'Choose a CSV file under 2 MB.'); redirect('admin/redirects'); }
    $fh = fopen($f['tmp_name'], 'r');
    $added = $updated = $skipped = 0;
    $errors = [];
    $line = 0;
    while (($row = fgetcsv($fh, 4000, ',', '"', '\\')) !== false) {
        $line++;
        if ($line === 1 && strtolower(trim((string) ($row[0] ?? ''))) === 'source') continue;
        if (count(array_filter($row, fn($c) => trim((string) $c) !== '')) === 0) continue;
        $source = redirect_normalize((string) ($row[0] ?? ''));
        [$target, $err] = redirect_clean_target((string) ($row[1] ?? ''), $source);
        $code = (int) ($row[2] ?? 301);
        if ($source === '/' || $err || ($loop = redirect_loop_error($source, (string) $target))) { $skipped++; if (count($errors) < 5) $errors[] = "line {$line}: " . ($err ?? $loop ?? 'invalid source'); continue; }
        $exists = (bool) db_val('SELECT id FROM redirects WHERE source_hash = ?', [redirect_hash($source)]);
        db_query('INSERT INTO redirects (source, source_hash, target, code, note) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE target = VALUES(target), code = VALUES(code), note = VALUES(note)',
            [$source, redirect_hash($source), $target, isset(REDIRECT_CODES[$code]) ? $code : 301, mb_substr(trim((string) ($row[3] ?? 'Imported')), 0, 190)]);
        $exists ? $updated++ : $added++;
        if ($line > 5000) break;
    }
    fclose($fh);
    flash($skipped ? 'info' : 'success', "Import finished: {$added} added, {$updated} updated, {$skipped} skipped." . ($errors ? ' ' . implode('; ', $errors) : ''));
    redirect('admin/redirects');
}

function admin_404_action(): void
{
    require_login('settings');
    csrf_check();
    $action = (string) input('action', '');
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    if ($action === 'clear') {
        db_query('DELETE FROM not_found_log');
        flash('success', '404 log cleared.');
    } elseif ($ids && in_array($action, ['ignore', 'delete'], true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        db_query($action === 'ignore' ? "UPDATE not_found_log SET ignored = 1 WHERE id IN ({$in})" : "DELETE FROM not_found_log WHERE id IN ({$in})", $ids);
        flash('success', count($ids) . ' entr' . (count($ids) === 1 ? 'y' : 'ies') . ($action === 'ignore' ? ' ignored.' : ' removed.'));
    } else {
        flash('error', 'Select at least one entry.');
    }
    redirect('admin/redirects?tab=404');
}

/* ================================================================== */
/* REPORTS                                                             */
/* ================================================================== */

/** Lead attribution & conversion report. */
function admin_reports(): void
{
    require_login('leads');
    [$from, $to, $range] = report_range();
    $group = in_array(query('by'), ['source', 'medium', 'campaign', 'landing', 'type', 'product', 'month'], true) ? (string) query('by') : 'source';
    $rows = report_rows($group, $from, $to);
    if (query('export') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lead-report-' . $group . '-' . $from . '-to-' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, [report_groups()[$group], 'Leads', 'Qualified+', 'Quoted+', 'Won', 'Lost', 'Won value', 'Open pipeline', 'Win rate %'], ',', '"', '\\');
        foreach ($rows as $r) fputcsv($out, [$r['label'], $r['leads'], $r['qualified'], $r['quoted'], $r['won'], $r['lost'], $r['won_value'], $r['pipeline'], $r['win_rate']], ',', '"', '\\');
        exit;
    }
    $totals = ['leads' => 0, 'qualified' => 0, 'quoted' => 0, 'won' => 0, 'lost' => 0, 'won_value' => 0.0, 'pipeline' => 0.0];
    foreach ($rows as $r) foreach ($totals as $k => $_) $totals[$k] += $r[$k];
    $totals['win_rate'] = ($totals['won'] + $totals['lost']) ? round($totals['won'] / ($totals['won'] + $totals['lost']) * 100) : null;
    $months = db_all("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) n, SUM(status = 'won') won FROM leads WHERE status <> 'spam' AND created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY m ORDER BY m");
    $events = array_column(db_all("SELECT type, COUNT(*) n FROM events WHERE created_at >= ? AND created_at < ? + INTERVAL 1 DAY GROUP BY type ORDER BY n DESC", [$from, $to]), 'n', 'type');
    $downloads = db_all("SELECT page, COUNT(*) n FROM events WHERE type IN ('datasheet','catalogue') AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY GROUP BY page ORDER BY n DESC LIMIT 10", [$from, $to]);
    $actions = '<a class="adm-btn adm-btn-light" href="' . e(url('admin/reports') . qs(['export' => 'csv'])) . '">' . icon('download', 'h-4 w-4') . ' Export CSV</a>';
    admin_render('Lead reports', 'reports', compact('rows', 'totals', 'group', 'from', 'to', 'range', 'months', 'events', 'downloads'), ['actions' => $actions, 'wide' => true]);
}

function report_groups(): array
{
    return ['source' => 'Source', 'medium' => 'Medium', 'campaign' => 'Campaign', 'landing' => 'Landing page', 'type' => 'Form', 'product' => 'Product / service', 'month' => 'Month'];
}

/** @return array{0:string,1:string,2:string} [from Y-m-d, to Y-m-d, range key] */
function report_range(): array
{
    $range = (string) query('range', '90');
    $to = date('Y-m-d');
    if ($range === 'custom') {
        $f = DateTime::createFromFormat('Y-m-d', (string) query('from', ''));
        $t = DateTime::createFromFormat('Y-m-d', (string) query('to', ''));
        if ($f && $t && $f <= $t) return [$f->format('Y-m-d'), $t->format('Y-m-d'), 'custom'];
        $range = '90';
    }
    $days = in_array($range, ['30', '90', '180', '365'], true) ? (int) $range : 90;
    return [date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), $to, (string) $days];
}

function report_rows(string $group, string $from, string $to): array
{
    $refHost = "SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(REPLACE(referrer,'https://',''),'http://',''),'www.',''),'/',1),'?',1)";
    $expr = match ($group) {
        'source' => "COALESCE(NULLIF(utm_source,''), CASE WHEN referrer IS NULL OR referrer = '' THEN '(direct)' ELSE {$refHost} END)",
        'medium' => "COALESCE(NULLIF(utm_medium,''), CASE WHEN referrer IS NULL OR referrer = '' THEN '(none)' WHEN referrer REGEXP 'google\\\\.|bing\\\\.|duckduckgo\\\\.|yahoo\\\\.' THEN 'organic' ELSE 'referral' END)",
        'campaign' => "COALESCE(NULLIF(utm_campaign,''), '(not set)')",
        'landing' => "COALESCE(NULLIF(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(source_page,'https://',''),'http://',''),'?',1),'#',1),''), '(unknown)')",
        'type' => "type",
        'product' => "COALESCE(NULLIF(product,''), '(not set)')",
        'month' => "DATE_FORMAT(created_at, '%Y-%m')",
    };
    $rows = db_all("SELECT {$expr} AS label, COUNT(*) leads,
            SUM(status IN ('qualified','quoted','negotiation','won')) qualified,
            SUM(status IN ('quoted','negotiation','won')) quoted,
            SUM(status = 'won') won, SUM(status = 'lost') lost,
            COALESCE(SUM(CASE WHEN status = 'won' THEN value END), 0) won_value,
            COALESCE(SUM(CASE WHEN status IN ('" . implode("','", LEAD_OPEN) . "') THEN value END), 0) pipeline
        FROM leads WHERE status <> 'spam' AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY
        GROUP BY label ORDER BY " . ($group === 'month' ? 'label' : 'leads DESC, won_value DESC') . ' LIMIT 100', [$from, $to]);
    foreach ($rows as &$r) {
        foreach (['leads', 'qualified', 'quoted', 'won', 'lost'] as $k) $r[$k] = (int) $r[$k];
        $r['won_value'] = (float) $r['won_value'];
        $r['pipeline'] = (float) $r['pipeline'];
        $r['win_rate'] = ($r['won'] + $r['lost']) ? round($r['won'] / ($r['won'] + $r['lost']) * 100) : null;
        if ($group === 'landing') $r['label'] = preg_replace('~^[^/]+~', '', (string) $r['label']) ?: '/';
    }
    unset($r);
    return $rows;
}

/* ================================================================== */
/* BACKUPS & AUTOMATION                                                */
/* ================================================================== */

function admin_backups(): void
{
    require_login('settings');
    $list = backup_list();
    $cron = [
        'last_run' => setting('cron_last_run'),
        'key' => cron_key(),
        'php' => PHP_BINARY,
        'tasks' => array_map(fn($t) => ($ts = (int) setting("cron_last_{$t}", 0)) ? date('Y-m-d H:i:s', $ts) : null,
            ['cleanup' => 'cleanup', 'trash' => 'trash', 'digest' => 'digest', 'backup' => 'backup', 'certificates' => 'certificates']),
    ];
    $free = @disk_free_space(STORAGE_DIR);
    admin_render('Backups & automation', 'backups', ['list' => $list, 'cron' => $cron, 'free' => $free, 'zip' => class_exists('ZipArchive')], ['wide' => true]);
}

function admin_backup_create(): void
{
    $user = require_login('settings');
    csrf_check();
    require_stepup('backup');
    try {
        $b = backup_create(!empty($_POST['uploads']));
        backup_prune(max(1, (int) setting('backup_keep', 14)));
        auth_log('backup_created', (int) $user['id'], $user['email']);
        notify_admins('backup_created', 'Manual backup created', actor_name() . " created a backup ({$b['file']}).", ['severity' => 'info', 'email' => false, 'link' => 'admin/backups', 'include_actor' => true]);
        flash('success', "Backup created: {$b['file']} — {$b['tables']} tables, {$b['rows']} rows, " . round($b['size'] / 1024) . ' KB' . ($b['uploads'] ? " + {$b['uploads']}" : '') . '.');
    } catch (Throwable $e) {
        error_log((string) $e);
        flash('error', 'Backup failed: ' . $e->getMessage());
    }
    redirect('admin/backups');
}

/** Download requires the current password (backups contain every lead and user record). */
function admin_backup_download(): void
{
    $user = require_login('settings');
    csrf_check();
    require_stepup('backup');
    $row = db_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    require_once __DIR__ . '/admin_settings.php';
    $ok = profile_password_ok($row, (string) ($_POST['password'] ?? ''));
    if ($ok !== true) { flash('error', $ok); redirect('admin/backups'); }
    $path = backup_path((string) input('file', ''));
    if (!$path) abort(404);
    auth_log('backup_downloaded', (int) $user['id'], $user['email']);
    notify_admins('backup_downloaded', 'A backup was downloaded', actor_name() . ' downloaded the backup ' . basename($path) . '. Backups contain every lead and user record.',
        ['severity' => 'critical', 'link' => 'admin/backups', 'include_actor' => true]);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . (str_ends_with($path, '.zip') ? 'application/zip' : 'application/gzip'));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

function admin_backup_delete(): void
{
    $user = require_login('settings');
    csrf_check();
    require_stepup('backup');
    $path = backup_path((string) input('file', ''));
    if ($path && @unlink($path)) {
        auth_log('backup_deleted', (int) $user['id'], $user['email']);
        notify_admins('backup_deleted', 'A backup was deleted', actor_name() . ' deleted the backup ' . basename($path) . '.', ['severity' => 'warning', 'link' => 'admin/backups', 'include_actor' => true]);
        flash('success', 'Backup deleted.');
    }
    else flash('error', 'Backup not found.');
    redirect('admin/backups');
}

function admin_cron_run(): void
{
    require_login('settings');
    csrf_check();
    $res = cron_run(!empty($_POST['force']));
    flash('success', 'Scheduled tasks ran: ' . implode(' · ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($res), $res)));
    redirect('admin/backups');
}
