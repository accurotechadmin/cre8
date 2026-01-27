# Authentication & Identity

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** This document is the authoritative specification for authentication mechanisms: RS256 JWT structure and claims, ApiKey exchange, Owner login, refresh token lifecycle (single-use rotation), and JWKS endpoint.

**SSoT Ownership:**
- JWT signing/verification rules (algorithm, claims, issuer/audience, leeway, token typing)
- Refresh token lifecycle, rotation, and replay handling
- ApiKey exchange request format and verification rules
- Owner login flow and password verification
- JWKS endpoint contract and signing-key rotation (`kid` overlap)
- Auth-related environment variables

---

## 1. Principals and Surfaces

CRE8.pw supports two types of principals:

### 1.1 Owner (Human Principal)
- **Authentication Method:** Password (email + password)
- **Login Endpoint:** `POST /console/login`
- **Token Type:** Owner JWT with `typ=owner`
- **Primary Surface:** Console (HTML + JSON)
- **Capabilities:** Mint Primary Author Keys, manage groups/keychains, view lineage

### 1.2 Key (Machine Principal)
- **Authentication Method:** ApiKey (`key_public_id` + `key_secret`)
- **Exchange Endpoint:** `POST /api/auth/exchange`
- **Token Type:** Key JWT with `typ=key`
- **Primary Surface:** Gateway (JSON API)
- **Capabilities:** Create posts, mint child keys, read feeds (varies by key type and permissions)

**Token Typing:** All JWTs include an `typ` claim:
- `typ=owner` → enforced by `JwtOwnerMiddleware` (Console JSON)
- `typ=key` → enforced by `JwtKeyMiddleware` (Gateway JSON)

This prevents token confusion attacks (using an Owner token on Gateway endpoints or vice versa).

---

## 2. JWT Structure (RS256)

### 2.1 Algorithm and Signing

**Algorithm:** RS256 (RSA Signature with SHA-256)

**Signing:**
- Private key: `JWT_PRIVATE_KEY_PATH` (PEM format, never exposed)
- Public key: `JWT_PUBLIC_KEY_PATH` (PEM format, published via JWKS)
- Key ID (`kid`): Included in JWT header for key rotation support

**Why RS256?**
- Asymmetric: Public key can be distributed safely (via JWKS)
- Verifiable by third parties without sharing secrets
- Supports key rotation with overlapping validity

### 2.2 Standard Claims (Normative)

All access JWTs **must** include:

| Claim | Description | Example |
|---|---|---|
| `iss` | Issuer (configured via `JWT_ISSUER`) | `https://cre8.pw` |
| `sub` | Subject (see Subject Convention below) | `owner:3f2a9c1c...` or `key:b5a1e8c0...` |
| `aud` | Audience (configured via `JWT_AUDIENCE`) | `https://cre8.pw/console` or `https://cre8.pw/api` |
| `iat` | Issued At (Unix timestamp) | `1737456000` |
| `nbf` | Not Before (Unix timestamp) | `1737456000` |
| `exp` | Expiration (Unix timestamp) | `1737456900` (15 min later) |

**Verification:**
- `iss` must match `JWT_ISSUER` env value
- `aud` must match expected audience (middleware responsibility)
- `exp` must be in the future (with `JWT_LEEWAY` tolerance)
- `nbf` must be in the past (with `JWT_LEEWAY` tolerance)

### 2.3 Application Claims (Normative)

In addition to standard claims, CRE8.pw JWTs include:

| Claim | Type | Required For | Description |
|---|---|---|---|
| `typ` | string | All JWTs | Token type: `owner` or `key` |
| `owner_id` | string (hex32) | Owner JWTs | Owner internal ID |
| `key_id` | string (hex32) | Key JWTs | Key internal ID |
| `key_public_id` | string (apub_...) | Key JWTs (optional) | Key public ID (debug/correlation only, not used for authz) |
| `roles` | array[string] | All JWTs | Role names (e.g., `["owner"]`, `["author"]`) |
| `permissions` | array[string] | All JWTs | Explicit permission strings (e.g., `["posts:create", "keys:issue"]`) |

**Identifier Formats:**
- `owner_id` and `key_id` are always `hex32` (32-character lowercase hex)
- `key_public_id` (if present) is always `apub_...` format
- See **[identifier-encoding.md](../10-reference/identifier-encoding.md)** for complete rules

### 2.4 Subject Convention (Normative)

To keep `sub` human-readable and unambiguous:

**Owner tokens:**
```
sub = "owner:<owner_id>"
```
Example: `owner:3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b`

**Key tokens:**
```
sub = "key:<key_id>"
```
Example: `key:b5a1e8c0d9f04c3aa1b2c3d4e5f60718`

**Important:** `sub` is for human readability and logging. The explicit `owner_id` / `key_id` claims are used for database foreign keys and authorization checks.

### 2.5 Example Owner JWT (Reference)

**JWT Payload:**
```json
{
  "iss": "https://cre8.pw",
  "sub": "owner:3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "aud": "https://cre8.pw/console",
  "iat": 1737456000,
  "nbf": 1737456000,
  "exp": 1737456900,
  "typ": "owner",
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "roles": ["owner"],
  "permissions": ["keys:issue", "keys:read", "keys:rotate", "keys:state:update", "groups:manage", "keychains:manage", "posts:admin:read", "posts:access:manage"]
}
```

**JWT Header:**
```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "cre8-rs256-2026-01"
}
```

### 2.6 Example Key JWT (Reference)

**JWT Payload:**
```json
{
  "iss": "https://cre8.pw",
  "sub": "key:b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "aud": "https://cre8.pw/api",
  "iat": 1737456000,
  "nbf": 1737456000,
  "exp": 1737456900,
  "typ": "key",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_public_id": "apub_8cd1a2b3c4d5e6f7",
  "roles": ["author"],
  "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write", "groups:read"]
}
```

---

## 3. ApiKey Exchange (Key Authentication)

### 3.1 ApiKey Format

ApiKeys consist of two parts:
1. **Key Public ID:** `apub_...` (stored in `key_public_ids` table, indexed)
2. **Key Secret:** Long random string (stored as Argon2id hash in `keys.key_secret_hash`)

**Format for Exchange:**
```
Authorization: ApiKey <key_public_id>:<key_secret>
```

Example:
```
Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

### 3.2 Exchange Endpoint

**Endpoint:** `POST /api/auth/exchange`

**Request Headers:**
```http
POST /api/auth/exchange HTTP/1.1
Host: cre8.pw
Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**No request body required.**

**Server Process:**
1. Parse `Authorization` header
2. Extract `key_public_id` and `key_secret`
3. Lookup `key_id` via `key_public_ids.key_public_id`
4. Load key record from `keys` table
5. Verify `key_secret` against `keys.key_secret_hash` using Argon2id
6. Verify key is `active=1`
7. Load permissions from `keys.permissions_json`
8. Generate access JWT (15 min TTL) and refresh token (30 day TTL)
9. Store refresh token (hashed) in `refresh_tokens` table

**Response (Success):**
```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImNyZTgtcnMyNTYtMjAyNi0wMSJ9...",
    "refresh_token": "rt_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "expires_in": 900
  }
}
```

**Response (Failure - Invalid ApiKey):**
```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid credentials",
    "details": {},
    "request_id": null
  }
}
```

**Security Rule:** Never reveal whether `key_public_id` exists. Always return generic "Invalid credentials" for:
- Non-existent `key_public_id`
- Wrong `key_secret`
- Inactive key (`active=0`)

### 3.3 Use Count and Device Limit Enforcement (Optional)

For Use Keys with `use_count` or `device_limit` set:

**Use Count:**
- Track in `keys.use_count_current`
- Increment on each successful authentication (or per request, implementation choice)
- Reject when `use_count_current >= use_count_limit`
- Return `403 forbidden` with `error.code = "use_limit_exceeded"`

**Device Limit:**
- Track in `key_devices` table: `key_id`, `device_fingerprint`, `first_seen_at`
- Device fingerprint: hash of IP + User-Agent (or more sophisticated fingerprinting)
- Count distinct devices: `SELECT COUNT(DISTINCT device_fingerprint) FROM key_devices WHERE key_id = ?`
- Reject when count >= `device_limit`
- Return `403 forbidden` with `error.code = "device_limit_exceeded"`

---

## 4. Owner Login (Password Authentication)

### 4.1 Registration

**Endpoint:** `POST /console/owners`

**Request:**
```json
{
  "email": "alice@example.com",
  "password": "SecurePassword123!"
}
```

**Server Process:**
1. Validate email format (RFC 5322)
2. Validate password strength (min 8 characters, complexity rules optional)
3. Check uniqueness: `SELECT id FROM owners WHERE email = ?`
4. Hash password with Argon2id
5. Insert: `INSERT INTO owners (id, email, password_hash) VALUES (?, ?, ?)`
6. Emit audit event (`owners:register`)

**Response:**
```json
{
  "data": {
    "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b"
  }
}
```

**Note:** No automatic login. User must subsequently call `/console/login`.

### 4.2 Login

**Endpoint:** `POST /console/login`

**Request:**
```json
{
  "email": "alice@example.com",
  "password": "SecurePassword123!"
}
```

**Server Process:**
1. Lookup owner by email
2. Verify password against `password_hash` using Argon2id
3. Load owner permissions (typically static: all owner permissions)
4. Generate Owner JWT (`typ=owner`, 15 min TTL)
5. Generate refresh token (30 day TTL)
6. Store refresh token (hashed) in `refresh_tokens` with `subject_type=owner`, `subject_id=<owner_id>`
7. Emit audit event (`owners:login`)

**Response:**
```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImNyZTgtcnMyNTYtMjAyNi0wMSJ9...",
    "refresh_token": "rt_b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7",
    "expires_in": 900
  }
}
```

**Failure (Invalid Credentials):**
```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid email or password",
    "details": {},
    "request_id": null
  }
}
```

**Security Rule:** Never reveal whether email exists. Always return generic "Invalid email or password".

---

## 5. Refresh Token Lifecycle (Single-Use Rotation)

### 5.1 Refresh Token Storage

**Table:** `refresh_tokens`

**Fields:**
- `id` (BINARY(16), PK)
- `subject_type` (enum: `owner`, `key`)
- `subject_id` (BINARY(16), references `owners.id` or `keys.id`)
- `token_hash` (VARCHAR, Argon2id hash of refresh token)
- `issued_at` (TIMESTAMP)
- `expires_at` (TIMESTAMP, default 30 days from issue)
- `revoked_at` (TIMESTAMP, nullable)
- `rotated_at` (TIMESTAMP, nullable, marks token as rotated)
- `replaced_by_id` (BINARY(16), nullable, references new `refresh_tokens.id`)
- `ip` (VARCHAR, optional)
- `user_agent` (VARCHAR, optional)

### 5.2 Refresh Endpoint

**Endpoint:** `POST /api/auth/refresh`

**Request:**
```json
{
  "refresh_token": "rt_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6"
}
```

**Server Process:**
1. Hash provided `refresh_token` with Argon2id
2. Lookup in `refresh_tokens` where `token_hash = ?`
3. Verify:
   - Token exists
   - `expires_at` is in the future
   - `revoked_at` is NULL
   - `rotated_at` is NULL (single-use enforcement)
4. Mark token as rotated: `UPDATE refresh_tokens SET rotated_at = NOW() WHERE id = ?`
5. Generate new access JWT (15 min TTL)
6. Generate new refresh token (30 day TTL)
7. Store new refresh token with `replaced_by_id` referencing old token
8. Return new token pair

**Response (Success):**
```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImNyZTgtcnMyNTYtMjAyNi0wMSJ9...",
    "refresh_token": "rt_c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8",
    "expires_in": 900
  }
}
```

### 5.3 Replay Detection (Normative)

**If `rotated_at` is NOT NULL:**
- Token has already been used for refresh
- This is a **replay attack** or client error
- Return `401 unauthorized` with `code = "unauthorized"`
- Emit security log event (`refresh_replay_attempt`) including:
  - `subject_type`, `subject_id`
  - Original `token_hash`
  - `ip`, `user_agent`
  - Timestamp

**Security Consideration:** Replay detection is a critical security feature. Repeated replay attempts may indicate a compromised refresh token and should trigger additional alerts.

### 5.4 Revocation

Refresh tokens can be manually revoked:

**Set `revoked_at = NOW()`**

Revoked tokens are rejected on refresh attempts with `401 unauthorized`.

---

## 6. JWKS Endpoint (Public Key Publishing)

### 6.1 Endpoint Specification

**Endpoint:** `GET /.well-known/jwks.json`

**Purpose:** Publish RS256 public keys for JWT verification

**Authentication:** None (public endpoint)

**Response Format:** JSON Web Key Set (JWKS)

### 6.2 Response Structure (Normative)

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "alg": "RS256",
      "kid": "cre8-rs256-2026-01",
      "n": "<base64url-encoded-modulus>",
      "e": "<base64url-encoded-exponent>"
    }
  ]
}
```

**Fields:**
- `kty`: Key type (always `RSA`)
- `use`: Key usage (always `sig` for signature verification)
- `alg`: Algorithm (always `RS256`)
- `kid`: Key ID (must match JWT header `kid`)
- `n`: RSA modulus (base64url-encoded)
- `e`: RSA exponent (base64url-encoded)

### 6.3 Response Headers (Normative)

```http
Content-Type: application/json
Cache-Control: public, max-age=600, must-revalidate
Access-Control-Allow-Origin: * (or allowlist from CORS_ALLOWED_ORIGINS)
```

**Caching:**
- `max-age=600` (10 minutes) minimizes JWKS fetch load
- Clients should cache and only re-fetch on verification failure with unknown `kid`

### 6.4 Key Rotation (Normative)

When rotating signing keys:

1. **Generate new key pair** (new `kid`, e.g., `cre8-rs256-2026-02`)
2. **Add new key to JWKS** alongside old key (overlap period)
3. **Start signing new JWTs** with new `kid`
4. **Keep old key in JWKS** for at least one access token TTL (15 min + buffer, recommend 1 hour)
5. **Remove old key** from JWKS after overlap period

**Overlap ensures:** Tokens signed with old key can still be verified during the transition.

### 6.5 Implementation Reference

```php
$app->get('/.well-known/jwks.json', function ($req, $res) {
    $pubPath = $_ENV['JWT_PUBLIC_KEY_PATH'];
    $pem = file_get_contents($pubPath);
    $publicKey = openssl_pkey_get_public($pem);
    $details = openssl_pkey_get_details($publicKey);

    $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
    $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

    // Derive kid from public key thumbprint
    $kid = rtrim(strtr(base64_encode(hash('sha256', $details['key'], true)), '+/', '-_'), '=');

    $jwks = [
        'keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $n,
            'e' => $e,
        ]]
    ];

    $res->getBody()->write(json_encode($jwks));
    return $res
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'public, max-age=600, must-revalidate');
});
```

---

## 7. Environment Configuration (Normative)

Auth-related settings from `.env`:

```bash
# JWT Configuration
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # Or /api for gateway
JWT_ACCESS_TTL=900       # 15 minutes
JWT_REFRESH_TTL=2592000  # 30 days
JWT_LEEWAY=10            # Clock skew tolerance in seconds

# Hashing Configuration
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536    # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

**Bootstrap Validation:**

The application **must** fail fast at startup if:
- `JWT_PRIVATE_KEY_PATH` or `JWT_PUBLIC_KEY_PATH` is missing or unreadable
- `JWT_ISSUER` or `JWT_AUDIENCE` is not set
- Private/public keys are malformed or mismatched

**See:** **[environment-configuration.md](../10-reference/environment-configuration.md)** for complete reference.

---

## 8. Security Considerations

### 8.1 Never Log Secrets

**Never log:**
- Passwords (plaintext)
- ApiKey secrets
- Refresh tokens (plaintext)
- Private keys

**Safe to log:**
- `owner_id`, `key_id` (hex32)
- `key_public_id` (apub_...)
- JWT `sub` (readable identifier)
- IP, User-Agent
- Timestamps, request paths

### 8.2 Token Storage (Client-Side)

**Console (Browser):**
- Store access token in `HttpOnly`, `Secure` cookie (recommended)
- Or `localStorage` with XSS precautions
- Never expose tokens in URL query parameters

**Gateway (Programmatic):**
- Store tokens in environment variables or secure credential store
- Never commit tokens to version control

### 8.3 Issuer and Audience Checking

**Critical:** JWT middleware must strictly enforce:
- `iss` matches `JWT_ISSUER`
- `aud` matches expected audience

Failure to check allows tokens issued by other systems to be accepted.

### 8.4 Refresh Token Security

- Store hashed (Argon2id) in database
- Single-use rotation prevents replay attacks
- Detect replay attempts and log to `security` channel
- Revoke all refresh tokens for a principal on password change or suspected compromise

---

## 9. Token Flow Diagrams (Reference)

### 9.1 ApiKey Exchange Flow

```
Client                          CRE8.pw Server
  |                                    |
  |  POST /api/auth/exchange           |
  |  Authorization: ApiKey pub:secret  |
  | ---------------------------------> |
  |                                    |
  |                  Verify pub+secret |
  |                  Generate tokens   |
  |                                    |
  |  { access_token, refresh_token }  |
  | <--------------------------------- |
  |                                    |
  |  POST /api/posts                   |
  |  Authorization: Bearer <access>    |
  | ---------------------------------> |
  |                                    |
  |                  Verify JWT        |
  |                  Process request   |
  |                                    |
  |  { data: { post } }               |
  | <--------------------------------- |
```

### 9.2 Refresh Flow

```
Client                          CRE8.pw Server
  |                                    |
  |  POST /api/posts                   |
  |  Authorization: Bearer <expired>   |
  | ---------------------------------> |
  |                                    |
  |  401 Unauthorized                 |
  | <--------------------------------- |
  |                                    |
  |  POST /api/auth/refresh            |
  |  { refresh_token }                 |
  | ---------------------------------> |
  |                                    |
  |                  Verify refresh    |
  |                  Mark rotated      |
  |                  Issue new tokens  |
  |                                    |
  |  { access_token, refresh_token }  |
  | <--------------------------------- |
  |                                    |
  |  POST /api/posts (retry)           |
  |  Authorization: Bearer <new>       |
  | ---------------------------------> |
  |                                    |
  |  { data: { post } }               |
  | <--------------------------------- |
```

---

## 10. Troubleshooting

**401 Unauthorized:**
- Check access token expiration (`exp` claim)
- Verify `iss` matches `JWT_ISSUER`
- Verify `aud` matches expected audience
- Check JWKS endpoint for `kid` mismatch
- For ApiKey exchange: verify `key_public_id` and `key_secret` are correct

**403 Forbidden (after successful auth):**
- Token is valid but lacks required permissions
- See **[authorization.md](authorization.md)**

**Refresh replay detected:**
- Client is reusing an already-rotated refresh token
- Check client refresh logic (should discard old refresh token after successful refresh)
- Inspect `security` logs for `refresh_replay_attempt` events

**JWKS fetch failures:**
- Verify `JWT_PUBLIC_KEY_PATH` is readable
- Check JWKS endpoint returns valid JSON
- Verify `kid` in JWT header matches a key in JWKS

---

**Next:** Proceed to **[authorization.md](authorization.md)** to understand the permission model, roles, bitmasks, and enforcement rules.
