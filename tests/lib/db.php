<?php
/**
 * Test helper: talk to the app database through the app's own code.
 *   php tests/lib/db.php '{"sql":"SELECT ...","params":[...]}'
 *   php tests/lib/db.php '{"setting":["key", value]}'
 *   php tests/lib/db.php '{"get_setting":"key"}'
 * Prints JSON. Never used by the website itself.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$cmd = json_decode($argv[1] ?? '{}', true) ?: [];
$out = null;
if (isset($cmd['sql'])) {
    $st = db_query((string) $cmd['sql'], (array) ($cmd['params'] ?? []));
    $out = $st->columnCount() ? $st->fetchAll() : ['affected' => $st->rowCount()];
} elseif (isset($cmd['setting'])) {
    setting_set((string) $cmd['setting'][0], $cmd['setting'][1]);
    $out = ['ok' => true];
} elseif (isset($cmd['get_setting'])) {
    $out = setting((string) $cmd['get_setting']);
} elseif (isset($cmd['debug'])) {
    $out = (bool) config('debug');
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
