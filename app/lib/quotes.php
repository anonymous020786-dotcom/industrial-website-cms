<?php
/**
 * Quotations: numbering, totals, branded PDF, emailing the customer a private link, and recording
 * the customer's online acceptance or decline (which also moves the related lead along).
 */
declare(strict_types=1);

const QUOTE_STATUSES = [
    'draft' => ['Draft', 'bg-gray-100 text-gray-700'],
    'sent' => ['Sent', 'bg-blue-100 text-blue-800'],
    'accepted' => ['Accepted', 'bg-emerald-100 text-emerald-800'],
    'declined' => ['Declined', 'bg-red-100 text-red-700'],
    'expired' => ['Expired', 'bg-amber-100 text-amber-800'],
];
const QUOTE_CURRENCIES = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED ', 'SAR' => 'SAR '];

/** Sent quotes past their validity date count as expired (without a cron job). */
function quote_status(array $q): string
{
    if ($q['status'] === 'sent' && $q['valid_until'] && strtotime($q['valid_until'] . ' 23:59:59') < time()) return 'expired';
    return (string) $q['status'];
}

function quote_badge(array $q): string
{
    [$l, $c] = QUOTE_STATUSES[quote_status($q)] ?? ['?', 'bg-gray-100 text-gray-700'];
    return '<span class="adm-badge ' . $c . '">' . e($l) . '</span>';
}

/** "₹1,25,000.00" style is region-specific; a plain thousands format reads well everywhere. */
function money(float $amount, string $currency = 'INR', bool $pdf = false): string
{
    $sym = QUOTE_CURRENCIES[$currency] ?? ($currency . ' ');
    if ($pdf && $currency === 'INR') $sym = 'Rs. ';
    if ($pdf && in_array($currency, ['EUR', 'GBP'], true)) $sym = $currency === 'EUR' ? 'EUR ' : 'GBP ';
    return $sym . number_format($amount, 2);
}

function quote_next_number(): string
{
    $prefix = (string) setting('quote_prefix', 'Q-') . date('Y') . '-';
    $last = (string) db_val('SELECT number FROM quotes WHERE number LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $n = $last !== '' ? (int) substr($last, strlen($prefix)) + 1 : 1;
    do { $num = $prefix . str_pad((string) $n++, 4, '0', STR_PAD_LEFT); } while (db_val('SELECT id FROM quotes WHERE number = ?', [$num]));
    return $num;
}

/** Clean the posted line items and work out the totals (never trust totals from the browser). */
function quote_calculate(array $items, float $taxRate): array
{
    $clean = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $desc = trim((string) ($it['description'] ?? ''));
        if ($desc === '') continue;
        $qty = max(0, (float) ($it['qty'] ?? 1));
        $price = max(0, (float) ($it['price'] ?? 0));
        $disc = min(100, max(0, (float) ($it['discount'] ?? 0)));
        $amount = round($qty * $price * (1 - $disc / 100), 2);
        $clean[] = ['description' => mb_substr($desc, 0, 500), 'part' => mb_substr(trim((string) ($it['part'] ?? '')), 0, 120),
            'qty' => $qty, 'unit' => mb_substr(trim((string) ($it['unit'] ?? 'pcs')), 0, 20) ?: 'pcs', 'price' => $price, 'discount' => $disc, 'amount' => $amount];
    }
    $subtotal = round(array_sum(array_column($clean, 'amount')), 2);
    $tax = round($subtotal * max(0, $taxRate) / 100, 2);
    return [$clean, $subtotal, $tax, round($subtotal + $tax, 2)];
}

function quote_link(array $q): string
{
    return abs_url('q/' . $q['token']);
}

function quote_new_from_lead(?array $lead): array
{
    $items = [];
    if ($lead) {
        foreach (json_decode((string) ($lead['items'] ?? ''), true) ?: [] as $it) {
            $items[] = ['description' => (string) ($it['name'] ?? ''), 'part' => (string) ($it['part'] ?? ''), 'qty' => max(1, (int) ($it['qty'] ?? 1)), 'unit' => 'pcs', 'price' => 0, 'discount' => 0];
        }
        if (!$items && $lead['product']) $items[] = ['description' => (string) $lead['product'], 'part' => (string) ($lead['part_number'] ?? ''), 'qty' => max(1, (int) ($lead['quantity'] ?: 1)), 'unit' => 'pcs', 'price' => 0, 'discount' => 0];
    }
    return [
        'lead_id' => $lead['id'] ?? null, 'customer_name' => $lead['name'] ?? '', 'company' => $lead['company'] ?? '', 'email' => $lead['email'] ?? '', 'phone' => $lead['phone'] ?? '',
        'address' => '', 'customer_tax_id' => '', 'currency' => (string) setting('quote_currency', 'INR'),
        'items' => $items ?: [['description' => '', 'part' => '', 'qty' => 1, 'unit' => 'pcs', 'price' => 0, 'discount' => 0]],
        'tax_label' => (string) setting('quote_tax_label', 'GST'), 'tax_rate' => (float) setting('quote_tax_rate', 18),
        'valid_until' => date('Y-m-d', strtotime('+' . max(1, (int) setting('quote_validity_days', 30)) . ' days')),
        'delivery' => (string) setting('quote_delivery', ''), 'payment_terms' => (string) setting('quote_payment_terms', ''),
        'notes' => '', 'terms' => (string) setting('quote_terms', ''),
    ];
}

/* ------------------------------------------------------------------ */
/* PDF                                                                 */
/* ------------------------------------------------------------------ */

function quote_pdf(array $q): string
{
    $pdf = new Pdf();
    $navy = [0.063, 0.114, 0.196]; $orange = [0.949, 0.549, 0.157]; $grey = [0.42, 0.45, 0.5]; $ink = [0.15, 0.17, 0.21]; $white = [1, 1, 1];
    $L = 40; $R = Pdf::W - 40; $items = json_decode((string) $q['items'], true) ?: [];
    $company = company('name', 'Company');

    $header = function () use ($pdf, $q, $navy, $orange, $white, $L, $R, $company) {
        $pdf->rect(0, 0, Pdf::W, 92, $navy);
        $pdf->rect(0, 92, Pdf::W, 4, $orange);
        $pdf->text($L, 44, $company, 20, true, $white);
        $tag = company('logo_sub', '');
        if ($tag !== '') $pdf->text($L, 62, strtoupper($tag), 8.5, false, [0.75, 0.8, 0.88]);
        $pdf->text($R, 40, 'QUOTATION', 18, true, $orange, 'right');
        $pdf->text($R, 58, $q['number'], 10.5, true, $white, 'right');
        $pdf->text($R, 74, 'Date ' . date('j M Y', strtotime((string) ($q['sent_at'] ?: $q['created_at']))) . ($q['valid_until'] ? '   ·   Valid until ' . date('j M Y', strtotime((string) $q['valid_until'])) : ''), 8.5, false, [0.75, 0.8, 0.88], 'right');
    };
    $footer = function (int $page, int $pages) use ($pdf, $L, $R, $grey, $company) {
        $pdf->line($L, Pdf::H - 42, $R, Pdf::H - 42);
        $bits = array_filter([$company, company('phone'), company('email'), parse_url(abs_url(''), PHP_URL_HOST)]);
        $pdf->text($L, Pdf::H - 28, implode('   ·   ', $bits), 8, false, $grey);
        $pdf->text($R, Pdf::H - 28, "Page {$page} of {$pages}", 8, false, $grey, 'right');
    };

    $pdf->addPage();
    $header();
    $y = 126;
    // From / To
    $pdf->text($L, $y, 'FROM', 7.5, true, $grey);
    $pdf->text(310, $y, 'QUOTATION FOR', 7.5, true, $grey);
    $from = array_filter(array_merge([company('legal_name') ?: $company], preg_split('~\R~', company('address')) ?: [], [company('phone'), company('email'), company('registration') ? 'Reg./Tax No. ' . company('registration') : '']));
    $to = array_filter(array_merge([(string) $q['customer_name'], (string) $q['company']], preg_split('~\R~', (string) $q['address']) ?: [], [(string) $q['phone'], (string) $q['email'], $q['customer_tax_id'] ? 'Tax No. ' . $q['customer_tax_id'] : '']));
    $yy = $y + 14;
    foreach (array_slice(array_values($from), 0, 8) as $i => $line) { $pdf->text($L, $yy, (string) $line, 9, $i === 0, $ink); $yy += 12.5; }
    $yt = $y + 14;
    foreach (array_slice(array_values($to), 0, 8) as $i => $line) { $pdf->text(310, $yt, (string) $line, 9, $i === 0, $ink); $yt += 12.5; }
    $y = max($yy, $yt) + 16;

    // Items table
    $cols = ['#' => [$L, 'left'], 'Description' => [$L + 22, 'left'], 'Qty' => [370, 'right'], 'Unit price' => [450, 'right'], 'Disc.' => [492, 'right'], 'Amount' => [$R, 'right']];
    $tableHead = function (float $y) use ($pdf, $cols, $navy, $L, $R) {
        $pdf->rect($L - 6, $y - 12, $R - $L + 12, 20, [0.95, 0.96, 0.97]);
        foreach ($cols as $label => [$x, $align]) $pdf->text($x, $y + 1, strtoupper($label), 7.5, true, $navy, $align);
        return $y + 22;
    };
    $y = $tableHead($y);
    foreach ($items as $i => $it) {
        $desc = $pdf->wrap((string) $it['description'], 270, 9);
        $h = count($desc) * 12 + ($it['part'] ? 11 : 0) + 8;
        if ($y + $h > Pdf::H - 170) { $pdf->addPage(); $header(); $y = $tableHead(126); }
        $pdf->text($L, $y, (string) ($i + 1), 9, false, $ink);
        $ly = $y;
        foreach ($desc as $k => $d) { $pdf->text($L + 22, $ly, $d, 9, $k === 0, $ink); $ly += 12; }
        if ($it['part']) { $pdf->text($L + 22, $ly - 1, 'Part no. ' . $it['part'], 8, false, $grey); }
        $qty = rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ''), '0'), '.');
        $pdf->text(370, $y, $qty . ' ' . $it['unit'], 9, false, $ink, 'right');
        $pdf->text(450, $y, number_format((float) $it['price'], 2), 9, false, $ink, 'right');
        $pdf->text(492, $y, (float) $it['discount'] > 0 ? rtrim(rtrim(number_format((float) $it['discount'], 2), '0'), '.') . '%' : '-', 9, false, $ink, 'right');
        $pdf->text($R, $y, number_format((float) $it['amount'], 2), 9, true, $ink, 'right');
        $y += $h;
        $pdf->line($L - 6, $y - 10, $R + 6, $y - 10);
    }

    // Totals
    if ($y > Pdf::H - 260) { $pdf->addPage(); $header(); $y = 126; }
    $y += 6;
    $rows = [['Subtotal', money((float) $q['subtotal'], $q['currency'], true)]];
    if ((float) $q['tax_rate'] > 0) $rows[] = [$q['tax_label'] . ' ' . rtrim(rtrim(number_format((float) $q['tax_rate'], 2), '0'), '.') . '%', money((float) $q['tax'], $q['currency'], true)];
    foreach ($rows as [$l, $v]) { $pdf->text(430, $y, $l, 9.5, false, $grey, 'right'); $pdf->text($R, $y, $v, 9.5, false, $ink, 'right'); $y += 16; }
    $pdf->rect(300, $y - 8, $R - 300 + 6, 26, $navy);
    $pdf->text(430, $y + 9, 'TOTAL', 10, true, [1, 1, 1], 'right');
    $pdf->text($R, $y + 9, money((float) $q['total'], $q['currency'], true), 12, true, $orange, 'right');
    $y += 40;

    // Terms, notes, bank details
    $blocks = array_filter([
        'Delivery' => (string) $q['delivery'], 'Payment terms' => (string) $q['payment_terms'], 'Notes' => (string) $q['notes'],
        'Terms & conditions' => (string) $q['terms'], 'Bank details' => (string) setting('quote_bank', ''),
    ], fn($v) => trim($v) !== '');
    foreach ($blocks as $label => $text) {
        $lines = $pdf->wrap($text, $R - $L, 8.5);
        if ($y + 16 + count($lines) * 11 > Pdf::H - 60) { $pdf->addPage(); $header(); $y = 126; }
        $pdf->text($L, $y, strtoupper($label), 7.5, true, $grey);
        $y += 13;
        foreach ($lines as $line) { $pdf->text($L, $y, $line, 8.5, false, $ink); $y += 11; }
        $y += 8;
    }
    if ($q['status'] === 'accepted') {
        if ($y > Pdf::H - 100) { $pdf->addPage(); $header(); $y = 126; }
        $pdf->rect($L, $y, $R - $L, 30, [0.9, 0.97, 0.93]);
        $pdf->text($L + 12, $y + 19, 'ACCEPTED online by ' . $q['decided_name'] . ' on ' . date('j M Y, H:i', strtotime((string) $q['decided_at'])), 10, true, [0.02, 0.45, 0.3]);
    }
    $pages = $pdf->pageCount();
    for ($p = 0; $p < $pages; $p++) { $pdf->setPage($p); $footer($p + 1, $pages); }
    return $pdf->output('Quotation ' . $q['number']);
}

/* ------------------------------------------------------------------ */
/* Sending and the customer's decision                                 */
/* ------------------------------------------------------------------ */

/** Email the quote (link + PDF) and mark it sent; the lead moves to "Quoted". */
function quote_send(array $q, string $message = ''): bool|string
{
    if (!filter_var((string) $q['email'], FILTER_VALIDATE_EMAIL)) return 'Add the customer’s email address first.';
    $link = quote_link($q);
    $inner = '<p style="font-size:14px;line-height:1.6">Dear ' . e((string) $q['customer_name']) . ',</p>'
        . ($message !== '' ? '<p style="font-size:14px;line-height:1.6;white-space:pre-wrap">' . e($message) . '</p>' : '<p style="font-size:14px;line-height:1.6">Thank you for your enquiry. Please find our quotation ' . e($q['number']) . ' attached.</p>')
        . '<table cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:14px"><tr><td style="padding:4px 16px 4px 0;color:#6b7280">Quotation</td><td><b>' . e($q['number']) . '</b></td></tr>'
        . '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Total</td><td><b>' . e(money((float) $q['total'], $q['currency'])) . '</b></td></tr>'
        . ($q['valid_until'] ? '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Valid until</td><td>' . e(date('j M Y', strtotime((string) $q['valid_until']))) . '</td></tr>' : '') . '</table>'
        . '<p style="margin:22px 0"><a href="' . e($link) . '" style="background:#F28C28;color:#101D32;padding:12px 22px;text-decoration:none;font-weight:bold">View and accept the quotation</a></p>'
        . '<p style="font-size:13px;color:#6b7280">You can accept it online in one click, or reply to this email with any questions.</p>';
    $replyTo = current_user()['email'] ?? null;
    $res = send_mail((string) $q['email'], 'Quotation ' . $q['number'] . ' from ' . company('name'), mail_layout('Quotation ' . $q['number'], $inner), $replyTo,
        [['name' => 'Quotation-' . $q['number'] . '.pdf', 'type' => 'application/pdf', 'data' => quote_pdf($q)]]);
    if ($res !== true) return $res;
    db_update('quotes', ['status' => in_array($q['status'], ['accepted', 'declined'], true) ? $q['status'] : 'sent', 'sent_at' => now()], (int) $q['id']);
    if ($q['lead_id'] && ($lead = db_one('SELECT * FROM leads WHERE id = ?', [(int) $q['lead_id']]))) {
        $upd = ['value' => $q['total']];
        if (in_array($lead['status'], ['new', 'contacted', 'qualified'], true)) $upd['status'] = 'quoted';
        db_update('leads', $upd, (int) $lead['id']);
        lead_note((int) $lead['id'], 'email', 'Quotation ' . $q['number'] . ' (' . money((float) $q['total'], $q['currency']) . ') sent to ' . $q['email'] . '.', current_user()['id'] ?? null);
    }
    content_log('quote', (int) $q['id'], 'sent', $q['number'], 'to ' . $q['email']);
    return true;
}

/** Record the customer's accept / decline from the private link. */
function quote_decide(array $q, bool $accept, string $name, string $reason = ''): void
{
    db_update('quotes', ['status' => $accept ? 'accepted' : 'declined', 'decided_at' => now(), 'decided_name' => mb_substr($name, 0, 150),
        'decided_ip' => client_ip(), 'decline_reason' => $accept ? null : mb_substr($reason, 0, 2000)], (int) $q['id']);
    $what = $accept ? 'accepted' : 'declined';
    $text = $q['customer_name'] . ($q['company'] ? ' (' . $q['company'] . ')' : '') . " {$what} quotation {$q['number']} for " . money((float) $q['total'], $q['currency']) . ' online'
        . ($name !== '' ? ' — signed by ' . $name : '') . '.' . (!$accept && $reason !== '' ? ' Reason: ' . $reason : '');
    if ($q['lead_id'] && db_one('SELECT id FROM leads WHERE id = ?', [(int) $q['lead_id']])) {
        if ($accept) db_update('leads', ['status' => 'won', 'value' => $q['total']], (int) $q['lead_id']);
        lead_note((int) $q['lead_id'], 'status', $text);
    }
    // The salesperson on the lead (or every administrator) hears about it straight away
    $owner = $q['lead_id'] ? db_one('SELECT u.* FROM leads l JOIN users u ON u.id = l.assigned_to WHERE l.id = ? AND u.active = 1', [(int) $q['lead_id']]) : null;
    $opt = ['severity' => $accept ? 'info' : 'warning', 'category' => 'admin', 'link' => 'admin/quotes/' . $q['id'] . '/edit', 'email' => true];
    $title = 'Quotation ' . $q['number'] . ' ' . $what;
    if ($owner) notify_user($owner, 'quote_' . $what, $title, $text, $opt);
    else notify_admins('quote_' . $what, $title, $text, $opt);
    content_log('quote', (int) $q['id'], $what, $q['number'], 'by customer' . ($name !== '' ? ' ' . $name : ''));
}
