# Single Source of Truth (SSOT) — CRE8.pw Appendix Documentation

**Version:** 1.0.0  
**Last Updated:** 2026-01-22  
**Status:** Canonical  
**Purpose:** Consolidated reference for all appendix information, helper matrices, implementation guides, and reference materials

---

## Table of Contents

1. [Identifier Encoding Rules](#1-identifier-encoding-rules)
2. [Environment Configuration Reference](#2-environment-configuration-reference)
3. [Glossary & Terminology](#3-glossary--terminology)
4. [Key Capability Matrix](#4-key-capability-matrix)
5. [Permission Matrix](#5-permission-matrix)
6. [Complete Route Inventory](#6-complete-route-inventory)
7. [Dependency Wiring Guide](#7-dependency-wiring-guide)
8. [Component Architecture](#8-component-architecture)
9. [Documentation Structure](#9-documentation-structure)
10. [Quick Reference Tables](#10-quick-reference-tables)
11. [Critical Rules Summary](#11-critical-rules-summary)
12. [Complete File Structure Reference](#12-complete-file-structure-reference)
13. [Complete Dependency Reference](#13-complete-dependency-reference)
14. [Complete Environment Variable Reference](#14-complete-environment-variable-reference)

---

## 1. Identifier Encoding Rules

### 1.1 Internal Storage Format

**Database Storage:** `BINARY(16)` for all primary keys and foreign keys

**Never expose:** Raw binary values outside repository layer

### 1.2 External Encoding Format

**Format:** `hex32` — 32-character lowercase hexadecimal (no hyphens)

**Example:** `b5a1e8c0d9f04c3aa1b2c3d4e5f60718`

**Usage:**
- Routes (`{postId}`, `{keyId}`, `{groupId}`)
- JSON responses
- JWT claims (`owner_id`, `key_id`)
- Logs and audit metadata

**Conversion Functions:**
```php
// Utilities/Ids.php
function binaryToHex32($binary): string {
    return bin2hex($binary);
}

function hex32ToBinary($hex32): string {
    return hex2bin($hex32);
}
```

### 1.3 Key Public ID Format

**Format:** `apub_` prefix + random string

**Example:** `apub_8cd1a2b3c4d5e6f7`

**Storage:** `key_public_ids.key_public_id` (VARCHAR, separate table)

**Usage:** ApiKey exchange ONLY (`Authorization: ApiKey <public_id>:<secret>`)

**NOT used in:**
- Route params named `*Id`
- JSON fields named `*_id`
- JWT claims (except optional `key_public_id` for debug/correlation)

### 1.4 Route Parameter Rules

**Convention:** All route parameters ending in `Id` are hex32, except when explicitly named `PublicId`

✅ **Valid:**
- `{postId}` → hex32
- `{keyId}` → hex32
- `{groupId}` → hex32
- `{authorKeyId}` → hex32
- `{keyPublicId}` → apub_...

❌ **Invalid:**
- Never accept `apub_...` in params named `{keyId}` or similar

### 1.5 JWT Claim Rules

**Owner tokens (`typ=owner`):**
- **MUST include:** `owner_id` (hex32)
- **MUST NOT include:** `key_id`, `key_public_id`

**Key tokens (`typ=key`):**
- **MUST include:** `key_id` (hex32)
- **MAY include:** `key_public_id` (apub_..., optional, debug/context only)

**Subject Convention:**
- Owner: `sub = "owner:<owner_id>"`
- Key: `sub = "key:<key_id>"`

**Note:** `sub` is for readability. Authorization uses explicit `owner_id` / `key_id` claims.

### 1.6 Identifier Matrix

| Identifier | Meaning | DB Storage | External Format | Where Used | Notes |
|:---:|:---:|:---:|:---:|:---:|:---:|
| `owner_id` | Owner principal ID | `owners.id` (BINARY(16)) | hex32 | JWT, request attrs, logs, JSON | Required in Owner JWTs |
| `key_id` | Key principal ID | `keys.id` (BINARY(16)) | hex32 | JWT, request attrs, logs, routes, JSON | Required in Key JWTs |
| `key_public_id` | Key public identifier | `key_public_ids.key_public_id` (VARCHAR) | apub_... | ApiKey exchange, debug logs | Never in `*_id` fields/params |
| `post_id` | Post ID | `posts.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `group_id` | Group ID | `groups.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `comment_id` | Comment ID | `comments.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `keychain_id` | Keychain ID | `keychains.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `refresh_token` | Refresh token secret | `refresh_tokens.token_hash` (VARCHAR, hashed) | opaque string | Refresh requests only | Never log plaintext |
| `request_id` | Correlation ID | N/A | UUID/ULID string | Error responses, logs | Tracing only |

### 1.7 Logging & Audit ID Rules

**Logs MUST use hex32 for all internal IDs:**
```json
{
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_public_id": "apub_8cd1a2b3c4d5e6f7"
}
```

**MAY include `key_public_id` for correlation, but:**
- MUST NEVER log `key_secret`
- MUST NEVER log refresh tokens (plaintext)
- MUST NEVER log passwords

**Audit events** use hex32 for `actor_id` and `subject_id`.

### 1.8 Implementation Guidance

**Repository Layer:**
- **Input:** Controllers pass hex32 IDs
- **Convert:** Repository converts hex32 → BINARY(16) for queries
- **Return:** Repository returns hex32 IDs to services/controllers

**ApiKey Exchange:**
- Only place accepting `key_public_id` from client
- Process: Parse header → extract `key_public_id` and `key_secret` → Lookup `key_id` via `key_public_ids.key_public_id` → Load key record using BINARY(16) `key_id` → Verify secret → Issue JWT with `key_id` (hex32)

---

## 2. Environment Configuration Reference

### 2.1 Application Configuration

```bash
APP_NAME=CRE8.pw
APP_ENV=production       # production, development, testing
APP_DEBUG=false          # true for dev, false for production
APP_URL=https://cre8.pw
```

### 2.2 Database Configuration

```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cre8pw
DB_USER=cre8_user
DB_PASS=secure_password_here
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin
DB_SSL_MODE=DISABLED     # or REQUIRED
```

**Bootstrap Validation:** Application MUST fail fast if DB connection fails.

### 2.3 JWT Configuration

```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # or /api
JWT_ACCESS_TTL=900         # 15 minutes (seconds)
JWT_REFRESH_TTL=2592000    # 30 days (seconds)
JWT_LEEWAY=10              # Clock skew tolerance (seconds)
```

**Bootstrap Validation:**
- MUST fail if `JWT_PRIVATE_KEY_PATH` or `JWT_PUBLIC_KEY_PATH` missing
- MUST fail if key files unreadable or malformed
- MUST fail if `JWT_ISSUER` or `JWT_AUDIENCE` not set

### 2.4 CORS Configuration

```bash
CORS_ALLOWED_ORIGINS=https://cre8.pw,https://www.cre8.pw
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_EXPOSED_HEADERS=X-Total-Count,X-Page-Number
```

**Format:** Comma-separated values

### 2.5 CSP Configuration

```bash
CSP_DEFAULT_SRC="'self' https://cre8.pw"
```

**Applied to:** HTML responses only

### 2.6 CSRF Configuration

```bash
CSRF_SECRET=random_32_char_secret_here
```

**Used by:** Slim CSRF Guard on HTML routes

### 2.7 Rate Limiting Configuration

```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory    # or database
```

**Format:** `<limit> per <interval>`  
**Backing:** `memory` (default) or `database` (persistent)

### 2.8 HTTP Client Configuration

```bash
HTTP_TIMEOUT=30         # Guzzle timeout (seconds)
HTTP_RETRY_MAX=3        # Max retries
```

### 2.9 Logging Configuration

```bash
LOG_CHANNEL=stack       # or daily, single
LOG_LEVEL=info          # debug, info, warning, error, critical
LOG_PATH=/app/logs
```

### 2.10 Hashing Configuration

```bash
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536    # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

**Tuning:** Adjust costs based on server capacity. Higher = more secure but slower.

### 2.11 Bootstrap Validation Checklist

Application MUST validate on startup:

✅ Database connection successful  
✅ JWT private/public keys readable and valid  
✅ `JWT_ISSUER` and `JWT_AUDIENCE` set  
✅ Required directories writable (logs/)  
✅ CORS origins parseable  
✅ Rate limit configuration valid  

**On failure:** Log error and exit with non-zero code (prevent misconfigured deployment).

---

## 3. Glossary & Terminology

### A

**Access Token** — Short-lived JWT (default 900 seconds) used to authenticate requests to protected endpoints. Contains principal identity, roles, and permissions.

**ADMIN (Bitmask Preset)** — Post access bitmask value 0x0B (VIEW + COMMENT + MANAGE_ACCESS). Grants full post interaction capabilities.

**ApiKey** — Authentication credential consisting of `key_public_id` (`apub_...`) and `key_secret`. Exchanged for access + refresh tokens via `POST /api/auth/exchange`.

**Argon2id** — Password and API key secret hashing algorithm. Industry-standard for credential storage.

**Audit Event** — Append-only record of privileged actions stored in `audit_events` table. Includes actor, subject, action, metadata, and timestamp.

**Author Key** — Machine principal that can create posts and mint child keys. Two types: Primary (root) and Secondary (delegated).

### B

**Bearer Token** — HTTP authentication scheme using access tokens. Format: `Authorization: Bearer <access_token>`

**BINARY(16)** — Internal database storage format for primary keys. Never exposed directly; converted to hex32 for external use.

**Bitmask** — Integer representation of post-scoped permissions. Each bit represents a specific capability (VIEW, COMMENT, MANAGE_ACCESS).

### C

**COMMENT (Bitmask)** — Bit 1 (0x02) of post access bitmask. Grants ability to create comments on a post.

**Console** — Human-facing surface of CRE8.pw consisting of HTML pages and Owner-protected JSON endpoints. Entry point for Owners to register, login, and manage keys/groups/posts.

**Console HTML** — Browser-facing routes (`/`, `/console/register`, `/console/login`, `/console/dashboard`) that use CSRF protection.

**Console JSON** — Owner-protected JSON API endpoints under `/console/*` that use Bearer JWT auth. No CSRF required.

**CORS** — Cross-Origin Resource Sharing. Security mechanism controlled via env variables (`CORS_ALLOWED_ORIGINS`, etc.).

**CSRF** — Cross-Site Request Forgery protection. Applied ONLY to Console HTML routes. JSON endpoints never require CSRF.

### D

**Device Limit** — Optional constraint on Use Keys limiting the number of distinct devices that can use the key. Enforced by tracking device fingerprints.

**DI (Dependency Injection)** — Pattern using PHP-DI container to wire dependencies. All components use constructor injection.

### E

**Envelope Rule** — Authorization constraint: child key permissions must be a subset (⊆) of parent key permissions.

**External Keychain** — Keychain created via Gateway API (not owner-scoped). Can be managed by Keys with `keychains:manage` permission.

### F

**Feed** — Ordered list of posts visible to a principal. Use Key Feed shows posts accessible to a specific Use Key.

### G

**Gateway** — Machine-facing JSON API surface under `/api/*`. Authenticated via Key JWTs. Entry point for programmatic post creation, commenting, and key minting.

**Group** — Owner-created collection of Keys. Used for bulk post access grants.

### H

**hex32** — External encoding format for internal IDs: 32-character lowercase hexadecimal string. Used in routes, JSON responses, JWT claims, and logs.

**HSTS** — HTTP Strict Transport Security. Header forcing browsers to use HTTPS.

### I

**initial_author_key_id** — Lineage field referencing the root Primary Author Key. Immutable once set. Enables full provenance tracking.

**INTERACT (Bitmask Preset)** — Post access bitmask value 0x03 (VIEW + COMMENT). Grants read and comment capabilities.

**issued_by_key_id** — Lineage field identifying the Key that minted this Key. NULL for Primary Author Keys.

### J

**JWT (JSON Web Token)** — RS256-signed access token containing principal identity, roles, and permissions. Used for authentication on all protected endpoints.

**JWKS** — JSON Web Key Set. Public endpoint at `/.well-known/jwks.json` publishing RS256 public keys for token verification.

### K

**Keychain** — Collection of Keys for combined authorization. Can be owner-managed (Console) or external (Gateway).

**key_id** — Internal Key identifier (BINARY(16) in DB, hex32 externally). Used in JWTs, routes, logs. Primary identifier for Key principals.

**key_public_id** — External Key identifier in format `apub_...`. Used ONLY for ApiKey exchange. Never used in route params or fields named `*_id`.

**key_secret** — Secret component of ApiKey. Stored as Argon2id hash. Never logged or returned after initial mint.

### L

**Lineage** — Provenance chain of key issuance tracked via `issued_by_key_id`, `parent_key_id`, and `initial_author_key_id`.

### M

**MANAGE_ACCESS (Bitmask)** — Bit 3 (0x08) of post access bitmask. Grants ability to manage post access grants (add/remove groups/keys).

**Middleware Pipeline** — Ordered chain of PSR-15 middleware processing requests. Four variants: Public API, Console JSON, Gateway JSON, Console HTML.

### O

**Owner** — Human principal authenticated via password. Can mint Primary Author Keys and manage groups/keychains via Console. JWT has `typ=owner` and includes `owner_id`.

**owner_id** — Internal Owner identifier (BINARY(16) in DB, hex32 externally). Used in Owner JWTs, rate limiting, and audit logs.

### P

**parent_key_id** — Lineage field identifying the immediate parent Key. NULL for Primary Author Keys.

**Permission** — String identifier (e.g., `posts:create`, `keys:issue`) granting a specific capability. Immutable once minted.

**Permission Envelope** — See Envelope Rule.

**Permission Mask** — See Bitmask.

**post_access** — Database table storing post-scoped access grants. Each record grants a target (group or key) a permission_mask for a specific post.

**Primary Author Key** — Root machine principal minted by Owner. Can create posts and mint child keys. Has no parent in lineage tree.

**Principal** — Authentication identity. Either an Owner (human, `typ=owner`) or a Key (machine, `typ=key`).

### R

**Rate Limiting** — Request throttling using Symfony rate-limiter. Keyed by IP (public), `owner_id` (Console JSON), or `key_id` (Gateway JSON).

**READ_ONLY (Bitmask Preset)** — Post access bitmask value 0x01 (VIEW only). Grants read-only post access.

**Refresh Token** — Long-lived opaque token (default 30 days) for obtaining new access tokens. Single-use; rotated on every refresh. Stored hashed in `refresh_tokens` table.

**Replay Detection** — Security mechanism preventing reuse of rotated refresh tokens.

**Repository** — Data access layer using PDO prepared statements. Converts between BINARY(16) and hex32. No business logic.

**Rotation (Key)** — Key lifecycle operation creating a new key to replace an old one. Old key retired, new key issued. Lineage tracked via `rotated_from_id`/`rotated_to_id`.

**Rotation (Refresh Token)** — Single-use refresh token pattern. Old token marked rotated, new token issued. Detected replay is a security event.

**Role** — Coarse permission grouping (`owner`, `author`, `use`). Resolved to explicit permissions at token issuance.

**RS256** — RSA + SHA-256 JWT signing algorithm. Requires private/public key pair.

### S

**Secondary Author Key** — Delegated machine principal minted by Primary or Secondary Author Key. Can create posts and mint child keys (within permission envelope).

**Service** — Business logic layer. Enforces permissions, manages transactions, emits audit events. Calls repositories.

**Surface** — CRE8.pw has two surfaces: Console (Owner-facing) and Gateway (Key-facing). Separated by route groups and JWT token typing.

**sub (Subject)** — JWT claim identifying the principal. Format: `owner:<owner_id>` or `key:<key_id>`.

### T

**Token Typing** — JWT `typ` claim enforcing surface separation. `typ=owner` for Console JSON, `typ=key` for Gateway JSON. Middleware enforces at surface boundaries.

**typ (Token Type)** — Custom JWT claim. Values: `owner` | `key`. Enforced by JwtOwnerMiddleware / JwtKeyMiddleware.

### U

**Use Count** — Optional constraint on Use Keys limiting total number of uses (1-time, N-times, or unlimited). Tracked per Use Key.

**Use Key** — Restricted machine principal for read/comment interactions. Cannot create posts or mint keys. Minted by Author Keys with optional use count and device limits.

### V

**Validation Middleware** — PSR-15 middleware applying Respect\Validation rules. Configured per-route in `config/validation.php` keyed by `"METHOD /pattern"`.

**VIEW (Bitmask)** — Bit 0 (0x01) of post access bitmask. Grants ability to view/read a post.

---

## 4. Key Capability Matrix

### 4.1 Complete Capability Table

| Capability | Owner | Primary Author | Secondary Author | Use Key |
|:---:|:---:|:---:|:---:|:---:|
| **Authentication** |
| Login via password | ✅ | ❌ | ❌ | ❌ |
| Exchange via ApiKey | ❌ | ✅ | ✅ | ✅ |
| **Key Issuance** |
| Mint Primary Author Key | ✅ | ❌ | ❌ | ❌ |
| Mint Secondary Author Key | ❌ | ✅ | ✅ | ❌ |
| Mint Use Key | ❌ | ✅ | ✅ | ❌ |
| **Content Creation** |
| Create posts | ❌ | ✅ | ✅ | ❌ |
| Create comments | ❌ | ✅* | ✅* | ✅* |
| **Content Access** |
| Read posts | ❌ | ✅* | ✅* | ✅* |
| Read feeds | ❌ | ✅* | ✅* | ✅ |
| **Access Management** |
| Grant post access | ❌ | ✅* | ✅* | ❌ |
| Revoke post access | ❌ | ✅* | ✅* | ❌ |
| **Group Management** |
| Create groups | ✅ | ❌ | ❌ | ❌ |
| Manage group members | ✅ | ❌ | ❌ | ❌ |
| Read groups | ✅ | ✅ | ✅ | ✅ |
| **Keychain Management** |
| Create owner keychain | ✅ | ❌ | ❌ | ❌ |
| Create external keychain | ❌ | ✅* | ✅* | ✅* |
| Manage keychain members | ✅ | ✅* | ✅* | ✅* |
| **Key Lifecycle** |
| View key lineage | ✅ | ❌ | ❌ | ❌ |
| Rotate keys | ✅ | ❌ | ❌ | ❌ |
| Activate/deactivate keys | ✅ | ❌ | ❌ | ❌ |
| **Provenance & Audit** |
| View downstream lineage | ✅ | ❌ | ❌ | ❌ |
| Disable key lineage | ✅ | ❌ | ❌ | ❌ |

**Legend:**
- ✅ = Capability available
- ❌ = Capability not available
- ✅* = Capability available if granted permission + post mask

### 4.2 Key Type Definitions

**Owner (Human Principal):**
- **Authentication:** Password → Owner JWT (`typ=owner`)
- **JWT Claims:** `owner_id` (hex32), `roles`, `permissions`
- **Primary Surface:** Console (HTML + JSON)
- **Root Capability:** Mint Primary Author Keys
- **Limitations:**
  - Cannot directly create posts (must use Author Keys)
  - Cannot be used in Gateway API

**Primary Author Key (Root Machine Principal):**
- **Authentication:** ApiKey exchange → Key JWT (`typ=key`)
- **JWT Claims:** `key_id` (hex32), `key_public_id` (apub_...), `roles`, `permissions`
- **Minted By:** Owner (Console only)
- **Primary Surface:** Gateway JSON
- **Key Capabilities:**
  - Create posts
  - Mint Secondary Author Keys (within permission envelope)
  - Mint Use Keys (within permission envelope)
- **Lineage Fields:**
  - `issued_by_key_id` = NULL
  - `parent_key_id` = NULL
  - `initial_author_key_id` = self

**Secondary Author Key (Delegated Machine Principal):**
- **Authentication:** ApiKey exchange → Key JWT (`typ=key`)
- **Minted By:** Primary or Secondary Author Key
- **Primary Surface:** Gateway JSON
- **Key Capabilities:**
  - Create posts
  - Mint Secondary Author Keys (within permission envelope, ⊆ parent)
  - Mint Use Keys (within permission envelope, ⊆ parent)
- **Lineage Fields:**
  - `issued_by_key_id` = issuing key
  - `parent_key_id` = parent key
  - `initial_author_key_id` = root Primary Author Key (immutable)

**Use Key (Interaction Principal):**
- **Authentication:** ApiKey exchange → Key JWT (`typ=key`)
- **Minted By:** Primary or Secondary Author Key
- **Primary Surface:** Gateway JSON
- **Key Capabilities:**
  - Read posts (if granted VIEW mask)
  - Create comments (if granted COMMENT mask)
  - Read feeds
- **Restrictions:**
  - **CANNOT** create posts
  - **CANNOT** mint keys
  - **CANNOT** be granted `posts:create` or `keys:issue`
- **Use Limits (Optional):**
  - Use count limits (1-time, N-times)
  - Device limits (restrict by fingerprint/IP)
- **Lineage Fields:**
  - Same as Secondary (issued_by_key_id, parent_key_id, initial_author_key_id)

### 4.3 Issuance Rules

**Primary Author Key Mint (Console Only):**
```
Owner → POST /console/keys/primary
Body: {
  "permissions": ["posts:create", "keys:issue", "groups:read", ...],
  "label": "optional-label"
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Owner must have `keys:issue` permission
- No parent/lineage (root key)

**Secondary Author Key Mint (Gateway):**
```
Author Key → POST /api/keys/{authorKeyId}/secondary
Body: {
  "permissions": ["posts:create", "keys:issue", ...],
  "label": "optional-label"
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Issuer must have `keys:issue`
- Child permissions ⊆ parent permissions
- Cannot include `posts:create` if parent doesn't have it

**Use Key Mint (Gateway):**
```
Author Key → POST /api/keys/{authorKeyId}/use
Body: {
  "permissions": ["posts:read", "comments:write"],
  "label": "optional-label",
  "use_count": 1,       // optional: 1, N, or null (unlimited)
  "device_limit": 3     // optional: max devices, or null
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Issuer must have `keys:issue`
- Child permissions ⊆ parent permissions
- MUST NOT include `posts:create` or `keys:issue`

### 4.4 Permission Envelope Validation

```
Parent permissions: ["posts:create", "keys:issue", "posts:read", "comments:write"]

✅ Valid child:   ["posts:create", "posts:read"]
✅ Valid child:   ["posts:read", "comments:write"]
❌ Invalid child: ["posts:create", "keys:issue", "groups:manage"] // groups:manage not in parent
❌ Invalid child: ["posts:create", "keys:issue"] // OK for Secondary, but Use Key cannot have these
```

### 4.5 Lineage Traversal

**Viewing Downstream Lineage (Owner Only):**
```
Owner → GET /console/keys/{keyId}/lineage
→ Returns tree of all descendants
```

**Disabling a Lineage (Owner Only):**
```
Owner → POST /console/keys/{keyId}/deactivate
Option: cascade=true
→ Deactivates key + all descendants
```

### 4.6 Combining Keys: "Keyring Key" Concept

**Scenario:** Owner wants to create a combined authorization context from multiple keys

**Implementation:**
1. Owner creates a **Group** containing multiple Keys
2. Owner creates a **Keychain** containing the Group
3. When granting post access:
   ```
   POST /console/posts/{postId}/access/grant-group
   Body: { "group_id": "...", "permission_mask": 0x03 }
   ```
4. All Keys in that Group inherit the access

**Alternative (Future):** "Keyring Key" as a synthetic Key with union permissions
- Not implemented in v1
- Would require special key type and permission resolution logic

---

## 5. Permission Matrix

### 5.1 Owner Permissions (Console-Scoped)

| Permission | Meaning | Used By | Notes |
|:---:|:---:|:---:|:---:|
| `owners:manage` | Manage owner profile/settings | Owner | Console self-management |
| `keys:issue` | Mint Primary Author Keys | Owner | Root capability; Console only |
| `keys:read` | List/view keys in owner scope | Owner | Console key inventory |
| `keys:rotate` | Rotate keys (retire + replace) | Owner | Console key lifecycle |
| `keys:state:update` | Activate/deactivate keys | Owner | Console key state management |
| `groups:manage` | Full CRUD on groups + membership | Owner | Console group administration |
| `groups:read` | Read-only group access | Owner/Key | Console listing |
| `keychains:manage` | Manage keychains + membership | Owner/Key | Both surfaces |
| `posts:admin:read` | Admin view of owner-scoped posts | Owner | Console post inventory |
| `posts:access:manage` | Grant/revoke group access to posts | Owner/Key | Both surfaces |

### 5.2 Key Permissions (Gateway-Scoped)

| Permission | Meaning | Used By | Notes |
|:---:|:---:|:---:|:---:|
| `keys:issue` | Mint Secondary Author or Use Keys | Primary Author, Secondary Author | Gateway key issuance |
| `posts:create` | Create new posts | Primary Author, Secondary Author | **NEVER granted to Use Keys** |
| `posts:read` | Read/list visible posts | All key types | Requires VIEW mask |
| `comments:write` | Write comments on posts | Use Keys (if granted), Author Keys | Requires COMMENT mask |
| `groups:read` | Read groups | All key types | Gateway group listing |
| `keychains:manage` | Manage external keychains | All key types (if granted) | Gateway keychain ops |
| `posts:access:manage` | Manage post access (requires MANAGE_ACCESS mask) | Primary, Secondary | Gateway access management |

### 5.3 Permission Envelope Rules

- Child keys MUST have permissions ⊆ parent permissions
- Use Keys MUST NEVER receive `posts:create` or `keys:issue`
- Permission sets are **immutable** once minted
- To change permissions: rotate key (new key with new permission set)

### 5.4 Post Access Bitmasks

| Bit Position | Hex Value | Name | Meaning |
|:---:|:---:|:---:|:---:|
| 0 | 0x01 | VIEW | View/read the post |
| 1 | 0x02 | COMMENT | Create comments |
| 3 | 0x08 | MANAGE_ACCESS | Manage post access grants |

**Presets:**
- `READ_ONLY` = 0x01 (VIEW only)
- `INTERACT` = 0x03 (VIEW + COMMENT)
- `ADMIN` = 0x0B (VIEW + COMMENT + MANAGE_ACCESS)

### 5.5 Combined Authorization

Actions require **BOTH**:
1. Global permission string
2. Post-scoped mask bit

**Examples:**
- Read post: `posts:read` + `VIEW`
- Comment: `comments:write` + `COMMENT`
- Grant access: `posts:access:manage` + `MANAGE_ACCESS`

### 5.6 Role Definitions

**Owner Role:**
- Assigned to: Owners (human principals)
- Implied permissions:
  - `owners:manage`
  - `keys:issue` (primary mint)
  - `keys:read`
  - `keys:rotate`
  - `keys:state:update`
  - `groups:manage`
  - `keychains:manage`
  - `posts:admin:read`
  - `posts:access:manage`

**Author Role (Primary/Secondary Keys):**
- Assigned to: Primary Author Keys, Secondary Author Keys
- Implied permissions (typical):
  - `keys:issue` (child key minting)
  - `posts:create`
  - `posts:read`
  - `comments:write`
  - `groups:read`
  - `keychains:manage`

**Use Role:**
- Assigned to: Use Keys
- Implied permissions (typical):
  - `posts:read`
  - `comments:write`
  - `groups:read`
- **NEVER** includes:
  - `posts:create`
  - `keys:issue`

### 5.7 Permission Assignment Flow

1. Owner registers → gets `owner` role
2. Owner mints Primary Author Key → permissions specified at mint time
3. Primary/Secondary Author mints child → child permissions ⊆ parent permissions
4. Use Key minted → permissions specified, validated against parent envelope
5. Access tokens include explicit `permissions` array in JWT

---

## 6. Complete Route Inventory

### 6.1 Route Organization Principles

- **Surface separation:** Console (HTML + JSON) vs Gateway (JSON)
- **Auth differentiation:** Owner JWT (`typ=owner`) vs Key JWT (`typ=key`)
- **CSRF scope:** HTML routes only (no CSRF on JSON)
- **ID formats:** All `{...Id}` params are hex32 unless explicitly `{keyPublicId}`

### 6.2 Public HTML (Console UI)

| Method | Path | Purpose | Auth | CSRF |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/` | Landing page | None | N/A (public) |
| GET | `/console/register` | Owner registration form | None | Yes |
| GET | `/console/login` | Owner login form | None | Yes |
| GET | `/console/dashboard` | Owner dashboard | Owner session/token | Yes |

**Controller:** ConsoleController  
**Service:** ConsoleService (page rendering + data prep)

### 6.3 Public API (JSON, No Auth or Special Auth)

**Health & Infrastructure:**

| Method | Path | Purpose | Auth |
|:---:|:---:|:---:|:---:|
| GET | `/health` | Health check | None |
| GET | `/.well-known/jwks.json` | RS256 public keys | None |

**Controller:** HealthController  
**Service:** N/A (direct response)

**Authentication:**

| Method | Path | Purpose | Auth | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/api/auth/exchange` | ApiKey → JWT + refresh | `Authorization: ApiKey <public_id>:<secret>` | `{ access_token, refresh_token, expires_in }` |
| POST | `/api/auth/refresh` | Rotate refresh token | Refresh token in body | `{ access_token, refresh_token }` |

**Controller:** ApiKeyController, AuthController  
**Service:** AuthService, TokenRepository

**Owner Management:**

| Method | Path | Purpose | Auth | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/console/owners` | Create new Owner | None (public registration) | `{ owner_id }` |
| POST | `/console/login` | Owner login | Password in body | `{ access_token, refresh_token }` |

**Controller:** OwnerController, AuthController  
**Service:** OwnerRepository, AuthService

### 6.4 Console JSON Routes (Owner-Protected, No CSRF)

**Auth:** `Authorization: Bearer <owner_jwt>` where `typ=owner`  
**Rate Limiting:** Keyed by `owner_id` (hex32)

**Key Management (Owner Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/console/keys/primary` | Mint Primary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| GET | `/console/keys` | List Owner's keys | `keys:read` | `{ data: [...keys] }` |
| GET | `/console/keys/{keyId}` | Get key details | `keys:read` | `{ data: {key} }` |
| GET | `/console/keys/{keyId}/lineage` | View key lineage tree | `keys:read` | `{ data: {tree} }` |
| POST | `/console/keys/{keyId}/rotate` | Rotate key | `keys:rotate` | `{ old_key_id, new_key_id, new_key_secret }` |
| POST | `/console/keys/{keyId}/activate` | Activate key | `keys:state:update` | `{ key_id, active: true }` |
| POST | `/console/keys/{keyId}/deactivate` | Deactivate key | `keys:state:update` | `{ key_id, active: false }` |

**Group Management (Owner Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/console/groups` | Create group | `groups:manage` | `{ group_id, name }` |
| GET | `/console/groups` | List groups | `groups:manage` | `{ data: [...groups] }` |
| GET | `/console/groups/{groupId}` | Get group details | `groups:manage` | `{ data: {group} }` |
| POST | `/console/groups/{groupId}/rename` | Rename group | `groups:manage` | `{ group_id, name }` |
| DELETE | `/console/groups/{groupId}` | Delete group | `groups:manage` | `{ deleted: true }` |
| POST | `/console/groups/{groupId}/members` | Add member | `groups:manage` | `{ group_id, key_id }` |
| DELETE | `/console/groups/{groupId}/members/{keyId}` | Remove member | `groups:manage` | `{ deleted: true }` |

**Keychain Management (Owner Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/console/keychains` | List owner keychains | `keychains:manage` | `{ data: [...keychains] }` |
| POST | `/console/keychains` | Create keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/console/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/console/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

**Post Management (Owner Admin Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/console/posts` | List posts from Owner's keys | `posts:admin:read` | `{ data: [...posts], paging }` |
| GET | `/console/posts/{postId}` | Get post details | `posts:admin:read` | `{ data: {post} }` |
| POST | `/console/posts/{postId}/access/grant-group` | Grant group access | `posts:access:manage` | `{ post_id, group_id, permission_mask }` |
| POST | `/console/posts/{postId}/access/revoke-group` | Revoke group access | `posts:access:manage` | `{ deleted: true }` |

### 6.5 Gateway JSON Routes (Key-Protected)

**Auth:** `Authorization: Bearer <key_jwt>` where `typ=key`  
**Rate Limiting:** Keyed by `key_id` (hex32)

**Key Issuance (Gateway Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/api/keys/{authorKeyId}/secondary` | Mint Secondary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| POST | `/api/keys/{authorKeyId}/use` | Mint Use Key | `keys:issue` | `{ key_id, key_public_id, key_secret, use_count?, device_limit? }` |

**Posts (Gateway Scope):**

| Method | Path | Purpose | Required Permission + Mask | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/api/posts` | List visible posts | `posts:read` + `VIEW` | `{ data: [...posts], paging }` |
| GET | `/api/posts/{postId}` | Get post | `posts:read` + `VIEW` | `{ data: {post} }` |
| POST | `/api/posts` | Create post | `posts:create` | `{ post_id, content, author_key_id }` |
| POST | `/api/posts/{postId}/access` | Grant/update post access | `posts:access:manage` + `MANAGE_ACCESS` | `{ post_id, target_type, target_id, permission_mask }` |
| DELETE | `/api/posts/{postId}/access/{accessId}` | Revoke post access | `posts:access:manage` + `MANAGE_ACCESS` | `{ deleted: true }` |

**Comments (Gateway Scope):**

| Method | Path | Purpose | Required Permission + Mask | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/api/posts/{postId}/comments` | List comments | `posts:read` + `VIEW` | `{ data: [...comments], paging }` |
| POST | `/api/posts/{postId}/comments` | Create comment | `comments:write` + `COMMENT` | `{ comment_id, body, created_by_key_id, post_id }` |

**Feeds (Gateway Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/api/feed/use/{useKeyId}` | Get Use Key feed | Use Key bearer; `useKeyId` must match `key_id` in JWT | `{ data: [...posts], paging }` |
| GET | `/api/feed/author` | Get Author feed (future) | Author Key bearer | `{ data: [...posts], paging }` |

**Groups (Gateway Read Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| GET | `/api/groups` | List groups | `groups:read` | `{ data: [...groups] }` |
| GET | `/api/groups/{groupId}` | Get group | `groups:read` | `{ data: {group} }` |
| GET | `/api/groups/{groupId}/members` | List members | `groups:read` | `{ data: [...keys] }` |

**Keychains (Gateway External Scope):**

| Method | Path | Purpose | Required Permission | Returns |
|:---:|:---:|:---:|:---:|:---:|
| POST | `/api/keychains` | Create external keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/api/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/api/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

### 6.6 Controller/Service/Repository Ownership Map

| Domain | Controller | Service | Repository |
|:---:|:---:|:---:|:---:|
| Health/Infrastructure | HealthController | N/A | N/A |
| Auth/Token | AuthController, ApiKeyController | AuthService | TokenRepository, OwnerRepository, KeyRepository |
| Owners | OwnerController | OwnerService (or AuthService) | OwnerRepository |
| Keys | KeyController | KeyService | KeyRepository, AuditRepository |
| Posts | PostController | PostService | PostRepository, AuditRepository |
| Comments | CommentController | CommentService | PostRepository (or CommentRepository) |
| Feeds | FeedController | FeedService | PostRepository |
| Groups | GroupController | GroupService | GroupRepository, AuditRepository |
| Keychains | KeychainController | KeychainService | KeychainRepository, AuditRepository |
| Console Pages | ConsoleController | ConsoleService | Multiple |

### 6.7 Route Addition Checklist

When adding a new endpoint:

1. ✅ Determine surface (Console HTML, Console JSON, Gateway JSON)
2. ✅ Determine auth requirements (Owner JWT, Key JWT, or public)
3. ✅ Determine required permissions (ref: Permission Matrix)
4. ✅ Determine post mask bits if applicable
5. ✅ Add route to `config/routes.php` under correct group
6. ✅ Add validation rules to `config/validation.php` keyed by `"METHOD /pattern"`
7. ✅ Implement controller method (thin adapter)
8. ✅ Implement service method (business logic + audits)
9. ✅ Implement repository methods if new data access needed
10. ✅ Update OpenAPI schema (`openapi.yaml`)
11. ✅ Test authorization enforcement (both permission string + mask if applicable)
12. ✅ Test error responses (401, 403, 404, 422, 429)
13. ✅ Verify logging/audit events emitted
14. ✅ Update this document

---

## 7. Dependency Wiring Guide

### 7.1 Complete Composer Dependencies

```json
{
  "require": {
    "php": "^8.3",
    "slim/slim": "^4.15",
    "slim/psr7": "^1.7",
    "php-di/php-di": "^7.1",
    "firebase/php-jwt": "^6.11",
    "ext-pdo": "*",
    "ext-sodium": "*",
    "respect/validation": "^2.4",
    "vlucas/phpdotenv": "^5.6",
    "guzzlehttp/guzzle": "^7.10",
    "neomerx/cors-psr7": "^3.0",
    "slim/csrf": "^1.5",
    "monolog/monolog": "^3.9",
    "symfony/rate-limiter": "^7.3",
    "symfony/cache": "^7.3"
  }
}
```

### 7.2 Dependency Flow Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ENTRY POINT                                        │
│                         public/index.php                                     │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        vlucas/phpdotenv                                      │
│                     Load .env → $_ENV                                        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          php-di/php-di                                       │
│                     Build DI Container                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ PDO (ext-pdo)                                                       │    │
│  │ JwtHelper (firebase/php-jwt)                                        │    │
│  │ HashingService (ext-sodium)                                         │    │
│  │ Loggers (monolog/monolog)                                           │    │
│  │ RateLimiters (symfony/rate-limiter + symfony/cache)                 │    │
│  │ CorsSettings (neomerx/cors-psr7)                                    │    │
│  │ CsrfGuard (slim/csrf)                                               │    │
│  │ HttpClient (guzzlehttp/guzzle)                                      │    │
│  │ ValidationRules (respect/validation)                                │    │
│  │ Repositories → Services → Controllers → Middleware                 │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           slim/slim                                          │
│                    Create Slim App with Container                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       MIDDLEWARE PIPELINE                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ HttpsMiddleware                                                     │    │
│  │ CorsMiddleware         ← neomerx/cors-psr7                          │    │
│  │ RateLimitMiddleware    ← symfony/rate-limiter                       │    │
│  │ JwtMiddleware          ← firebase/php-jwt                           │    │
│  │ ValidationMiddleware   ← respect/validation                         │    │
│  │ CsrfGuard (HTML only)  ← slim/csrf                                  │    │
│  │ ErrorHandlingMiddleware                                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          slim/psr7                                           │
│                   Request → Controller → Response                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SERVICE LAYER                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ AuthService (JwtHelper, HashingService, TokenRepository)            │    │
│  │ KeyService (KeyRepository, AuditRepository, HashingService)         │    │
│  │ PostService (PostRepository, KeyRepository)                         │    │
│  │ ... etc                                                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       REPOSITORY LAYER                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ PDO Prepared Statements                                             │    │
│  │ hex32 ↔ BINARY(16) conversion (Utilities/Ids)                       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MariaDB 11.4.x                                     │
│                         utf8mb4_bin collation                                │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 7.3 Key Dependency Integrations

**Slim Framework (`slim/slim` + `slim/psr7`):**
- Entry point: `public/index.php`
- Route configuration: `config/routes.php`
- Middleware pipeline ordering
- PSR-7 request/response handling

**PHP-DI (`php-di/php-di`):**
- Container configuration: `config/container.php`
- Autowiring for repositories, services, controllers
- Singleton pattern for expensive resources (JwtHelper, Loggers)
- Named parameter injection for multiple loggers

**JWT (`firebase/php-jwt`):**
- JwtHelper class: RS256 signing/verification
- JWT middleware: Token verification and principal attachment
- JWKS endpoint: Public key publishing

**Validation (`respect/validation`):**
- Centralized rules: `config/validation.php`
- Keyed by `"METHOD /pattern"`
- ValidationMiddleware: Applies rules per route

**CORS (`neomerx/cors-psr7`):**
- CorsMiddleware: Applies CORS headers from env config
- Handles preflight OPTIONS requests

**CSRF (`slim/csrf`):**
- Slim\Csrf\Guard: Applied ONLY to HTML routes
- Never applied to JSON endpoints

**Rate Limiting (`symfony/rate-limiter` + `symfony/cache`):**
- Three buckets: GENERAL, AUTH, API
- Backing: Memory or Database (no Redis/Memcached)
- Keying: IP (public), owner_id (Console), key_id (Gateway)

**Logging (`monolog/monolog`):**
- Structured JSON logging
- Multiple channels: api, auth, security, db
- Never log secrets

**Hashing (`ext-sodium`):**
- Argon2id for passwords and API key secrets
- Configurable cost parameters

**HTTP Client (`guzzlehttp/guzzle`):**
- Outbound HTTP requests
- Configurable timeouts and retries

**Environment (`vlucas/phpdotenv`):**
- Loads `.env` file
- Bootstrap validation for critical variables

---

## 8. Component Architecture

### 8.1 Layering Rules

**Controllers (HTTP Adapters):**
- Extract params (route, query, body, headers)
- Call exactly ONE service method
- Shape response using standardized envelopes
- Return PSR-7 response
- **Forbidden:** Business logic, direct database access, multi-service orchestration, permission enforcement

**Services (Business Logic):**
- Enforce global permissions (check JWT permissions)
- Enforce post bitmasks (for post-scoped actions)
- Enforce invariants (key type, lineage, immutability)
- Orchestrate multiple repositories (transactions)
- Emit audit events
- Throw deterministic exceptions
- **Forbidden:** HTTP concerns, direct SQL queries

**Repositories (Data Access):**
- PDO prepared statements exclusively
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs
- Handle database-level errors
- **Forbidden:** Business logic, permission checks, HTTP concerns, logging secrets

**Middleware (Cross-Cutting Concerns):**
- HTTPS enforcement, CORS, rate limiting
- JWT verification, token typing
- Validation (request schemas)
- Error normalization
- **Forbidden:** Business logic, database access (except rate limiter storage)

### 8.2 Component Responsibilities

| Component Type | Responsibilities | Forbidden |
|:---:|:---:|:---:|
| **Middleware** | Cross-cutting concerns (HTTPS, CORS, auth verification, rate limiting, validation, error normalization) | Business logic, database access, multi-step workflows |
| **Controllers** | HTTP adapters (parse request, call service, shape response) | Business logic, direct database access, multi-repo coordination |
| **Services** | Domain rules, authorization checks, transactions, audit emission | HTTP concerns, middleware logic |
| **Repositories** | Prepared SQL, data access | HTTP concerns, authorization checks, business rules |

### 8.3 Dependency Injection Pattern

**Container Setup (`config/container.php`):**
- PDO with charset/collation configuration
- Named loggers for different channels
- JWT helper (singleton)
- Rate limiter factories
- CORS settings
- CSRF guard (HTML routes only)
- HTTP client factory
- Hashing service
- Repositories (autowired with PDO)
- Services (autowired with repositories)
- Controllers (autowired with services)
- Middleware (autowired with dependencies)

**Singletons:**
- JwtHelper (stateless, expensive setup)
- Loggers
- Rate limiter factories
- Guzzle client factory

**Autowire:**
- Controllers, Services, Repositories
- Middleware (if they have dependencies)

---

## 9. Documentation Structure

### 9.1 Main Documents

1. [introduction.md](../01-getting-started/introduction.md) — What is CRE8.pw?
2. [architecture-overview.md](../04-architecture/architecture-overview.md) — System Architecture
3. [authentication.md](../05-authentication-authorization/authentication.md) — Auth System (RS256 JWT, JWKS, Refresh)
4. [authorization.md](../05-authentication-authorization/authorization.md) — Permission System & Bitmasks
5. [api-reference.md](../06-api-reference/api-reference.md) — Complete API Catalog
6. [feed-system.md](../06-api-reference/feed-system.md) — Content Access & Feeds
7. [database-schema.md](../07-data-model/database-schema.md) — Database Schema Contract
8. [key-lifecycle.md](../03-core-concepts/key-lifecycle.md) — Key Management & Lineage
9. [post-sharing.md](../03-core-concepts/post-sharing.md) — Content Sharing Model
10. [implementation-guide.md](../08-implementation/implementation-guide.md) — Developer Manual
11. [logging-and-audit.md](../09-operations/logging-and-audit.md) — Operational Concerns

### 9.2 Reference Documents

- [identifier-encoding.md](../10-reference/identifier-encoding.md) — ID formats (hex32, apub_, BINARY(16))
- [environment-configuration.md](../10-reference/environment-configuration.md) — Complete .env reference
- [glossary.md](../03-core-concepts/glossary.md) — Alphabetical term definitions
- [executive-summary.md](../01-getting-started/executive-summary.md) — Executive summary

### 9.3 Additional Reference

- [docset.json](../10-reference/docset.json) — JSON metadata
- [document-outlines.md](../10-reference/document-outlines.md) — Document outlines
- [key-capabilities.md](../05-authentication-authorization/key-capabilities.md) — Key capabilities
- [permissions.md](../05-authentication-authorization/permissions.md) — Permission matrix
- [routes-inventory.md](../06-api-reference/routes-inventory.md) — Route catalog
- [dependency-wiring.md](../08-implementation/dependency-wiring.md) — Dependency wiring
- [component-architecture.md](../04-architecture/component-architecture.md) — Component breakdown
- [layering-rules.md](../04-architecture/layering-rules.md) — Architecture details

### 9.4 SSoT Ownership Map

| Document | Owns |
|:---:|:---:|
| **[architecture-overview.md](../04-architecture/architecture-overview.md)** | Middleware pipelines, CSRF scope, request flow |
| **[authentication.md](../05-authentication-authorization/authentication.md)** | JWT structure, JWKS, refresh lifecycle, ApiKey exchange |
| **[authorization.md](../05-authentication-authorization/authorization.md)** | Permissions catalog, roles, bitmasks, envelope rules |
| **[api-reference.md](../06-api-reference/api-reference.md)** | API endpoint catalog, required permissions |
| **[feed-system.md](../06-api-reference/feed-system.md)** | Feed visibility, pagination, authorization |
| **[database-schema.md](../07-data-model/database-schema.md)** | Database schema, migrations, invariants |
| **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)** | Key issuance, rotation, lineage, provenance |
| **[post-sharing.md](../03-core-concepts/post-sharing.md)** | Access grants, Use Key limits, sharing workflows |
| **[implementation-guide.md](../08-implementation/implementation-guide.md)** | Code patterns, DI wiring, layering rules |
| **[response-schemas.md](../06-api-reference/response-schemas.md)** | Success/error formats, HTTP status codes |
| **[logging-and-audit.md](../09-operations/logging-and-audit.md)** | Log channels, audit events, rate limiting |
| **[identifier-encoding.md](../10-reference/identifier-encoding.md)** | Identifier formats (hex32, apub_, BINARY(16)) |
| **[environment-configuration.md](../10-reference/environment-configuration.md)** | Environment configuration reference |
| **[glossary.md](../03-core-concepts/glossary.md)** | Glossary and terminology |

---

## 10. Quick Reference Tables

### 10.1 Key Type Capabilities

| Key Type | Can Mint Keys | Can Create Posts | Can Comment | Can Read Feeds |
|:---:|:---:|:---:|:---:|:---:|
| Primary Author | ✅ | ✅ | ✅* | ✅* |
| Secondary Author | ✅ | ✅ | ✅* | ✅* |
| Use | ❌ | ❌ | ✅* | ✅ |

*If granted permission + bitmask

### 10.2 Post Bitmask Values

| Bit | Hex | Name | Capability |
|:---:|:---:|:---:|:---:|
| 0 | 0x01 | VIEW | Read post |
| 1 | 0x02 | COMMENT | Create comments |
| 3 | 0x08 | MANAGE_ACCESS | Grant/revoke access |

### 10.3 Bitmask Presets

| Preset | Hex Value | Bits | Meaning |
|:---:|:---:|:---:|:---:|
| READ_ONLY | 0x01 | VIEW | Read-only access |
| INTERACT | 0x03 | VIEW + COMMENT | Read and comment |
| ADMIN | 0x0B | VIEW + COMMENT + MANAGE_ACCESS | Full interaction and management |

### 10.4 HTTP Status Codes

| Status | Usage |
|:---:|:---:|
| 200 OK | Successful GET, PUT, PATCH |
| 201 Created | Successful POST (resource created) |
| 204 No Content | Successful DELETE |
| 400 Bad Request | Malformed request |
| 401 Unauthorized | Auth failure |
| 403 Forbidden | Authz failure |
| 404 Not Found | Resource missing/hidden |
| 409 Conflict | Uniqueness/state conflict |
| 422 Unprocessable Entity | Validation failure |
| 429 Too Many Requests | Rate limited |
| 500 Internal Server Error | Uncaught exception |
| 503 Service Unavailable | Dependency down |

### 10.5 ID Format Rules

| Context | Format | Example |
|:---:|:---:|:---:|
| Route params (`{postId}`, `{keyId}`) | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |
| Key public ID (`{keyPublicId}`) | apub_... | `apub_8cd1a2b3c4d5e6f7` |
| Database storage | BINARY(16) | (binary) |
| JSON responses | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |
| JWT claims (`owner_id`, `key_id`) | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |

### 10.6 Middleware Pipeline Order

**Public API Pipeline:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (IP-based)
4. Body Parsing Middleware
5. ValidationMiddleware
6. Routing Middleware
7. Controller → Service → Repository
8. ErrorHandlingMiddleware

**Console JSON Pipeline:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (Principal: `owner_id`)
4. JwtOwnerMiddleware (verify RS256; enforce `typ=owner`)
5. Body Parsing Middleware
6. ValidationMiddleware
7. Routing Middleware → Controller → Service → Repository
8. ErrorHandlingMiddleware

**Gateway JSON Pipeline:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (Principal: `key_id`)
4. JwtKeyMiddleware (verify RS256; enforce `typ=key`)
5. Body Parsing Middleware
6. ValidationMiddleware
7. Routing Middleware → Controller → Service → Repository
8. ErrorHandlingMiddleware

**Console HTML Pipeline:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (IP-based or session-based)
4. Slim\Csrf\Guard (HTML routes only)
5. CsrfExposeMiddleware (optional)
6. Render HTML
7. ErrorHandlingMiddleware

### 10.7 Rate Limiting Buckets

| Bucket | Default Limit | Keying Strategy | Used For |
|:---:|:---:|:---:|:---:|
| GENERAL | 100 per minute | IP (public), owner_id (Console) | Default endpoints |
| AUTH | 10 per minute | IP | Authentication endpoints |
| API | 60 per minute | key_id (Gateway) | Gateway endpoints |

### 10.8 Log Channels

| Channel | Purpose | Typical Events |
|:---:|:---:|:---:|
| `api` | Request summaries | Method, path, status, latency |
| `auth` | Auth events | Exchange, login, refresh, introspect |
| `security` | Security events | Auth failures, refresh replay, rate limits, CSRF (HTML) |
| `db` | Database errors | Query failures, transaction rollbacks |
| `guzzle.http` | Outbound HTTP | External API calls |

### 10.9 Audit Event Catalog

**Owner lifecycle:**
- `owners:register`
- `owners:login`

**Key lifecycle:**
- `keys:mint` (primary, secondary, use)
- `keys:rotate`
- `keys:activate`, `keys:deactivate`

**Group/keychain:**
- `groups:create`, `groups:rename`, `groups:delete`
- `groups:member:add`, `groups:member:remove`
- `keychains:create`
- `keychains:member:add`, `keychains:member:remove`

**Post lifecycle:**
- `posts:create`
- `posts:update:title`

**Post access:**
- `posts:access:grant`
- `posts:access:revoke`

**Security:**
- `refresh:replay_attempt`
- `apikey:exchange:failed` (optional throttle)

**Action Naming Convention:** `<domain>:<action>` or `<domain>:<subdomain>:<action>`

---

## 11. Critical Rules Summary

### 11.1 Identifier Rules

1. **All internal IDs:** BINARY(16) in database
2. **All external IDs:** hex32 (32-char lowercase hex)
3. **Key public IDs:** apub_... format, used ONLY for ApiKey exchange
4. **Route params:** All `{...Id}` are hex32 except `{keyPublicId}`
5. **Never accept:** apub_... in params named `*Id`
6. **Conversion:** Repository layer converts hex32 ↔ BINARY(16)

### 11.2 Environment Configuration Rules

1. **Bootstrap validation:** Fail fast if critical vars missing
2. **Critical vars:** DB_*, JWT_PRIVATE_KEY_PATH, JWT_PUBLIC_KEY_PATH, JWT_ISSUER, JWT_AUDIENCE
3. **Format validation:** CORS origins parseable, rate limits valid
4. **File validation:** JWT keys readable and valid

### 11.3 Permission & Authorization Rules

1. **Envelope rule:** Child permissions ⊆ Parent permissions
2. **Use Key restrictions:** Cannot have `posts:create` or `keys:issue`
3. **Permission immutability:** Cannot change after minting (use rotation)
4. **Combined checks:** Global permission + post mask both required
5. **Visibility rules:** 404 when hiding existence, 403 when revealing lack of permission

### 11.4 Route & API Rules

1. **CSRF scope:** HTML routes only, never JSON endpoints
2. **Token typing:** Enforce `typ=owner` vs `typ=key` strictly
3. **ID formats:** All `{...Id}` params are hex32
4. **Response format:** Standardized envelopes for all JSON endpoints
5. **Error format:** Standardized error schema with codes

### 11.5 Dependency Wiring Rules

1. **DI container:** PHP-DI with autowiring
2. **Singletons:** JwtHelper, Loggers, Rate limiters
3. **Middleware order:** Critical (see pipeline order tables)
4. **Repository pattern:** PDO prepared statements only
5. **Service pattern:** Business logic + permissions + audits

### 11.6 Logging & Security Rules

1. **Never log:** Passwords, ApiKey secrets, refresh tokens, private keys
2. **Structured logging:** JSON format always
3. **Log channels:** Use appropriate channel (api, auth, security, db)
4. **Audit events:** Required for all state-changing operations
5. **Rate limiting:** Enforce per bucket with appropriate keying

---

## 12. Complete File Structure Reference

### 12.1 Source Code Files

**Controllers (`src/Controllers/`):**
- `BaseController.php` - Base class with response helpers
- `Console/` - Owner-facing controllers
  - `ConsoleController.php` - HTML page rendering
  - `KeyController.php` - Key management
  - `GroupController.php` - Group management
  - `KeychainController.php` - Keychain management
  - `PostController.php` - Post admin
- `Gateway/` - Key-facing controllers
  - `PostController.php` - Post creation and access
  - `CommentController.php` - Comment management
  - `KeyController.php` - Key issuance
  - `FeedController.php` - Feed endpoints
  - `GroupController.php` - Group read-only
  - `KeychainController.php` - External keychain management
- `HealthController.php` - Health check endpoint
- `JwksController.php` - JWKS endpoint
- `OwnerController.php` - Owner registration

**Services (`src/Services/`):**
- `BaseService.php` - Base service class (if needed)
- `AuthService.php` - Authentication and token management
- `KeyService.php` - Key lifecycle management
- `PostService.php` - Post creation and access management
- `CommentService.php` - Comment management
- `FeedService.php` - Feed generation
- `GroupService.php` - Group management
- `KeychainService.php` - Keychain management
- `ConsoleService.php` - Console page data preparation

**Repositories (`src/Repositories/`):**
- `BaseRepository.php` - Base repository class (if needed)
- `OwnerRepository.php` - Owner data access
- `KeyRepository.php` - Key data access
- `PostRepository.php` - Post and comment data access
- `GroupRepository.php` - Group data access
- `KeychainRepository.php` - Keychain data access
- `TokenRepository.php` - Refresh token data access
- `AuditRepository.php` - Audit event data access

**Middleware (`src/Middleware/`):**
- `HttpsMiddleware.php` - HTTPS enforcement and HSTS
- `CorsMiddleware.php` - CORS header management
- `RateLimitMiddleware.php` - Rate limiting
- `JwtOwnerMiddleware.php` - Owner JWT verification
- `JwtKeyMiddleware.php` - Key JWT verification
- `ValidationMiddleware.php` - Request validation
- `ErrorHandlingMiddleware.php` - Error normalization
- `CsrfExposeMiddleware.php` - CSRF token exposure (HTML only)

**Security (`src/Security/`):**
- `JwtService.php` - JWT signing and verification
- `PermissionCatalog.php` - Permission string definitions
- `PostAccessBitmask.php` - Post access bitmask constants

**Utilities (`src/Utilities/`):**
- `Ids.php` - ID conversion utilities (hex32 ↔ BINARY(16))
- `ResponseFactory.php` - Standardized response creation
- `ErrorFactory.php` - Standardized error response creation
- `BootstrapValidator.php` - Bootstrap validation
- `SchemaContractVerifier.php` - Schema verification
- `SensitiveDataSanitizer.php` - Log sanitization

**Exceptions (`src/Exceptions/`):**
- `NotFoundException.php` - 404 Not Found
- `ForbiddenException.php` - 403 Forbidden
- `UnauthorizedException.php` - 401 Unauthorized
- `ValidationException.php` - 422 Validation Failed
- `BadRequestException.php` - 400 Bad Request
- `RateLimitException.php` - 429 Too Many Requests

### 12.2 Configuration Files

**Configuration (`config/`):**
- `container.php` - PHP-DI container configuration
- `routes.php` - Route group registration
- `validation.php` - Validation schemas (Respect\Validation)
- `routes/public_api.php` - Public API routes
- `routes/console_html.php` - Console HTML routes
- `routes/console_json.php` - Console JSON routes
- `routes/gateway_json.php` - Gateway JSON routes
- `routes/gateway_html.php` - Gateway HTML routes

**Environment:**
- `.env.example` - Environment variable template
- `.env.local.example` - Local environment template
- `.env` - Actual environment variables (not in git)

### 12.3 Database Migrations

**Migrations (`migrations/`):**
- `001_create_owners.php` - Owners table
- `002_create_keys.php` - Keys table
- `003_create_key_public_ids.php` - Key public IDs table
- `004_create_posts_and_comments.php` - Posts and comments tables
- `005_create_post_access.php` - Post access grants table
- `006_create_groups.php` - Groups and group members tables
- `007_create_keychains.php` - Keychains and keychain members tables
- `008_create_refresh_tokens.php` - Refresh tokens table
- `009_create_audit_events.php` - Audit events table
- `010_add_label_to_keys.php` - Add label column to keys
- `011_add_lookup_hash_to_refresh_tokens.php` - Add lookup hash for refresh tokens
- `012_add_owner_id_to_keys.php` - Add owner_id to keys table
- `013_create_key_devices.php` - Key devices table (for device limits)

**Migration Order:** Must be executed sequentially (001 → 013)

### 12.4 Templates

**Templates (`templates/`):**
- `landing.php` - Landing page
- `register.php` - Owner registration form
- `login.php` - Owner login form
- `dashboard.php` - Owner dashboard
- `keys_list.php` - Keys list page
- `groups_list.php` - Groups list page
- `keychains_list.php` - Keychains list page
- `posts_list.php` - Posts list page
- `_permission_helpers.php` - Permission helper functions
- `gateway/` - Gateway example pages (14 files)

### 12.5 Tools & Scripts

**Tools (`tools/`):**
- `db/migrate.php` - Run database migrations
- `db/verify_schema.php` - Verify database schema
- `contract/test_id_format_compliance.php` - ID format compliance test
- `contract/test_audience_segregation.php` - Token typing test
- `contract/test_doc_ssot_alignment.php` - Documentation alignment test

### 12.6 Documentation

**Documentation:**
- `01-getting-started/` through `12-comprehensive-reference/` — Documentation organized by workflow
- [/TOC.md](../../TOC.md) — Master index
- [/SSOT.md](../../SSOT.md) — SSOT hub
- [table-of-contents.md](../table-of-contents.md) — Full documentation catalog

---

## 13. Complete Dependency Reference

### 13.1 Composer Dependencies

**Core Framework:**
- `slim/slim` (^4.15) - HTTP framework
- `slim/psr7` (^1.7) - PSR-7 implementation
- `php-di/php-di` (^7.1) - Dependency injection

**Security:**
- `firebase/php-jwt` (^6.11) - JWT signing/verification
- `ext-sodium` (*) - Argon2id hashing
- `slim/csrf` (^1.5) - CSRF protection (HTML only)

**Validation:**
- `respect/validation` (^2.4) - Input validation

**Infrastructure:**
- `monolog/monolog` (^3.9) - Structured logging
- `symfony/rate-limiter` (^7.3) - Rate limiting
- `symfony/cache` (^7.3) - Rate limiter storage
- `guzzlehttp/guzzle` (^7.10) - HTTP client
- `neomerx/cors-psr7` (^3.0) - CORS handling
- `vlucas/phpdotenv` (^5.6) - Environment variables

**Database:**
- `ext-pdo` (*) - Database access
- `ext-pdo_mysql` (*) - MySQL/MariaDB driver

### 13.2 Dependency Usage Map

**Slim Framework:**
- Used in: `src/bootstrap.php`, all controllers
- Purpose: HTTP request/response handling, routing, middleware pipeline

**PHP-DI:**
- Used in: `config/container.php`, all constructors
- Purpose: Dependency injection, autowiring, singleton management

**firebase/php-jwt:**
- Used in: `src/Security/JwtService.php`, JWT middleware
- Purpose: RS256 JWT signing and verification

**ext-sodium:**
- Used in: `src/Security/HashingService.php` (or similar)
- Purpose: Argon2id password and API key secret hashing

**respect/validation:**
- Used in: `config/validation.php`, `src/Middleware/ValidationMiddleware.php`
- Purpose: Request body/query/header validation

**monolog/monolog:**
- Used in: `config/container.php`, all services/repositories
- Purpose: Structured JSON logging across channels

**symfony/rate-limiter:**
- Used in: `src/Middleware/RateLimitMiddleware.php`
- Purpose: Request rate limiting (IP-based or principal-based)

**guzzlehttp/guzzle:**
- Used in: Services making outbound HTTP requests
- Purpose: External API calls (if needed)

**neomerx/cors-psr7:**
- Used in: `src/Middleware/CorsMiddleware.php`
- Purpose: CORS header management

**vlucas/phpdotenv:**
- Used in: `src/bootstrap.php`
- Purpose: Loading `.env` file into `$_ENV`

---

## 14. Complete Environment Variable Reference

### 14.1 Application Variables

```bash
APP_NAME=CRE8.pw
APP_ENV=production          # production, development, testing
APP_DEBUG=false            # true for dev, false for production
APP_URL=https://cre8.pw
```

### 14.2 Database Variables

```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cre8pw
DB_USER=cre8_user
DB_PASS=secure_password_here
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin
DB_SSL_MODE=DISABLED      # or REQUIRED
```

### 14.3 JWT Variables

```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # or /api for gateway
JWT_ACCESS_TTL=900         # 15 minutes (seconds)
JWT_REFRESH_TTL=2592000   # 30 days (seconds)
JWT_LEEWAY=10             # Clock skew tolerance (seconds)
```

### 14.4 CORS Variables

```bash
CORS_ALLOWED_ORIGINS=https://cre8.pw,https://www.cre8.pw
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_EXPOSED_HEADERS=X-Total-Count,X-Page-Number
```

### 14.5 CSRF Variables

```bash
CSRF_SECRET=random_32_char_secret_here
```

### 14.6 Rate Limiting Variables

```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory    # or database
```

### 14.7 HTTP Client Variables

```bash
HTTP_TIMEOUT=30         # Guzzle timeout (seconds)
HTTP_RETRY_MAX=3        # Max retries
```

### 14.8 Logging Variables

```bash
LOG_CHANNEL=stack       # or daily, single
LOG_LEVEL=info          # debug, info, warning, error, critical
LOG_PATH=/app/logs
```

### 14.9 Hashing Variables

```bash
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536    # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

### 14.10 Redis Variables (Optional, for Rate Limiting)

```bash
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
```

---

**End of SSOT Appendix Document**

This document consolidates all concepts, rules, reference materials, and implementation guides from the appendix documentation. For detailed specifications, refer to the individual canon documents (see [toc-canon.md](toc-canon.md)) and the reference documents (see [toc-appendix.md](toc-appendix.md)).
