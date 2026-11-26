<?php
/**
 * Appointment booking: site inspections, factory visits, video calls.
 * Free slots come from weekly working hours minus existing bookings, holidays and notice time.
 */
declare(strict_types=1);

const APPOINTMENT_STATUSES = ['pending' => ['Waiting for confirmation', 'bg-amber-100 text-amber-800'], 'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-800'],
    'completed' => ['Completed', 'bg-blue-100 text-blue-800'], 'no_show' => ['No-show', 'bg-gray-200 text-gray-700'], 'cancelled' => ['Cancelled', 'bg-red-100 text-red-700']];
const APPOINTMENT_MODES = ['onsite' => ['At your site', 'map-pin'], 'factory' => ['At our factory', 'factory'], 'video' => ['Video call', 'video'], 'phone' => ['Phone call', 'phone']];
const BOOKING_DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function booking_enabled(): bool
{
    return (bool) setting('booking_enabled', true);
}

function booking_types(): array
{
    try { return db_all("SELECT * FROM appointment_types WHERE status = 'published' ORDER BY sort_order, name"); } catch (Throwable) { return []; }
}

/** Working hours for a weekday (1 = Monday) as [[startMinute, endMinute], ...] from "09:00-13:00, 14:00-17:30". */
function booking_hours(int $dow): array
{
    $default = $dow <= 5 ? '09:00-13:00, 14:00-17:00' : ($dow === 6 ? '09:00-13:00' : '');
    $raw = (string) setting('booking_hours_' . $dow, $default);
    $out = [];
    foreach (preg_split('~[,;]~', $raw) ?: [] as $part) {
        if (preg_match('~^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$~', $part, $m)) {
            $a = (int) $m[1] * 60 + (int) $m[2];
            $b = (int) $m[3] * 60 + (int) $m[4];
            if ($b > $a && $b <= 1440) $out[] = [$a, $b];
        }
    }
    return $out;
}

/** Days off (Y-m-d), from Settings → Bookings (one date per line, or a range "2026-12-24 to 2026-12-31"). */
function booking_holidays(): array
{
    $days = [];
    foreach (preg_split('~\R~', (string) setting('booking_holidays', '')) ?: [] as $line) {
        if (preg_match('~(\d{4}-\d{2}-\d{2})(?:\s*(?:to|-|–)\s*(\d{4}-\d{2}-\d{2}))?~', $line, $m)) {
            $a = strtotime($m[1]);
            $b = isset($m[2]) && $m[2] !== '' ? strtotime($m[2]) : $a;
            for ($t = $a; $t && $t <= $b && count($days) < 400; $t += 86400) $days[date('Y-m-d', $t)] = true;
        }
    }
    return $days;
}

/** Free start times (unix timestamps) on a date for an appointment type. */
function booking_slots(array $type, string $date, ?int $ignoreId = null): array
{
    $day = strtotime($date . ' 00:00');
    if (!$day || date('Y-m-d', $day) !== $date || isset(booking_holidays()[$date])) return [];
    $minStart = time() + max(0, (int) setting('booking_notice_hours', 24)) * 3600;
    if ($day > strtotime('today') + max(1, (int) setting('booking_days_ahead', 30)) * 86400) return [];
    $len = max(15, (int) $type['duration_min']);
    $buffer = max(0, (int) $type['buffer_min']);
    $step = max(15, (int) setting('booking_interval', 30));
    $capacity = max(1, (int) setting('booking_capacity', 1));
    $taken = db_all("SELECT a.starts_at, a.ends_at, COALESCE(t.buffer_min, 0) buffer FROM appointments a LEFT JOIN appointment_types t ON t.id = a.type_id
        WHERE a.status IN ('pending', 'confirmed') AND a.starts_at < ? AND a.ends_at > ?" . ($ignoreId ? ' AND a.id <> ' . (int) $ignoreId : ''),
        [date('Y-m-d H:i:s', $day + 86400), date('Y-m-d H:i:s', $day)]);
    $busy = array_map(fn($r) => [strtotime($r['starts_at']) - (int) $r['buffer'] * 60, strtotime($r['ends_at']) + (int) $r['buffer'] * 60], $taken);
    $slots = [];
    foreach (booking_hours((int) date('N', $day)) as [$a, $b]) {
        for ($m = $a; $m + $len <= $b; $m += $step) {
            $s = $day + $m * 60;
            $e = $s + $len * 60;
            if ($s < $minStart) continue;
            $overlap = 0;
            foreach ($busy as [$bs, $be]) if ($s - $buffer * 60 < $be && $e + $buffer * 60 > $bs) $overlap++;
            if ($overlap < $capacity) $slots[] = $s;
        }
    }
    return $slots;
}

/** The next days (Y-m-d) that have at least one free slot. */
function booking_days(array $type, int $limit = 21): array
{
    $out = [];
    $ahead = max(1, (int) setting('booking_days_ahead', 30));
    for ($i = 0; $i <= $ahead && count($out) < $limit; $i++) {
        $d = date('Y-m-d', strtotime("+{$i} days"));
        if ($n = count(booking_slots($type, $d))) $out[$d] = $n;
    }
    return $out;
}

function appointment_ref(array $a): string
{
    return 'BK-' . str_pad((string) $a['id'], 5, '0', STR_PAD_LEFT);
}

function appointment_manage_url(array $a): string
{
    return abs_url('book/manage/' . $a['token']);
}

/** iCalendar invite (RFC 5545). $method REQUEST for new/changed, CANCEL for cancelled. */
function appointment_ics(array $a, array $type, string $method = 'REQUEST'): string
{
    $esc = fn(string $s) => addcslashes(str_replace(["\r\n", "\n"], '\n', $s), ',;\\');
    $utc = fn(string $dt) => gmdate('Ymd\THis\Z', strtotime($dt));
    $from = (string) setting('mail_from_email', company('email'));
    $loc = $a['mode'] === 'onsite' ? (string) $a['location'] : ($a['mode'] === 'factory' ? str_replace("\n", ', ', company('address')) : (string) ($type['location_note'] ?: APPOINTMENT_MODES[$a['mode']][0]));
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc(company('name')) . '//Bookings//EN', 'CALSCALE:GREGORIAN', 'METHOD:' . $method, 'BEGIN:VEVENT',
        'UID:appointment-' . $a['id'] . '@' . (parse_url(abs_url(''), PHP_URL_HOST) ?: 'localhost'), 'SEQUENCE:' . (int) ($a['sequence'] ?? 0), 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $utc((string) $a['starts_at']), 'DTEND:' . $utc((string) $a['ends_at']),
        'SUMMARY:' . $esc($type['name'] . ' — ' . company('name')), 'LOCATION:' . $esc($loc),
        'DESCRIPTION:' . $esc(appointment_ref($a) . "\nManage or cancel: " . appointment_manage_url($a) . ($a['notes'] ? "\n\n" . $a['notes'] : '')),
        'ORGANIZER;CN=' . $esc(company('name')) . ':mailto:' . $from, 'ATTENDEE;CN=' . $esc((string) $a['name']) . ';RSVP=FALSE:mailto:' . $a['email'],
        'STATUS:' . ($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT', 'END:VCALENDAR'];
    // Fold lines longer than 75 octets
    return implode("\r\n", array_map(fn($l) => rtrim(chunk_split($l, 74, "\r\n "), "\r\n "), $lines)) . "\r\n";
}

function appointment_when(array $a): string
{
    return date('l j F Y, H:i', strtotime((string) $a['starts_at'])) . '–' . date('H:i', strtotime((string) $a['ends_at']));
}

/** Email the customer about their booking (new, confirmed, changed, cancelled, reminder). */
function appointment_email(array $a, string $kind): bool
{
    $type = db_one('SELECT * FROM appointment_types WHERE id = ?', [(int) $a['type_id']]) ?: ['name' => 'Appointment', 'location_note' => ''];
    [$subject, $intro] = match ($kind) {
        'booked' => [($a['status'] === 'confirmed' ? 'Confirmed: ' : 'Request received: ') . $type['name'], $a['status'] === 'confirmed' ? 'Your appointment is confirmed.' : 'Thank you — we received your request and will confirm it shortly.'],
        'confirmed' => ['Confirmed: ' . $type['name'], 'Good news — your appointment is confirmed.'],
        'changed' => ['New time: ' . $type['name'], 'Your appointment has moved to a new time.'],
        'cancelled' => ['Cancelled: ' . $type['name'], 'Your appointment has been cancelled.' . ($a['cancel_reason'] ? ' Reason: ' . $a['cancel_reason'] : '')],
        'reminder' => ['Reminder: ' . $type['name'] . ' tomorrow', 'A reminder of your appointment with us.'],
        default => [$type['name'], ''],
    };
    $mode = APPOINTMENT_MODES[$a['mode']][0] ?? '';
    $html = '<p style="font-size:14px;line-height:1.6">Hello ' . e((string) $a['name']) . ',</p><p style="font-size:14px;line-height:1.6">' . e($intro) . '</p>'
        . '<table cellpadding="0" cellspacing="0" style="margin:12px 0;font-size:14px">'
        . '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">What</td><td><b>' . e((string) $type['name']) . '</b> (' . e($mode) . ')</td></tr>'
        . '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">When</td><td><b>' . e(appointment_when($a)) . '</b> (' . e(date_default_timezone_get()) . ')</td></tr>'
        . ($a['mode'] === 'onsite' && $a['location'] ? '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Where</td><td>' . e((string) $a['location']) . '</td></tr>' : '')
        . ($a['mode'] === 'factory' ? '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Where</td><td>' . nl2br(e(company('address'))) . '</td></tr>' : '')
        . ($type['location_note'] ? '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Details</td><td>' . e((string) $type['location_note']) . '</td></tr>' : '')
        . '<tr><td style="padding:4px 16px 4px 0;color:#6b7280">Reference</td><td>' . e(appointment_ref($a)) . '</td></tr></table>'
        . ($kind !== 'cancelled' ? '<p style="margin:20px 0"><a href="' . e(appointment_manage_url($a)) . '" style="background:#F28C28;color:#101D32;padding:10px 18px;text-decoration:none;font-weight:bold">Reschedule or cancel</a></p>' : '');
    $ics = in_array($kind, ['booked', 'confirmed', 'changed', 'cancelled'], true) && ($kind !== 'booked' || $a['status'] === 'confirmed')
        ? [['name' => 'invite.ics', 'type' => 'text/calendar; charset=utf-8; method=' . ($kind === 'cancelled' ? 'CANCEL' : 'REQUEST'), 'data' => appointment_ics($a, $type, $kind === 'cancelled' ? 'CANCEL' : 'REQUEST')]] : [];
    return send_mail((string) $a['email'], $subject . ' — ' . date('j M, H:i', strtotime((string) $a['starts_at'])), mail_layout($subject, $html), (string) (setting('booking_email') ?: company('email')) ?: null, $ics) === true;
}

/** Tell the team (in-app, email by preference, Telegram). */
function appointment_notify_team(array $a, string $what): void
{
    $type = (string) db_val('SELECT name FROM appointment_types WHERE id = ?', [(int) $a['type_id']]);
    foreach (db_all("SELECT * FROM users WHERE active = 1 AND role IN ('" . implode("','", PERMISSIONS['leads'] ?? ['admin']) . "')") as $u) {
        notify_user($u, 'appointment_' . $what, ucfirst($what) . ': ' . $type . ' ' . date('D j M H:i', strtotime((string) $a['starts_at'])),
            $a['name'] . ($a['company'] ? ' (' . $a['company'] . ')' : '') . ' — ' . appointment_ref($a) . ($a['mode'] === 'onsite' && $a['location'] ? ' · ' . $a['location'] : ''),
            ['link' => 'admin/appointments/' . $a['id'], 'category' => 'admin', 'email' => true]);
    }
}

/** Cron: reminders the day before. */
function cron_booking_reminders(bool $force): ?string
{
    $rows = db_all("SELECT * FROM appointments WHERE status = 'confirmed' AND reminded_at IS NULL AND starts_at BETWEEN NOW() + INTERVAL 2 HOUR AND NOW() + INTERVAL 26 HOUR");
    if (!$rows) return null;
    $n = 0;
    foreach ($rows as $a) {
        if (appointment_email($a, 'reminder')) $n++;
        db_update('appointments', ['reminded_at' => now()], (int) $a['id']);
    }
    return "booking reminders: {$n} sent";
}
