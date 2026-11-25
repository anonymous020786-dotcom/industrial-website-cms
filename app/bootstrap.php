<?php
/**
 * Application bootstrap — loaded by public/index.php and app/cli.php.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
// The web root is /public. On hosts with a fixed document root (Hostinger/cPanel: domains/<site>/public_html)
// the contents of /public go into public_html and app/, content/, database/ sit next to it, outside the web root.
define('PUBLIC_DIR', is_dir(APP_ROOT . '/public') ? APP_ROOT . '/public' : APP_ROOT . '/public_html');
define('STORAGE_DIR', APP_DIR . '/storage');
define('APP_VERSION', '2.0.0');
define('SCHEMA_VERSION', 13); // bump with every database change in app/lib/seed.php run_migrations()

$configFile = APP_DIR . '/config.php';
$GLOBALS['__config'] = is_file($configFile) ? require $configFile : [];

date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'UTC');
mb_internal_encoding('UTF-8');

foreach (['helpers', 'db', 'settings', 'html', 'auth', 'devices', 'notify', 'telegram', 'stepup', 'totp', 'webauthn', 'fields', 'templates', 'content', 'mailer', 'leads', 'history', 'pdf', 'quotes', 'calculators', 'search', 'engage', 'newsletter', 'integrations', 'components', 'images', 'redirects', 'backup', 'report', 'cron', 'errors', 'maintenance', 'updater', 'router'] as $lib) {
    require APP_DIR . "/lib/{$lib}.php";
}

// Errors: show details only in debug mode; always log.
$debug = (bool) ($GLOBALS['__config']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
@mkdir(STORAGE_DIR . '/logs', 0775, true);
ini_set('error_log', STORAGE_DIR . '/logs/php-error.log');
error_reporting(E_ALL);

errors_register($debug); // app/lib/errors.php: branded error pages, error log, fatal-error fallback

function is_installed(): bool
{
    return !empty($GLOBALS['__config']['db']) && is_file(STORAGE_DIR . '/installed.lock');
}
