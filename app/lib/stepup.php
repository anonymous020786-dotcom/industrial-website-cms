<?php
/**
 * Step-up verification ("confirm it's you") for critical actions.
 * A one-time code (emailed, or from the authenticator app) or a passkey is required before any
 * action in STEPUP_ACTIONS; the confirmation then lasts STEPUP_WINDOW seconds for that session.
 * The interrupted request is resumed afterwards: GET requests are re-opened, POST forms are
 * re-submitted automatically — except forms containing passwords or secrets, which are never stored
 * and must simply be submitted again.
 */
declare(strict_types=1);

const STEPUP_WINDOW = 600; // 10 minutes
const STEPUP_ACTIONS = [
    'password' => 'Change your password',
    'email' => 'Change your sign-in email',
    'backup_email' => 'Change or remove your backup email',
    'passkey_add' => 'Add a passkey',
    'passkey_remove' => 'Remove a passkey',
    'totp' => 'Change authenticator-app sign-in',
    'recovery' => 'Create new recovery codes',
    'backup' => 'Create, download or delete a backup',
    'export' => 'Export lead data',
    'users' => 'Create, change or delete a user account',
    'settings' => 'Change security, email or integration settings',
    'destroy' => 'Permanently delete content',
    'lead_delete' => 'Delete a lead',
    'maintenance' => 'Turn maintenance mode on or off',
    'telegram' => 'Connect or change Telegram sign-in',
];

/** 10 minutes, or longer on a device the user trusted (Settings → Security & Login). */
function stepup_window(int $userId): int
{
    $min = (int) setting('stepup_trusted_minutes', 10);
    return $min > 10 && device_trusted($userId) ? min(240, $min) * 60 : STEPUP_WINDOW;
}

function stepup_valid(): bool
{
    $u = current_user();
    $s = $_SESSION['stepup'] ?? null;
    return $u && is_array($s) && (int) $s['uid'] === (int) $u['id'] && (int) $s['until'] > time()
        && hash_equals((string) $s['ver'], session_fingerprint((int) $u['id']));
}

function stepup_remaining(): int
{
    return stepup_valid() ? max(0, (int) $_SESSION['stepup']['until'] - time()) : 0;
}

function stepup_grant(array $user, string $method): void
{
    $_SESSION['stepup'] = ['uid' => (int) $user['id'], 'until' => time() + stepup_window((int) $user['id']), 'ver' => session_fingerprint((int) $user['id']), 'method' => $method];
    auth_log('stepup_ok', (int) $user['id'], $user['email']);
}

/** Same-site, app-relative path to return to (never an external URL). */
function stepup_safe_path(?string $url, string $fallback): string
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $host = (string) parse_url((string) $url, PHP_URL_HOST);
    if ($path === '' || ($host !== '' && strcasecmp($host, preg_replace('~:\d+$~', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))) !== 0)) return $fallback;
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
    $q = (string) parse_url((string) $url, PHP_URL_QUERY);
    return ltrim($path, '/') . ($q !== '' ? '?' . $q : '');
}

/**
 * Call at the start of a critical handler. Returns when the session is already confirmed;
 * otherwise remembers the request and sends the user to the confirmation page.
 */
function require_stepup(string $action): void
{
    if (stepup_valid()) return;
    $user = current_user();
    if (!$user) redirect('admin/login');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') || wants_json();
    $back = stepup_safe_path($_SERVER['HTTP_REFERER'] ?? null, 'admin');
    $fields = null;
    if ($method === 'POST' && !$isJson) {
        $fields = $_POST;
        unset($fields['_token']);
        // Never keep passwords, codes or secrets in the session — those forms are re-submitted by the user
        $secret = false;
        array_walk_recursive($fields, function ($v, $k) use (&$secret) {
            if (is_string($k) && preg_match('~pass|secret|code|otp|private~i', $k) && trim((string) $v) !== '') $secret = true;
        });
        if ($secret) $fields = null;
        if (!empty($_FILES) && array_filter($_FILES, fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) $fields = null;
    }
    $_SESSION['stepup_pending'] = [
        'action' => $action,
        'method' => $isJson ? 'GET' : $method,
        'path' => $isJson ? $back : ltrim(request_path(), '/') . (($q = (string) ($_SERVER['QUERY_STRING'] ?? '')) !== '' ? '?' . $q : ''),
        'fields' => $fields,
        'back' => $back,
        'at' => time(),
    ];
    auth_log('stepup_required', (int) $user['id'], $user['email']);
    if ($isJson) json_response(['error' => 'Please confirm it’s you first.', 'stepup' => url('admin/confirm')], 428);
    redirect('admin/confirm');
}
