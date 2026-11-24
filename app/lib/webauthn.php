<?php
/**
 * Passkeys (WebAuthn Level 2) — dependency-free server implementation.
 *
 * Supports ES256 (-7, P-256) and RS256 (-257) credentials, which cover platform authenticators
 * (Windows Hello, Touch ID / Face ID, Android) and security keys. Attestation is not required
 * ("none"): we trust the key the browser created, as recommended for consumer/admin sign-in.
 * User verification (PIN / biometric) is REQUIRED, so a passkey is a full two-factor sign-in.
 */
declare(strict_types=1);

const WEBAUTHN_TIMEOUT = 120000;           // ms the browser prompt stays open
const WEBAUTHN_CHALLENGE_TTL = 300;        // s a challenge stays valid

function b64url_encode(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function b64url_decode(string $s): string
{
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode($s, true);
    if ($out === false) throw new RuntimeException('Invalid base64url data.');
    return $out;
}

/** Relying-party ID = the host name the admin is served on (no scheme, no port). */
function webauthn_rp_id(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return preg_replace('~:\d+$~', '', $host) ?: 'localhost';
}

function webauthn_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

/** Opaque, stable per-user handle (no personal data inside). */
function webauthn_user_handle(int $userId): string
{
    return substr(hash_hmac('sha256', 'webauthn-user:' . $userId, (string) config('app_key'), true), 0, 16);
}

function webauthn_new_challenge(string $purpose, array $extra = []): string
{
    session_boot();
    $c = random_bytes(32);
    $_SESSION['webauthn'] = ['challenge' => b64url_encode($c), 'purpose' => $purpose, 'at' => time()] + $extra;
    return b64url_encode($c);
}

/** Consume (single use) the pending challenge for a purpose. */
function webauthn_take_challenge(string $purpose): ?array
{
    session_boot();
    $p = $_SESSION['webauthn'] ?? null;
    unset($_SESSION['webauthn']);
    if (!$p || $p['purpose'] !== $purpose || time() - (int) $p['at'] > WEBAUTHN_CHALLENGE_TTL) return null;
    return $p;
}

/* ------------------------------------------------------------------ */
/* Minimal CBOR decoder (RFC 8949) — enough for WebAuthn structures    */
/* ------------------------------------------------------------------ */

final class Cbor
{
    private int $pos = 0;
    private function __construct(private string $data) {}

    /** Decode one item; $consumed receives the number of bytes read. */
    public static function decode(string $data, ?int &$consumed = null): mixed
    {
        $c = new self($data);
        $v = $c->item(0);
        $consumed = $c->pos;
        return $v;
    }

    private function take(int $n): string
    {
        if ($n < 0 || $this->pos + $n > strlen($this->data)) throw new RuntimeException('CBOR: unexpected end of data.');
        $s = substr($this->data, $this->pos, $n);
        $this->pos += $n;
        return $s;
    }

    private function length(int $info): int
    {
        if ($info < 24) return $info;
        return match ($info) {
            24 => ord($this->take(1)),
            25 => unpack('n', $this->take(2))[1],
            26 => unpack('N', $this->take(4))[1],
            27 => (function () { $v = unpack('J', $this->take(8))[1]; if ($v < 0) throw new RuntimeException('CBOR: length too large.'); return $v; })(),
            default => throw new RuntimeException('CBOR: indefinite lengths are not supported.'),
        };
    }

    private function item(int $depth): mixed
    {
        if ($depth > 16) throw new RuntimeException('CBOR: nesting too deep.');
        $b = ord($this->take(1));
        $major = $b >> 5;
        $info = $b & 0x1f;
        switch ($major) {
            case 0: return $this->length($info);
            case 1: return -1 - $this->length($info);
            case 2: return ['__bytes' => $this->take($this->length($info))];
            case 3: return $this->take($this->length($info));
            case 4:
                $n = $this->length($info);
                if ($n > 1000) throw new RuntimeException('CBOR: array too large.');
                $a = [];
                for ($i = 0; $i < $n; $i++) $a[] = $this->item($depth + 1);
                return $a;
            case 5:
                $n = $this->length($info);
                if ($n > 1000) throw new RuntimeException('CBOR: map too large.');
                $m = [];
                for ($i = 0; $i < $n; $i++) {
                    $k = $this->item($depth + 1);
                    if (is_array($k)) $k = $k['__bytes'] ?? json_encode($k);
                    $m[$k] = $this->item($depth + 1);
                }
                return $m;
            case 6: $this->length($info); return $this->item($depth + 1); // ignore tags
            case 7:
                return match ($info) {
                    20 => false, 21 => true, 22, 23 => null,
                    25 => (function () { $this->take(2); return null; })(),
                    26 => unpack('G', $this->take(4))[1],
                    27 => unpack('E', $this->take(8))[1],
                    default => throw new RuntimeException('CBOR: unsupported simple value.'),
                };
        }
        throw new RuntimeException('CBOR: invalid major type.');
    }
}

function cbor_bytes(mixed $v): ?string { return is_array($v) && isset($v['__bytes']) ? $v['__bytes'] : null; }

/* ------------------------------------------------------------------ */
/* Authenticator data + COSE keys                                      */
/* ------------------------------------------------------------------ */

/**
 * @return array{rpIdHash:string, flags:int, up:bool, uv:bool, be:bool, bs:bool, signCount:int, aaguid?:string, credentialId?:string, cose?:array}
 */
function webauthn_parse_auth_data(string $ad): array
{
    if (strlen($ad) < 37) throw new RuntimeException('Authenticator data is too short.');
    $flags = ord($ad[32]);
    $out = [
        'rpIdHash' => substr($ad, 0, 32), 'flags' => $flags,
        'up' => (bool) ($flags & 0x01), 'uv' => (bool) ($flags & 0x04),
        'be' => (bool) ($flags & 0x08), 'bs' => (bool) ($flags & 0x10),
        'signCount' => unpack('N', substr($ad, 33, 4))[1],
    ];
    if ($flags & 0x40) { // AT: attested credential data present
        if (strlen($ad) < 55) throw new RuntimeException('Attested credential data is truncated.');
        $out['aaguid'] = substr($ad, 37, 16);
        $len = unpack('n', substr($ad, 53, 2))[1];
        if ($len < 16 || $len > 1023) throw new RuntimeException('Invalid credential ID length.');
        $out['credentialId'] = substr($ad, 55, $len);
        $cose = Cbor::decode(substr($ad, 55 + $len));
        if (!is_array($cose)) throw new RuntimeException('Invalid credential public key.');
        $out['cose'] = $cose;
    }
    return $out;
}

function der_len(int $n): string
{
    if ($n < 0x80) return chr($n);
    $b = ltrim(pack('N', $n), "\0");
    return chr(0x80 | strlen($b)) . $b;
}
function der_int(string $bytes): string
{
    $bytes = ltrim($bytes, "\0");
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) $bytes = "\0" . $bytes;
    return "\x02" . der_len(strlen($bytes)) . $bytes;
}
function der_seq(string $content): string { return "\x30" . der_len(strlen($content)) . $content; }

/** Convert a COSE public key to PEM. @return array{pem:string, alg:int} */
function webauthn_cose_to_pem(array $cose): array
{
    $kty = $cose[1] ?? null;
    $alg = $cose[3] ?? null;
    if ($kty === 2 && $alg === -7) { // EC2 / ES256
        $x = cbor_bytes($cose[-2] ?? null);
        $y = cbor_bytes($cose[-3] ?? null);
        if (($cose[-1] ?? null) !== 1 || strlen((string) $x) !== 32 || strlen((string) $y) !== 32) throw new RuntimeException('Only P-256 EC keys are supported.');
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
    } elseif ($kty === 3 && $alg === -257) { // RSA / RS256
        $n = cbor_bytes($cose[-1] ?? null);
        $e = cbor_bytes($cose[-2] ?? null);
        if (!$n || !$e || strlen($n) < 256) throw new RuntimeException('RSA keys must be at least 2048 bits.');
        $rsaKey = der_seq(der_int($n) . der_int($e));
        $algId = der_seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $der = der_seq($algId . "\x03" . der_len(strlen($rsaKey) + 1) . "\x00" . $rsaKey);
    } else {
        throw new RuntimeException('Unsupported passkey algorithm. Use a device that supports ES256 or RS256.');
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    if (!openssl_pkey_get_public($pem)) throw new RuntimeException('The passkey public key could not be read.');
    return ['pem' => $pem, 'alg' => (int) $alg];
}

/** Validate clientDataJSON against the expected ceremony type and challenge. */
function webauthn_check_client_data(string $json, string $type, string $challenge): void
{
    $cd = json_decode($json, true);
    if (!is_array($cd)) throw new RuntimeException('Invalid client data.');
    if (($cd['type'] ?? '') !== $type) throw new RuntimeException('Unexpected ceremony type.');
    if (!hash_equals($challenge, (string) ($cd['challenge'] ?? ''))) throw new RuntimeException('The security challenge did not match. Please try again.');
    if (($cd['origin'] ?? '') !== webauthn_origin()) throw new RuntimeException('This passkey was used from a different website address.');
    if (!empty($cd['crossOrigin'])) throw new RuntimeException('Cross-origin passkey requests are not allowed.');
}

/* ------------------------------------------------------------------ */
/* Ceremonies                                                          */
/* ------------------------------------------------------------------ */

function webauthn_registration_options(array $user): array
{
    $exclude = array_map(fn($c) => ['type' => 'public-key', 'id' => $c['credential_id'], 'transports' => array_values(array_filter(explode(',', (string) $c['transports'])))],
        db_all('SELECT credential_id, transports FROM passkeys WHERE user_id = ?', [(int) $user['id']]));
    return [
        'challenge' => webauthn_new_challenge('register', ['uid' => (int) $user['id']]),
        'rp' => ['id' => webauthn_rp_id(), 'name' => (string) company('name', 'Admin')],
        'user' => ['id' => b64url_encode(webauthn_user_handle((int) $user['id'])), 'name' => (string) ($user['username'] ?: $user['email']), 'displayName' => (string) $user['name']],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
        'timeout' => WEBAUTHN_TIMEOUT,
        'attestation' => 'none',
        'excludeCredentials' => $exclude,
        'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
    ];
}

/** Verify a registration response and store the passkey. @return int new passkey id */
function webauthn_register(array $user, array $cred, string $name): int
{
    $pending = webauthn_take_challenge('register');
    if (!$pending || (int) $pending['uid'] !== (int) $user['id']) throw new RuntimeException('The passkey request expired. Please start again.');
    $clientJson = b64url_decode((string) ($cred['response']['clientDataJSON'] ?? ''));
    webauthn_check_client_data($clientJson, 'webauthn.create', $pending['challenge']);
    $att = Cbor::decode(b64url_decode((string) ($cred['response']['attestationObject'] ?? '')));
    $authData = cbor_bytes($att['authData'] ?? null);
    if (!is_array($att) || $authData === null) throw new RuntimeException('Invalid attestation object.');
    $ad = webauthn_parse_auth_data($authData);
    if (!hash_equals(hash('sha256', webauthn_rp_id(), true), $ad['rpIdHash'])) throw new RuntimeException('The passkey was created for a different website.');
    if (!$ad['up']) throw new RuntimeException('User presence was not confirmed.');
    if (!$ad['uv']) throw new RuntimeException('Your device did not verify you (PIN, fingerprint or face). Passkeys here require it.');
    if (empty($ad['credentialId']) || empty($ad['cose'])) throw new RuntimeException('No credential was returned.');
    if (!hash_equals(b64url_encode($ad['credentialId']), (string) ($cred['rawId'] ?? $cred['id'] ?? ''))) throw new RuntimeException('Credential ID mismatch.');
    $key = webauthn_cose_to_pem($ad['cose']);
    $credId = b64url_encode($ad['credentialId']);
    if (db_val('SELECT id FROM passkeys WHERE credential_id = ?', [$credId])) throw new RuntimeException('This passkey is already registered.');
    $aaguid = bin2hex($ad['aaguid']);
    $aaguid = $aaguid === str_repeat('0', 32) ? null
        : substr($aaguid, 0, 8) . '-' . substr($aaguid, 8, 4) . '-' . substr($aaguid, 12, 4) . '-' . substr($aaguid, 16, 4) . '-' . substr($aaguid, 20);
    $transports = implode(',', array_intersect((array) ($cred['response']['transports'] ?? []), ['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card']));
    $name = trim($name) !== '' ? mb_substr(trim($name), 0, 120) : 'Passkey ' . date('j M Y');
    $id = db_insert('passkeys', ['user_id' => (int) $user['id'], 'credential_id' => $credId, 'public_key' => $key['pem'], 'alg' => $key['alg'],
        'sign_count' => $ad['signCount'], 'transports' => $transports ?: null, 'aaguid' => $aaguid, 'backed_up' => (int) $ad['bs'], 'name' => $name]);
    auth_log('passkey_added', (int) $user['id'], $user['email']);
    security_notice($user, 'A passkey was added to your account', 'A new passkey named “' . $name . '” was added to your ' . company('name') . ' control panel account on ' . date('j M Y \a\t H:i') . ' from IP ' . client_ip() . '. It can be used to sign in without a password.');
    return $id;
}

function webauthn_login_options(): array
{
    return [
        'challenge' => webauthn_new_challenge('login'),
        'rpId' => webauthn_rp_id(),
        'timeout' => WEBAUTHN_TIMEOUT,
        'userVerification' => 'required',
        'allowCredentials' => [], // discoverable credentials: the device offers its passkeys for this site
    ];
}

/** Verify an assertion. @return array the user row */
function webauthn_login(array $cred): array
{
    $pending = webauthn_take_challenge('login');
    if (!$pending) throw new RuntimeException('The sign-in request expired. Please try again.');
    $credId = (string) ($cred['rawId'] ?? $cred['id'] ?? '');
    $pk = db_one('SELECT * FROM passkeys WHERE credential_id = ?', [$credId]);
    if (!$pk) throw new RuntimeException('This passkey is not registered here. It may have been removed from your account.');
    $user = db_one('SELECT * FROM users WHERE id = ?', [(int) $pk['user_id']]);
    if (!$user || !$user['active']) throw new RuntimeException('This account is disabled.');
    $handle = (string) ($cred['response']['userHandle'] ?? '');
    if ($handle !== '' && !hash_equals(b64url_encode(webauthn_user_handle((int) $user['id'])), $handle)) throw new RuntimeException('Passkey does not belong to this account.');

    $clientJson = b64url_decode((string) ($cred['response']['clientDataJSON'] ?? ''));
    webauthn_check_client_data($clientJson, 'webauthn.get', $pending['challenge']);
    $authData = b64url_decode((string) ($cred['response']['authenticatorData'] ?? ''));
    $ad = webauthn_parse_auth_data($authData);
    if (!hash_equals(hash('sha256', webauthn_rp_id(), true), $ad['rpIdHash'])) throw new RuntimeException('The passkey belongs to a different website.');
    if (!$ad['up'] || !$ad['uv']) throw new RuntimeException('Your device did not verify you (PIN, fingerprint or face).');
    $sig = b64url_decode((string) ($cred['response']['signature'] ?? ''));
    $ok = openssl_verify($authData . hash('sha256', $clientJson, true), $sig, $pk['public_key'], OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        auth_log('passkey_failed', (int) $user['id'], $user['email']);
        throw new RuntimeException('The passkey signature could not be verified.');
    }
    // Signature counter: a counter that goes backwards suggests a cloned authenticator
    $stored = (int) $pk['sign_count'];
    if (($stored > 0 || $ad['signCount'] > 0) && $ad['signCount'] <= $stored) {
        auth_log('passkey_counter_error', (int) $user['id'], $user['email']);
        throw new RuntimeException('This passkey looks like a copy of another device and was blocked. Remove it and add it again.');
    }
    db_update('passkeys', ['sign_count' => $ad['signCount'], 'backed_up' => (int) $ad['bs'], 'last_used_at' => now()], (int) $pk['id']);
    return $user;
}
