<?php
/** Key/value site settings stored as JSON in the `settings` table. */
declare(strict_types=1);

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;
    $cache = [];
    if (!is_installed()) return $cache;
    foreach (db_all('SELECT `key`, `value` FROM settings') as $row) {
        $v = json_decode((string) $row['value'], true);
        $cache[$row['key']] = (json_last_error() === JSON_ERROR_NONE) ? $v : $row['value'];
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    $v = $all[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function setting_set(string $key, mixed $value): void
{
    db_query(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
        [$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
    );
    settings_all(true);
}

/** Company helper used across templates */
function company(string $key, string $default = ''): string
{
    return (string) setting('company_' . $key, $default);
}
