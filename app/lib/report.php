<?php
/**
 * Weekly owner report: the last 7 days at a glance, by email (and a short Telegram summary).
 */
declare(strict_types=1);

/** Figures for the 7 days ending $end (default: now), with the week before for comparison. */
function weekly_report_data(?int $end = null): array
{
    $end ??= time() + 1; // include events recorded this second
    $to = date('Y-m-d H:i:s', $end);
    $from = date('Y-m-d H:i:s', $end - 7 * 86400);
    $prev = date('Y-m-d H:i:s', $end - 14 * 86400);
    $w = [$from, $to];
    $n = fn(string $sql, array $p = []) => (int) db_val($sql, $p);

    $leadTypes = [];
    foreach (db_all("SELECT type, COUNT(*) n FROM leads WHERE created_at >= ? AND created_at < ? AND status <> 'spam' AND type <> 'job' GROUP BY type ORDER BY n DESC", $w) as $r) $leadTypes[$r['type']] = (int) $r['n'];
    $money = function (string $where, array $p): array {
        $out = [];
        foreach (db_all("SELECT currency, COUNT(*) n, SUM(total) v FROM quotes WHERE {$where} GROUP BY currency", $p) as $r) $out[] = ['n' => (int) $r['n'], 'value' => (float) $r['v'], 'currency' => $r['currency']];
        return $out;
    };
    $sumN = fn(array $rows) => array_sum(array_column($rows, 'n'));
    $sumV = fn(array $rows) => implode(' + ', array_map(fn($r) => money($r['value'], $r['currency']), $rows)) ?: '—';
    $sent = $money('sent_at >= ? AND sent_at < ?', $w);
    $won = $money("status = 'accepted' AND decided_at >= ? AND decided_at < ?", $w);
    $openQ = $money("status = 'sent' AND (valid_until IS NULL OR valid_until >= CURDATE())", []);

    $backups = function_exists('backup_list') ? backup_list() : [];
    $lastBackup = $backups[0]['time'] ?? null;
    $cronLast = (string) setting('cron_last_run', '');
    $top = fn(string $type) => db_all('SELECT page, COUNT(*) n FROM events WHERE type = ? AND created_at >= ? AND created_at < ? AND page <> \'\' GROUP BY page ORDER BY n DESC LIMIT 5', [$type, $from, $to]);

    return [
        'from' => $from, 'to' => $to,
        'leads' => array_sum($leadTypes), 'leads_prev' => $n("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND created_at < ? AND status <> 'spam' AND type <> 'job'", [$prev, $from]),
        'lead_types' => $leadTypes,
        'high' => $n("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND created_at < ? AND priority = 'high' AND status <> 'spam' AND type <> 'job'", $w),
        'uncontacted' => $n("SELECT COUNT(*) FROM leads WHERE status = 'new' AND type <> 'job' AND created_at < NOW() - INTERVAL 24 HOUR"),
        'overdue' => $n("SELECT COUNT(*) FROM leads WHERE follow_up_at < CURDATE() AND status IN ('" . implode("','", LEAD_OPEN) . "')"),
        'quotes_sent' => $sumN($sent), 'quotes_sent_value' => $sumV($sent),
        'quotes_won' => $sumN($won), 'quotes_won_value' => $sumV($won),
        'quotes_declined' => $n("SELECT COUNT(*) FROM quotes WHERE status = 'declined' AND decided_at >= ? AND decided_at < ?", $w),
        'quotes_open' => $sumN($openQ), 'quotes_open_value' => $sumV($openQ),
        'service_new' => $n("SELECT COUNT(*) FROM leads WHERE type = 'service' AND created_at >= ? AND created_at < ?", $w),
        'service_open' => $n("SELECT COUNT(*) FROM leads WHERE type = 'service' AND status <> 'spam' AND COALESCE(service_status, 'received') NOT IN ('resolved', 'closed')"),
        'applications' => $n("SELECT COUNT(*) FROM leads WHERE type = 'job' AND created_at >= ? AND created_at < ?", $w),
        'downloads' => $n("SELECT COUNT(*) FROM events WHERE type IN ('datasheet', 'catalogue', 'download') AND created_at >= ? AND created_at < ?", $w),
        'top_pages' => db_all("SELECT source_page AS page, COUNT(*) n FROM leads WHERE created_at >= ? AND created_at < ? AND status <> 'spam' AND type <> 'job' AND source_page <> '' GROUP BY source_page ORDER BY n DESC LIMIT 5", $w),
        'missed_parts' => $top('part_missing'),
        'missed_searches' => $top('search_empty'),
        'errors' => $n('SELECT COUNT(DISTINCT fingerprint) FROM app_errors WHERE created_at >= ? AND created_at < ? AND resolved_at IS NULL', $w),
        'pending_reviews' => $n("SELECT COUNT(*) FROM content_reviews WHERE status = 'pending'"),
        'last_backup' => $lastBackup, 'backup_ok' => $lastBackup && $lastBackup > time() - 2 * 86400,
        'cron_ok' => $cronLast !== '' && strtotime($cronLast) > time() - 3 * 3600,
    ];
}

function report_trend(int $now, int $before): string
{
    if ($before === 0) return $now > 0 ? 'new' : '—';
    $pct = (int) round(($now - $before) / $before * 100);
    return ($pct > 0 ? '▲ ' : ($pct < 0 ? '▼ ' : '')) . abs($pct) . '% vs previous week';
}

function report_path(string $p): string
{
    $path = (string) (parse_url($p, PHP_URL_PATH) ?: $p);
    return $path === '' ? '/' : $path;
}

function weekly_report_html(array $d): string
{
    $period = date('j M', strtotime($d['from'])) . ' – ' . date('j M Y', strtotime($d['to']) - 1);
    $tile = fn(string $label, string $value, string $sub = '') => '<td style="width:33%;padding:12px;border:1px solid #e5e7eb;vertical-align:top"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280">' . e($label) . '</div>'
        . '<div style="font-size:24px;font-weight:bold;color:#101D32;margin-top:4px">' . e($value) . '</div>' . ($sub !== '' ? '<div style="font-size:12px;color:#6b7280;margin-top:2px">' . e($sub) . '</div>' : '') . '</td>';
    $list = function (string $title, array $rows, callable $fmt): string {
        if (!$rows) return '';
        $h = '<h3 style="margin:22px 0 6px;font-size:15px;color:#101D32">' . e($title) . '</h3><table cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;border-collapse:collapse">';
        foreach ($rows as $r) $h .= '<tr><td style="padding:6px 0;border-bottom:1px solid #f1f5f9">' . $fmt($r) . '</td><td style="padding:6px 0;border-bottom:1px solid #f1f5f9;text-align:right;color:#6b7280">' . (int) $r['n'] . '</td></tr>';
        return $h . '</table>';
    };
    $types = implode(', ', array_map(fn($k, $v) => (LEAD_FORMS[$k] ?? ucfirst($k)) . ' ' . $v, array_keys($d['lead_types']), $d['lead_types']));
    $warn = [];
    if ($d['uncontacted']) $warn[] = $d['uncontacted'] . ' enquir' . ($d['uncontacted'] === 1 ? 'y has' : 'ies have') . ' not been contacted for more than 24 hours.';
    if ($d['overdue']) $warn[] = $d['overdue'] . ' follow-up' . ($d['overdue'] === 1 ? ' is' : 's are') . ' overdue.';
    if ($d['service_open']) $warn[] = $d['service_open'] . ' service request' . ($d['service_open'] === 1 ? ' is' : 's are') . ' still open.';
    if ($d['pending_reviews']) $warn[] = $d['pending_reviews'] . ' content change' . ($d['pending_reviews'] === 1 ? ' is' : 's are') . ' waiting for your approval.';
    if ($d['errors']) $warn[] = $d['errors'] . ' new website error' . ($d['errors'] === 1 ? '' : 's') . ' to look at.';
    if (!$d['backup_ok']) $warn[] = $d['last_backup'] ? 'The last backup is from ' . date('j M Y', $d['last_backup']) . ' — check the scheduled job.' : 'No backup found — check Backups & automation.';
    if (!$d['cron_ok']) $warn[] = 'Scheduled jobs have not run recently — check the cron job.';

    return '<p style="font-size:14px;line-height:1.6">Your website week, <b>' . e($period) . '</b>.</p>'
        . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0">'
        . '<tr>' . $tile('New enquiries', (string) $d['leads'], report_trend($d['leads'], $d['leads_prev'])) . $tile('Quotes sent', (string) $d['quotes_sent'], $d['quotes_sent_value']) . $tile('Quotes won', (string) $d['quotes_won'], $d['quotes_won_value']) . '</tr>'
        . '<tr>' . $tile('Service requests', (string) $d['service_new'], $d['service_open'] . ' open in total') . $tile('Open quotes', (string) $d['quotes_open'], $d['quotes_open_value']) . $tile('Downloads', (string) $d['downloads'], $d['applications'] ? $d['applications'] . ' job application(s)' : '') . '</tr></table>'
        . ($types !== '' ? '<p style="font-size:13px;color:#374151">Enquiries by type: ' . e($types) . ($d['high'] ? ' · <b style="color:#b91c1c">' . $d['high'] . ' high priority</b>' : '') . '</p>' : '')
        . ($warn ? '<h3 style="margin:22px 0 6px;font-size:15px;color:#b45309">Needs attention</h3><ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.7;color:#374151">' . implode('', array_map(fn($x) => '<li>' . e($x) . '</li>', $warn)) . '</ul>'
            : '<p style="font-size:13px;color:#166534">✔ Nothing needs your attention — backups, scheduled jobs and follow-ups are all on track.</p>')
        . $list('Pages bringing enquiries', $d['top_pages'], fn($r) => e(report_path((string) $r['page'])))
        . $list('Part numbers people could not find', $d['missed_parts'], fn($r) => '<span style="font-family:monospace">' . e((string) $r['page']) . '</span> — consider adding a cross-reference')
        . $list('Searches with no results', $d['missed_searches'], fn($r) => '“' . e((string) $r['page']) . '”')
        . '<p style="margin-top:24px"><a href="' . e(abs_url('admin/reports')) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Open reports</a></p>'
        . '<p style="font-size:12px;color:#9ca3af">Change the day, time and recipients in Settings → Workflow &amp; Reports.</p>';
}

function weekly_report_telegram(array $d): string
{
    $lines = ['📊 <b>Weekly report — ' . e(date('j M', strtotime($d['from']))) . ' to ' . e(date('j M', strtotime($d['to']) - 1)) . '</b>', '',
        '📥 Enquiries: <b>' . $d['leads'] . '</b> (' . e(report_trend($d['leads'], $d['leads_prev'])) . ')',
        '📄 Quotes sent: <b>' . $d['quotes_sent'] . '</b> · won: <b>' . $d['quotes_won'] . '</b>' . ($d['quotes_won'] ? ' (' . e($d['quotes_won_value']) . ')' : ''),
        '🛠 Service requests: <b>' . $d['service_new'] . '</b> · open: ' . $d['service_open']];
    $warn = array_filter([
        $d['uncontacted'] ? $d['uncontacted'] . ' uncontacted > 24 h' : '', $d['overdue'] ? $d['overdue'] . ' overdue follow-ups' : '',
        $d['pending_reviews'] ? $d['pending_reviews'] . ' approvals waiting' : '', $d['errors'] ? $d['errors'] . ' new errors' : '',
        !$d['backup_ok'] ? 'backup missing/old' : '', !$d['cron_ok'] ? 'cron not running' : '']);
    $lines[] = $warn ? "\n⚠️ " . e(implode(' · ', $warn)) : "\n✅ All on track";
    return implode("\n", $lines);
}

/** @return array{emails:int, telegram:int} */
function weekly_report_send(?array $d = null): array
{
    $d ??= weekly_report_data();
    $to = array_values(array_filter(array_map('trim', explode(',', (string) setting('report_recipients', ''))), fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    $admins = db_all("SELECT * FROM users WHERE role = 'admin' AND active = 1");
    if (!$to) $to = array_values(array_filter(array_column($admins, 'email'), fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    $subject = 'Weekly report: ' . $d['leads'] . ' enquiries, ' . $d['quotes_sent'] . ' quotes sent, ' . $d['quotes_won'] . ' won';
    $emails = $to && send_mail($to, $subject, mail_layout('Weekly report', weekly_report_html($d))) === true ? count($to) : 0;
    $tg = 0;
    if (setting('report_telegram', true) && telegram_enabled()) {
        foreach ($admins as $a) if (telegram_linked($a) && telegram_send((string) $a['telegram_chat_id'], weekly_report_telegram($d), telegram_can_link() ? [[['text' => 'Open reports', 'url' => abs_url('admin/reports')]]] : []) !== null) $tg++;
    }
    return ['emails' => $emails, 'telegram' => $tg];
}

/** Cron: once a week on the chosen day, at/after the chosen hour. */
function cron_weekly_report(bool $force): ?string
{
    if (!setting('report_weekly', true)) return null;
    if (!$force) {
        if ((int) date('N') !== (int) setting('report_day', 1) || (int) date('G') < (int) setting('report_hour', 8)) return null;
        if (date('o-W', (int) setting('cron_last_weekly', 0)) === date('o-W')) return null;
    }
    $r = weekly_report_send();
    return "weekly report sent ({$r['emails']} email recipient(s), {$r['telegram']} Telegram)";
}
