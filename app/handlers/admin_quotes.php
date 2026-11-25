<?php
/** Admin: quotations — list, create from a lead, edit, PDF, email to the customer. */
declare(strict_types=1);
require_once __DIR__ . '/admin.php';

function quote_or_404(int $id): array
{
    $q = db_one('SELECT * FROM quotes WHERE id = ?', [$id]);
    if (!$q) abort(404);
    return $q;
}

function admin_quotes(): void
{
    require_login('leads');
    $status = (string) query('status', '');
    $s = trim((string) query('q', ''));
    $where = []; $params = [];
    if ($status === 'expired') { $where[] = "q.status = 'sent' AND q.valid_until < CURDATE()"; }
    elseif ($status === 'sent') { $where[] = "q.status = 'sent' AND (q.valid_until IS NULL OR q.valid_until >= CURDATE())"; }
    elseif (isset(QUOTE_STATUSES[$status])) { $where[] = 'q.status = ?'; $params[] = $status; }
    if ($s !== '') { $where[] = '(q.number LIKE ? OR q.customer_name LIKE ? OR q.company LIKE ? OR q.email LIKE ?)'; array_push($params, "%$s%", "%$s%", "%$s%", "%$s%"); }
    $rows = db_all('SELECT q.*, u.name AS author FROM quotes q LEFT JOIN users u ON u.id = q.created_by' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY q.id DESC LIMIT 300', $params);
    $stats = db_one("SELECT COUNT(*) AS n,
        SUM(status = 'sent' AND (valid_until IS NULL OR valid_until >= CURDATE())) AS open_n, SUM(CASE WHEN status = 'sent' AND (valid_until IS NULL OR valid_until >= CURDATE()) THEN total ELSE 0 END) AS open_v,
        SUM(status = 'accepted') AS won_n, SUM(CASE WHEN status = 'accepted' THEN total ELSE 0 END) AS won_v, SUM(status = 'declined') AS lost_n
        FROM quotes WHERE created_at >= NOW() - INTERVAL 90 DAY") ?: [];
    $actions = '<a class="adm-btn adm-btn-primary" href="' . e(url('admin/quotes/new')) . '">' . icon('plus', 'h-4 w-4') . ' New quote</a>';
    admin_render('Quotes', 'quotes/index', compact('rows', 'status', 's', 'stats'), ['actions' => $actions]);
}

function admin_quote_edit(?string $id = null): void
{
    $user = require_login('leads');
    $q = $id ? quote_or_404((int) $id) : null;
    $lead = !$q && ctype_digit((string) query('lead', '')) ? db_one('SELECT * FROM leads WHERE id = ?', [(int) query('lead')]) : null;
    $locked = $q && in_array($q['status'], ['accepted', 'declined'], true);
    $values = $q ? array_merge($q, ['items' => json_decode((string) $q['items'], true) ?: []]) : quote_new_from_lead($lead);
    $errors = [];

    if (is_post()) {
        csrf_check();
        if ($locked) { flash('error', 'This quotation was ' . $q['status'] . ' by the customer and can no longer be changed. Duplicate it to make a new version.'); redirect('admin/quotes/' . $q['id'] . '/edit'); }
        $in = fn($k, $max = 190) => mb_substr(trim((string) input($k, '')), 0, $max);
        $v = [
            'customer_name' => $in('customer_name', 150), 'company' => $in('company') ?: null, 'email' => strtolower($in('email')) ?: null, 'phone' => $in('phone', 50) ?: null,
            'address' => $in('address', 1000) ?: null, 'customer_tax_id' => $in('customer_tax_id', 60) ?: null,
            'currency' => isset(QUOTE_CURRENCIES[$c = (string) input('currency')]) ? $c : 'INR',
            'tax_label' => $in('tax_label', 30) ?: 'Tax', 'tax_rate' => min(100, max(0, (float) input('tax_rate', 0))),
            'valid_until' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) input('valid_until')) ? (string) input('valid_until') : null,
            'delivery' => $in('delivery', 255) ?: null, 'payment_terms' => $in('payment_terms', 255) ?: null,
            'notes' => $in('notes', 4000) ?: null, 'terms' => $in('terms', 8000) ?: null,
        ];
        [$items, $v['subtotal'], $v['tax'], $v['total']] = quote_calculate((array) ($_POST['items'] ?? []), (float) $v['tax_rate']);
        $v['items'] = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($v['customer_name'] === '') $errors['customer_name'] = 'Enter the customer’s name.';
        if ($v['email'] !== null && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (!$items) $errors['items'] = 'Add at least one line item with a description.';
        if (!$errors) {
            if ($q) {
                db_update('quotes', $v, (int) $q['id']);
                $qid = (int) $q['id'];
                content_log('quote', $qid, 'updated', $q['number'], 'Total ' . money((float) $v['total'], $v['currency']));
            } else {
                $v += ['number' => quote_next_number(), 'token' => bin2hex(random_bytes(20)), 'status' => 'draft', 'created_by' => (int) $user['id'],
                    'lead_id' => ctype_digit((string) input('lead_id', '')) ? (int) input('lead_id') : null];
                $qid = db_insert('quotes', $v);
                content_log('quote', $qid, 'created', $v['number'], 'Total ' . money((float) $v['total'], $v['currency']));
                if ($v['lead_id']) lead_note((int) $v['lead_id'], 'note', 'Quotation ' . $v['number'] . ' drafted (' . money((float) $v['total'], $v['currency']) . ').', (int) $user['id']);
            }
            flash('success', 'Quotation saved.' . (input('then') === 'send' ? '' : ''));
            if (input('then') === 'send') {
                $res = quote_send(quote_or_404($qid), (string) input('message', ''));
                flash($res === true ? 'success' : 'error', $res === true ? 'Quotation emailed to the customer with the PDF attached.' : 'Saved, but not sent: ' . $res);
            }
            redirect('admin/quotes/' . $qid . '/edit');
        }
        $values = array_merge($values, $v, ['items' => $items ?: (array) ($_POST['items'] ?? [])]);
        flash('error', 'Please fix the errors below.');
    }
    $title = $q ? 'Quotation ' . $q['number'] : 'New quotation';
    $actions = $q ? '<a class="adm-btn adm-btn-light" href="' . e(url('admin/quotes/' . $q['id'] . '/pdf')) . '" target="_blank">' . icon('file-down', 'h-4 w-4') . ' PDF</a>'
        . '<a class="adm-btn adm-btn-light" href="' . e(quote_link($q)) . '" target="_blank">' . icon('eye', 'h-4 w-4') . ' Customer view</a>' : '';
    admin_render($title, 'quotes/edit', compact('q', 'values', 'errors', 'lead', 'locked'), ['actions' => $actions, 'crumbs' => [['Quotes', 'admin/quotes']], 'wide' => true]);
}

function admin_quote_pdf(string $id): void
{
    require_login('leads');
    $q = quote_or_404((int) $id);
    $pdf = quote_pdf($q);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Quotation-' . $q['number'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    echo $pdf;
    exit;
}

function admin_quote_send(string $id): void
{
    require_login('leads');
    csrf_check();
    $q = quote_or_404((int) $id);
    $res = quote_send($q, (string) input('message', ''));
    flash($res === true ? 'success' : 'error', $res === true ? 'Quotation emailed to ' . $q['email'] . ' with the PDF attached.' : (string) $res);
    redirect('admin/quotes/' . $q['id'] . '/edit');
}

function admin_quote_duplicate(string $id): void
{
    $user = require_login('leads');
    csrf_check();
    $q = quote_or_404((int) $id);
    $copy = array_intersect_key($q, array_flip(['lead_id', 'customer_name', 'company', 'email', 'phone', 'address', 'customer_tax_id', 'currency', 'items', 'tax_label', 'tax_rate', 'subtotal', 'tax', 'total', 'delivery', 'payment_terms', 'notes', 'terms']));
    $copy += ['number' => quote_next_number(), 'token' => bin2hex(random_bytes(20)), 'status' => 'draft', 'created_by' => (int) $user['id'],
        'valid_until' => date('Y-m-d', strtotime('+' . max(1, (int) setting('quote_validity_days', 30)) . ' days'))];
    $nid = db_insert('quotes', $copy);
    content_log('quote', $nid, 'created', $copy['number'], 'Copy of ' . $q['number']);
    flash('success', 'New draft ' . $copy['number'] . ' created from ' . $q['number'] . '.');
    redirect('admin/quotes/' . $nid . '/edit');
}

function admin_quote_delete(string $id): void
{
    $user = require_login('leads');
    csrf_check();
    $q = quote_or_404((int) $id);
    if ($q['status'] !== 'draft' && $user['role'] !== 'admin') { flash('error', 'Only administrators can delete a quotation that was sent to a customer.'); redirect('admin/quotes/' . $q['id'] . '/edit'); }
    db_delete('quotes', (int) $q['id']);
    content_log('quote', (int) $q['id'], 'deleted', $q['number']);
    flash('success', 'Quotation ' . $q['number'] . ' deleted. Its customer link no longer works.');
    redirect('admin/quotes');
}
