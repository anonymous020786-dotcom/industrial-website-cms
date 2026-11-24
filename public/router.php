<?php
/**
 * Router for PHP's built-in development server:
 *   php -S localhost:8000 -t public public/router.php
 * Serves existing static files directly; everything else goes to index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    // Never execute PHP from uploads
    if (str_starts_with($path, '/uploads/') && preg_match('~\.ph(p\d?|ar|tml)$~i', $path)) { http_response_code(403); return true; }
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
