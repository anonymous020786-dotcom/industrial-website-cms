<?php
/** Admin: lead / enquiry management (CRM-lite). */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

function lead_filters(): array
{
    $where = ['1=1'];
    $params = [];
    $status = (string) query('status', '');
    if ($status === 'open') $where[] = "l.status IN ('" . implode("','", LEAD_OPEN) . "')";
    elseif (isset(LEAD_STATUSES[$status])) { $where[] = 'l.status = ?'; $params[] = $status; }
    else $where[] = "l.status <> 'spam'";
    if (in_array($t = (string) query('type', ''), ['contact', 'quote', 'manual'], true)) { $where[] = 'l.type = ?'; $params[] = $t; }
    if (in_array($p = (string) query('priority', ''), ['low', 'normal', 'high'], true)) { $where[] = 'l.priority = ?'; $params[] = $p; }
    $a = (string) query('assigned', '');
    if ($a === 'me') { $where[] = 'l.assigned_to = ?'; $params[] = (int) current_user()['id']; }
    elseif ($a === 'none') $where[] = 'l.assigned_to IS NULL';
    elseif (ctype_digit($a)) { $where[] = 'l.assigned_to = ?'; $params[] = (int) $a; }
    if ($q = (string) query('q', '')) {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $where[] = '(l.name LIKE ? OR l.company LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.product LIKE ? OR l.tags LIKE ? OR l.message LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($from = (string) query('from', '')) { $where[] = 'l.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
    if ($to = (string) query('to', '')) { $where[] = 'l.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
    if (query('due')) $where[] = 'l.follow_up_at IS NOT NULL AND l.follow_up_at <= CURDATE()';
    return [implode(' AND ', $where), $params];
}

function lead_users(): array
{
    return array_column(db_all("SELECT id, name FROM users WHERE active = 1 AND role IN ('admin','sales') ORDER BY name"), 'name', 'id');
}

function admin_leads(): void
{
    require_login('leads');
    [$where, $params] = lead_filters();
    $sorts = ['newest' => 'l.created_at DESC', 'oldest' => 'l.created_at ASC', 'followup' => 'l.follow_up_at IS NULL, l.follow_up_at ASC', 'value' => 'l.value IS NULL, l.value DESC', 'name' => 'l.name ASC'];
    $sort = $sorts[(string) query('sort', 'newest')] ?? $sorts['newest'];
    $total = (int) db_val("SELECT COUNT(*) FROM leads l WHERE {$where}", $params);
    $pager = paginate($total, 25, (int) query('page', 1));
    $rows = db_all("SELECT l.*, u.name AS assignee, (SELECT COUNT(*) FROM lead_files f WHERE f.lead_id = l.id) AS files
        FROM leads l LEFT JOIN users u ON u.id = l.assigned_to WHERE {$where} ORDER BY {$sort} LIMIT {$pager['per']} OFFSET {$pager['offset']}", $params);
    $counts = array_column(db_all('SELECT status, COUNT(*) n FROM leads GROUP BY status'), 'n', 'status');
    $actions = '<a class="adm-btn adm-btn-light" href="' . e(url('admin/leads/export') . qs()) . '">' . icon('download', 'h-4 w-4') . ' Export CSV</a>'
        . '<a class="adm-btn adm-btn-primary" href="' . e(url('admin/leads/new')) . '">' . icon('plus', 'h-4 w-4') . ' Add lead</a>';
    admin_render('Leads & Enquiries', 'leads/index', compact('rows', 'pager', 'counts') + ['users' => lead_users()], ['actions' => $actions, 'wide' => true]);
}

function admin_leads_export(): void
{
    require_login('leads');
    [$where, $params] = lead_filters();
    $rows = db_all("SELECT l.*, u.name AS assignee FROM leads l LEFT JOIN users u ON u.id = l.assigned_to WHERE {$where} ORDER BY l.created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    $cols = ['id', 'created_at', 'type', 'status', 'priority', 'name', 'company', 'email', 'phone', 'product', 'quantity', 'required_by', 'part_number', 'message', 'value', 'tags', 'assignee', 'follow_up_at', 'source_page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign'];
    fputcsv($out, $cols, ',', '"', '');
    foreach ($rows as $r) {
        // Prevent CSV formula injection in spreadsheet apps
        fputcsv($out, array_map(fn($c) => is_string($r[$c]) && preg_match('~^[=+\-@\t\r]~', $r[$c]) ? "'" . $r[$c] : $r[$c], $cols), ',', '"', '');
    }
    fclose($out);
    exit;
}

function admin_leads_bulk(): void
{
    require_login('leads');
    csrf_check();
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    $action = (string) ($_POST['bulk_action'] ?? '');
    if (!$ids) { flash('error', 'Select at least one lead.'); back('admin/leads'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $uid = (int) current_user()['id'];
    if (str_starts_with($action, 'status:') && isset(LEAD_STATUSES[$s = substr($action, 7)])) {
        db_query("UPDATE leads SET status = ? WHERE id IN ({$in})", array_merge([$s], $ids));
        foreach ($ids as $id) lead_note($id, 'status', 'Status changed to ' . LEAD_STATUSES[$s][0] . ' (bulk action).', $uid);
        flash('success', count($ids) . ' lead(s) updated.');
    } elseif (str_starts_with($action, 'assign:')) {
        $to = substr($action, 7);
        db_query("UPDATE leads SET assigned_to = ? WHERE id IN ({$in})", array_merge([$to === 'none' ? null : (int) $to], $ids));
        flash('success', count($ids) . ' lead(s) reassigned.');
    } elseif ($action === 'delete' && current_user()['role'] === 'admin') {
        foreach ($ids as $id) lead_delete_files($id);
        db_query("DELETE FROM leads WHERE id IN ({$in})", $ids);
        flash('success', count($ids) . ' lead(s) deleted.');
    } else {
        flash('error', 'Choose a valid bulk action.');
    }
    back('admin/leads');
}

function lead_or_404(int $id): array
{
    $lead = db_one('SELECT l.*, u.name AS assignee FROM leads l LEFT JOIN users u ON u.id = l.assigned_to WHERE l.id = ?', [$id]);
    if (!$lead) abort(404);
    return $lead;
}

function admin_lead_show(string $id): void
{
    require_login('leads');
    $lead = lead_or_404((int) $id);
    $files = db_all('SELECT * FROM lead_files WHERE lead_id = ?', [$lead['id']]);
    $notes = db_all('SELECT n.*, u.name AS user_name FROM lead_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.lead_id = ? ORDER BY n.created_at DESC, n.id DESC', [$lead['id']]);
    $others = $lead['email'] ? db_all('SELECT id, product, status, created_at FROM leads WHERE email = ? AND id <> ? ORDER BY created_at DESC LIMIT 5', [$lead['email'], $lead['id']]) : [];
    $nav = [
        'prev' => db_val('SELECT id FROM leads WHERE created_at > ? OR (created_at = ? AND id > ?) ORDER BY created_at ASC, id ASC LIMIT 1', [$lead['created_at'], $lead['created_at'], $lead['id']]),
        'next' => db_val('SELECT id FROM leads WHERE created_at < ? OR (created_at = ? AND id < ?) ORDER BY created_at DESC, id DESC LIMIT 1', [$lead['created_at'], $lead['created_at'], $lead['id']]),
    ];
    admin_render('Lead #' . $lead['id'] . ' — ' . $lead['name'], 'leads/show', compact('lead', 'files', 'notes', 'others', 'nav') + ['users' => lead_users()], ['crumbs' => [['Leads', 'admin/leads']], 'wide' => true]);
}

function admin_lead_update(string $id): void
{
    $user = require_login('leads');
    csrf_check();
    $lead = lead_or_404((int) $id);
    $data = [
        'status' => isset(LEAD_STATUSES[$s = (string) input('status')]) ? $s : $lead['status'],
        'priority' => in_array($p = (string) input('priority'), ['low', 'normal', 'high'], true) ? $p : $lead['priority'],
        'assigned_to' => ctype_digit((string) input('assigned_to')) ? (int) input('assigned_to') : null,
        'follow_up_at' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) input('follow_up_at')) ? input('follow_up_at') : null,
        'value' => is_numeric(input('value')) ? (float) input('value') : null,
        'tags' => mb_substr(implode(', ', array_filter(array_map('trim', explode(',', (string) input('tags'))))), 0, 255) ?: null,
    ];
    foreach (['name', 'company', 'email', 'phone', 'product'] as $f) {
        if (isset($_POST[$f])) $data[$f] = mb_substr((string) input($f), 0, $f === 'phone' ? 50 : 190);
    }
    if (isset($data['name']) && $data['name'] === '') $data['name'] = $lead['name'];

    $changes = [];
    if ($data['status'] !== $lead['status']) lead_note($lead['id'], 'status', 'Status changed from ' . (LEAD_STATUSES[$lead['status']][0] ?? $lead['status']) . ' to ' . LEAD_STATUSES[$data['status']][0] . '.', (int) $user['id']);
    $labels = ['priority' => 'Priority', 'assigned_to' => 'Owner', 'follow_up_at' => 'Follow-up date', 'value' => 'Value', 'tags' => 'Tags', 'name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone', 'product' => 'Product'];
    foreach ($labels as $k => $l) {
        if (array_key_exists($k, $data) && (string) $data[$k] !== (string) $lead[$k] && !($k === 'value' && (float) $data[$k] === (float) $lead[$k])) $changes[] = $l;
    }
    if ($changes) lead_note($lead['id'], 'system', 'Updated: ' . implode(', ', $changes) . '.', (int) $user['id']);
    db_update('leads', $data, (int) $lead['id']);
    flash('success', 'Lead updated.');
    redirect('admin/leads/' . $lead['id']);
}

function admin_lead_note(string $id): void
{
    $user = require_login('leads');
    csrf_check();
    $lead = lead_or_404((int) $id);
    $body = trim((string) input('body'));
    $kind = in_array($k = (string) input('kind'), ['note', 'call', 'email'], true) ? $k : 'note';
    if ($body === '') { flash('error', 'Write something before saving the note.'); redirect('admin/leads/' . $lead['id']); }
    lead_note((int) $lead['id'], $kind, $body, (int) $user['id']);
    if ($lead['status'] === 'new' && in_array($kind, ['call', 'email'], true)) {
        db_update('leads', ['status' => 'contacted'], (int) $lead['id']);
        lead_note((int) $lead['id'], 'status', 'Status changed from New to Contacted (first contact logged).', (int) $user['id']);
    }
    flash('success', ucfirst($kind) . ' added to the timeline.');
    redirect('admin/leads/' . $lead['id'] . '#timeline');
}

function lead_delete_files(int $leadId): void
{
    foreach (db_all('SELECT stored_name FROM lead_files WHERE lead_id = ?', [$leadId]) as $f) {
        $path = STORAGE_DIR . '/leads/' . basename($f['stored_name']);
        if (is_file($path)) @unlink($path);
    }
}

function admin_lead_delete(string $id): void
{
    $user = require_login('leads');
    csrf_check();
    if ($user['role'] !== 'admin') abort(403, 'Only administrators can delete leads. Mark it as Spam or Lost instead.');
    $lead = lead_or_404((int) $id);
    lead_delete_files((int) $lead['id']);
    db_delete('leads', (int) $lead['id']);
    flash('success', 'Lead deleted.');
    redirect('admin/leads');
}

function admin_lead_file(string $id): void
{
    require_login('leads');
    $f = db_one('SELECT * FROM lead_files WHERE id = ?', [(int) $id]);
    $path = $f ? STORAGE_DIR . '/leads/' . basename($f['stored_name']) : '';
    if (!$f || !is_file($path)) abort(404);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $f['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function admin_lead_new(): void
{
    $user = require_login('leads');
    $errors = [];
    $v = [];
    if (is_post()) {
        csrf_check();
        $v = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        if (mb_strlen($v['name'] ?? '') < 2) $errors['name'] = 'Enter the contact name.';
        if (($v['email'] ?? '') !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        if (!$errors) {
            $id = db_insert('leads', [
                'type' => 'manual', 'status' => isset(LEAD_STATUSES[$v['status'] ?? '']) ? $v['status'] : 'new', 'priority' => in_array($v['priority'] ?? '', ['low', 'normal', 'high'], true) ? $v['priority'] : 'normal',
                'name' => mb_substr($v['name'], 0, 150), 'company' => mb_substr($v['company'] ?? '', 0, 190) ?: null, 'email' => $v['email'] ?: null, 'phone' => mb_substr($v['phone'] ?? '', 0, 50) ?: null,
                'product' => mb_substr($v['product'] ?? '', 0, 190) ?: null, 'message' => $v['message'] ?? null, 'value' => is_numeric($v['value'] ?? '') ? (float) $v['value'] : null,
                'source_page' => mb_substr('Manual: ' . ($v['source'] ?? 'Other'), 0, 500), 'assigned_to' => ctype_digit((string) ($v['assigned_to'] ?? '')) ? (int) $v['assigned_to'] : (int) $user['id'],
                'follow_up_at' => preg_match('~^\d{4}-\d{2}-\d{2}$~', $v['follow_up_at'] ?? '') ? $v['follow_up_at'] : null,
            ]);
            lead_note($id, 'system', 'Lead added manually (source: ' . ($v['source'] ?? 'Other') . ').', (int) $user['id']);
            flash('success', 'Lead created.');
            redirect('admin/leads/' . $id);
        }
    }
    admin_render('Add lead', 'leads/new', ['v' => $v, 'errors' => $errors, 'users' => lead_users()], ['crumbs' => [['Leads', 'admin/leads']]]);
}
