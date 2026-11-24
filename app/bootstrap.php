<?php
/**
 * Application bootstrap — loaded by public/index.php and app/cli.php.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('PUBLIC_DIR', APP_ROOT . '/public');
define('STORAGE_DIR', APP_DIR . '/storage');
define('APP_VERSION', '2.0.0');

$configFile = APP_DIR . '/config.php';
$GLOBALS['__config'] = is_file($configFile) ? require $configFile : [];

date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'UTC');
mb_internal_encoding('UTF-8');

foreach (['helpers', 'db', 'settings', 'html', 'auth', 'notify', 'stepup', 'totp', 'webauthn', 'fields', 'templates', 'content', 'mailer', 'leads', 'integrations', 'components', 'images', 'redirects', 'backup', 'cron', 'router'] as $lib) {
    require APP_DIR . "/lib/{$lib}.php";
}

// Errors: show details only in debug mode; always log.
$debug = (bool) ($GLOBALS['__config']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
@mkdir(STORAGE_DIR . '/logs', 0775, true);
ini_set('error_log', STORAGE_DIR . '/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($debug) {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
    if (!headers_sent()) http_response_code(500);
    if ($debug) {
        echo '<pre style="padding:2rem;white-space:pre-wrap">' . e((string) $e) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Server error</title><div style="font-family:sans-serif;padding:3rem"><h1>Something went wrong</h1><p>Please try again later.</p></div>';
    }
});

function is_installed(): bool
{
    return !empty($GLOBALS['__config']['db']) && is_file(STORAGE_DIR . '/installed.lock');
}
