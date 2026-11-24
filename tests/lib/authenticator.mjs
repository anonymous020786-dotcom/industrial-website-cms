// A software WebAuthn authenticator (ES256 / P-256) for testing passkey registration and sign-in
// against the real server code — the same bytes a phone or Windows Hello would produce.
import { createHash, generateKeyPairSync, randomBytes, sign } from 'node:crypto';

const b64url = (buf) => Buffer.from(buf).toString('base64url');
const sha256 = (data) => createHash('sha256').update(data).digest();

/* Minimal CBOR encoder: unsigned/negative ints, byte strings, text, arrays, maps */
function head(major, n) {
  if (n < 24) return Buffer.from([(major << 5) | n]);
  if (n < 256) return Buffer.from([(major << 5) | 24, n]);
  if (n < 65536) { const b = Buffer.alloc(3); b[0] = (major << 5) | 25; b.writeUInt16BE(n, 1); return b; }
  const b = Buffer.alloc(5); b[0] = (major << 5) | 26; b.writeUInt32BE(n, 1); return b;
}
export function cbor(v) {
  if (Number.isInteger(v)) return v >= 0 ? head(0, v) : head(1, -1 - v);
  if (Buffer.isBuffer(v)) return Buffer.concat([head(2, v.length), v]);
  if (typeof v === 'string') { const b = Buffer.from(v, 'utf8'); return Buffer.concat([head(3, b.length), b]); }
  if (Array.isArray(v)) return Buffer.concat([head(4, v.length), ...v.map(cbor)]);
  if (v instanceof Map) return Buffer.concat([head(5, v.size), ...[...v].flatMap(([k, x]) => [cbor(k), cbor(x)])]);
  throw new Error('cbor: unsupported value');
}

export class SoftAuthenticator {
  constructor() {
    this.keys = generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
    this.credId = randomBytes(32);
    this.counter = 0;
    this.userHandle = null;
  }

  coseKey() {
    const jwk = this.keys.publicKey.export({ format: 'jwk' });
    return new Map([[1, 2], [3, -7], [-1, 1], [-2, Buffer.from(jwk.x, 'base64url')], [-3, Buffer.from(jwk.y, 'base64url')]]);
  }

  authData(rpId, flags, withCredential) {
    const count = Buffer.alloc(4); count.writeUInt32BE(this.counter);
    const parts = [sha256(rpId), Buffer.from([flags]), count];
    if (withCredential) {
      const len = Buffer.alloc(2); len.writeUInt16BE(this.credId.length);
      parts.push(Buffer.alloc(16), len, this.credId, cbor(this.coseKey()));
    }
    return Buffer.concat(parts);
  }

  /** Respond to PublicKeyCredentialCreationOptions (JSON from the server). */
  create(options, origin, { uv = true, rpIdOverride } = {}) {
    this.userHandle = options.user.id;
    const clientData = Buffer.from(JSON.stringify({ type: 'webauthn.create', challenge: options.challenge, origin, crossOrigin: false }));
    const flags = 0x01 | (uv ? 0x04 : 0) | 0x40; // UP, UV, AT
    const att = cbor(new Map([['fmt', 'none'], ['attStmt', new Map()], ['authData', this.authData(rpIdOverride || options.rp.id, flags, true)]]));
    return { id: b64url(this.credId), rawId: b64url(this.credId), type: 'public-key', response: { clientDataJSON: b64url(clientData), attestationObject: b64url(att), transports: ['internal'] } };
  }

  /** Respond to PublicKeyCredentialRequestOptions. */
  get(options, origin, { uv = true, counter, originOverride, rpIdOverride } = {}) {
    this.counter = counter ?? this.counter + 1;
    const clientData = Buffer.from(JSON.stringify({ type: 'webauthn.get', challenge: options.challenge, origin: originOverride || origin, crossOrigin: false }));
    const ad = this.authData(rpIdOverride || options.rpId, 0x01 | (uv ? 0x04 : 0), false);
    const signature = sign('sha256', Buffer.concat([ad, sha256(clientData)]), this.keys.privateKey); // DER-encoded ECDSA
    return { id: b64url(this.credId), rawId: b64url(this.credId), type: 'public-key', response: { clientDataJSON: b64url(clientData), authenticatorData: b64url(ad), signature: b64url(signature), userHandle: this.userHandle || '' } };
  }
}
