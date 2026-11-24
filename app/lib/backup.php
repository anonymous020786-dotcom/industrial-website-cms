<?php
/**
 * Database (+ optional uploads) backups — pure PHP, no mysqldump needed (works on shared hosting).
 * Files: app/storage/backups/backup-YYYYmmdd-His-<rand>.sql.gz  (+ matching -uploads.zip)
 * Restore: php app/cli.php restore <file.sql.gz> --yes
 */
declare(strict_types=1);

const BACKUP_DIR = STORAGE_DIR . '/backups';
const BACKUP_DATA_SKIP = ['rate_limits', 'login_otps']; // transient: structure only

/** @return array{file:string, size:int, tables:int, rows:int, uploads:?string} */
function backup_create(bool $withUploads = false): array
{
    @mkdir(BACKUP_DIR, 0775, true);
    if (!is_file(BACKUP_DIR . '/.htaccess')) file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\n");
    @set_time_limit(600);
    $stamp = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $file = BACKUP_DIR . "/backup-{$stamp}.sql.gz";
    $gz = gzopen($file . '.part', 'wb6');
    if (!$gz) throw new RuntimeException('Cannot write to app/storage/backups.');
    $pdo = db();
    $w = fn(string $s) => gzwrite($gz, $s);
    $w("-- " . company('name', 'Site') . " database backup\n-- Created " . date('c') . " · schema v" . (int) setting('schema_version', 0) . "\n");
    $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    $tables = array_column(db_all('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\''), null);
    $tableNames = array_map(fn($r) => array_values($r)[0], $tables);
    $rowsTotal = 0;
    foreach ($tableNames as $t) {
        $create = db_one("SHOW CREATE TABLE `{$t}`")['Create Table'] ?? '';
        $w("DROP TABLE IF EXISTS `{$t}`;\n{$create};\n");
        if (in_array($t, BACKUP_DATA_SKIP, true)) { $w("\n"); continue; }
        $offset = 0;
        while (true) {
            $rows = $pdo->query("SELECT * FROM `{$t}` LIMIT 500 OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) break;
            $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
            $vals = [];
            foreach ($rows as $r) {
                $vals[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
            }
            $w("INSERT INTO `{$t}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n");
            $rowsTotal += count($rows);
            $offset += 500;
        }
        $w("\n");
    }
    $w("SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    rename($file . '.part', $file);

    $zipFile = null;
    if ($withUploads && class_exists('ZipArchive') && is_dir(PUBLIC_DIR . '/uploads')) {
        $zipFile = BACKUP_DIR . "/backup-{$stamp}-uploads.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PUBLIC_DIR . '/uploads', FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen(PUBLIC_DIR . '/uploads/'))));
            }
            $zip->close();
        } else {
            $zipFile = null;
        }
    }
    setting_set('backup_last_at', now());
    return ['file' => basename($file), 'size' => (int) filesize($file), 'tables' => count($tableNames), 'rows' => $rowsTotal, 'uploads' => $zipFile ? basename($zipFile) : null];
}

/** @return array<int, array{file:string,size:int,time:int,kind:string}> newest first */
function backup_list(): array
{
    $out = [];
    foreach (glob(BACKUP_DIR . '/backup-*.{sql.gz,zip}', GLOB_BRACE) ?: [] as $f) {
        $out[] = ['file' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f), 'kind' => str_ends_with($f, '.zip') ? 'Uploads (zip)' : 'Database (sql.gz)'];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

/** Resolve a backup file name safely (no path traversal). */
function backup_path(string $name): ?string
{
    if (!preg_match('~^backup-\d{8}-\d{6}-[a-f0-9]{6}(-uploads\.zip|\.sql\.gz)$~', $name)) return null;
    $p = BACKUP_DIR . '/' . $name;
    return is_file($p) ? $p : null;
}

/** Keep the newest $keep database backups (and their upload zips). @return int files removed */
function backup_prune(int $keep): int
{
    $db = array_values(array_filter(backup_list(), fn($b) => str_ends_with($b['file'], '.sql.gz')));
    $removed = 0;
    foreach (array_slice($db, max(1, $keep)) as $b) {
        $stamp = substr($b['file'], 0, -7);
        foreach ([$b['file'], $stamp . '-uploads.zip'] as $f) if (($p = backup_path($f)) && @unlink($p)) $removed++;
    }
    return $removed;
}

/** Restore a .sql.gz created by backup_create(). @return int statements executed */
function backup_restore(string $path): int
{
    $gz = gzopen($path, 'rb');
    if (!$gz) throw new RuntimeException('Cannot read the backup file.');
    $pdo = db();
    $buf = '';
    $n = 0;
    while (!gzeof($gz)) {
        $buf .= gzgets($gz, 1 << 20);
        // Statements end with ";\n" — values are escaped by PDO::quote, so raw newlines never occur inside strings
        if (str_ends_with($buf, ";\n")) {
            $sql = trim($buf);
            $buf = '';
            if ($sql === '' || str_starts_with($sql, '--')) {
                $sql = preg_replace('~^(--[^\n]*\n)+~', '', $sql . "\n");
                if (trim((string) $sql) === '') continue;
            }
            $pdo->exec((string) $sql);
            $n++;
        }
    }
    gzclose($gz);
    return $n;
}
