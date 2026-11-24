<?php
/**
 * Command-line tools.
 *   php app/cli.php install --db-name=industrial_cms --db-user=root --db-pass= --admin-email=you@x.com --admin-pass=Secret12345 [--admin-name=Admin] [--site-url=http://localhost:8000]
 *   php app/cli.php import-posts [--refresh]   Import new drafts from database/seed/posts.json
 *                                              (--refresh also updates posts that are still drafts)
 *   php app/cli.php migrate                    Create any new tables after an upgrade
 *   php app/cli.php otp --on|--off             Turn email one-time-passcode login on/off (lock-out recovery)
 *   php app/cli.php maintenance --on|--off     Maintenance mode [--until="+2 hours"] [--message="…"]; no flag = status
 *   php app/cli.php stock-ids                  List Unsplash photo ids used in the database (for npm run images)
 *   php app/cli.php cron [--force]             Run scheduled tasks (add to the server's cron every 5-15 min)
 *   php app/cli.php backup [--uploads]         Create a backup now
 *   php app/cli.php restore <file> --yes       Restore a .sql.gz backup (OVERWRITES the database)
 *   php app/cli.php create-admin --email=… --pass=… [--name=…]
 *   php app/cli.php reset-password --email=… --pass=…
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/seed.php';

$cmd = $argv[1] ?? 'help';
$opt = [];
foreach (array_slice($argv, 2) as $a) if (preg_match('~^--([a-z-]+)=(.*)$~', $a, $m)) $opt[$m[1]] = $m[2];
$need = function (string $k) use ($opt): string { if (!isset($opt[$k]) || $opt[$k] === '') { fwrite(STDERR, "Missing --{$k}\n"); exit(1); } return $opt[$k]; };

switch ($cmd) {
    case 'install':
        if (is_installed()) { echo "Already installed (delete app/storage/installed.lock to reinstall).\n"; exit(1); }
        $db = ['host' => $opt['db-host'] ?? '127.0.0.1', 'port' => (int) ($opt['db-port'] ?? 3306), 'name' => $need('db-name'), 'user' => $need('db-user'), 'pass' => $opt['db-pass'] ?? ''];
        $pass = $need('admin-pass');
        if ($p = password_problems($pass)) { fwrite(STDERR, $p . "\n"); exit(1); }
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (!preg_match('~^\w+$~', $db['name'])) exit("Invalid database name\n");
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        db($db);
        write_config($db, 'auto', (bool) ($opt['debug'] ?? false));
        run_migrations();
        $id = create_admin($opt['admin-name'] ?? 'Administrator', $need('admin-email'), $pass);
        foreach (seed_all(rtrim($opt['site-url'] ?? '', '/')) as $line) echo "  • {$line}\n";
        db_query('UPDATE posts SET author_id = ? WHERE author_id IS NULL', [$id]);
        setting_set('lead_notify_emails', strtolower($opt['admin-email']));
        finish_install();
        echo "Installed. Sign in at /admin with {$opt['admin-email']}\n";
        break;
    case 'cron':
        foreach (cron_run(in_array('--force', $argv, true)) as $task => $result) echo str_pad($task, 14) . $result . PHP_EOL;
        break;
    case 'backup':
        $b = backup_create(in_array('--uploads', $argv, true));
        backup_prune(max(1, (int) setting('backup_keep', 14)));
        echo "Created {$b['file']} ({$b['tables']} tables, {$b['rows']} rows, " . round($b['size'] / 1024) . ' KB)' . ($b['uploads'] ? " + {$b['uploads']}" : '') . PHP_EOL;
        break;
    case 'restore':
        $file = $argv[2] ?? '';
        $path = is_file($file) ? $file : (backup_path(basename($file)) ?? '');
        if ($path === '' || !str_ends_with($path, '.sql.gz')) { fwrite(STDERR, "Usage: php app/cli.php restore <backup-….sql.gz> --yes\n"); exit(1); }
        if (!in_array('--yes', $argv, true)) { fwrite(STDERR, "This REPLACES every table in the database with the backup. Re-run with --yes to continue.\n"); exit(1); }
        $n = backup_restore($path);
        echo "Restored {$n} statements from " . basename($path) . PHP_EOL;
        break;
    case 'stock-ids':
        $ids = [];
        $cols = db_all("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND DATA_TYPE IN ('varchar','text','mediumtext','longtext','json') AND TABLE_NAME NOT IN ('leads','lead_notes','lead_files','events','auth_events','login_otps','rate_limits')");
        foreach ($cols as $col) {
            foreach (db_all("SELECT `{$col['c']}` v FROM `{$col['t']}` WHERE `{$col['c']}` LIKE '%images.unsplash.com%'") as $r) {
                preg_match_all('~images\.unsplash\.com/(photo-[\w-]+)~', (string) $r['v'], $m);
                foreach ($m[1] as $id) $ids[$id] = true;
            }
        }
        echo implode(PHP_EOL, array_keys($ids)), PHP_EOL;
        break;
    case 'migrate':
        $changes = run_migrations(); // safe to re-run: new tables + missing columns
        echo $changes ? 'Applied: ' . implode(', ', $changes) . PHP_EOL : 'Database schema is up to date.' . PHP_EOL;
        break;
    case 'otp':
        if (in_array('--off', $argv, true)) { setting_set('admin_otp_enabled', false); echo "Email OTP login disabled.\n"; }
        elseif (in_array('--on', $argv, true)) { setting_set('admin_otp_enabled', true); echo "Email OTP login enabled — make sure email delivery works.\n"; }
        else echo 'Email OTP login is ' . (setting('admin_otp_enabled') ? 'ON' : 'OFF') . ". Use --on or --off.\n";
        break;
    case 'maintenance':
        $was = maintenance_active();
        if (in_array('--on', $argv, true)) {
            $until = null;
            if (!empty($opt['until'])) {
                $ts = strtotime($opt['until']);
                if (!$ts || $ts <= time()) { fwrite(STDERR, "--until must be a future time, e.g. --until=\"+2 hours\" or --until=\"2026-12-01 14:00\"
"); exit(1); }
                $until = date('Y-m-d H:i:s', $ts);
            }
            if (isset($opt['message'])) setting_set('maintenance_message', $opt['message']);
            maintenance_switch(true, $until);
        } elseif (in_array('--off', $argv, true)) {
            maintenance_switch(false);
        }
        $s = maintenance_state(true);
        if ($s['active'] !== $was) maintenance_notify($s['active'], 'A server command');
        echo 'Maintenance mode is ' . ($s['active'] ? 'ON' . ($s['source'] === 'scheduled' ? ' (scheduled window)' : '') . ($s['until'] ? ', expected back ' . maintenance_time($s['until']) : '') : 'OFF' . ($s['starts'] ? ' (scheduled to start ' . maintenance_time($s['starts']) . ')' : '')) . ".
";
        if (!in_array('--on', $argv, true) && !in_array('--off', $argv, true)) echo "Use --on [--until=\"+2 hours\"] [--message=\"…\"] or --off.
";
        break;
    case 'import-posts':
        echo seed_posts(null, in_array('--refresh', $argv, true)) . " imported.\n";
        break;
    case 'create-admin':
    case 'reset-password':
        $pass = $need('pass');
        if ($p = password_problems($pass)) { fwrite(STDERR, $p . "\n"); exit(1); }
        create_admin($opt['name'] ?? 'Administrator', $need('email'), $pass);
        echo "Admin account ready for {$opt['email']}\n";
        break;
    default:
        echo file_get_contents(__FILE__, false, null, 0, 700);
}
