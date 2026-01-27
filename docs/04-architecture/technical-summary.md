# CRE8.pw — Complete Technical Deep-Dive

## Executive Summary

**CRE8.pw** is a **secure content creation and sharing platform** built on a **hierarchical, key-based authentication system** with **full provenance tracking**. It enables content creators to maintain complete control over their work while selectively sharing access through a sophisticated permission and key management system.

---

## 1. Core Architecture Philosophy

### 1.1 Dual-Surface Design

CRE8.pw operates as a **single Slim 4 PHP application** exposing two distinct "surfaces":

| Surface | Purpose | Principal Type | Authentication |
|---------|---------|----------------|----------------|
| **Console** | Human-facing management interface | Owners (humans) | Password → Owner JWT (`typ=owner`) |
| **Gateway** | Machine-facing programmatic API | Keys (machines) | ApiKey → Key JWT (`typ=key`) |

This separation enforces strict boundaries—an Owner token cannot access Gateway endpoints, and a Key token cannot access Console endpoints. The `typ` claim in the JWT is validated by dedicated middleware for each surface.

### 1.2 Request Pipeline Architecture

```
HTTP Request
    ↓
public/index.php (Entry Point)
    ↓
┌──────────────────────────────────────┐
│       MIDDLEWARE PIPELINE            │
│  ┌────────────────────────────────┐  │
│  │ 1. HttpsMiddleware             │  │  ← Enforce HTTPS, set HSTS headers
│  │ 2. CorsMiddleware              │  │  ← Apply CORS from env allowlist
│  │ 3. RateLimitMiddleware         │  │  ← Throttle by IP/owner_id/key_id
│  │ 4. JwtOwner/JwtKeyMiddleware   │  │  ← Verify JWT, attach principal context
│  │ 5. Body Parsing Middleware     │  │  ← Parse JSON/form data
│  │ 6. ValidationMiddleware        │  │  ← Validate per "METHOD /pattern"
│  │ 7. Routing Middleware          │  │  ← Slim route dispatch
│  │ 8. ErrorHandlingMiddleware     │  │  ← Normalize errors to standard schema
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
    ↓
Controller (thin HTTP adapter)
    ↓
Service (business logic, permissions, transactions, audits)
    ↓
Repository (PDO prepared statements, hex32 ↔ BINARY(16) conversion)
    ↓
MariaDB 11.4.x (utf8mb4_bin collation)
```

### 1.3 Four Middleware Pipeline Variants

1. **Public API Pipeline**: Health checks, JWKS, auth endpoints — no JWT required
2. **Console JSON Pipeline**: Owner-protected endpoints — Owner JWT (`typ=owner`)
3. **Gateway JSON Pipeline**: Key-protected endpoints — Key JWT (`typ=key`)
4. **Console HTML Pipeline**: Browser UI — CSRF protection enabled (HTML forms only)

**Critical CSRF Rule**: CSRF middleware is **only** applied to HTML routes. JSON endpoints (both Console and Gateway) never require CSRF because they use stateless Bearer token authentication.

---

## 2. Identity Model: Principals

### 2.1 Owners (Human Principals)

**What they are**: Human users who register with email/password.

**Authentication flow**:
```
1. POST /console/owners         → Create account (email + password hashed with Argon2id)
2. POST /console/login          → Authenticate → receive Owner JWT + refresh token
3. Use Bearer token on /console/* JSON endpoints
```

**Capabilities**:
- Mint **Primary Author Keys** (the root machine credentials)
- Manage **Groups** (collections of keys for bulk access grants)
- Manage **Keychains** (collections for organizational purposes)
- View all **Posts** created by their keys (admin view)
- Grant/revoke **Group-level access** to posts
- View **key lineage** (provenance chain)
- **Rotate, activate, deactivate** keys
- **Cascade deactivation** (disable a key + all its descendants)

**JWT Structure** (`typ=owner`):
```json
{
  "iss": "https://cre8.pw",
  "sub": "owner:3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "aud": "https://cre8.pw/console",
  "typ": "owner",
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "roles": ["owner"],
  "permissions": ["keys:issue", "keys:read", "keys:rotate", "groups:manage", ...]
}
```

### 2.2 Keys (Machine Principals)

**What they are**: Machine credentials used for programmatic API access. Three types exist in a strict hierarchy.

#### 2.2.1 Primary Author Key

**What it is**: The **root** machine principal, minted by an Owner via Console.

**Capabilities**:
- Create **posts**
- Mint **Secondary Author Keys** (delegated authoring)
- Mint **Use Keys** (read/comment only)
- Full authoring and delegation privileges

**Lineage fields** (all NULL or self-referencing since it's the root):
```
issued_by_key_id = NULL
parent_key_id = NULL
initial_author_key_id = <self> (points to itself)
```

#### 2.2.2 Secondary Author Key

**What it is**: A **delegated** machine principal, minted by a Primary or another Secondary Author Key via Gateway.

**Capabilities**:
- Create **posts**
- Mint **Secondary Author Keys** (further delegation, within permission envelope)
- Mint **Use Keys**
- Same authoring capabilities as Primary, but permissions constrained by parent

**Lineage fields** (populated, tracking provenance):
```
issued_by_key_id = <key that issued this key>
parent_key_id = <immediate parent key>
initial_author_key_id = <root Primary Author Key — immutable>
```

#### 2.2.3 Use Key

**What it is**: A **restricted** machine principal for **interaction only**. Cannot create content or mint keys.

**Capabilities**:
- **Read** posts (if granted VIEW mask)
- **Comment** on posts (if granted COMMENT mask)
- Access **feeds**

**Absolute restrictions** (enforced at mint time):
- **CANNOT** have `posts:create` permission
- **CANNOT** have `keys:issue` permission
- **CANNOT** mint any keys
- **CANNOT** create posts

**Optional constraints** (set at mint time):
- `use_count`: Maximum number of uses (1-time, N-times, or unlimited)
- `device_limit`: Maximum distinct devices that can use this key

**JWT Structure** (`typ=key`):
```json
{
  "iss": "https://cre8.pw",
  "sub": "key:b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "aud": "https://cre8.pw/api",
  "typ": "key",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_public_id": "apub_8cd1a2b3c4d5e6f7",
  "roles": ["use"],
  "permissions": ["posts:read", "comments:write"]
}
```

---

## 3. Authorization Model

### 3.1 Two-Layer Authorization System

CRE8.pw uses a **dual-check** authorization model:

1. **Global Permission Strings**: Capabilities that apply system-wide (e.g., `posts:create`, `keys:issue`)
2. **Post-Scoped Bitmasks**: Fine-grained permissions for individual posts (VIEW, COMMENT, MANAGE_ACCESS)

**Critical Rule**: Most post-related actions require **BOTH**:
- The appropriate global permission string in the JWT
- The appropriate bitmask bit granted via `post_access` table

### 3.2 Permission Strings Catalog

#### Owner Permissions (Console-scoped)

| Permission | Meaning | Used For |
|------------|---------|----------|
| `owners:manage` | Manage owner profile/settings | Self-management |
| `keys:issue` | Mint Primary Author Keys | Console key creation |
| `keys:read` | List/view keys | Console inventory |
| `keys:rotate` | Rotate keys | Key lifecycle |
| `keys:state:update` | Activate/deactivate keys | Key state management |
| `groups:manage` | Full CRUD on groups + membership | Group administration |
| `keychains:manage` | Manage keychains + membership | Keychain administration |
| `posts:admin:read` | Admin view of posts | Console post inventory |
| `posts:access:manage` | Grant/revoke group access | Access control |

#### Key Permissions (Gateway-scoped)

| Permission | Meaning | Key Types Allowed |
|------------|---------|-------------------|
| `keys:issue` | Mint Secondary/Use Keys | Primary, Secondary |
| `posts:create` | Create posts | Primary, Secondary (**NEVER Use**) |
| `posts:read` | Read posts (requires VIEW mask) | All |
| `comments:write` | Write comments (requires COMMENT mask) | All |
| `groups:read` | Read groups | All |
| `keychains:manage` | Manage external keychains | All (if granted) |
| `posts:access:manage` | Manage post access (requires MANAGE_ACCESS mask) | Primary, Secondary |

### 3.3 Post Access Bitmasks

Stored in `post_access.permission_mask` as an integer:

| Bit Position | Hex Value | Name | Capability |
|--------------|-----------|------|------------|
| 0 | `0x01` | VIEW | Read/view the post |
| 1 | `0x02` | COMMENT | Create comments |
| 3 | `0x08` | MANAGE_ACCESS | Grant/revoke access |

**Presets**:
- `READ_ONLY` = `0x01` (VIEW only)
- `INTERACT` = `0x03` (VIEW + COMMENT)
- `ADMIN` = `0x0B` (VIEW + COMMENT + MANAGE_ACCESS)

### 3.4 Combined Authorization Examples

| Action | Global Permission | Post Mask Required | Notes |
|--------|-------------------|-------------------|-------|
| Read a post | `posts:read` | VIEW (0x01) | Both required |
| Comment on a post | `comments:write` | COMMENT (0x02) | Both required |
| Grant access to a post | `posts:access:manage` | MANAGE_ACCESS (0x08) | Both required |
| Create a post | `posts:create` | N/A | No post mask (creating new) |
| Mint a Use Key | `keys:issue` | N/A | Not post-scoped |

### 3.5 Permission Envelope Rule

**Critical Invariant**: Child key permissions must be a **strict subset** (⊆) of parent key permissions.

```
Parent: ["posts:create", "keys:issue", "posts:read", "comments:write"]

✅ Valid child:   ["posts:create", "posts:read"]
✅ Valid child:   ["posts:read", "comments:write"]
❌ Invalid child: ["posts:create", "groups:manage"]  // groups:manage not in parent
```

**Immutability**: Once minted, a key's permissions **cannot change**. To modify permissions, the key must be **rotated** (retired and replaced with a new key).

### 3.6 Visibility vs Access (404 vs 403)

| Scenario | Response | Rationale |
|----------|----------|-----------|
| Resource doesn't exist | 404 Not Found | Standard behavior |
| Resource exists but principal lacks VIEW | 404 Not Found | **Hide existence** |
| Resource visible but lacks action permission | 403 Forbidden | User knows it exists |

This prevents information leakage—attackers cannot enumerate resources they can't access.

---

## 4. Authentication Mechanisms

### 4.1 RS256 JWT Signing

**Algorithm**: RS256 (RSA + SHA-256 asymmetric signatures)

**Benefits**:
- Private key never leaves the server
- Public key can be distributed via JWKS for verification
- Supports key rotation with `kid` overlap

**Configuration**:
```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # or /api
JWT_ACCESS_TTL=900       # 15 minutes
JWT_REFRESH_TTL=2592000  # 30 days
JWT_LEEWAY=10            # Clock skew tolerance
```

### 4.2 ApiKey Exchange (Key Authentication)

**Format**:
```
Authorization: ApiKey <key_public_id>:<key_secret>
```

**Example**:
```
Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**Exchange Flow**:
```
POST /api/auth/exchange
Authorization: ApiKey apub_...:<secret>

↓

1. Parse header, extract public_id + secret
2. Lookup key_id via key_public_ids table
3. Load key record from keys table
4. Verify secret against Argon2id hash
5. Verify key is active
6. Generate access JWT (15 min) + refresh token (30 days)
7. Return tokens
```

**Security**: Never reveal whether `key_public_id` exists. Always return generic "Invalid credentials" for any failure.

### 4.3 Owner Login (Password Authentication)

**Flow**:
```
POST /console/login
{ "email": "...", "password": "..." }

↓

1. Lookup owner by email
2. Verify password against Argon2id hash
3. Generate Owner JWT (15 min) + refresh token (30 days)
4. Return tokens
```

### 4.4 Refresh Token Lifecycle (Single-Use Rotation)

**Security Model**: Each refresh token can only be used **once**. Using it generates a new token pair and marks the old one as rotated.

**Storage**:
```sql
refresh_tokens:
  - id (BINARY(16))
  - subject_type ('owner' | 'key')
  - subject_id (BINARY(16))
  - token_hash (Argon2id hash)
  - issued_at, expires_at
  - revoked_at (nullable)
  - rotated_at (nullable)       ← Set when used
  - replaced_by_id (nullable)   ← Points to new token
```

**Replay Detection**: If `rotated_at IS NOT NULL`, the token has already been used. This is a **replay attack**:
- Return 401 Unauthorized
- Log security event (`refresh:replay_attempt`)
- Include IP, User-Agent, subject info in log

### 4.5 JWKS Endpoint

**Path**: `GET /.well-known/jwks.json`

**Purpose**: Publish RS256 public keys for external JWT verification

**Response**:
```json
{
  "keys": [{
    "kty": "RSA",
    "use": "sig",
    "alg": "RS256",
    "kid": "cre8-rs256-2026-01",
    "n": "<base64url-modulus>",
    "e": "<base64url-exponent>"
  }]
}
```

**Key Rotation**: During rotation, both old and new keys appear in JWKS for overlap period (minimum 1 hour).

---

## 5. Key Lifecycle and Provenance

### 5.1 Hierarchical Key System

```
Owner (human)
 └─ Primary Author Key (root machine principal)
     ├─ Secondary Author Key (delegated)
     │   ├─ Secondary Author Key (further delegated)
     │   │   └─ Use Key (read/comment only)
     │   └─ Use Key
     └─ Use Key
```

**Every key traces back to its root Primary Author Key** via immutable lineage fields.

### 5.2 Minting Workflows

#### Owner Mints Primary Author Key (Console)

```http
POST /console/keys/primary
Authorization: Bearer <owner_jwt>
Content-Type: application/json

{
  "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write"],
  "label": "My Content Creation Key"
}
```

**Response** (returned ONCE, never again):
```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
  }
}
```

#### Author Key Mints Secondary (Gateway)

```http
POST /api/keys/b5a1e8c0d9f04c3aa1b2c3d4e5f60718/secondary
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "permissions": ["posts:create", "posts:read"],
  "label": "Delegated Content Key"
}
```

**Validation**:
1. Parent key must have `keys:issue`
2. Child permissions ⊆ parent permissions (envelope rule)

#### Author Key Mints Use Key (Gateway)

```http
POST /api/keys/b5a1e8c0d9f04c3aa1b2c3d4e5f60718/use
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "permissions": ["posts:read", "comments:write"],
  "label": "Share Link for Alice",
  "use_count": 1,
  "device_limit": null
}
```

**Validation**:
1. Parent key must have `keys:issue`
2. Child permissions ⊆ parent permissions
3. **MUST NOT** include `posts:create` or `keys:issue`

### 5.3 Use Count Enforcement

```php
if ($key->use_count_limit !== null) {
    if ($key->use_count_current >= $key->use_count_limit) {
        throw new ForbiddenException('Use limit exceeded', 'use_limit_exceeded');
    }
    KeyRepository::incrementUseCount($key->id);
}
```

### 5.4 Device Limit Enforcement

```php
if ($key->device_limit !== null) {
    $fingerprint = hash('sha256', $ip . $userAgent);
    $deviceCount = KeyDeviceRepository::countDistinct($key->id);

    if (!KeyDeviceRepository::exists($key->id, $fingerprint)) {
        if ($deviceCount >= $key->device_limit) {
            throw new ForbiddenException('Device limit exceeded', 'device_limit_exceeded');
        }
        KeyDeviceRepository::register($key->id, $fingerprint);
    }
}
```

### 5.5 Key Rotation

**Purpose**: Replace a key while preserving lineage

```http
POST /console/keys/{keyId}/rotate
Authorization: Bearer <owner_jwt>
```

**Process**:
1. Load old key
2. Create new key with same permissions and lineage
3. Set `rotated_from_id = <old key>` on new key
4. Set `rotated_to_id = <new key>`, `retired_at = NOW()`, `active = 0` on old key
5. Return new key credentials

### 5.6 Cascade Deactivation

**Purpose**: Disable a key and all its descendants in one operation

```http
POST /console/keys/{keyId}/deactivate?cascade=true
```

**SQL (Recursive CTE)**:
```sql
WITH RECURSIVE descendants AS (
  SELECT id FROM keys WHERE id = ?
  UNION ALL
  SELECT k.id FROM keys k
  INNER JOIN descendants d ON k.parent_key_id = d.id
)
UPDATE keys SET active = 0 WHERE id IN (SELECT id FROM descendants);
```

### 5.7 Lineage Immutability

**These fields NEVER change after mint**:
- `issued_by_key_id`
- `parent_key_id`
- `initial_author_key_id`

This ensures full accountability and provenance tracking.

---

## 6. Post Sharing and Access Control

### 6.1 Post Creation

```http
POST /api/posts
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "content": "This is my first post!",
  "title": "Hello CRE8.pw"
}
```

**Stored with**:
- `author_key_id`: The key that created this post
- `initial_author_key_id`: Root Primary Author Key (for provenance)

**Default visibility**: Posts are **private by default**. No access grants exist until explicitly created.

### 6.2 Post Access Table

```sql
post_access:
  - id (BINARY(16))
  - post_id (BINARY(16))
  - target_type ('key' | 'group')
  - target_id (BINARY(16))
  - permission_mask (INT)
  - created_at

UNIQUE(post_id, target_type, target_id)
```

### 6.3 Complete Sharing Workflow

**Scenario**: Author wants to share a post with Alice (who doesn't have an account)

```
Step 1: Create Post
────────────────────
POST /api/posts
Authorization: Bearer <author_jwt>
{ "content": "Exclusive content!", "title": "For Alice" }
→ { post_id: "abc123..." }

Step 2: Mint Use Key for Alice
────────────────────────────────
POST /api/keys/{authorKeyId}/use
Authorization: Bearer <author_jwt>
{
  "permissions": ["posts:read", "comments:write"],
  "label": "Share Link for Alice",
  "use_count": 1
}
→ {
  key_id: "use_xyz...",
  key_public_id: "apub_alice...",
  key_secret: "sec_alice..."
}

Step 3: Grant Use Key Access to Post
─────────────────────────────────────
POST /api/posts/abc123.../access
Authorization: Bearer <author_jwt>
{
  "target_type": "key",
  "target_id": "use_xyz...",
  "permission_mask": 3  // VIEW + COMMENT
}

Step 4: Share Credentials with Alice
─────────────────────────────────────
Author sends Alice:
  - ApiKey: apub_alice...:sec_alice...
  - Post ID: abc123...

Step 5: Alice Exchanges ApiKey
───────────────────────────────
POST /api/auth/exchange
Authorization: ApiKey apub_alice...:sec_alice...
→ { access_token, refresh_token }

Step 6: Alice Reads Post
─────────────────────────
GET /api/posts/abc123...
Authorization: Bearer <alice_access_token>
→ { data: { content, title, ... } }

Step 7: Alice Comments
───────────────────────
POST /api/posts/abc123.../comments
Authorization: Bearer <alice_access_token>
{ "body": "Thanks for sharing!" }
→ { comment_id, body, ... }

Step 8: Use Count Exhausted
────────────────────────────
POST /api/auth/exchange (again)
Authorization: ApiKey apub_alice...:sec_alice...
→ 403 { error: { code: "use_limit_exceeded" } }
```

### 6.4 Group-Based Access Grants

**Purpose**: Grant access to multiple keys simultaneously

```
1. Owner creates Group
   POST /console/groups
   { "name": "Team Alpha" }

2. Owner adds team members
   POST /console/groups/{groupId}/members
   { "key_id": "key1..." }

3. Owner grants Group access to Post
   POST /console/posts/{postId}/access/grant-group
   { "group_id": "...", "permission_mask": 3 }

4. All keys in "Team Alpha" can now VIEW + COMMENT
```

---

## 7. Feed System

### 7.1 Use Key Feed

**Endpoint**: `GET /api/feed/use/{useKeyId}`

**Security**: Path `{useKeyId}` **must match** JWT `key_id`. Otherwise return 404 (prevents cross-key snooping).

**Visibility Resolution**:
```sql
SELECT p.*
FROM posts p
INNER JOIN post_access pa ON p.id = pa.post_id
LEFT JOIN group_members gm ON pa.target_type = 'group' AND pa.target_id = gm.group_id
WHERE (
  (pa.target_type = 'key' AND pa.target_id = ?)
  OR
  (pa.target_type = 'group' AND gm.key_id = ?)
)
AND (pa.permission_mask & 0x01) > 0  -- VIEW bit
ORDER BY p.created_at DESC
LIMIT ?;
```

**Pagination**:
- `limit`: Posts per page (default 20, max 100)
- `before_id`: Cursor for older posts
- `since_id`: Cursor for newer posts

**Response**:
```json
{
  "data": [
    { "post_id": "...", "content": "...", "created_at": "..." }
  ],
  "paging": {
    "limit": 20,
    "cursor": "<last_post_id>"
  }
}
```

---

## 8. Data Model (Database Schema)

### 8.1 Database Configuration

- **Engine**: MariaDB 11.4.x
- **Charset**: utf8mb4
- **Collation**: utf8mb4_bin (binary, case-sensitive)
- **Access**: PDO prepared statements exclusively

### 8.2 ID Encoding

| Context | Format | Example |
|---------|--------|---------|
| Database storage | `BINARY(16)` | Raw 16 bytes |
| External/API | `hex32` | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |
| Key public ID | `apub_...` | `apub_8cd1a2b3c4d5e6f7` |

**Conversion**: `Utilities/Ids.php` provides `binaryToHex32()` and `hex32ToBinary()`.

### 8.3 Core Tables

#### `owners`
```sql
id              BINARY(16) PK
email           VARCHAR(255) UNIQUE NOT NULL
password_hash   VARCHAR(255) NOT NULL  -- Argon2id
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### `keys`
```sql
id                      BINARY(16) PK
type                    ENUM('primary','secondary','use') NOT NULL
key_secret_hash         VARCHAR(255) NOT NULL  -- Argon2id
permissions_json        JSON NOT NULL
active                  BOOLEAN DEFAULT 1
issued_by_key_id        BINARY(16) FK keys.id, NULL for primary
parent_key_id           BINARY(16) FK keys.id, NULL for primary
initial_author_key_id   BINARY(16) FK keys.id, SELF for primary
rotated_from_id         BINARY(16) FK keys.id, nullable
rotated_to_id           BINARY(16) FK keys.id, nullable
retired_at              TIMESTAMP nullable
use_count_limit         INT nullable
use_count_current       INT DEFAULT 0
device_limit            INT nullable
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

#### `key_public_ids`
```sql
id              BINARY(16) PK
key_id          BINARY(16) FK keys.id UNIQUE
key_public_id   VARCHAR(64) UNIQUE NOT NULL  -- apub_...
created_at      TIMESTAMP
```

#### `posts`
```sql
id                      BINARY(16) PK
author_key_id           BINARY(16) FK keys.id NOT NULL
initial_author_key_id   BINARY(16) FK keys.id NOT NULL  -- Provenance
title                   VARCHAR(255) nullable
content                 TEXT NOT NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

#### `post_access`
```sql
id              BINARY(16) PK
post_id         BINARY(16) FK posts.id NOT NULL
target_type     ENUM('key','group') NOT NULL
target_id       BINARY(16) NOT NULL
permission_mask INT NOT NULL
created_at      TIMESTAMP

UNIQUE(post_id, target_type, target_id)
```

#### `comments`
```sql
id                  BINARY(16) PK
post_id             BINARY(16) FK posts.id NOT NULL
created_by_key_id   BINARY(16) FK keys.id NOT NULL
body                TEXT NOT NULL
created_at          TIMESTAMP
```

#### `groups`
```sql
id          BINARY(16) PK
owner_id    BINARY(16) FK owners.id NOT NULL
name        VARCHAR(255) NOT NULL
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### `group_members`
```sql
group_id    BINARY(16) FK groups.id NOT NULL
key_id      BINARY(16) FK keys.id NOT NULL
created_at  TIMESTAMP

PRIMARY KEY (group_id, key_id)
```

#### `keychains`
```sql
id          BINARY(16) PK
name        VARCHAR(255) NOT NULL
owner_id    BINARY(16) FK owners.id nullable  -- NULL = external
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### `refresh_tokens`
```sql
id              BINARY(16) PK
subject_type    ENUM('owner','key') NOT NULL
subject_id      BINARY(16) NOT NULL
token_hash      VARCHAR(255) NOT NULL  -- Argon2id
issued_at       TIMESTAMP
expires_at      TIMESTAMP
revoked_at      TIMESTAMP nullable
rotated_at      TIMESTAMP nullable
replaced_by_id  BINARY(16) FK refresh_tokens.id nullable
ip              VARCHAR(45) nullable
user_agent      VARCHAR(255) nullable
```

#### `audit_events`
```sql
id              BINARY(16) PK
actor_type      ENUM('owner','key') NOT NULL
actor_id        BINARY(16) NOT NULL
action          VARCHAR(100) NOT NULL  -- e.g., 'keys:mint'
subject_type    VARCHAR(50) nullable
subject_id      BINARY(16) nullable
metadata_json   JSON nullable
ip              VARCHAR(45) nullable
user_agent      VARCHAR(255) nullable
created_at      TIMESTAMP
```

---

## 9. API Route Catalog

### 9.1 Public Routes (No Auth)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | Health check |
| GET | `/.well-known/jwks.json` | RS256 public keys |
| POST | `/api/auth/exchange` | ApiKey → JWT |
| POST | `/api/auth/refresh` | Rotate refresh token |
| POST | `/console/owners` | Create Owner account |
| POST | `/console/login` | Owner login |

### 9.2 Console JSON Routes (Owner JWT)

| Method | Path | Permission | Purpose |
|--------|------|------------|---------|
| POST | `/console/keys/primary` | `keys:issue` | Mint Primary Author Key |
| GET | `/console/keys` | `keys:read` | List keys |
| GET | `/console/keys/{keyId}` | `keys:read` | Get key details |
| GET | `/console/keys/{keyId}/lineage` | `keys:read` | View lineage tree |
| POST | `/console/keys/{keyId}/rotate` | `keys:rotate` | Rotate key |
| POST | `/console/keys/{keyId}/activate` | `keys:state:update` | Activate key |
| POST | `/console/keys/{keyId}/deactivate` | `keys:state:update` | Deactivate key |
| POST | `/console/groups` | `groups:manage` | Create group |
| GET | `/console/groups` | `groups:manage` | List groups |
| POST | `/console/groups/{groupId}/members` | `groups:manage` | Add member |
| DELETE | `/console/groups/{groupId}/members/{keyId}` | `groups:manage` | Remove member |
| GET | `/console/posts` | `posts:admin:read` | List posts |
| POST | `/console/posts/{postId}/access/grant-group` | `posts:access:manage` | Grant group access |

### 9.3 Gateway JSON Routes (Key JWT)

| Method | Path | Permission + Mask | Purpose |
|--------|------|-------------------|---------|
| POST | `/api/keys/{authorKeyId}/secondary` | `keys:issue` | Mint Secondary Key |
| POST | `/api/keys/{authorKeyId}/use` | `keys:issue` | Mint Use Key |
| POST | `/api/posts` | `posts:create` | Create post |
| GET | `/api/posts` | `posts:read` + VIEW | List visible posts |
| GET | `/api/posts/{postId}` | `posts:read` + VIEW | Get post |
| POST | `/api/posts/{postId}/access` | `posts:access:manage` + MANAGE_ACCESS | Grant access |
| DELETE | `/api/posts/{postId}/access/{accessId}` | `posts:access:manage` + MANAGE_ACCESS | Revoke access |
| POST | `/api/posts/{postId}/comments` | `comments:write` + COMMENT | Create comment |
| GET | `/api/posts/{postId}/comments` | `posts:read` + VIEW | List comments |
| GET | `/api/feed/use/{useKeyId}` | Use Key bearer | Use Key feed |
| GET | `/api/groups` | `groups:read` | List groups |
| GET | `/api/groups/{groupId}` | `groups:read` | Get group |

---

## 10. Response and Error Schemas

### 10.1 Success Responses

**Single object**:
```json
{ "data": { "key_id": "...", "type": "primary" } }
```

**List with pagination**:
```json
{
  "data": [...],
  "paging": { "limit": 20, "cursor": "..." }
}
```

### 10.2 Error Response

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "email": ["Email is required"],
        "password": ["Password must be at least 8 characters"]
      }
    },
    "request_id": "req_abc123"
  }
}
```

### 10.3 Error Code Taxonomy

| HTTP | Code | When |
|------|------|------|
| 400 | `bad_request` | Malformed JSON, invalid header |
| 401 | `unauthorized` | Invalid/expired token, wrong credentials |
| 403 | `forbidden` | Lacks permission/mask |
| 404 | `not_found` | Resource missing or hidden |
| 409 | `conflict` | Uniqueness violation |
| 422 | `validation_failed` | Validation errors (includes `details.fields`) |
| 429 | `rate_limited` | Rate limit exceeded (includes `retry_after_seconds`) |
| 500 | `internal_error` | Unhandled error |
| 503 | `service_unavailable` | Dependency outage |

---

## 11. Technology Stack

### Core

- **PHP**: 8.3+
- **Framework**: Slim 4.15+
- **DI**: PHP-DI 7.1+
- **Database**: MariaDB 11.4.x

### Security

- **JWT**: firebase/php-jwt 6.11+ (RS256)
- **Hashing**: Argon2id (sodium extension)
- **CORS**: neomerx/cors-psr7 3.0+
- **CSRF**: slim/csrf 1.5+ (HTML routes only)

### Validation & Utilities

- **Validation**: Respect\Validation 2.4+
- **Env**: vlucas/phpdotenv 5.6+
- **HTTP Client**: Guzzle 7.10+

### Logging & Rate Limiting

- **Logging**: Monolog 3.9+ (structured JSON)
- **Rate Limiting**: Symfony rate-limiter 7.3+ + cache 7.3+
- **Storage**: Memory or Database (no Redis/Memcached in v1)

---

## 12. Logging and Audit

### 12.1 Log Channels

| Channel | Purpose |
|---------|---------|
| `api` | Request summaries (method, path, status, latency) |
| `auth` | Auth events (exchange, login, refresh) |
| `security` | Security events (auth failures, replay attempts, rate limits) |
| `db` | Database errors |
| `guzzle.http` | Outbound HTTP requests |

### 12.2 Never Log

- Passwords (plaintext)
- ApiKey secrets
- Refresh tokens (plaintext)
- Private keys
- Stack traces (in production)

### 12.3 Audit Event Catalog

| Action | Trigger |
|--------|---------|
| `owners:register` | Owner registration |
| `owners:login` | Owner login |
| `keys:mint` | Key creation (any type) |
| `keys:rotate` | Key rotation |
| `keys:activate`, `keys:deactivate` | Key state change |
| `groups:create`, `groups:member:add` | Group management |
| `posts:create` | Post creation |
| `posts:access:grant`, `posts:access:revoke` | Access control |
| `refresh:replay_attempt` | Security: token replay detected |

---

## 13. Rate Limiting

### Configuration

```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory  # or database
```

### Keying Strategy

| Surface | Key |
|---------|-----|
| Public routes | IP address |
| Console JSON | `owner_id` (hex32) |
| Gateway JSON | `key_id` (hex32) |

### On Limit Exceeded

```json
{
  "error": {
    "code": "rate_limited",
    "message": "Too many requests",
    "details": { "retry_after_seconds": 60 }
  }
}
```

---

## 14. UI Demo (Vanilla JS)

The repository includes a **complete static UI demo** in `/UI/` that showcases the full platform without requiring a backend:

- **Framework-free**: Pure HTML/CSS/JS
- **Client-side routing**: Custom router handling all paths
- **Mock data**: Demonstrates all flows with sample data
- **Two sections**: Console (Owner) and Gateway (API Client)

**To run**:
```bash
cd UI
python -m http.server 8000
# Open http://localhost:8000
```

---

## Summary

CRE8.pw is a **sophisticated, security-first platform** that provides:

1. **Hierarchical key-based authentication** with full provenance tracking
2. **Dual-surface architecture** (Console for humans, Gateway for machines)
3. **Fine-grained authorization** combining global permissions with post-level bitmasks
4. **Controlled sharing** via Use Keys with optional use counts and device limits
5. **Complete accountability** through immutable lineage and comprehensive auditing
6. **Secure token management** with RS256 JWT and single-use refresh token rotation

The system is designed for developers who need a drop-in authentication/authorization platform that handles complex sharing scenarios while maintaining security, auditability, and complete control over content distribution.