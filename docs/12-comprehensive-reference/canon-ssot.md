# Single Source of Truth (SSOT) — CRE8.pw

**Version:** 1.0.0  
**Last Updated:** 2026-01-22  
**Status:** Canonical  
**Purpose:** Consolidated reference for all concepts, rules, do's, don'ts, and implementation guidelines

---

## Table of Contents

1. [Core Concepts](#1-core-concepts)
2. [Critical Rules & Constraints](#2-critical-rules--constraints)
3. [Architecture & Design Principles](#3-architecture--design-principles)
4. [Authentication & Identity](#4-authentication--identity)
5. [Authorization & Permissions](#5-authorization--permissions)
6. [Data Model & Identifiers](#6-data-model--identifiers)
7. [API Design & Responses](#7-api-design--responses)
8. [Security Requirements](#8-security-requirements)
9. [Implementation Patterns](#9-implementation-patterns)
10. [Do's and Don'ts](#10-dos-and-donts)
11. [Error Handling](#11-error-handling)
12. [Logging & Observability](#12-logging--observability)
13. [Key Lifecycle & Provenance](#13-key-lifecycle--provenance)
14. [Post Sharing & Access Control](#14-post-sharing--access-control)
15. [Environment Configuration](#15-environment-configuration)
16. [Troubleshooting Guide](#16-troubleshooting-guide)
17. [Quick Reference Tables](#17-quick-reference-tables)
18. [Migration Ordering](#18-migration-ordering)
19. [Adding a New Endpoint: Checklist](#19-adding-a-new-endpoint-checklist)
20. [Codebase Structure & Conventions](#20-codebase-structure--conventions)
21. [Development Workflow](#21-development-workflow)
22. [Summary: Critical Rules at a Glance](#22-summary-critical-rules-at-a-glance)
23. [Cross-Document Traceability Map](#23-cross-document-traceability-map)

---

## 1. Core Concepts

### 1.1 Platform Overview

CRE8.pw is a secure content creation and sharing platform built on:
- **Hierarchical key-based authentication** — Owners mint Primary Author Keys; keys mint child keys with delegated permissions
- **Fine-grained access control** — Global permissions + post-level access bitmasks
- **Full provenance tracking** — Every key traces back to its root Primary Author Key
- **Dual-surface architecture** — Console (Owner-facing) and Gateway (API-facing)
- **Secure sharing** — Single-use or limited-use keys with device restrictions

### 1.2 Principals

**Owner (Human):**
- Authenticates with email + password
- Accesses Console (HTML + JSON API)
- Mints Primary Author Keys
- Manages groups, views lineage, oversees downstream key activity

**Key (Machine):**
- Authenticates with ApiKey (`public_id` + `secret`)
- Accesses Gateway (JSON API)
- Three types:
  - **Primary Author Key:** Root key; can create posts and mint child keys
  - **Secondary Author Key:** Delegated key; can create posts and mint child keys (within permission envelope)
  - **Use Key:** Interaction-only; can read and comment but cannot create posts or mint keys

### 1.3 Two Surfaces

**Console** (`/console/*`):
- HTML pages for Owners: landing, registration, login, dashboard
- JSON endpoints for Owner management: keys, groups, keychains, posts
- CSRF protection on HTML forms only (NOT on JSON endpoints)
- Auth: Owner JWT (`typ=owner`)

**Gateway** (`/api/*`):
- JSON API for programmatic access
- Key-based authentication using JWT tokens
- Endpoints for posting, commenting, key issuance, feeds
- Auth: Key JWT (`typ=key`)

### 1.4 Authorization Model

**Two-Layer System:**
1. **Permission Strings:** Global capabilities like `posts:create`, `keys:issue`, `comments:write`
2. **Post Bitmasks:** Post-specific permissions encoded as bits (VIEW, COMMENT, MANAGE_ACCESS)

**Rule:** Every action requires **both** the appropriate permission string AND (for post-scoped actions) the appropriate bitmask.

**Example:** To comment on a post, a Key needs:
- Global permission: `comments:write`
- Post-level mask bit: `COMMENT` (0x02)

### 1.5 Hierarchical Permission Envelope

Child keys inherit a **subset** of their parent's permissions:

```
Owner
 └─ Primary Author Key [posts:create, keys:issue, comments:write]
     ├─ Secondary Author Key [posts:create, comments:write]
     │   └─ Use Key [comments:write]
     └─ Use Key [comments:write]
```

**Envelope Rule:** Child permissions ⊆ Parent permissions

**Immutability:** Once minted, a key's permissions cannot change. To grant new permissions, rotate the key (retire old, issue new).

---

## 2. Critical Rules & Constraints

### 2.1 CSRF Scope (CRITICAL)

**✅ DO:** Apply CSRF Guard to Console HTML routes (`/`, `/console/register`, `/console/login`, `/console/dashboard`)

**❌ DO NOT:** Apply CSRF Guard to Console JSON endpoints, Gateway JSON endpoints, or Public API JSON endpoints

**Rationale:** JSON endpoints use token-based authentication (Bearer JWT). CSRF protection is unnecessary and inappropriate for stateless token auth. CSRF is only relevant for browser-based session/cookie authentication used in HTML form submissions.

### 2.2 Use Key Restrictions (CRITICAL)

**❌ Use Keys MUST NEVER be granted:**
- `posts:create`
- `keys:issue`

These permissions are **forbidden** for Use Keys. Any attempt to mint a Use Key with these permissions must be rejected with `422 validation_failed`.

### 2.3 Permission Envelope Rule (CRITICAL)

**Child permissions ⊆ Parent permissions**

When minting a child key (Secondary or Use), the child's permissions must be a **subset** of the parent's permissions.

**Validation:**
```php
$forbidden = array_diff($requestedPermissions, $parentPermissions);
if (!empty($forbidden)) {
    throw new ValidationException("Permissions not in parent: " . implode(', ', $forbidden));
}
```

### 2.4 Permission Immutability (CRITICAL)

Once a key is minted, its permissions **cannot be changed**.

**To change permissions:**
1. Rotate the key (retire old, issue new)
2. Create new key with desired permissions
3. Retire old key (`retired_at` timestamp)
4. Update lineage references

### 2.5 Lineage Immutability (CRITICAL)

Once set, these fields **never change**:
- `issued_by_key_id`
- `parent_key_id`
- `initial_author_key_id`

**Enforced By:** Database constraints (NO UPDATE) or application logic rejecting updates

### 2.6 Visibility vs Access (404 vs 403 Rules)

**Return 404 Not Found when:**
- The resource does not exist, OR
- The principal lacks the permission/mask to even know the resource exists

**Return 403 Forbidden when:**
- The resource exists and the principal can see it (has VIEW), BUT
- The principal lacks permission for the attempted action

**Rationale:** Prevents information leakage. An authenticated user should not be able to discover the existence of posts they cannot access.

### 2.7 Token Typing (CRITICAL)

All JWTs include an `typ` claim:
- `typ=owner` → enforced by `JwtOwnerMiddleware` (Console JSON)
- `typ=key` → enforced by `JwtKeyMiddleware` (Gateway JSON)

**Prevents token confusion attacks** (using an Owner token on Gateway endpoints or vice versa).

### 2.8 Refresh Token Single-Use (CRITICAL)

Refresh tokens are **single-use** and automatically rotated:
- On refresh, mark old token as `rotated_at = NOW()`
- Issue new refresh token
- Reject any attempt to reuse an already-rotated token (replay attack)

---

## 3. Architecture & Design Principles

### 3.1 Technology Stack

**Core:**
- PHP 8.3+
- Slim Framework 4.15+
- MariaDB 11.4.x

**Security:**
- RS256 JWT (firebase/php-jwt)
- Argon2id password/secret hashing
- HTTPS enforcement with HSTS
- CORS with allowlist
- CSRF protection (HTML routes only)

**Infrastructure:**
- PHP-DI 7.1+ for dependency injection
- Monolog 3.9+ for structured logging
- Symfony rate-limiter 7.3+ for throttling
- Respect\Validation 2.4+ for input validation
- Guzzle 7.10+ for outbound HTTP

### 3.2 Layering Rules

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

### 3.3 Middleware Ordering (Normative)

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

### 3.4 Database Baseline

**Database:** MariaDB 11.4.x or higher  
**Charset:** utf8mb4  
**Collation:** utf8mb4_bin (binary, case-sensitive)  
**Access:** PDO with prepared statements exclusively

---

## 4. Authentication & Identity

### 4.1 JWT Structure (RS256)

**Algorithm:** RS256 (RSA Signature with SHA-256)

**Signing:**
- Private key: `JWT_PRIVATE_KEY_PATH` (PEM format, never exposed)
- Public key: `JWT_PUBLIC_KEY_PATH` (PEM format, published via JWKS)
- Key ID (`kid`): Included in JWT header for key rotation support

**Standard Claims (Required):**
- `iss` — Issuer (configured via `JWT_ISSUER`)
- `sub` — Subject (`owner:<owner_id>` or `key:<key_id>`)
- `aud` — Audience (configured via `JWT_AUDIENCE`)
- `iat` — Issued At (Unix timestamp)
- `nbf` — Not Before (Unix timestamp)
- `exp` — Expiration (Unix timestamp, 15 min TTL)

**Application Claims:**
- `typ` — Token type: `owner` or `key` (REQUIRED)
- `owner_id` — Owner internal ID (hex32, Owner JWTs)
- `key_id` — Key internal ID (hex32, Key JWTs)
- `key_public_id` — Key public ID (apub_..., Key JWTs, optional, debug/correlation only)
- `roles` — Role names (array)
- `permissions` — Explicit permission strings (array)

### 4.2 ApiKey Exchange

**Format:**
```
Authorization: ApiKey <key_public_id>:<key_secret>
```

**Endpoint:** `POST /api/auth/exchange`

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

**Security Rule:** Never reveal whether `key_public_id` exists. Always return generic "Invalid credentials" for:
- Non-existent `key_public_id`
- Wrong `key_secret`
- Inactive key (`active=0`)

### 4.3 Owner Login

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
6. Store refresh token (hashed) in `refresh_tokens` with `subject_type=owner`
7. Emit audit event (`owners:login`)

**Security Rule:** Never reveal whether email exists. Always return generic "Invalid email or password".

### 4.4 Refresh Token Lifecycle

**Single-Use Rotation:**
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

**Replay Detection:** If `rotated_at` is NOT NULL, return `401 unauthorized` and log to `security` channel.

### 4.5 JWKS Endpoint

**Endpoint:** `GET /.well-known/jwks.json`

**Response Format:**
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

**Key Rotation:** When rotating signing keys, add new key to JWKS alongside old key (overlap period). Keep old key in JWKS for at least one access token TTL (15 min + buffer, recommend 1 hour).

---

## 5. Authorization & Permissions

### 5.1 Permission Strings Catalog

**Owner Permissions (Console-Scoped):**
- `owners:manage` — Manage owner profile and settings
- `keys:issue` — Mint Primary Author Keys
- `keys:read` — List and view keys in owner scope
- `keys:rotate` — Rotate keys (retire + replace)
- `keys:state:update` — Activate/deactivate keys
- `groups:manage` — Full CRUD on groups + membership
- `keychains:manage` — Manage owner keychains + membership
- `posts:admin:read` — Admin view of posts from owner's keys
- `posts:access:manage` — Grant/revoke group access to posts

**Key Permissions (Gateway-Scoped):**
- `keys:issue` — Mint Secondary Author or Use Keys
- `posts:create` — Create new posts
- `posts:read` — Read/list visible posts (requires VIEW mask)
- `comments:write` — Write comments on posts (requires COMMENT mask)
- `groups:read` — Read groups (read-only access)
- `keychains:manage` — Manage external keychains + membership
- `posts:access:manage` — Manage post access grants (requires MANAGE_ACCESS mask)

### 5.2 Post Access Bitmasks

| Bit Position | Hex Value | Name | Meaning |
|:---:|:---:|---|---|
| 0 | 0x01 | VIEW | View/read the post |
| 1 | 0x02 | COMMENT | Create comments on the post |
| 3 | 0x08 | MANAGE_ACCESS | Manage post access grants/revocations |

**Presets:**
- `READ_ONLY = 0x01` (VIEW)
- `INTERACT = 0x03` (VIEW + COMMENT)
- `ADMIN = 0x0B` (VIEW + COMMENT + MANAGE_ACCESS)

### 5.3 Combined Authorization Checks

**Read Post:**
- Global: `posts:read` permission in JWT
- Post-scoped: `VIEW` bitmask (0x01) in `post_access`

**Create Comment:**
- Global: `comments:write`
- Post-scoped: `COMMENT` (0x02)

**Grant Post Access:**
- Global: `posts:access:manage`
- Post-scoped: `MANAGE_ACCESS` (0x08)

**Create Post:**
- Global: `posts:create`
- Key type: `primary` or `secondary` (not `use`)

### 5.4 Key Type Capabilities

| Key Type | Can Mint Keys | Can Create Posts | Can Comment | Can Read Feeds |
|:---:|:---:|:---:|:---:|:---:|
| **Primary Author** | ✅ | ✅ | ✅* | ✅* |
| **Secondary Author** | ✅ | ✅ | ✅* | ✅* |
| **Use** | ❌ | ❌ | ✅* | ✅ |

*If granted permission + bitmask

---

## 6. Data Model & Identifiers

### 6.1 ID Formats

**Internal Storage:** `BINARY(16)` for all primary/foreign keys  
**External Representation:** `hex32` (32-char lowercase hex)  
**Key Public IDs:** `apub_...` format (stored separately in `key_public_ids` table)

**Conversion:** `Utilities/Ids.php` provides `binaryToHex32()` and `hex32ToBinary()` functions

**Rules:**
- All `{...Id}` params (e.g., `{postId}`, `{keyId}`) are `hex32` format
- Exception: `{keyPublicId}` is `apub_...` format
- Never accept `apub_...` in params named `*Id`

### 6.2 Core Entities

**owners:**
- `id` (BINARY(16), PK)
- `email` (VARCHAR(255), UNIQUE, NOT NULL)
- `password_hash` (VARCHAR(255), NOT NULL, Argon2id)
- `created_at`, `updated_at` (TIMESTAMP)

**keys:**
- `id` (BINARY(16), PK)
- `type` (ENUM('primary','secondary','use'), NOT NULL)
- `key_secret_hash` (VARCHAR(255), NOT NULL, Argon2id)
- `permissions_json` (JSON, NOT NULL)
- `active` (BOOLEAN, DEFAULT 1)
- `issued_by_key_id` (BINARY(16), FK keys.id, NULL for primary)
- `parent_key_id` (BINARY(16), FK keys.id, NULL for primary)
- `initial_author_key_id` (BINARY(16), FK keys.id, SELF for primary)
- `rotated_from_id`, `rotated_to_id` (BINARY(16), FK keys.id, nullable)
- `retired_at` (TIMESTAMP, nullable)
- `use_count_limit`, `use_count_current` (INT, nullable)
- `device_limit` (INT, nullable)
- `created_at`, `updated_at` (TIMESTAMP)

**Lineage Invariants:**
- Primary: `issued_by_key_id = NULL`, `parent_key_id = NULL`, `initial_author_key_id = id` (self)
- Secondary/Use: All lineage fields NOT NULL

**posts:**
- `id` (BINARY(16), PK)
- `author_key_id` (BINARY(16), FK keys.id, NOT NULL)
- `initial_author_key_id` (BINARY(16), FK keys.id, NOT NULL)
- `title` (VARCHAR(255), nullable)
- `content` (TEXT, NOT NULL)
- `created_at`, `updated_at` (TIMESTAMP)

**post_access:**
- `id` (BINARY(16), PK)
- `post_id` (BINARY(16), FK posts.id, NOT NULL)
- `target_type` (ENUM('key','group'), NOT NULL)
- `target_id` (BINARY(16), NOT NULL)
- `permission_mask` (INT, NOT NULL)
- `created_at` (TIMESTAMP)
- **Composite Index:** `(post_id, target_type, target_id)` UNIQUE

**refresh_tokens:**
- `id` (BINARY(16), PK)
- `subject_type` (ENUM('owner','key'), NOT NULL)
- `subject_id` (BINARY(16), NOT NULL)
- `token_hash` (VARCHAR(255), NOT NULL, Argon2id)
- `issued_at`, `expires_at` (TIMESTAMP)
- `revoked_at`, `rotated_at` (TIMESTAMP, nullable)
- `replaced_by_id` (BINARY(16), FK refresh_tokens.id, nullable)
- `ip`, `user_agent` (VARCHAR, nullable)

**audit_events:**
- `id` (BINARY(16), PK)
- `actor_type` (ENUM('owner','key'), NOT NULL)
- `actor_id` (BINARY(16), NOT NULL)
- `action` (VARCHAR(100), NOT NULL)
- `subject_type` (VARCHAR(50), nullable)
- `subject_id` (BINARY(16), nullable)
- `metadata_json` (JSON, nullable)
- `ip`, `user_agent` (VARCHAR, nullable)
- `created_at` (TIMESTAMP)

### 6.3 Critical Indexes

```sql
-- keys
CREATE INDEX idx_keys_type ON keys(type);
CREATE INDEX idx_keys_active ON keys(active);
CREATE INDEX idx_keys_lineage ON keys(initial_author_key_id);

-- posts
CREATE INDEX idx_posts_author ON posts(author_key_id);
CREATE INDEX idx_posts_created ON posts(created_at DESC);

-- post_access
CREATE UNIQUE INDEX idx_post_access_unique ON post_access(post_id, target_type, target_id);

-- refresh_tokens
CREATE INDEX idx_refresh_token_hash ON refresh_tokens(token_hash);
CREATE INDEX idx_refresh_subject ON refresh_tokens(subject_type, subject_id);

-- audit_events
CREATE INDEX idx_audit_actor ON audit_events(actor_type, actor_id, created_at);
```

---

## 7. API Design & Responses

### 7.1 Standard Success Response

**Single Object:**
```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "type": "primary"
  }
}
```

**List (With Paging):**
```json
{
  "data": [
    { "post_id": "abc...", "content": "..." },
    { "post_id": "def...", "content": "..." }
  ],
  "paging": {
    "limit": 20,
    "cursor": "def..."
  }
}
```

### 7.2 Standard Error Response

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

**Fields:**
- `code`: Stable, machine-readable error identifier
- `message`: Human-readable summary (safe to display)
- `details`: Structured error-specific data
- `request_id`: Correlation ID (if tracing enabled)

### 7.3 HTTP Status Code Mapping

| Status | Usage |
|:---:|---|
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

### 7.4 Error Code Taxonomy

| HTTP | Code | When | Required Details |
|:---:|---|---|---|
| 400 | `bad_request` | Malformed JSON, invalid header format, invalid state transition | optional |
| 401 | `unauthorized` | Missing/invalid/expired token; refresh replay; invalid ApiKey | optional |
| 403 | `forbidden` | Authenticated but lacks permission/mask | `required` (list permissions) |
| 404 | `not_found` | Resource missing or hidden | optional |
| 409 | `conflict` | Uniqueness violation, state conflict | optional |
| 422 | `validation_failed` | Validation errors | **required** (`details.fields`) |
| 429 | `rate_limited` | Rate limit exceeded | `retry_after_seconds` when known |
| 500 | `internal_error` | Unhandled error | none (log internally) |
| 503 | `service_unavailable` | Dependency outage | optional |

---

## 8. Security Requirements

### 8.1 Never Log Secrets (CRITICAL)

**NEVER log:**
- Passwords (plaintext)
- ApiKey secrets
- Refresh tokens (plaintext)
- Private keys
- Stack traces (in production)

**Safe to log:**
- `owner_id`, `key_id` (hex32)
- `key_public_id` (apub_...)
- JWT `sub` (readable identifier)
- IP, User-Agent
- Timestamps, request paths

### 8.2 Authentication Security

**ApiKey Exchange Failures:**
- Return `401 unauthorized`
- Message: Generic "Invalid credentials"
- Never reveal: Whether `key_public_id` exists, which part failed

**Owner Login Failures:**
- Return `401 unauthorized`
- Message: Generic "Invalid email or password"
- Never reveal: Whether email exists

**Issuer and Audience Checking:**
- JWT middleware must strictly enforce:
  - `iss` matches `JWT_ISSUER`
  - `aud` matches expected audience
- Failure to check allows tokens issued by other systems to be accepted

### 8.3 HTTPS & HSTS

**HttpsMiddleware:**
- Redirect HTTP → HTTPS (if behind proxy, respect `X-Forwarded-Proto`)
- Apply HSTS headers in production (`Strict-Transport-Security: max-age=31536000; includeSubDomains`)
- Configuration: `APP_ENV` (apply HSTS only in `production`)

### 8.4 CORS

**CorsMiddleware:**
- Apply env-driven allowlist:
  - `CORS_ALLOWED_ORIGINS` (comma-separated)
  - `CORS_ALLOWED_METHODS`
  - `CORS_ALLOWED_HEADERS`
  - `CORS_EXPOSED_HEADERS`
- Handle preflight OPTIONS requests efficiently (short-circuit before expensive middleware)

### 8.5 Rate Limiting

**Buckets:**
- `GENERAL`: Default for all endpoints (default: 100 per minute)
- `AUTH`: Authentication endpoints (default: 10 per minute)
- `API`: Gateway endpoints (default: 60 per minute)

**Keying Strategy:**
- Public routes: IP address
- Console JSON: `owner_id` (hex32, from Owner JWT claim)
- Gateway JSON: `key_id` (hex32, from Key JWT claim)

**Backing Store:**
- Configured via `RATE_LIMIT_BACKING` (default: `memory`)
- Production may use `database` for persistence across restarts

**On Limit Exceeded:**
- Return `429 Too Many Requests`
- Use standardized error schema
- Include `retry_after_seconds` in `details` when possible
- Log to `security` channel

### 8.6 Input Validation

**Centralized Validation:**
- Configured in `config/validation.php` as map: `"METHOD /pattern" => rules`
- Use Respect\Validation rules
- Prefer `rejectUnknown: true` for security (prevent mass assignment vulnerabilities)
- Validation errors are always `422` with `details.fields`

**Example:**
```php
"POST /api/posts" => [
    'body' => v::key('content', v::stringType()->notEmpty()->length(1, 10000))
                ->key('title', v::optional(v::stringType()->length(1, 255))),
    'rejectUnknown' => true,
],
```

### 8.7 Prepared Statements

**All database access MUST use PDO with parameter binding.**

**Forbidden:** String concatenation, `sprintf()` for SQL, direct variable interpolation

**Example:**
```php
$sql = "SELECT * FROM posts WHERE id = ?";
$stmt = $this->pdo->prepare($sql);
$stmt->execute([Ids::hex32ToBinary($postIdHex32)]);
```

---

## 9. Implementation Patterns

### 9.1 Controller Pattern

```php
class PostController {
    public function __construct(
        private PostService $postService,
        private ResponseFactory $responseFactory
    ) {}

    public function create(Request $req, Response $res): Response {
        $keyId = $req->getAttribute('key_id'); // from JwtKeyMiddleware
        $body = $req->getParsedBody();

        $post = $this->postService->createPost($keyId, $body['content'], $body['title'] ?? null);

        return $this->responseFactory->json($res, ['data' => $post], 201);
    }
}
```

### 9.2 Service Pattern

```php
class PostService {
    public function createPost(string $keyIdHex32, string $content, ?string $title): array {
        // 1. Load key and verify type
        $key = $this->keyRepository->findById($keyIdHex32);
        if (!$key) throw new NotFoundException('Key not found');
        if ($key['type'] === 'use') throw new ForbiddenException('Use Keys cannot create posts');

        // 2. Verify permission
        if (!in_array('posts:create', $key['permissions'])) {
            throw new ForbiddenException('Missing permission: posts:create');
        }

        // 3. Create post
        $postIdHex32 = $this->keyRepository->generateId();
        $this->postRepository->create([
            'id' => $postIdHex32,
            'author_key_id' => $keyIdHex32,
            'initial_author_key_id' => $key['initial_author_key_id'],
            'content' => $content,
            'title' => $title,
        ]);

        // 4. Emit audit event
        $this->auditRepository->log('posts:create', 'key', $keyIdHex32, 'post', $postIdHex32);

        // 5. Return
        return $this->postRepository->findById($postIdHex32);
    }
}
```

### 9.3 Repository Pattern

```php
class PostRepository {
    public function create(array $data): void {
        $sql = "INSERT INTO posts (id, author_key_id, initial_author_key_id, content, title, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['author_key_id']),
            Ids::hex32ToBinary($data['initial_author_key_id']),
            $data['content'],
            $data['title'],
        ]);
    }

    public function findById(string $postIdHex32): ?array {
        $sql = "SELECT * FROM posts WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($postIdHex32)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'post_id' => Ids::binaryToHex32($row['id']),
            'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
            'content' => $row['content'],
            'title' => $row['title'],
            'created_at' => $row['created_at'],
        ];
    }
}
```

### 9.4 Dependency Injection

**Container Setup:**
```php
use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // PDO
    PDO::class => function() {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'], $_ENV['DB_NAME']);
        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_bin");
        return $pdo;
    },

    // Singletons
    JwtHelper::class => DI\create()->singleton(),
    LoggerInterface::class => DI\create()->singleton(),

    // Autowire
    KeyRepository::class => DI\autowire(),
    PostService::class => DI\autowire(),
    PostController::class => DI\autowire(),
]);

return $builder->build();
```

---

## 10. Do's and Don'ts

### 10.1 Authentication & Authorization

**✅ DO:**
- Enforce token typing (`typ=owner` vs `typ=key`)
- Verify `iss` and `aud` claims in JWT middleware
- Use single-use refresh tokens with automatic rotation
- Return generic error messages for auth failures (don't reveal existence)
- Check both global permissions AND post bitmasks for post-scoped actions
- Enforce envelope rule when minting child keys
- Validate Use Key restrictions (no `posts:create` or `keys:issue`)

**❌ DON'T:**
- Apply CSRF protection to JSON endpoints
- Log passwords, ApiKey secrets, or refresh tokens
- Allow child keys to have permissions not in parent
- Allow Use Keys to have `posts:create` or `keys:issue`
- Change key permissions after minting (use rotation instead)
- Change lineage fields after creation
- Reveal whether `key_public_id` or email exists in auth failures

### 10.2 API Design

**✅ DO:**
- Use standardized response envelopes (`{ data: {...} }` or `{ error: {...} }`)
- Return appropriate HTTP status codes (200, 201, 204, 400, 401, 403, 404, 422, 429, 500, 503)
- Include `details.fields` for validation errors (422)
- Include `required` permissions in 403 responses
- Use `hex32` format for all ID parameters (except `apub_...` for key public IDs)
- Return 404 when hiding resource existence
- Return 403 when resource exists but action is forbidden

**❌ DON'T:**
- Return different response formats for different endpoints
- Include stack traces or internal paths in error responses
- Reveal sensitive information in error messages
- Use different ID formats inconsistently
- Return 404 when you should return 403 (or vice versa)

### 10.3 Database & Data Access

**✅ DO:**
- Use PDO prepared statements exclusively
- Convert hex32 ↔ BINARY(16) at repository boundary
- Use `utf8mb4_bin` collation
- Create required indexes (see Critical Indexes section)
- Use transactions for multi-step operations
- Emit audit events for all state-changing operations

**❌ DON'T:**
- Use string concatenation for SQL queries
- Store IDs as strings (use BINARY(16))
- Skip parameter binding
- Access database directly from controllers or middleware
- Log secrets or sensitive data

### 10.4 Logging & Observability

**✅ DO:**
- Use structured JSON logging
- Include `request_id` for correlation
- Log to appropriate channels (`api`, `auth`, `security`, `db`)
- Include principal identifiers (`owner_id` or `key_id`) in protected endpoint logs
- Log rate limit hits to `security` channel
- Log refresh replay attempts to `security` channel

**❌ DON'T:**
- Log passwords, secrets, or tokens
- Log stack traces in production
- Mix log formats (always use structured JSON)
- Skip audit events for important actions

### 10.5 Security

**✅ DO:**
- Enforce HTTPS in production
- Apply HSTS headers in production
- Use RS256 JWT signing
- Use Argon2id for password/secret hashing
- Validate all input with centralized schemas
- Use `rejectUnknown: true` in validation to prevent mass assignment
- Rate limit by IP (public) or principal (authenticated)
- Detect and log refresh token replay attempts

**❌ DON'T:**
- Allow HTTP in production
- Use symmetric JWT signing (HS256)
- Use weak password hashing (MD5, SHA1, bcrypt)
- Skip input validation
- Allow mass assignment vulnerabilities
- Skip rate limiting
- Ignore refresh token replay attempts

---

## 11. Error Handling

### 11.1 Error Response Format

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

### 11.2 Validation Errors (422)

**MUST include `details.fields`:**
```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "content": ["Content is required"],
        "permissions": ["Must be a subset of parent permissions"]
      }
    }
  }
}
```

### 11.3 Permission Failures (403)

**Missing global permission:**
```json
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient permissions",
    "details": {
      "required": ["posts:create"]
    }
  }
}
```

**Missing post mask:**
```json
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient post access",
    "details": {
      "required_mask": "COMMENT",
      "required_mask_value": 2
    }
  }
}
```

### 11.4 Rate Limiting (429)

```json
{
  "error": {
    "code": "rate_limited",
    "message": "Too many requests",
    "details": {
      "retry_after_seconds": 60
    }
  }
}
```

### 11.5 ErrorHandlingMiddleware Responsibilities

- Catch typed exceptions from controllers/services/repos/middleware
- Map to HTTP status codes (400, 401, 403, 404, 422, 429, 500, 503)
- Return standardized error body
- Log to appropriate channels (`api`, `auth`, `security`, `db`)
- Never leak sensitive details (stack traces, internal paths, secret values)

---

## 12. Logging & Observability

### 12.1 Log Format

**All logs MUST be structured JSON:**

```json
{
  "timestamp": "2026-01-21T12:00:00Z",
  "level": "INFO",
  "channel": "api",
  "message": "Request completed",
  "request_id": "req_abc123",
  "method": "POST",
  "path": "/api/posts",
  "status": 201,
  "owner_id": null,
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "ip": "192.168.1.100",
  "user_agent": "curl/7.64.1",
  "latency_ms": 45
}
```

### 12.2 Log Channels

| Channel | Purpose | Typical Events |
|:---:|---|---|
| `api` | Request summaries | Method, path, status, latency |
| `auth` | Auth events | Exchange, login, refresh, introspect |
| `security` | Security events | Auth failures, refresh replay, rate limits, CSRF (HTML) |
| `db` | Database errors | Query failures, transaction rollbacks |
| `guzzle.http` | Outbound HTTP | External API calls |

### 12.3 Audit Events

**Required Audit Events:**

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

**Examples:**
- `keys:mint`
- `keys:rotate`
- `groups:member:add`
- `posts:access:grant`
- `refresh:replay_attempt`

### 12.4 Rate Limiting

**Configuration:**
```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory
```

**Keying Strategy:**
- Public routes: IP address
- Console JSON: `owner_id` (hex32)
- Gateway JSON: `key_id` (hex32)

**On Limit Exceeded:**
- Return `429 Too Many Requests`
- Log to `security` channel
- Include `retry_after_seconds` in response when known

---

## 13. Key Lifecycle & Provenance

### 13.1 Key Minting

**Primary Author Key (Owner):**
- Endpoint: `POST /console/keys/primary`
- Auth: Owner JWT (`typ=owner`)
- Permission: `keys:issue`
- Lineage: `issued_by_key_id = NULL`, `parent_key_id = NULL`, `initial_author_key_id = id` (self)

**Secondary Author Key (Author Key):**
- Endpoint: `POST /api/keys/{authorKeyId}/secondary`
- Auth: Key JWT (`typ=key`)
- Permission: `keys:issue`
- Key Type: Primary or Secondary Author
- Validation: Envelope rule (child permissions ⊆ parent)
- Lineage: `issued_by_key_id = <parent>`, `parent_key_id = <parent>`, `initial_author_key_id = <parent's initial_author_key_id>`

**Use Key (Author Key):**
- Endpoint: `POST /api/keys/{authorKeyId}/use`
- Auth: Key JWT (`typ=key`)
- Permission: `keys:issue`
- Key Type: Primary or Secondary Author
- Validation: Envelope rule + Use Key restrictions (no `posts:create` or `keys:issue`)
- Optional Limits: `use_count`, `device_limit`

### 13.2 Key Rotation

**Endpoint:** `POST /console/keys/{keyId}/rotate`

**Process:**
1. Load old key
2. Generate new key with:
   - Same `type`, `permissions_json`
   - Same lineage fields (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`)
   - `rotated_from_id = <old key_id>`
3. Update old key:
   - `rotated_to_id = <new key_id>`
   - `retired_at = NOW()`
   - `active = 0`
4. Emit audit event: `keys:rotate`

### 13.3 Key Activation/Deactivation

**Endpoints:**
- `POST /console/keys/{keyId}/activate`
- `POST /console/keys/{keyId}/deactivate`

**Query Param (Deactivate):** `?cascade=true` (optional, deactivate descendants)

**Cascade Deactivation:**
```sql
WITH RECURSIVE descendants AS (
  SELECT id FROM keys WHERE id = ?
  UNION ALL
  SELECT k.id FROM keys k
  INNER JOIN descendants d ON k.parent_key_id = d.id
)
UPDATE keys SET active = 0 WHERE id IN (SELECT id FROM descendants);
```

### 13.4 Use Count & Device Limits

**Use Count Enforcement:**
- Track in `keys.use_count_limit`, `keys.use_count_current`
- Increment on successful ApiKey exchange (or per request, implementation choice)
- Reject when `use_count_current >= use_count_limit`
- Return `403 forbidden` with `error.code = "use_limit_exceeded"`

**Device Limit Enforcement:**
- Track in `key_devices` table: `key_id`, `device_fingerprint`, `first_seen_at`
- Device fingerprint: hash of IP + User-Agent (or more sophisticated)
- Count distinct devices
- Reject when count >= `device_limit`
- Return `403 forbidden` with `error.code = "device_limit_exceeded"`

---

## 14. Post Sharing & Access Control

### 14.1 Post Creation

**Endpoint:** `POST /api/posts`

**Requirements:**
- Global: `posts:create` permission
- Key type: `primary` or `secondary` (not `use`)

**Default Visibility:** Newly created posts have NO access grants by default. Author must grant access to make post visible.

### 14.2 Post Visibility

Posts are visible to a Key if:
1. Key has global `posts:read` permission, AND
2. `post_access` table has a grant with VIEW mask (0x01) where:
   - `target_type = 'key'` AND `target_id = <key_id>`, OR
   - `target_type = 'group'` AND Key is a member of that group

### 14.3 Granting Access

**Grant Access to Key:**
- Endpoint: `POST /api/posts/{postId}/access`
- Auth: Key JWT with `posts:access:manage` permission + MANAGE_ACCESS mask (0x08)
- Request: `{ target_type: "key", target_id: "...", permission_mask: 3 }`

**Grant Access to Group:**
- Endpoint: `POST /console/posts/{postId}/access/grant-group`
- Auth: Owner JWT with `posts:access:manage`
- Request: `{ group_id: "...", permission_mask: 3 }`
- Effect: All keys in the group inherit the mask

### 14.4 Use Key Sharing Workflow

1. Create Post (`POST /api/posts`)
2. Mint Use Key (`POST /api/keys/{authorKeyId}/use`)
3. Grant Use Key Access to Post (`POST /api/posts/{postId}/access`)
4. Share Credentials (`apub_...:sec_...`)
5. Recipient Exchanges ApiKey (`POST /api/auth/exchange`)
6. Recipient Reads Post (`GET /api/posts/{postId}`)
7. Recipient Comments (Optional) (`POST /api/posts/{postId}/comments`)

---

## 15. Environment Configuration

### 15.1 JWT Configuration

```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # Or /api for gateway
JWT_ACCESS_TTL=900       # 15 minutes
JWT_REFRESH_TTL=2592000  # 30 days
JWT_LEEWAY=10            # Clock skew tolerance in seconds
```

### 15.2 Hashing Configuration

```bash
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536    # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

### 15.3 Database Configuration

```bash
DB_HOST=localhost
DB_NAME=cre8pw
DB_USER=cre8pw_user
DB_PASS=secure_password
```

### 15.4 CORS Configuration

```bash
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOWED_METHODS=GET,POST,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type
CORS_EXPOSED_HEADERS=X-CSRF-Name,X-CSRF-Value
```

### 15.5 Rate Limiting Configuration

```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory
```

### 15.6 Application Configuration

```bash
APP_ENV=production  # or development
LOG_PATH=/var/log/cre8pw
```

**Bootstrap Validation:** Application **must** fail fast at startup if:
- `JWT_PRIVATE_KEY_PATH` or `JWT_PUBLIC_KEY_PATH` is missing or unreadable
- `JWT_ISSUER` or `JWT_AUDIENCE` is not set
- Private/public keys are malformed or mismatched

---

## 16. Troubleshooting Guide

### 16.1 401 Unauthorized

**Possible Causes:**
- Access token expired (`exp` claim in past)
- Invalid JWT signature
- `iss` doesn't match `JWT_ISSUER`
- `aud` doesn't match expected audience
- JWKS `kid` mismatch
- Invalid ApiKey (`key_public_id` or `key_secret` wrong)
- Refresh token replay (already rotated)

**Check:**
1. Decode JWT and inspect claims (use jwt.io)
2. Verify `exp` timestamp
3. Check JWKS endpoint for matching `kid`
4. Inspect `auth` log channel for auth failures
5. For refresh: check `security` logs for `refresh:replay_attempt`

### 16.2 403 Forbidden

**Possible Causes:**
- Token valid but lacks required permission
- Token valid but lacks required post mask bit
- Use Key attempting `posts:create` or `keys:issue`
- Inactive key (`active = 0`)

**Check:**
1. Decode JWT and inspect `permissions` array
2. Compare required permission (see Permission Strings Catalog)
3. For post-scoped actions, check `post_access` table
4. Verify key `active` status in `keys` table

### 16.3 422 Validation Failed

**Possible Causes:**
- Missing required fields
- Invalid format (email, password strength)
- Permission envelope violation (child ⊄ parent)
- Use Key restriction violation (`posts:create` or `keys:issue` requested)

**Check:**
1. Inspect `details.fields` in error response
2. Review `config/validation.php` for `"METHOD /pattern"` rule
3. For envelope errors, compare child vs parent permissions

### 16.4 429 Rate Limited

**Possible Causes:**
- Too many requests from IP (public routes)
- Too many requests from `owner_id` (Console JSON)
- Too many requests from `key_id` (Gateway JSON)

**Check:**
1. Verify rate limit bucket configuration (`RATE_LIMIT_*`)
2. Inspect `security` logs for rate limit triggers
3. Wait `retry_after_seconds` before retrying
4. For legitimate high volume, consider increasing limits

### 16.5 500 Internal Error

**Possible Causes:**
- Unhandled exception in service/repository
- Database connection failure
- Missing environment configuration
- DI wiring error

**Check:**
1. Inspect `db` log channel for query failures
2. Verify all required env vars are set
3. Check DI container configuration in `config/container.php`
4. Review application error logs (not exposed to client)

---

## 17. Quick Reference Tables

### 17.1 Key Type Capabilities

| Key Type | Can Mint Keys | Can Create Posts | Can Comment | Can Read Feeds |
|:---:|:---:|:---:|:---:|:---:|
| Primary Author | ✅ | ✅ | ✅* | ✅* |
| Secondary Author | ✅ | ✅ | ✅* | ✅* |
| Use | ❌ | ❌ | ✅* | ✅ |

*If granted permission + bitmask

### 17.2 Post Bitmask Values

| Bit | Hex | Name | Capability |
|:---:|:---:|---|---|
| 0 | 0x01 | VIEW | Read post |
| 1 | 0x02 | COMMENT | Create comments |
| 3 | 0x08 | MANAGE_ACCESS | Grant/revoke access |

### 17.3 Bitmask Presets

| Preset | Hex Value | Bits | Meaning |
|:---:|:---:|---|---|
| READ_ONLY | 0x01 | VIEW | Read-only access |
| INTERACT | 0x03 | VIEW + COMMENT | Read and comment |
| ADMIN | 0x0B | VIEW + COMMENT + MANAGE_ACCESS | Full interaction and management |

### 17.4 HTTP Status Codes

| Status | Usage |
|:---:|---|
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

### 17.5 ID Format Rules

| Context | Format | Example |
|:---:|---|---|
| Route params (`{postId}`, `{keyId}`) | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |
| Key public ID (`{keyPublicId}`) | apub_... | `apub_8cd1a2b3c4d5e6f7` |
| Database storage | BINARY(16) | (binary) |
| JSON responses | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |
| JWT claims (`owner_id`, `key_id`) | hex32 | `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` |

---

## 18. Migration Ordering

Migrations must be executed in this order:

1. `001_create_owners.php`
2. `002_create_keys.php`
3. `003_create_posts_and_comments.php`
4. `004_create_groups.php`
5. `005_create_keychains.php`
6. `006_create_tokens.php`
7. `007_create_audit_events.php`
8. `008_indexes.php`
9. `009_foreign_keys.php` (if not inline)

---

## 19. Adding a New Endpoint: Checklist

1. ✅ Determine surface (Console HTML, Console JSON, Gateway JSON, Public API)
2. ✅ Determine auth requirements (Owner JWT, Key JWT, or public)
3. ✅ Determine required permissions (refer to Permission Strings Catalog)
4. ✅ Determine post mask bits if applicable
5. ✅ Add route to `config/routes.php` under correct group
6. ✅ Add validation rules to `config/validation.php` keyed by `"METHOD /pattern"`
7. ✅ Implement controller method (thin adapter)
8. ✅ Implement service method (business logic + audits)
9. ✅ Implement repository methods if new data access needed
10. ✅ Test authorization enforcement (both permission string + mask if applicable)
11. ✅ Test error responses (401, 403, 404, 422, 429)
12. ✅ Verify logging/audit events emitted
13. ✅ Update documentation

### 19.1 Detailed Implementation Steps

**Step 1: Route Definition**
- Add route to appropriate file in `config/routes/`:
  - `public_api.php` - Public endpoints (no auth)
  - `console_html.php` - Owner HTML pages
  - `console_json.php` - Owner JSON API
  - `gateway_json.php` - Key JSON API
  - `gateway_html.php` - Gateway example pages
- Route pattern must match validation key exactly: `"METHOD /pattern"`

**Step 2: Validation Schema**
- Add entry to `config/validation.php`:
```php
"POST /api/posts" => [
    'body' => v::key('content', v::stringType()->notEmpty()->length(1, 10000))
                ->key('title', v::optional(v::stringType()->length(1, 255))),
    'rejectUnknown' => true,  // CRITICAL: prevents mass assignment
],
```
- Use `rejectUnknown: true` for security
- Provide clear error messages for each field

**Step 3: Controller Implementation**
- Create or extend controller in appropriate directory:
  - `src/Controllers/Console/` - Owner-facing
  - `src/Controllers/Gateway/` - Key-facing
- Controller method should:
  - Extract params from request (route, query, body, headers)
  - Call exactly ONE service method
  - Shape response using `ResponseFactory` helpers
  - Return PSR-7 response
- Example:
```php
public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
    $keyId = $request->getAttribute('key_id');  // from JwtKeyMiddleware
    $body = $request->getParsedBody();
    
    $post = $this->postService->createPost($keyId, $body['content'], $body['title'] ?? null);
    
    return $this->responseFactory->json($response, ['data' => $post], 201);
}
```

**Step 4: Service Implementation**
- Create or extend service in `src/Services/`
- Service method should:
  - Load and verify principal (key/owner) exists
  - Verify key type restrictions (e.g., Use Keys cannot create posts)
  - Check global permissions (from JWT `permissions` claim)
  - Check post bitmasks (for post-scoped actions)
  - Enforce business rules (envelope rule, immutability)
  - Orchestrate repositories (use transactions for multi-step)
  - Emit audit events for state changes
  - Throw typed exceptions (NotFoundException, ForbiddenException, ValidationException)
- Example:
```php
public function createPost(string $keyIdHex32, string $content, ?string $title): array {
    // 1. Load key and verify type
    $key = $this->keyRepository->findById($keyIdHex32);
    if (!$key) throw new NotFoundException('Key not found');
    if ($key['type'] === 'use') throw new ForbiddenException('Use Keys cannot create posts');
    
    // 2. Verify permission (already checked in middleware, but double-check)
    if (!in_array('posts:create', $key['permissions'])) {
        throw new ForbiddenException('Missing permission: posts:create');
    }
    
    // 3. Create post (transaction if needed)
    $this->pdo->beginTransaction();
    try {
        $postIdHex32 = $this->keyRepository->generateId();
        $this->postRepository->create([
            'id' => $postIdHex32,
            'author_key_id' => $keyIdHex32,
            'initial_author_key_id' => $key['initial_author_key_id'],
            'content' => $content,
            'title' => $title,
        ]);
        
        // 4. Emit audit event
        $this->auditRepository->log('posts:create', 'key', $keyIdHex32, 'post', $postIdHex32);
        
        $this->pdo->commit();
    } catch (\Exception $e) {
        $this->pdo->rollBack();
        throw $e;
    }
    
    // 5. Return
    return $this->postRepository->findById($postIdHex32);
}
```

**Step 5: Repository Implementation**
- Create or extend repository in `src/Repositories/`
- Repository methods should:
  - Use PDO prepared statements exclusively
  - Convert hex32 → BINARY(16) for database queries
  - Convert BINARY(16) → hex32 for return values
  - Return arrays or DTOs (never expose BINARY(16) directly)
  - Handle database errors gracefully
- Example:
```php
public function create(array $data): void {
    $sql = "INSERT INTO posts (id, author_key_id, initial_author_key_id, content, title, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        Ids::hex32ToBinary($data['id']),
        Ids::hex32ToBinary($data['author_key_id']),
        Ids::hex32ToBinary($data['initial_author_key_id']),
        $data['content'],
        $data['title'],
    ]);
}

public function findById(string $postIdHex32): ?array {
    $sql = "SELECT * FROM posts WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([Ids::hex32ToBinary($postIdHex32)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) return null;
    
    return [
        'post_id' => Ids::binaryToHex32($row['id']),
        'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
        'content' => $row['content'],
        'title' => $row['title'],
        'created_at' => $row['created_at'],
    ];
}
```

**Step 6: Dependency Injection**
- Controllers, Services, Repositories are autowired by PHP-DI
- No manual wiring needed if following naming conventions
- For special cases (multiple loggers, etc.), configure in `config/container.php`

**Step 7: Testing Checklist**
- ✅ Authorization: Test with missing permission (should return 403)
- ✅ Authorization: Test with wrong token type (should return 401)
- ✅ Validation: Test with invalid input (should return 422 with `details.fields`)
- ✅ Not Found: Test with non-existent resource (should return 404)
- ✅ Rate Limiting: Test exceeding rate limit (should return 429)
- ✅ Audit Events: Verify audit event is logged
- ✅ Logging: Verify request is logged to appropriate channel
- ✅ Error Format: Verify standardized error response format
- ✅ Success Format: Verify standardized success response format

### 19.2 Common Patterns

**Pattern: Post-Scoped Action**
```php
// Service method
public function grantAccess(string $keyIdHex32, string $postIdHex32, string $targetType, string $targetIdHex32, int $mask): array {
    // 1. Load key
    $key = $this->keyRepository->findById($keyIdHex32);
    if (!$key) throw new NotFoundException('Key not found');
    
    // 2. Check global permission
    if (!in_array('posts:access:manage', $key['permissions'])) {
        throw new ForbiddenException('Missing permission: posts:access:manage');
    }
    
    // 3. Load post and check MANAGE_ACCESS mask
    $post = $this->postRepository->findById($postIdHex32);
    if (!$post) throw new NotFoundException('Post not found');
    
    $access = $this->postRepository->getAccess($postIdHex32, $keyIdHex32);
    if (!$access || !($access['permission_mask'] & PostAccessBitmask::MANAGE_ACCESS)) {
        throw new ForbiddenException('Missing MANAGE_ACCESS mask');
    }
    
    // 4. Grant access
    // ...
}
```

**Pattern: Hierarchical Key Minting**
```php
public function mintSecondaryKey(string $parentKeyIdHex32, array $permissions, ?string $label): array {
    // 1. Load parent key
    $parent = $this->keyRepository->findById($parentKeyIdHex32);
    if (!$parent) throw new NotFoundException('Parent key not found');
    
    // 2. Verify parent can issue keys
    if (!in_array('keys:issue', $parent['permissions'])) {
        throw new ForbiddenException('Parent key cannot issue keys');
    }
    
    // 3. Enforce envelope rule
    $forbidden = array_diff($permissions, $parent['permissions']);
    if (!empty($forbidden)) {
        throw new ValidationException('Permissions not in parent: ' . implode(', ', $forbidden));
    }
    
    // 4. Mint key
    // ...
}
```

**Pattern: Use Key Restrictions**
```php
public function mintUseKey(string $parentKeyIdHex32, array $permissions, ?string $label, ?int $useCount, ?int $deviceLimit): array {
    // 1-3. Same as Secondary Key minting
    
    // 4. Enforce Use Key restrictions
    $forbidden = ['posts:create', 'keys:issue'];
    $violations = array_intersect($permissions, $forbidden);
    if (!empty($violations)) {
        throw new ValidationException('Use Keys cannot have: ' . implode(', ', $violations));
    }
    
    // 5. Mint Use Key
    // ...
}
```

---

## 20. Codebase Structure & Conventions

### 20.1 Directory Structure

```
./
├── public/                    # Public web root
│   ├── index.php            # Application entry point
│   └── css/                 # Static assets
├── src/                      # Application source code
│   ├── bootstrap.php        # Application bootstrap
│   ├── Controllers/         # HTTP adapters
│   │   ├── Console/        # Owner-facing controllers
│   │   └── Gateway/        # Key-facing controllers
│   ├── Services/           # Business logic layer
│   ├── Repositories/       # Data access layer
│   ├── Middleware/         # PSR-15 middleware
│   ├── Security/           # Security utilities
│   ├── Utilities/          # Helper utilities
│   └── Exceptions/         # Custom exceptions
├── config/                  # Configuration files
│   ├── container.php      # DI container wiring
│   ├── routes.php         # Route group registration
│   ├── validation.php     # Validation schemas
│   └── routes/            # Route group definitions
├── migrations/             # Database migrations
├── templates/             # PHP templates (HTML rendering)
├── tools/                 # Utility scripts
├── docs/                  # Documentation
├── TOC.md                 # Master documentation index
└── SSOT.md                # Master SSOT hub
```

### 20.2 Naming Conventions

**Files:**
- Controllers: `[Domain]Controller.php` (e.g., `PostController.php`)
- Services: `[Domain]Service.php` (e.g., `PostService.php`)
- Repositories: `[Entity]Repository.php` (e.g., `PostRepository.php`)
- Middleware: `[Name]Middleware.php` (e.g., `JwtKeyMiddleware.php`)
- Exceptions: `[Name]Exception.php` (e.g., `NotFoundException.php`)

**Classes:**
- Controllers: `[Domain]Controller` (e.g., `PostController`)
- Services: `[Domain]Service` (e.g., `PostService`)
- Repositories: `[Entity]Repository` (e.g., `PostRepository`)
- Middleware: `[Name]Middleware` (e.g., `JwtKeyMiddleware`)
- Exceptions: `[Name]Exception` (e.g., `NotFoundException`)

**Methods:**
- Controllers: HTTP verb names (`create`, `read`, `update`, `delete`, `list`)
- Services: Domain actions (`createPost`, `grantAccess`, `mintKey`)
- Repositories: CRUD operations (`create`, `findById`, `update`, `delete`, `findAll`)

**Variables:**
- Use camelCase: `$keyIdHex32`, `$postId`, `$permissionMask`
- Suffix hex32 IDs: `$keyIdHex32` (not `$keyId` when it's hex32)
- Database IDs: Use `BINARY(16)` internally, convert at boundary

### 20.3 Code Organization Rules

**Controllers:**
- One controller per domain (Post, Key, Group, etc.)
- One method per HTTP endpoint
- Methods should be 10-30 lines (extract to service if longer)
- No business logic, no database access, no authorization checks

**Services:**
- One service per domain
- Methods implement business logic
- Methods enforce authorization (permissions + masks)
- Methods orchestrate repositories (transactions)
- Methods emit audit events

**Repositories:**
- One repository per entity (Post, Key, Owner, etc.)
- Methods are CRUD-focused
- All SQL uses prepared statements
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs (never expose BINARY(16))

**Middleware:**
- One middleware per concern (HTTPS, CORS, JWT, Validation, etc.)
- Middleware should be stateless (no request-specific state)
- Middleware should be composable (can be used in any pipeline)
- Middleware should fail fast (throw exceptions, don't continue on error)

### 20.4 File Organization

**Controllers:**
- Console controllers: `src/Controllers/Console/[Domain]Controller.php`
- Gateway controllers: `src/Controllers/Gateway/[Domain]Controller.php`
- Shared controllers: `src/Controllers/[Domain]Controller.php`

**Services:**
- All services: `src/Services/[Domain]Service.php`
- Base service: `src/Services/BaseService.php` (if needed)

**Repositories:**
- All repositories: `src/Repositories/[Entity]Repository.php`
- Base repository: `src/Repositories/BaseRepository.php` (if needed)

**Middleware:**
- All middleware: `src/Middleware/[Name]Middleware.php`

**Utilities:**
- All utilities: `src/Utilities/[Name].php`
- Common utilities: `Ids.php`, `ResponseFactory.php`, `ErrorFactory.php`

### 20.5 Import Organization

**Standard Order:**
1. PHP built-ins
2. PSR interfaces (PSR-7, PSR-15, PSR-11, PSR-3)
3. Framework classes (Slim, PHP-DI)
4. Application classes (Controllers, Services, Repositories)
5. Utilities

**Example:**
```php
<?php

declare(strict_types=1);

namespace App\Controllers\Gateway;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\PostService;
use App\Utilities\ResponseFactory;
```

### 20.6 Type Declarations

**Always Use:**
- `declare(strict_types=1);` at top of every PHP file
- Type hints for all parameters: `public function create(string $keyIdHex32, string $content): array`
- Return type hints: `public function findById(string $id): ?array`
- Property type hints: `private PostService $postService;`

**Nullable Types:**
- Use `?string` for optional parameters: `public function create(?string $title): array`
- Use `?array` for methods that may return null: `public function findById(string $id): ?array`

**Array Types:**
- Use `array` for generic arrays: `public function findAll(): array`
- Use specific array shapes in docblocks: `@return array{post_id: string, content: string}`

### 20.7 Error Handling Patterns

**Exception Hierarchy:**
- `NotFoundException` → 404 Not Found
- `ForbiddenException` → 403 Forbidden
- `UnauthorizedException` → 401 Unauthorized
- `ValidationException` → 422 Unprocessable Entity
- `BadRequestException` → 400 Bad Request
- `RateLimitException` → 429 Too Many Requests

**Exception Usage:**
```php
// Not found
if (!$key) throw new NotFoundException('Key not found');

// Forbidden (permission)
if (!in_array('posts:create', $permissions)) {
    throw new ForbiddenException('Missing permission: posts:create');
}

// Forbidden (mask)
if (!($mask & PostAccessBitmask::COMMENT)) {
    throw new ForbiddenException('Missing COMMENT mask');
}

// Validation
if (empty($content)) {
    throw new ValidationException('Content is required');
}
```

---

## 21. Development Workflow

### 21.1 Local Development Setup

**Prerequisites:**
- PHP 8.3+ with extensions: `pdo`, `pdo_mysql`, `sodium`, `openssl`, `json`, `mbstring`
- Composer
- MariaDB 11.4.x or MySQL 8.0+
- OpenSSL (for JWT key generation)

**Setup Steps:**
1. Clone/download codebase
2. Run `composer install`
3. Create database: `CREATE DATABASE cre8pw CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;`
4. Copy `.env.example` to `.env` and configure
5. Generate JWT keys: `openssl genrsa -out keys/private.pem 2048 && openssl rsa -in keys/private.pem -pubout -out keys/public.pem`
6. Run migrations: `php tools/db/migrate.php`
7. Start server: `php -S localhost:8000 -t public`

**Quick Start Workflow:**
1. Register owner: `POST /console/owners`
2. Login: `POST /console/login` → get access_token
3. Mint Primary Author Key: `POST /console/keys/primary` → get `key_public_id` and `key_secret`
4. Exchange ApiKey: `POST /api/auth/exchange` → get access_token
5. Create post: `POST /api/posts`
6. Grant access: `POST /api/posts/{postId}/access`

### 21.2 Testing & Verification

**Contract Tests:**
- `tools/contract/test_id_format_compliance.php` - Verify ID format rules
- `tools/contract/test_audience_segregation.php` - Verify token typing
- `tools/contract/test_doc_ssot_alignment.php` - Verify documentation alignment

**Schema Verification:**
- `tools/db/verify_schema.php` - Verify database schema matches migrations

**Manual Testing Checklist:**
- ✅ All endpoints return standardized responses
- ✅ Authorization enforced (403 for missing permissions)
- ✅ Validation enforced (422 for invalid input)
- ✅ Rate limiting works (429 when exceeded)
- ✅ Audit events logged
- ✅ No secrets in logs
- ✅ Error messages are generic (no information leakage)

### 21.3 Debugging Tips

**Common Issues:**

**401 Unauthorized:**
- Check JWT signature (verify JWKS endpoint)
- Check `exp` claim (token may be expired)
- Check `iss` and `aud` claims (must match config)
- Check token type (`typ=owner` vs `typ=key`)

**403 Forbidden:**
- Check JWT `permissions` array
- Check post `permission_mask` (for post-scoped actions)
- Check key `active` status
- Check key type restrictions (Use Keys cannot create posts)

**422 Validation Failed:**
- Check `details.fields` in error response
- Verify validation rules in `config/validation.php`
- Check `rejectUnknown` setting (should be `true`)

**Database Errors:**
- Check connection string in `.env`
- Check database charset/collation (`utf8mb4_bin`)
- Check migration order (run migrations in sequence)
- Check BINARY(16) conversion (use `Ids::hex32ToBinary()`)

**Logging:**
- Check log files in `LOG_PATH` directory
- Use structured JSON logs (parse with `jq` for readability)
- Check appropriate channel (`api`, `auth`, `security`, `db`)
- Never log secrets (passwords, ApiKey secrets, refresh tokens)

### 21.4 Production Readiness Checklist

**Security:**
- ✅ HTTPS enforced (HSTS headers)
- ✅ CORS configured (allowlist only)
- ✅ CSRF on HTML routes only
- ✅ Rate limiting enabled
- ✅ JWT keys rotated (if needed)
- ✅ Secrets not in code (use environment variables)
- ✅ No secrets in logs
- ✅ Prepared statements only (no SQL injection)
- ✅ Input validation on all endpoints
- ✅ Generic auth error messages

**Performance:**
- ✅ Database indexes created
- ✅ Rate limiting configured
- ✅ Logging not excessive
- ✅ Transactions used appropriately
- ✅ No N+1 queries

**Observability:**
- ✅ Structured JSON logging
- ✅ Audit events for state changes
- ✅ Request correlation IDs
- ✅ Error tracking
- ✅ Health check endpoint

**Documentation:**
- ✅ API documentation updated
- ✅ SSOT documents current
- ✅ Installation guide complete
- ✅ Troubleshooting guide available

---

## 22. Summary: Critical Rules at a Glance

### 22.1 Security Rules

1. **CSRF only on HTML routes** — Never apply CSRF to JSON endpoints
2. **Use Keys cannot have `posts:create` or `keys:issue`** — Enforce at mint time
3. **Child permissions ⊆ Parent permissions** — Envelope rule is mandatory
4. **Permissions are immutable** — Use rotation to change permissions
5. **Lineage fields are immutable** — Never update after creation
6. **404 vs 403** — Hide existence (404) vs reveal lack of permission (403)
7. **Token typing** — Enforce `typ=owner` vs `typ=key` strictly
8. **Refresh tokens are single-use** — Rotate automatically, detect replay
9. **Never log secrets** — Passwords, ApiKey secrets, refresh tokens, private keys
10. **Generic auth errors** — Never reveal whether email or `key_public_id` exists

### 22.2 Code Quality Rules

11. **Prepared statements only** — Never use string concatenation for SQL
12. **Structured JSON logging** — Always use consistent format
13. **Standardized error responses** — Always use `{ error: { code, message, details } }`
14. **Standardized success responses** — Always use `{ data: {...} }` or `{ data: [...], paging: {...} }`
15. **Audit all state changes** — Emit audit events for important actions
16. **Validate all input** — Use centralized validation schemas
17. **Type declarations** — Always use strict types and type hints
18. **Layering rules** — Controllers → Services → Repositories (no cross-layer violations)

### 22.3 API Design Rules

19. **ID formats** — All `{...Id}` params are hex32 (except `{keyPublicId}`)
20. **Response envelopes** — Always wrap responses in `{ data: {...} }` or `{ error: {...} }`
21. **HTTP status codes** — Use appropriate codes (200, 201, 204, 400, 401, 403, 404, 422, 429, 500, 503)
22. **Error details** — Include `details.fields` for validation errors, `details.required` for permission errors
23. **Pagination** — Use cursor-based pagination for lists
24. **Rate limiting** — Enforce per bucket (GENERAL, AUTH, API) with appropriate keying

### 22.4 Development Rules

25. **Dependency injection** — Use PHP-DI autowiring for all components
26. **Middleware order** — Follow canonical pipeline order (HTTPS → CORS → RateLimit → JWT → Validation → Route)
27. **Exception handling** — Use typed exceptions (NotFoundException, ForbiddenException, etc.)
28. **Transaction management** — Use transactions for multi-step operations
29. **Code organization** — Follow directory structure and naming conventions
30. **Documentation** — Update SSOT and API docs when adding features

---

## 23. Cross-Document Traceability Map

Use this map to trace a system capability across canonical specs, reference documents, and implementation touchpoints. Each row shows where requirements live, where they surface in routes, and which data model or middleware enforces them.

### 23.1 Identity & Authentication

| Capability | Canonical Requirements | Reference Detail | Routes / Surfaces | Middleware / Services | Data Model |
|---|---|---|---|---|---|
| Owner login (password → Owner JWT) | `authentication.md` (JWT claims, `typ=owner`, issuer/audience validation) | `response-schemas.md` (401 behavior) | `POST /console/login` (Public API) | Console pipelines, `AuthService` | `owners`, `refresh_tokens` |
| ApiKey exchange (public_id + secret → Key JWT) | `authentication.md` (generic 401 on failure) | `identifier-encoding.md` (`apub_...` usage) | `POST /api/auth/exchange` (Public API) | Public pipeline, `AuthService` | `key_public_ids`, `keys`, `refresh_tokens` |
| Refresh token rotation | `authentication.md` (single-use rotation + replay detection) | `logging-and-audit.md` (`refresh:replay_attempt`) | `POST /api/auth/refresh` (Public API) | Public pipeline, `AuthService` | `refresh_tokens` |
| JWKS publishing | `authentication.md` (kid overlap, caching) | `environment-configuration.md` (JWT key paths) | `GET /.well-known/jwks.json` (Public API) | Public pipeline | Key files on disk |

### 23.2 Authorization & Access Control

| Capability | Canonical Requirements | Reference Detail | Routes / Surfaces | Middleware / Services | Data Model |
|---|---|---|---|---|---|
| Global permissions + post bitmasks | `authorization.md` (dual-layer model) | `permissions.md` (catalog + presets) | All post/comment routes | Service-layer checks | `post_access`, `keys.permissions_json` |
| Use Key restrictions | `authorization.md` (forbid `posts:create`, `keys:issue`) | `key-capabilities.md` (capability matrix) | `POST /api/keys/{authorKeyId}/use` | `KeyService` validation | `keys` |
| 404 vs 403 visibility | `authorization.md` (VIEW mask hiding) | `response-schemas.md` (error format) | Post read/comment/access endpoints | `PostService`/`CommentService` | `post_access`, `posts` |
| Group-based access grants | `post-sharing.md` (grant/revoke workflows) | `routes-inventory.md` (grant/revoke endpoints) | Console JSON + Gateway JSON | `PostService`, `GroupService` | `groups`, `group_members`, `post_access` |

### 23.3 Key Lifecycle & Provenance

| Capability | Canonical Requirements | Reference Detail | Routes / Surfaces | Middleware / Services | Data Model |
|---|---|---|---|---|---|
| Mint Primary Author Key | `key-lifecycle.md` | `api-reference.md` | `POST /console/keys/primary` (Console JSON) | `JwtOwnerMiddleware`, `KeyService` | `keys`, `key_public_ids`, `audit_events` |
| Mint Secondary Author Key | `key-lifecycle.md` (envelope rule) | `permissions.md` | `POST /api/keys/{authorKeyId}/secondary` (Gateway JSON) | `JwtKeyMiddleware`, `KeyService` | `keys`, `key_public_ids` |
| Mint Use Key | `key-lifecycle.md` (Use Key restrictions) | `key-capabilities.md` | `POST /api/keys/{authorKeyId}/use` (Gateway JSON) | `JwtKeyMiddleware`, `KeyService` | `keys`, `key_public_ids` |
| Rotate key | `key-lifecycle.md` | `logging-and-audit.md` (`keys:rotate`) | `POST /console/keys/{keyId}/rotate` | `JwtOwnerMiddleware`, `KeyService` | `keys`, `key_public_ids`, `audit_events` |
| Deactivate key + cascade | `key-lifecycle.md` | `database-schema.md` (lineage fields) | `POST /console/keys/{keyId}/deactivate?cascade=true` | `JwtOwnerMiddleware`, `KeyService` | `keys` (recursive CTE) |
| Use count / device limits | `key-lifecycle.md` | `identifier-encoding.md` (key_id format) | ApiKey exchange + Gateway JSON | `UseKeyLimitMiddleware` / `AuthService` | `keys`, `key_devices` |

### 23.4 Posts, Comments, Sharing, Feeds

| Capability | Canonical Requirements | Reference Detail | Routes / Surfaces | Middleware / Services | Data Model |
|---|---|---|---|---|---|
| Create post | `post-sharing.md` | `api-reference.md` | `POST /api/posts` (Gateway JSON) | `JwtKeyMiddleware`, `PostService` | `posts` |
| Read/list posts | `authorization.md` (VIEW mask) | `response-schemas.md` | `GET /api/posts`, `GET /api/posts/{postId}` | `PostService` | `posts`, `post_access`, `group_members` |
| Comment on post | `post-sharing.md` | `api-reference.md` | `POST /api/posts/{postId}/comments` | `CommentService` | `comments`, `post_access` |
| Grant/revoke access | `post-sharing.md` | `routes-inventory.md` | `POST /api/posts/{postId}/access`, `DELETE /api/posts/{postId}/access/{accessId}` | `PostService` | `post_access` |
| Use Key feed | `feed-system.md` (path guard) | `identifier-encoding.md` | `GET /api/feed/use/{useKeyId}` | `FeedService` | `posts`, `post_access`, `group_members` |

### 23.5 Operational Guarantees

| Capability | Canonical Requirements | Reference Detail | Routes / Surfaces | Middleware / Services | Data Model |
|---|---|---|---|---|---|
| Standard response envelopes | `response-schemas.md` | `implementation-guide.md` (ResponseFactory) | All JSON endpoints | Controllers + `ErrorHandlingMiddleware` | N/A |
| Logging & audit rules | `logging-and-audit.md` | `identifier-encoding.md` (hex32 IDs) | All endpoints | `LoggingService` + `AuditService` | `audit_events` |
| Rate limiting | `architecture-overview.md` (pipeline order) | `environment-configuration.md` | All endpoints | `RateLimitMiddleware` | Optional DB backing |
| CSRF HTML-only | `architecture-overview.md` | `layering-rules.md` | Console HTML only | `Slim\Csrf\Guard` | N/A |

---

**End of SSOT Document**

This document consolidates all concepts, rules, do's, don'ts, and implementation guidelines from the CRE8.pw canon documentation. For detailed specifications, refer to the individual canon documents (00-11).
