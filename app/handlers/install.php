<?php
/** Web installer — available only until installation completes. */
declare(strict_types=1);
require_once APP_DIR . '/lib/seed.php';

function install_handler(): void
{
    if (is_installed()) redirect('admin/login');
    session_boot();
    $errors = [];
    $v = [
        'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'industrial_cms', 'db_user' => 'root', 'db_pass' => '',
        'site_url' => (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path()),
        'admin_name' => '', 'admin_email' => '', 'admin_pass' => '',
    ];
    $done = null;

    if (is_post()) {
        csrf_check();
        foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? ''));
        $v['db_pass'] = (string) ($_POST['db_pass'] ?? '');
        $v['admin_pass'] = (string) ($_POST['admin_pass'] ?? '');
        if (!preg_match('~^[A-Za-z0-9_]+$~', $v['db_name'])) $errors[] = 'Database name may contain only letters, numbers and underscores.';
        if (mb_strlen($v['admin_name']) < 2) $errors[] = 'Enter the administrator name.';
        if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid administrator email.';
        if ($p = password_problems($v['admin_pass'])) $errors[] = $p;

        if (!$errors) {
            $db = ['host' => $v['db_host'], 'port' => (int) $v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $v['db_pass']];
            try {
                // Create the database if the user has permission (shared hosting usually pre-creates it).
                try {
                    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (PDOException) { /* fall through: connect directly to an existing database */ }
                db($db);
                write_config($db);
                run_migrations();
                $adminId = create_admin($v['admin_name'], $v['admin_email'], $v['admin_pass']);
                $done = seed_all(rtrim($v['site_url'], '/'));
                db_query('UPDATE posts SET author_id = ? WHERE author_id IS NULL', [$adminId]);
                setting_set('lead_notify_emails', $v['admin_email']);
                finish_install();
            } catch (Throwable $e) {
                error_log((string) $e);
                @unlink(APP_DIR . '/config.php');
                $errors[] = 'Installation failed: ' . $e->getMessage();
                $done = null;
            }
        }
    }
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/install', ['v' => $v, 'errors' => $errors, 'done' => $done]);
}
