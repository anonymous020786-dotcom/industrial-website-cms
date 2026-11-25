<?php
/**
 * Automatic updates from GitHub, pulled by the server's own cron (no SSH, FTP or secrets needed).
 *
 * CI moves the `release` branch to each commit that passed the full test suite (deploy.yml). Every
 * cron run asks GitHub for that branch's commit; when it changed, the code of that exact commit is
 * downloaded and copied into place in the site's layout. Server-only files are never touched:
 * app/config.php, app/storage (backups, logs), uploads and .well-known (SSL). Files that were removed
 * from the repository are removed here too, then database migrations run and admins are alerted.
 *
 * Off unless app/config.php (private, server-only) contains:
 *   'auto_update' => ['repo' => 'owner/name', 'branch' => 'release'],
 * so an admin account alone can never point the site at other code.
 */
declare(strict_types=1);

function updater_config(): ?array
{
    $c = config('auto_update');
    if (!is_array($c) || !preg_match('~^[\w.-]+/[\w.-]+$~', (string) ($c['repo'] ?? ''))) return null;
    $branch = (string) ($c['branch'] ?? 'release');
    if (!preg_match('~^[\w./-]+$~', $branch)) return null;
    // Test installs (debug only) may use a local stand-in for GitHub
    $github = config('debug') && !empty($c['github']) ? rtrim((string) $c['github'], '/') : 'https://github.com';
    $codeload = config('debug') && !empty($c['codeload']) ? rtrim((string) $c['codeload'], '/') : 'https://codeload.github.com';
    return ['repo' => $c['repo'], 'branch' => $branch, 'github' => $github, 'codeload' => $codeload];
}

function updater_enabled(): bool
{
    return updater_config() !== null;
}

function updater_http(string $url, ?string $saveTo = null): string
{
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'ignore_errors' => true,
        'header' => "User-Agent: git/2.45 industrial-cms-updater\r\nAccept: */*\r\n"]]);
    $in = @fopen($url, 'rb', false, $ctx);
    if (!$in) throw new RuntimeException('Could not reach ' . parse_url($url, PHP_URL_HOST) . '.');
    $status = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+ (\d{3})~', $h, $mm)) $status = (int) $mm[1]; // last one after redirects
    if ($status !== 200) { fclose($in); throw new RuntimeException('GitHub answered HTTP ' . $status . ' for ' . parse_url($url, PHP_URL_PATH) . '.'); }
    if ($saveTo === null) { $body = (string) stream_get_contents($in, 2 * 1024 * 1024); fclose($in); return $body; }
    $out = fopen($saveTo, 'wb');
    $size = 0;
    while (!feof($in)) {
        $chunk = fread($in, 1 << 16);
        if ($chunk === false) break;
        $size += strlen($chunk);
        if ($size > 200 * 1024 * 1024) { fclose($in); fclose($out); throw new RuntimeException('Download too large.'); }
        fwrite($out, $chunk);
    }
    fclose($in); fclose($out);
    return '';
}

/** The commit the release branch points at (git smart-HTTP ref list: no API key, no rate limit). */
function updater_remote_sha(): string
{
    $c = updater_config();
    if (!$c) throw new RuntimeException('Automatic updates are not configured.');
    $refs = updater_http($c['github'] . '/' . $c['repo'] . '.git/info/refs?service=git-upload-pack');
    if (!preg_match('~([0-9a-f]{40}) refs/heads/' . preg_quote($c['branch'], '~') . '(?:\x00|\n)~', $refs, $m)) {
        throw new RuntimeException('Branch "' . $c['branch'] . '" was not found in ' . $c['repo'] . '.');
    }
    return $m[1];
}

/** Where a file from the repository goes on this server, or null when it is not deployed. */
function updater_target(string $rel): ?string
{
    $webRootSeparate = basename(PUBLIC_DIR) !== 'public';
    if (preg_match('~^(app/config\.php|app/storage/|public/uploads/|public/\.well-known/)~', $rel)) return null;
    if (str_starts_with($rel, 'public/')) {
        if ($webRootSeparate && $rel === 'public/router.php') return null; // dev server only
        return PUBLIC_DIR . '/' . substr($rel, 7);
    }
    if (preg_match('~^(app|content|database)/~', $rel)) return APP_ROOT . '/' . $rel;
    if ($rel === '.htaccess' && !$webRootSeparate) return APP_ROOT . '/.htaccess';
    return null; // tests, docs, build tooling, CI
}

/**
 * Check for a new release and install it. Returns a one-line result.
 * $force re-installs the current release (repairs changed or missing files).
 */
function updater_run(bool $force = false): string
{
    if (!updater_enabled()) return 'off (not configured)';
    $fh = fopen(STORAGE_DIR . '/update.lock', 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return 'another update is running';
    $work = STORAGE_DIR . '/update';
    $plan = [];
    try {
        $sha = updater_remote_sha();
        setting_set('update_checked_at', now());
        $current = (string) setting('deployed_sha', '');
        if ($sha === $current && !$force) return 'up to date (' . substr($sha, 0, 7) . ')';

        @mkdir($work, 0775, true);
        $zipPath = $work . '/release.zip';
        $c = updater_config();
        updater_http($c['codeload'] . '/' . $c['repo'] . '/zip/' . $sha, $zipPath);
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('The download is not a valid zip.');
        $prefix = (string) strstr((string) $zip->getNameIndex(0), '/', true) . '/';
        if ($zip->locateName($prefix . 'app/bootstrap.php') === false || $zip->locateName($prefix . 'public/index.php') === false) {
            throw new RuntimeException('The download does not look like this application.');
        }

        // 1) Stage every deployed file next to its destination, then 2) swap them in (rename is atomic)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_starts_with($name, $prefix) || str_ends_with($name, '/') || str_contains($name, '..')) continue;
            $target = updater_target(substr($name, strlen($prefix)));
            if ($target === null) continue;
            @mkdir(dirname($target), 0775, true);
            $tmp = $target . '.upd-' . substr($sha, 0, 7);
            if (file_put_contents($tmp, $zip->getFromIndex($i)) === false) throw new RuntimeException('Cannot write ' . $target . ' — check permissions.');
            $plan[$target] = $tmp;
        }
        $zip->close();
        if (count($plan) < 50) throw new RuntimeException('Too few files in the release (' . count($plan) . ').');
        $changed = 0;
        foreach ($plan as $target => $tmp) {
            if (is_file($target) && md5_file($target) === md5_file($tmp)) { unlink($tmp); continue; }
            rename($tmp, $target);
            $changed++;
        }

        // 3) Remove files that were in the previous release but are gone now (only ones we deployed)
        $manifestFile = STORAGE_DIR . '/deploy-manifest.json';
        $old = is_file($manifestFile) ? (json_decode((string) file_get_contents($manifestFile), true) ?: []) : [];
        $removed = 0;
        foreach (array_diff($old, array_keys($plan)) as $gone) {
            $real = realpath((string) $gone);
            $inside = $real && (str_starts_with($real, realpath(APP_ROOT) . DIRECTORY_SEPARATOR) || str_starts_with($real, realpath(PUBLIC_DIR) . DIRECTORY_SEPARATOR));
            if ($inside && is_file($real) && !preg_match('~[\\\\/](config\.php|storage[\\\\/].*|uploads[\\\\/].*)$~', $real) && @unlink($real)) $removed++;
        }
        file_put_contents($manifestFile, json_encode(array_keys($plan), JSON_UNESCAPED_SLASHES));
        if (function_exists('opcache_reset')) @opcache_reset();

        // 4) Database changes, record and alert
        require_once APP_DIR . '/lib/seed.php';
        $migrations = run_migrations();
        setting_set('deployed_sha', $sha);
        setting_set('update_error', '');
        setting_set('deployed_at', now());
        $msg = 'updated ' . ($current !== '' ? substr($current, 0, 7) . ' → ' : 'to ') . substr($sha, 0, 7) . " ({$changed} file(s) changed, {$removed} removed"
            . ($migrations ? ', database: ' . implode(', ', $migrations) : '') . ')';
        notify_admins('site_updated', 'Website updated to ' . substr($sha, 0, 7),
            'The website was updated automatically from ' . $c['repo'] . ' (' . $c['branch'] . '): ' . $msg . '. Only versions that passed all automated tests are installed.',
            ['severity' => 'info', 'category' => 'system', 'email' => false, 'link' => 'admin/settings/system']);
        return $msg;
    } catch (Throwable $e) {
        foreach ($plan as $tmp) if (is_file($tmp)) @unlink($tmp); // staged copies that were not swapped in
        setting_set('update_error', mb_substr($e->getMessage(), 0, 300) . ' (' . now() . ')');
        error_log('updater: ' . $e->getMessage());
        return 'ERROR: ' . $e->getMessage();
    } finally {
        foreach (['release.zip'] as $f) @unlink($work . '/' . $f);
        @rmdir($work);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
