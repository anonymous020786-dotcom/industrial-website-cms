<?php
/** PDO database helpers (MySQL / MariaDB). Table + column names always come from code, never from user input. */
declare(strict_types=1);

function db(?array $cfg = null): PDO
{
    static $pdo = null;
    if ($cfg !== null) $pdo = null;
    if ($pdo) return $pdo;
    $c = $cfg ?? config('db');
    if (!$c) throw new RuntimeException('Database is not configured. Run the installer.');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $name = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ":{$k}");
        $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        $st->bindValue($name, $v, $type);
    }
    $st->execute();
    return $st;
}

function db_all(string $sql, array $params = []): array { return db_query($sql, $params)->fetchAll(); }

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = []): mixed
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, implode('`,`', $cols), implode(',', array_map(fn($c) => ":{$c}", $cols)));
    db_query($sql, $data);
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, int $id): void
{
    if (!$data) return;
    $set = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($data)));
    db_query("UPDATE `{$table}` SET {$set} WHERE id = :__id", $data + ['__id' => $id]);
}

function db_delete(string $table, int $id): void
{
    db_query("DELETE FROM `{$table}` WHERE id = ?", [$id]);
}

/** Make a slug unique within a table (optionally scoped by extra WHERE). */
function unique_slug(string $table, string $slug, ?int $ignoreId = null, string $scopeSql = '', array $scope = []): string
{
    $base = $slug;
    $i = 2;
    while (true) {
        $params = array_merge([$slug], $scope);
        $sql = "SELECT id FROM `{$table}` WHERE slug = ?" . ($scopeSql ? " AND {$scopeSql}" : '');
        if ($ignoreId) { $sql .= ' AND id <> ?'; $params[] = $ignoreId; }
        if (!db_val($sql, $params)) return $slug;
        $slug = $base . '-' . $i++;
    }
}

/** Simple sliding-window rate limiter. Returns true if the action is allowed. */
function rate_limit(string $bucket, int $max, int $seconds): bool
{
    $bucket = substr($bucket, 0, 120);
    db_query('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL ? SECOND)', [max($seconds, 3600)]);
    $count = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= (NOW() - INTERVAL ? SECOND)', [$bucket, $seconds]);
    if ($count >= $max) return false;
    db_insert('rate_limits', ['bucket' => $bucket]);
    return true;
}

function ip_bucket(string $action): string
{
    return $action . ':' . substr(hash('sha256', client_ip() . (string) config('app_key')), 0, 32);
}
