<?php
/**
 * Verifies a backup by restoring it into a temporary database (never the live one)
 * and comparing row counts of content tables. Prints JSON {ok, compared, mismatches}.
 *   php tests/lib/restore-check.php backup-YYYYmmdd-His-xxxxxx.sql.gz
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$path = backup_path(basename((string) ($argv[1] ?? ''))) ?? '';
if ($path === '') { echo json_encode(['ok' => false, 'error' => 'backup not found']); exit(1); }
$cfg = config('db');
$tables = ['posts', 'pages', 'products', 'industries', 'categories', 'users', 'media', 'redirects', 'projects', 'certificates', 'leads'];
$before = [];
foreach ($tables as $t) $before[$t] = (int) db_val("SELECT COUNT(*) FROM `{$t}`");

$tmp = preg_replace('~\W~', '', $cfg['name']) . '_restore_check';
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], (int) ($cfg['port'] ?? 3306)), $cfg['user'], $cfg['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("DROP DATABASE IF EXISTS `{$tmp}`");
$server->exec("CREATE DATABASE `{$tmp}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    db(array_merge($cfg, ['name' => $tmp]));
    $statements = backup_restore($path);
    $mismatches = [];
    foreach ($tables as $t) {
        $after = (int) db_val("SELECT COUNT(*) FROM `{$t}`");
        if ($after !== $before[$t]) $mismatches[$t] = ['live' => $before[$t], 'restored' => $after];
    }
    echo json_encode(['ok' => !$mismatches, 'statements' => $statements, 'compared' => $before, 'mismatches' => $mismatches]);
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$tmp}`");
}
