<?php
/**
 * Clears the web server's PHP code cache (OPcache) right after an automatic update, so visitors never
 * get a mix of cached old files and new ones. The updater runs from cron (command line), and clearing the
 * cache there does not reach the web server. It calls this URL with a one-time token that expires after
 * two minutes. Standalone on purpose: it must work even while other files are still cached.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$file = dirname(__DIR__) . '/app/storage/refresh.token';
[$token, $expires] = array_pad(explode('|', trim(is_file($file) ? (string) @file_get_contents($file) : ''), 2), 2, '0');
$given = (string) ($_GET['t'] ?? '');
if (strlen($token) !== 64 || (int) $expires < time() || !hash_equals($token, $given)) {
    http_response_code(404);
    echo "Not found\n";
    exit;
}
@unlink($file); // one use only
echo function_exists('opcache_reset') && @opcache_reset() ? "cache cleared\n" : "no code cache\n";
