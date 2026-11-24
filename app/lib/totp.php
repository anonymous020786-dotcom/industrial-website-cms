<?php
/**
 * Authenticator-app two-factor sign-in (TOTP, RFC 6238) + one-time recovery codes.
 * Works with Google Authenticator, Microsoft Authenticator, 1Password, Authy, Bitwarden, etc.
 * Secrets are encrypted at rest with AES-256-GCM using a key derived from config app_key.
 */
declare(strict_types=1);

const TOTP_DIGITS = 6;
const TOTP_PERIOD = 30;
const TOTP_WINDOW = 1;          // accept codes from one step before/after (clock drift)
const RECOVERY_CODE_COUNT = 10;

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $s): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('~[^A-Za-z2-7]~', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $secretB32, int $step): string
{
    $hash = hash_hmac('sha1', pack('J', $step), base32_decode($secretB32), true);
    $o = ord($hash[19]) & 0x0f;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($n % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/** @return int|null the matched time step, or null */
function totp_match(string $secretB32, string $code, ?int $now = null): ?int
{
    $code = preg_replace('~\D~', '', $code) ?? '';
    if (strlen($code) !== TOTP_DIGITS) return null;
    $step = intdiv($now ?? time(), TOTP_PERIOD);
    for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
        if (hash_equals(totp_code($secretB32, $step + $i), $code)) return $step + $i;
    }
    return null;
}

function totp_new_secret(): string { return base32_encode(random_bytes(20)); } // 160-bit, as RFC 4226 recommends

function totp_uri(array $user, string $secret): string
{
    $issuer = (string) company('name', 'Admin');
    $label = rawurlencode($issuer . ':' . ($user['email'] ?? 'admin'));
    return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . TOTP_DIGITS . '&period=' . TOTP_PERIOD;
}

/* ---------------- encryption at rest ---------------- */

function secret_encrypt(string $plain): string
{
    $key = hash('sha256', 'totp|' . config('app_key'), true);
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function secret_decrypt(?string $stored): ?string
{
    if (!$stored || !str_starts_with($stored, 'v1:')) return null;
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) return null;
    $key = hash('sha256', 'totp|' . config('app_key'), true);
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? null : $pt;
}

/* ---------------- per-user helpers ---------------- */

function totp_enabled_for(array $user): bool
{
    return !empty($user['totp_enabled_at']) && !empty($user['totp_secret']);
}

/**
 * Verify a code for a user, with replay protection (each time step can be used once).
 * @return true|string
 */
function totp_verify_user(array $user, string $code): bool|string
{
    $uid = (int) $user['id'];
    $fails = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= NOW() - INTERVAL 15 MINUTE', ["totp-fail:{$uid}"]);
    if ($fails >= 5) return 'Too many incorrect codes. Please wait 15 minutes.';
    $secret = secret_decrypt($user['totp_secret'] ?? null);
    $step = $secret ? totp_match($secret, $code) : null;
    if ($step === null) {
        db_insert('rate_limits', ['bucket' => "totp-fail:{$uid}"]);
        auth_log('totp_failed', $uid, $user['email']);
        return 'That code is not correct. Check the time on your phone and try the newest code.';
    }
    $used = "totp-used:{$uid}:{$step}";
    if (db_val('SELECT id FROM rate_limits WHERE bucket = ?', [$used])) return 'That code was already used. Wait for the next code.';
    db_insert('rate_limits', ['bucket' => $used]);
    db_query('DELETE FROM rate_limits WHERE bucket = ?', ["totp-fail:{$uid}"]);
    return true;
}

/** Create a fresh set of recovery codes (old ones are invalidated). @return string[] plain codes, shown once */
function recovery_codes_generate(int $userId): array
{
    db_query('DELETE FROM recovery_codes WHERE user_id = ?', [$userId]);
    $codes = [];
    $alpha = 'abcdefghjkmnpqrstuvwxyz23456789'; // no look-alike characters
    for ($i = 0; $i < RECOVERY_CODE_COUNT; $i++) {
        $c = '';
        for ($j = 0; $j < 10; $j++) $c .= $alpha[random_int(0, strlen($alpha) - 1)];
        $code = substr($c, 0, 5) . '-' . substr($c, 5);
        $codes[] = $code;
        db_insert('recovery_codes', ['user_id' => $userId, 'code_hash' => password_hash($code, PASSWORD_DEFAULT)]);
    }
    return $codes;
}

function recovery_codes_left(int $userId): int
{
    return (int) db_val('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);
}

/** @return true|string */
function recovery_code_use(array $user, string $code): bool|string
{
    $uid = (int) $user['id'];
    $fails = (int) db_val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= NOW() - INTERVAL 15 MINUTE', ["recovery-fail:{$uid}"]);
    if ($fails >= 5) return 'Too many incorrect codes. Please wait 15 minutes.';
    $code = strtolower(preg_replace('~[^a-z0-9]~i', '', $code) ?? '');
    if (strlen($code) === 10) $code = substr($code, 0, 5) . '-' . substr($code, 5);
    foreach (db_all('SELECT id, code_hash FROM recovery_codes WHERE user_id = ? AND used_at IS NULL', [$uid]) as $row) {
        if (password_verify($code, $row['code_hash'])) {
            db_query('UPDATE recovery_codes SET used_at = NOW() WHERE id = ?', [$row['id']]);
            auth_log('recovery_code_used', $uid, $user['email']);
            $left = recovery_codes_left($uid);
            security_notice($user, 'A recovery code was used', 'A one-time recovery code was used to sign in to your account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . ". You have {$left} recovery code(s) left. If this was not you, change your password now.");
            return true;
        }
    }
    db_insert('rate_limits', ['bucket' => "recovery-fail:{$uid}"]);
    auth_log('recovery_code_failed', $uid, $user['email']);
    return 'That recovery code is not valid or was already used.';
}
