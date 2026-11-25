<?php
/**
 * Scheduled tasks. Run every 5–15 minutes from the host's cron:
 *     php /path/to/site/app/cli.php cron
 * or, where only URL cron is available: GET https://your-site/cron/<cron key> (Settings → Automation).
 * Each task remembers when it last ran, so running cron more often is harmless.
 */
declare(strict_types=1);

/** @return array<string, string> task => result */
function cron_run(bool $force = false): array
{
    @set_time_limit(300);
    $lock = STORAGE_DIR . '/cron.lock';
    $fh = fopen($lock, 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return ['lock' => 'Another cron run is in progress.'];
    $out = [];
    $tasks = [
        'cleanup' => [3600, 'cron_cleanup'],
        'trash' => [86400, 'cron_purge_trash'],
        'digest' => [0, 'cron_lead_digest'],          // time-of-day gated inside
        'backup' => [0, 'cron_backup'],               // time-of-day gated inside
        'certificates' => [86400, 'cron_certificate_alerts'],
        'update' => [0, 'cron_auto_update'],        // last: installs a newer tested release, if configured
    ];
    foreach ($tasks as $name => [$every, $fn]) {
        $last = (int) setting("cron_last_{$name}", 0);
        if (!$force && $every > 0 && time() - $last < $every) { $out[$name] = 'skipped (ran ' . time_ago(date('Y-m-d H:i:s', $last)) . ')'; continue; }
        try {
            $res = $fn($force);
            if ($res !== null) setting_set("cron_last_{$name}", time());
            $out[$name] = $res ?? 'not due';
        } catch (Throwable $e) {
            error_log("cron {$name}: " . $e);
            $out[$name] = 'ERROR: ' . $e->getMessage();
        }
    }
    setting_set('cron_last_run', now());
    flock($fh, LOCK_UN);
    fclose($fh);
    return $out;
}

/** True once per day, at/after the configured hour (site timezone). */
function cron_daily_due(string $name, int $hour, bool $force): bool
{
    if ($force) return true;
    if ((int) date('G') < $hour) return false;
    return date('Y-m-d', (int) setting("cron_last_{$name}", 0)) !== date('Y-m-d');
}

function cron_cleanup(bool $force): string
{
    $n = db_query('DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL 1 DAY')->rowCount();
    $n += db_query('DELETE FROM login_otps WHERE expires_at < NOW() - INTERVAL 1 DAY')->rowCount();
    $n += db_query('DELETE FROM events WHERE created_at < NOW() - INTERVAL 730 DAY')->rowCount();
    $n += db_query('DELETE FROM auth_events WHERE created_at < NOW() - INTERVAL 180 DAY')->rowCount();
    $n += db_query('DELETE FROM app_errors WHERE created_at < NOW() - INTERVAL 60 DAY')->rowCount();
    return "{$n} old rows removed";
}

function cron_purge_trash(bool $force): string
{
    $n = 0;
    foreach (['posts' => 'post', 'pages' => 'page'] as $table => $entity) {
        $ids = array_map('intval', array_column(db_all("SELECT id FROM `{$table}` WHERE deleted_at IS NOT NULL AND deleted_at < NOW() - INTERVAL 30 DAY"), 'id'));
        if (!$ids) continue;
        $in = implode(',', $ids);
        db_query("DELETE FROM revisions WHERE entity = '{$entity}' AND entity_id IN ({$in})");
        $n += db_query("DELETE FROM `{$table}` WHERE id IN ({$in})")->rowCount();
    }
    return "{$n} trashed item(s) deleted";
}

/**
 * Daily sales digest: overdue follow-ups, follow-ups due today, and new leads nobody has
 * contacted for 24 h. Each salesperson gets their own leads; digest recipients get everything.
 */
function cron_lead_digest(bool $force): ?string
{
    if (!setting('digest_enabled', true)) return null;
    if (!cron_daily_due('digest', (int) setting('digest_hour', 8), $force)) return null;
    $open = "('" . implode("','", LEAD_OPEN) . "')";
    $overdue = db_all("SELECT l.*, u.email AS owner_email, u.name AS owner_name FROM leads l LEFT JOIN users u ON u.id = l.assigned_to
        WHERE l.follow_up_at < CURDATE() AND l.status IN {$open} ORDER BY l.follow_up_at");
    $today = db_all("SELECT l.*, u.email AS owner_email, u.name AS owner_name FROM leads l LEFT JOIN users u ON u.id = l.assigned_to
        WHERE l.follow_up_at = CURDATE() AND l.status IN {$open} ORDER BY l.priority = 'high' DESC, l.id");
    $stale = db_all("SELECT l.*, u.email AS owner_email, u.name AS owner_name FROM leads l LEFT JOIN users u ON u.id = l.assigned_to
        WHERE l.status = 'new' AND l.created_at < NOW() - INTERVAL 24 HOUR ORDER BY l.created_at");
    if (!$overdue && !$today && !$stale) return 'nothing to report';

    $section = function (string $title, array $rows, string $dateCol) {
        if (!$rows) return '';
        $h = '<h3 style="margin:22px 0 8px;font-size:15px;color:#101D32">' . e($title) . ' (' . count($rows) . ')</h3><table cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;border-collapse:collapse">';
        foreach ($rows as $l) {
            $when = $dateCol === 'created_at' ? 'received ' . time_ago($l['created_at']) : 'due ' . date('j M', strtotime($l['follow_up_at']));
            $h .= '<tr><td style="padding:7px 0;border-bottom:1px solid #e5e7eb"><a href="' . e(abs_url('admin/leads/' . $l['id'])) . '" style="color:#174A78;font-weight:bold;text-decoration:none">#' . $l['id'] . ' ' . e($l['name']) . '</a>'
                . ($l['company'] ? ' · ' . e($l['company']) : '') . '<br><span style="color:#6b7280">' . e((string) $l['product']) . ' · ' . e($when) . ($l['owner_name'] ? ' · ' . e($l['owner_name']) : ' · unassigned') . ($l['priority'] === 'high' ? ' · <b style="color:#b91c1c">HIGH</b>' : '') . '</span></td></tr>';
        }
        return $h . '</table>';
    };
    $build = fn(array $o, array $t, array $s) => '<p style="font-size:14px;line-height:1.6">Here is today’s lead follow-up summary.</p>'
        . $section('Overdue follow-ups', $o, 'follow_up_at') . $section('Follow-ups due today', $t, 'follow_up_at') . $section('New leads not contacted in 24 hours', $s, 'created_at')
        . '<p style="margin-top:22px"><a href="' . e(abs_url('admin/leads')) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Open leads</a></p>';
    $sent = 0;
    // 1) Everything to the digest recipients
    $to = array_filter(array_map('trim', explode(',', (string) (setting('digest_emails') ?: setting('lead_notify_emails', '')))));
    $subject = 'Lead follow-ups: ' . count($overdue) . ' overdue, ' . count($today) . ' due today, ' . count($stale) . ' uncontacted';
    if ($to && send_mail(array_values($to), $subject, mail_layout('Daily lead summary', $build($overdue, $today, $stale))) === true) $sent++;
    // 2) Each owner gets only their own leads (if they are not already a digest recipient)
    $owners = [];
    foreach ([$overdue, $today, $stale] as $k => $rows) foreach ($rows as $l) if ($l['owner_email']) $owners[$l['owner_email']][$k][] = $l;
    foreach ($owners as $email => $parts) {
        if (in_array(strtolower($email), array_map('strtolower', $to), true)) continue;
        $o = $parts[0] ?? []; $t = $parts[1] ?? []; $s = $parts[2] ?? [];
        if (send_mail($email, 'Your follow-ups: ' . count($o) . ' overdue, ' . count($t) . ' due today', mail_layout('Your lead follow-ups', $build($o, $t, $s))) === true) $sent++;
    }
    return "digest emailed ({$sent} message(s); " . count($overdue) . ' overdue, ' . count($today) . ' today, ' . count($stale) . ' uncontacted)';
}

function cron_backup(bool $force): ?string
{
    if (!setting('backup_enabled', true)) return null;
    if (!cron_daily_due('backup', (int) setting('backup_hour', 2), $force)) return null;
    $b = backup_create((bool) setting('backup_uploads', false));
    $removed = backup_prune(max(1, (int) setting('backup_keep', 14)));
    return "{$b['file']} (" . round($b['size'] / 1024) . " KB, {$b['rows']} rows)" . ($b['uploads'] ? " + {$b['uploads']}" : '') . ($removed ? "; {$removed} old file(s) removed" : '');
}

/** Warn administrators 30 and 7 days before a published certificate expires (once per threshold). */
function cron_certificate_alerts(bool $force): string
{
    $sent = 0;
    $admins = array_column(db_all("SELECT email FROM users WHERE role = 'admin' AND active = 1"), 'email');
    foreach (db_all("SELECT * FROM certificates WHERE status = 'published' AND expires_at IS NOT NULL AND expires_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY") as $c) {
        $days = (int) floor((strtotime($c['expires_at']) - strtotime('today')) / 86400);
        $threshold = $days <= 7 ? 7 : 30;
        $key = "cert_alert_{$c['id']}_{$threshold}_{$c['expires_at']}";
        if (setting($key) || !$admins) continue;
        $inner = '<p style="font-size:14px;line-height:1.6">The certificate <b>' . e($c['name']) . '</b>' . ($c['number'] ? ' (no. ' . e($c['number']) . ')' : '')
            . ' expires on <b>' . e(date('j M Y', strtotime($c['expires_at']))) . "</b> — in {$days} day(s).</p>"
            . '<p style="font-size:14px;line-height:1.6">After that date it is hidden from the website automatically. Upload the renewed certificate in the admin panel to keep it visible.</p>'
            . '<p><a href="' . e(abs_url('admin/r/certificates/' . $c['id'] . '/edit')) . '" style="color:#174A78;font-weight:bold">Update certificate</a></p>';
        if (send_mail($admins, 'Certificate expiring: ' . $c['name'] . " ({$days} days)", mail_layout('Certificate expiring soon', $inner)) === true) { setting_set($key, now()); $sent++; }
    }
    return "{$sent} expiry warning(s) sent";
}

/** Cron key for URL-based cron (created on first use). */
function cron_key(): string
{
    $k = (string) setting('cron_key', '');
    if (strlen($k) < 32) { $k = bin2hex(random_bytes(24)); setting_set('cron_key', $k); }
    return $k;
}


/** Install a newer release that passed CI (see app/lib/updater.php). Off unless configured. */
function cron_auto_update(bool $force): ?string
{
    return updater_enabled() ? updater_run(false) : null;
}
