# CRE8.pw Elevator Pitches

**Version:** 1.0.0  
**Last Updated:** 2026-01-25  
**Purpose:** Elevator pitches of varying lengths for presenting CRE8.pw to different audiences

---

## 30-Second Elevator Pitch

**CRE8.pw is a secure content platform that solves the hardest problem in sharing: controlled access with full accountability.**

Owners mint keys; keys mint child keys with delegated permissions. Every action requires both global permissions and post-level access control. RS256 JWT authentication, single-use refresh tokens, and complete provenance tracking ensure security. Structured JSON logging and standardized APIs make integration trivial.

**Bottom line:** Drop-in authentication and authorization that handles complex sharing scenarios while maintaining security and auditability. Perfect for developers who need fine-grained access control without building it from scratch.

---

## 2-Minute Elevator Pitch

### The Problem

Most platforms struggle with controlled content sharing. You need to share posts with specific people, limit access by time or device, delegate permissions safely, and maintain a complete audit trail—all while keeping security tight.

### The Solution: CRE8.pw

CRE8.pw is a security-first content platform built on hierarchical key-based authentication. Here's what makes it powerful:

**Hierarchical Key System:**
- Owners mint Primary Author Keys
- Keys mint child keys with delegated permissions (envelope rule: child ⊆ parent)
- Full provenance tracking—every key traces back to its root
- Bulk revocation: disable a key and all descendants instantly

**Two-Layer Authorization:**
- Global permissions (`posts:create`, `keys:issue`, `comments:write`)
- Post-level bitmasks (VIEW, COMMENT, MANAGE_ACCESS)
- Both required for post-scoped actions—no privilege escalation

**Security Built-In:**
- RS256 JWT with token typing (`typ=owner` vs `typ=key`) prevents confusion attacks
- Single-use refresh tokens with automatic rotation and replay detection
- Argon2id hashing for all secrets
- Never logs passwords, secrets, or tokens

**Easy Integration:**
- Simple ApiKey exchange: `Authorization: ApiKey <public_id>:<secret>`
- Standardized JSON responses with consistent error codes
- Comprehensive documentation with code examples
- Clean architecture: middleware → controllers → services → repositories

**Operational Excellence:**
- Structured JSON logging across channels (api, auth, security, db)
- Complete audit trail for all state changes
- Rate limiting (IP-based or principal-based)
- CORS, HTTPS/HSTS, CSRF (HTML only)

**Use Cases:**
- Share posts with single-use keys (1-time access)
- Delegate content creation to team members (Secondary Author Keys)
- Grant group access to posts (bulk sharing)
- Track who created what and when (full provenance)

**Bottom Line:** CRE8.pw gives you enterprise-grade security and fine-grained access control without the complexity. Perfect for developers who need a drop-in authentication and authorization platform.

---

## 5-Minute Elevator Pitch

### Introduction

CRE8.pw is a secure content creation and sharing platform that solves controlled access with full accountability. Built on hierarchical key-based authentication, it provides fine-grained permissions, post-level access control, and complete provenance tracking—all while maintaining security-first principles.

### Core Architecture

**Dual-Surface Design:**
- **Console** (`/console/*`): Owner-facing HTML pages and JSON API for key/group/post management
- **Gateway** (`/api/*`): Machine-facing JSON API for programmatic content creation and sharing

**Four Request Pipelines:**
1. **Public API**: No auth (health checks, JWKS, auth endpoints)
2. **Console JSON**: Owner JWT (`typ=owner`), principal-based rate limiting
3. **Gateway JSON**: Key JWT (`typ=key`), principal-based rate limiting
4. **Console HTML**: CSRF protection (HTML routes only, never JSON)

**Layered Architecture:**
- **Middleware**: HTTPS, CORS, rate limiting, JWT verification, validation, error handling
- **Controllers**: HTTP adapters (extract params, call service, shape response)
- **Services**: Business logic, authorization checks, transactions, audit emission
- **Repositories**: Data access with PDO prepared statements, hex32 ↔ BINARY(16) conversion

### Security Model

**Authentication:**
- **RS256 JWT**: Asymmetric cryptography with JWKS key rotation support
- **Token Typing**: `typ=owner` vs `typ=key` prevents token confusion attacks
- **ApiKey Exchange**: `Authorization: ApiKey <public_id>:<secret>` → access + refresh tokens
- **Single-Use Refresh Tokens**: Automatic rotation with replay detection

**Authorization:**
- **Two-Layer System**: Global permissions + post-level bitmasks
- **Permission Envelope**: Child permissions ⊆ parent permissions (enforced at mint time)
- **Use Key Restrictions**: Cannot have `posts:create` or `keys:issue`
- **Visibility Rules**: 404 when hiding existence, 403 when revealing lack of permission

**Security Features:**
- Argon2id hashing for passwords and secrets
- Never logs secrets (passwords, ApiKey secrets, refresh tokens, private keys)
- Generic auth errors (never reveal existence)
- Prepared statements only (no SQL injection)
- Rate limiting (IP-based or principal-based)

### Extensibility

**Clean Architecture:**
- **Dependency Injection**: PHP-DI with autowiring for all components
- **Standardized Responses**: Consistent success/error envelopes
- **Middleware Pipeline**: Easy to add cross-cutting concerns
- **Repository Pattern**: Isolated data access, easy to swap implementations

**Adding New Endpoints:**
1. Add route to `config/routes.php` under correct group
2. Add validation rule to `config/validation.php` (keyed by `"METHOD /pattern"`)
3. Implement controller (thin adapter)
4. Implement service (business logic + audits)
5. Implement repository methods if needed
6. Test authorization enforcement

**Configuration-Driven:**
- Environment variables for all settings (JWT, CORS, rate limits, logging)
- Bootstrap validation (fail fast if misconfigured)
- Centralized validation schemas
- Flexible rate limiting buckets

### Integration Simplicity

**ApiKey Exchange:**
```http
POST /api/auth/exchange
Authorization: ApiKey apub_xyz:sec_abc123

→ Returns: { access_token, refresh_token, expires_in }
```

**Making Authenticated Requests:**
```http
POST /api/posts
Authorization: Bearer <access_token>
Content-Type: application/json

{ "content": "Hello CRE8.pw!" }
```

**Standardized Responses:**
```json
// Success
{ "data": { "post_id": "...", "content": "..." } }

// Error
{ "error": { "code": "validation_failed", "message": "...", "details": { "fields": {...} } } }
```

**Error Codes:**
- `401 unauthorized`: Auth failure (expired token, invalid ApiKey)
- `403 forbidden`: Authz failure (missing permission or mask)
- `404 not_found`: Resource missing or hidden
- `422 validation_failed`: Validation errors (includes `details.fields`)
- `429 rate_limited`: Rate limit exceeded (includes `retry_after_seconds`)

### Logging & Observability

**Structured JSON Logging:**
- Multiple channels: `api`, `auth`, `security`, `db`
- Never logs secrets (passwords, ApiKey secrets, refresh tokens)
- Includes request context (method, path, status, latency, principal IDs)
- Correlation IDs for tracing

**Audit Trail:**
- Required events: `keys:mint`, `keys:rotate`, `posts:create`, `posts:access:grant`, `owners:login`
- Includes actor, subject, action, metadata, IP, user agent
- Append-only records in `audit_events` table

**Rate Limiting:**
- Three buckets: GENERAL (100/min), AUTH (10/min), API (60/min)
- Keying: IP (public), `owner_id` (Console), `key_id` (Gateway)
- Backing: Memory (default) or database (persistent)

### Use Cases

**Single-Use Sharing:**
1. Create post
2. Mint Use Key with `use_count: 1`
3. Grant Use Key access to post
4. Share ApiKey credentials
5. Recipient exchanges ApiKey, reads/comments
6. After 1 use, key is exhausted

**Team Delegation:**
1. Owner mints Primary Author Key
2. Primary Key mints Secondary Author Key for team member
3. Secondary Key creates posts (within permission envelope)
4. Owner can view downstream lineage and revoke if needed

**Group-Based Sharing:**
1. Owner creates Group
2. Owner adds Keys to Group
3. Owner grants Group access to Post
4. All group members inherit access

### Bottom Line

CRE8.pw provides enterprise-grade security and fine-grained access control with a clean, extensible architecture. Perfect for developers who need a drop-in authentication and authorization platform that handles complex sharing scenarios while maintaining security and auditability.

---

## 20-Minute Presentation

### Slide 1: Title Slide

**CRE8.pw: Secure Content Platform with Hierarchical Key-Based Authentication**

*Drop-in authentication and authorization for developers*

---

### Slide 2: The Problem

**Challenges in Content Sharing:**
- Controlled access: Share with specific people, limit by time/device
- Delegation: Grant permissions safely without privilege escalation
- Accountability: Track who created what and when
- Security: Prevent token confusion, replay attacks, credential enumeration
- Integration: Easy to integrate, hard to misuse

**Most platforms solve one or two of these. CRE8.pw solves all of them.**

---

### Slide 3: The Solution Overview

**CRE8.pw is a security-first content platform built on:**
- Hierarchical key-based authentication
- Two-layer authorization (global permissions + post bitmasks)
- Full provenance tracking
- Dual-surface architecture (Console + Gateway)
- Operational excellence (structured logging, audit trail, rate limiting)

**Result:** Enterprise-grade security with developer-friendly APIs.

---

### Slide 4: Core Concepts

**Principals:**
- **Owners** (Human): Authenticate with email/password, mint Primary Author Keys
- **Keys** (Machine): Authenticate with ApiKey, create posts, mint child keys

**Key Types:**
- **Primary Author Key**: Root key, can create posts and mint children
- **Secondary Author Key**: Delegated key, can create posts and mint children (within envelope)
- **Use Key**: Interaction-only, can read/comment but cannot create posts or mint keys

**Surfaces:**
- **Console** (`/console/*`): Owner-facing HTML + JSON
- **Gateway** (`/api/*`): Machine-facing JSON API

---

### Slide 5: Hierarchical Key System

**Key Minting Flow:**
```
Owner
 └─ Primary Author Key [posts:create, keys:issue, comments:write]
     ├─ Secondary Author Key [posts:create, comments:write]
     │   └─ Use Key [comments:write]
     └─ Use Key [comments:write]
```

**Key Features:**
- **Permission Envelope**: Child permissions ⊆ parent permissions (enforced at mint time)
- **Immutability**: Permissions cannot change after minting (use rotation)
- **Lineage Tracking**: Every key traces back to root via `initial_author_key_id`
- **Bulk Revocation**: Disable key + all descendants in one operation

**Example:**
- Owner mints Primary Key with `["posts:create", "keys:issue", "comments:write"]`
- Primary Key mints Secondary Key with `["posts:create", "comments:write"]` ✅ (subset)
- Primary Key mints Use Key with `["posts:create"]` ❌ (Use Keys cannot have `posts:create`)

---

### Slide 6: Two-Layer Authorization

**Global Permissions:**
- `posts:create`, `keys:issue`, `comments:write`, `groups:read`, etc.
- Stored in JWT, checked by services

**Post-Level Bitmasks:**
- VIEW (0x01): Read post
- COMMENT (0x02): Create comments
- MANAGE_ACCESS (0x08): Grant/revoke access

**Combined Checks:**
- Most actions require **both** global permission AND post mask
- Example: Comment requires `comments:write` + COMMENT mask (0x02)

**Visibility Rules:**
- **404 Not Found**: Resource doesn't exist OR principal lacks VIEW mask (hide existence)
- **403 Forbidden**: Resource exists and visible BUT principal lacks action permission

---

### Slide 7: Security Architecture

**Authentication:**
- **RS256 JWT**: Asymmetric cryptography with JWKS key rotation
- **Token Typing**: `typ=owner` vs `typ=key` prevents confusion attacks
- **ApiKey Exchange**: `Authorization: ApiKey <public_id>:<secret>`
- **Single-Use Refresh Tokens**: Automatic rotation with replay detection

**Security Features:**
- Argon2id hashing for passwords and secrets
- Never logs secrets (passwords, ApiKey secrets, refresh tokens)
- Generic auth errors (never reveal existence)
- Prepared statements only (no SQL injection)
- Rate limiting (IP-based or principal-based)

**CSRF Scope:**
- **HTML routes only**: `/`, `/console/register`, `/console/login`, `/console/dashboard`
- **Never on JSON endpoints**: Token-based auth doesn't need CSRF

---

### Slide 8: Request Pipeline Architecture

**Four Canonical Pipelines:**

**Public API:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (IP-based)
4. Body Parsing
5. ValidationMiddleware
6. Routing → Controller → Service → Repository
7. ErrorHandlingMiddleware

**Console JSON:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (Principal: `owner_id`)
4. JwtOwnerMiddleware (`typ=owner`)
5. Body Parsing
6. ValidationMiddleware
7. Routing → Controller → Service → Repository
8. ErrorHandlingMiddleware

**Gateway JSON:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (Principal: `key_id`)
4. JwtKeyMiddleware (`typ=key`)
5. Body Parsing
6. ValidationMiddleware
7. Routing → Controller → Service → Repository
8. ErrorHandlingMiddleware

**Console HTML:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware (IP-based)
4. Slim\Csrf\Guard (HTML routes only)
5. Render HTML
6. ErrorHandlingMiddleware

---

### Slide 9: Layered Architecture

**Responsibilities:**

**Middleware:**
- Cross-cutting concerns (HTTPS, CORS, rate limiting, JWT verification, validation, error handling)
- **Forbidden**: Business logic, database access

**Controllers:**
- HTTP adapters (extract params, call service, shape response)
- **Forbidden**: Business logic, direct database access, multi-service orchestration

**Services:**
- Business logic, authorization checks, transactions, audit emission
- **Forbidden**: HTTP concerns, direct SQL queries

**Repositories:**
- Data access with PDO prepared statements, hex32 ↔ BINARY(16) conversion
- **Forbidden**: Business logic, permission checks, HTTP concerns

**Benefits:**
- Clear separation of concerns
- Easy to test (mock dependencies)
- Easy to extend (add new endpoints following patterns)

---

### Slide 10: Extensibility

**Adding a New Endpoint:**

1. **Determine surface** (Console HTML, Console JSON, Gateway JSON)
2. **Add route** to `config/routes.php` under correct group
3. **Add validation** rule to `config/validation.php` (keyed by `"METHOD /pattern"`)
4. **Implement controller** (thin adapter)
5. **Implement service** (business logic + audits)
6. **Implement repository** methods if needed
7. **Test** authorization enforcement and error responses

**Example: Adding a new Gateway endpoint:**
```php
// config/routes.php
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->post('/posts/{postId}/publish', [PostController::class, 'publish']);
})->add(JwtKeyMiddleware::class);

// config/validation.php
"POST /api/posts/{postId}/publish" => [
    'body' => v::key('public', v::boolType()),
    'rejectUnknown' => true,
],

// Controller
public function publish(Request $req, Response $res, array $args): Response {
    $keyId = $req->getAttribute('key_id');
    $postId = $args['postId'];
    $body = $req->getParsedBody();
    
    $post = $this->postService->publishPost($keyId, $postId, $body['public']);
    return $this->responseFactory->json($res, ['data' => $post]);
}

// Service
public function publishPost(string $keyId, string $postId, bool $public): array {
    // Check permission: posts:access:manage
    // Check mask: MANAGE_ACCESS (0x08)
    // Update post
    // Emit audit event
    // Return post
}
```

---

### Slide 11: Integration Simplicity

**ApiKey Exchange:**
```http
POST /api/auth/exchange
Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6

→ Returns:
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImNyZTgtcnMyNTYtMjAyNi0wMSJ9...",
    "refresh_token": "rt_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "expires_in": 900
  }
}
```

**Making Authenticated Requests:**
```http
POST /api/posts
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "content": "Hello CRE8.pw!",
  "title": "My First Post"
}

→ Returns:
{
  "data": {
    "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
    "author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "content": "Hello CRE8.pw!",
    "title": "My First Post",
    "created_at": "2026-01-25T10:30:00Z"
  }
}
```

**Error Handling:**
```json
// Validation Error (422)
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "content": ["Content is required"],
        "title": ["Title must be at most 255 characters"]
      }
    }
  }
}

// Permission Error (403)
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient permissions",
    "details": {
      "required": ["posts:create"]
    }
  }
}

// Rate Limit Error (429)
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

---

### Slide 12: Use Case: Single-Use Sharing

**Scenario:** Share a post with Alice for one-time access

**Steps:**
1. **Create Post:**
   ```http
   POST /api/posts
   Authorization: Bearer <author_jwt>
   { "content": "Exclusive content for Alice" }
   → Returns: { "data": { "post_id": "abc123..." } }
   ```

2. **Mint Use Key:**
   ```http
   POST /api/keys/{authorKeyId}/use
   Authorization: Bearer <author_jwt>
   {
     "permissions": ["posts:read", "comments:write"],
     "use_count": 1,
     "label": "Share Link for Alice"
   }
   → Returns: { "data": { "key_id": "...", "key_public_id": "apub_xyz", "key_secret": "sec_abc" } }
   ```

3. **Grant Access:**
   ```http
   POST /api/posts/abc123.../access
   Authorization: Bearer <author_jwt>
   {
     "target_type": "key",
     "target_id": "<use_key_id>",
     "permission_mask": 3  // VIEW + COMMENT
   }
   ```

4. **Share Credentials:**
   - Send Alice: `ApiKey: apub_xyz:sec_abc`

5. **Alice Exchanges ApiKey:**
   ```http
   POST /api/auth/exchange
   Authorization: ApiKey apub_xyz:sec_abc
   → Returns: { "data": { "access_token": "...", "refresh_token": "..." } }
   ```

6. **Alice Reads Post:**
   ```http
   GET /api/posts/abc123...
   Authorization: Bearer <alice_access_token>
   → Returns: { "data": { "post": {...} } }
   ```

7. **After 1 Use:**
   - Next exchange attempt → `403 Forbidden` with `error.code = "use_limit_exceeded"`

---

### Slide 13: Use Case: Team Delegation

**Scenario:** Owner delegates content creation to team member

**Steps:**
1. **Owner Mints Primary Key:**
   ```http
   POST /console/keys/primary
   Authorization: Bearer <owner_jwt>
   {
     "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write"],
     "label": "Content Team Key"
   }
   → Returns: { "data": { "key_id": "...", "key_public_id": "apub_primary", "key_secret": "sec_primary" } }
   ```

2. **Primary Key Mints Secondary Key:**
   ```http
   POST /api/keys/{primaryKeyId}/secondary
   Authorization: Bearer <primary_jwt>
   {
     "permissions": ["posts:create", "posts:read", "comments:write"],
     "label": "Team Member Key"
   }
   → Returns: { "data": { "key_id": "...", "key_public_id": "apub_secondary", "key_secret": "sec_secondary" } }
   ```

3. **Team Member Creates Posts:**
   ```http
   POST /api/posts
   Authorization: Bearer <secondary_jwt>
   { "content": "Team post" }
   ```

4. **Owner Views Lineage:**
   ```http
   GET /console/keys/{primaryKeyId}/lineage
   Authorization: Bearer <owner_jwt>
   → Returns: { "data": { "tree": { "children": [{ "type": "secondary", "children": [...] }] } } }
   ```

5. **Owner Revokes (if needed):**
   ```http
   POST /console/keys/{primaryKeyId}/deactivate?cascade=true
   Authorization: Bearer <owner_jwt>
   → Deactivates primary key + all descendants
   ```

---

### Slide 14: Logging & Observability

**Structured JSON Logging:**

**Channels:**
- `api`: Request summaries (method, path, status, latency)
- `auth`: Auth events (exchange, login, refresh)
- `security`: Security events (auth failures, refresh replay, rate limits, CSRF)
- `db`: Database errors (query failures, transaction rollbacks)

**Log Format:**
```json
{
  "timestamp": "2026-01-25T10:30:00Z",
  "level": "INFO",
  "channel": "api",
  "message": "Request completed",
  "request_id": "req_abc123",
  "method": "POST",
  "path": "/api/posts",
  "status": 201,
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "ip": "192.168.1.100",
  "user_agent": "curl/7.64.1",
  "latency_ms": 45
}
```

**Never Logs:**
- Passwords (plaintext)
- ApiKey secrets
- Refresh tokens (plaintext)
- Private keys
- Stack traces (in production)

**Audit Trail:**
- Required events: `keys:mint`, `keys:rotate`, `posts:create`, `posts:access:grant`, `owners:login`
- Includes: actor, subject, action, metadata, IP, user agent, timestamp
- Append-only records in `audit_events` table

---

### Slide 15: Rate Limiting

**Three Buckets:**

| Bucket | Default Limit | Keying Strategy | Used For |
|:---:|:---:|:---:|:---:|
| GENERAL | 100 per minute | IP (public), `owner_id` (Console) | Default endpoints |
| AUTH | 10 per minute | IP | Authentication endpoints |
| API | 60 per minute | `key_id` (Gateway) | Gateway endpoints |

**Backing Store:**
- **Memory** (default): Fast, resets on restart
- **Database** (production): Persistent across restarts

**Response on Limit Exceeded:**
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

**Logging:**
- Rate limit hits logged to `security` channel
- Includes bucket, key (sanitized), IP, path

---

### Slide 16: Configuration & Environment

**Environment Variables:**

**Application:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cre8.pw
```

**Database:**
```bash
DB_HOST=localhost
DB_NAME=cre8pw
DB_USER=cre8_user
DB_PASS=secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin
```

**JWT:**
```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=2592000
```

**CORS:**
```bash
CORS_ALLOWED_ORIGINS=https://cre8.pw,https://www.cre8.pw
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type
```

**Rate Limiting:**
```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory
```

**Bootstrap Validation:**
- Application fails fast if critical vars missing or invalid
- Prevents misconfigured deployments

---

### Slide 17: Technology Stack

**Core:**
- PHP 8.3+
- Slim Framework 4.15+
- MariaDB 11.4.x (utf8mb4_bin collation)

**Security:**
- RS256 JWT (firebase/php-jwt)
- Argon2id password/secret hashing (ext-sodium)
- HTTPS enforcement with HSTS
- CORS with allowlist (neomerx/cors-psr7)
- CSRF protection (slim/csrf, HTML routes only)

**Infrastructure:**
- PHP-DI 7.1+ for dependency injection
- Monolog 3.9+ for structured logging
- Symfony rate-limiter 7.3+ for throttling
- Respect\Validation 2.4+ for input validation
- Guzzle 7.10+ for outbound HTTP

**Architecture:**
- PSR-7 request/response handling
- PSR-15 middleware pipeline
- PSR-11 dependency injection container
- PSR-3 logging interface

---

### Slide 18: Data Model

**Core Entities:**

**owners:**
- `id` (BINARY(16)), `email`, `password_hash` (Argon2id)

**keys:**
- `id` (BINARY(16)), `type` (primary/secondary/use), `permissions_json`, `active`
- Lineage: `issued_by_key_id`, `parent_key_id`, `initial_author_key_id`
- Limits: `use_count_limit`, `use_count_current`, `device_limit`

**posts:**
- `id` (BINARY(16)), `author_key_id`, `initial_author_key_id`, `content`, `title`

**post_access:**
- `id` (BINARY(16)), `post_id`, `target_type` (key/group), `target_id`, `permission_mask`

**refresh_tokens:**
- `id` (BINARY(16)), `subject_type` (owner/key), `subject_id`, `token_hash` (Argon2id)
- Lifecycle: `issued_at`, `expires_at`, `revoked_at`, `rotated_at`, `replaced_by_id`

**audit_events:**
- `id` (BINARY(16)), `actor_type`, `actor_id`, `action`, `subject_type`, `subject_id`, `metadata_json`

**ID Formats:**
- **Internal**: BINARY(16) for all primary/foreign keys
- **External**: hex32 (32-char lowercase hex) for routes, JSON, JWT claims
- **Key Public IDs**: `apub_...` format (used only for ApiKey exchange)

---

### Slide 19: API Reference Highlights

**Public Endpoints:**
- `GET /health` - Health check
- `GET /.well-known/jwks.json` - RS256 public keys
- `POST /api/auth/exchange` - ApiKey → JWT
- `POST /api/auth/refresh` - Refresh token rotation
- `POST /console/owners` - Owner registration
- `POST /console/login` - Owner login

**Console JSON (Owner-Protected):**
- `POST /console/keys/primary` - Mint Primary Author Key
- `GET /console/keys` - List keys
- `GET /console/keys/{keyId}/lineage` - View lineage tree
- `POST /console/keys/{keyId}/rotate` - Rotate key
- `POST /console/groups` - Create group
- `POST /console/posts/{postId}/access/grant-group` - Grant group access

**Gateway JSON (Key-Protected):**
- `POST /api/keys/{authorKeyId}/secondary` - Mint Secondary Author Key
- `POST /api/keys/{authorKeyId}/use` - Mint Use Key
- `POST /api/posts` - Create post
- `GET /api/posts/{postId}` - Get post
- `POST /api/posts/{postId}/access` - Grant post access
- `POST /api/posts/{postId}/comments` - Create comment
- `GET /api/feed/use/{useKeyId}` - Get Use Key feed

**Complete Reference:** See [api-reference.md](../06-api-reference/api-reference.md)

---

### Slide 20: Documentation

**Canon Documents:**
1. [introduction.md](introduction.md) — What is CRE8.pw?
2. [architecture-overview.md](../04-architecture/architecture-overview.md) — System architecture
3. [authentication.md](../05-authentication-authorization/authentication.md) — JWT, ApiKey exchange, refresh tokens
4. [authorization.md](../05-authentication-authorization/authorization.md) — Permission model, bitmasks
5. [api-reference.md](../06-api-reference/api-reference.md) — Complete API catalog
6. [feed-system.md](../06-api-reference/feed-system.md) — Feed visibility and pagination
7. [database-schema.md](../07-data-model/database-schema.md) — Database schema
8. [key-lifecycle.md](../03-core-concepts/key-lifecycle.md) — Key management
9. [post-sharing.md](../03-core-concepts/post-sharing.md) — Content sharing
10. [implementation-guide.md](../08-implementation/implementation-guide.md) — Developer manual
11. [logging-and-audit.md](../09-operations/logging-and-audit.md) — Operational concerns

**Reference Documents:**
- [identifier-encoding.md](../10-reference/identifier-encoding.md) — ID formats
- [environment-configuration.md](../10-reference/environment-configuration.md) — Complete .env reference
- [glossary.md](../03-core-concepts/glossary.md) — Terminology
- [canon-ssot.md](../12-comprehensive-reference/canon-ssot.md) — Consolidated reference

**Helper Documents:**
- Key capability matrix
- Permission matrix
- Route inventory
- Dependency wiring guide
- Component architecture

**All documentation is canonical, comprehensive, and example-driven.**

---

### Slide 21: Key Differentiators

**What Makes CRE8.pw Unique:**

1. **Hierarchical Key System**: Full provenance tracking with bulk revocation
2. **Two-Layer Authorization**: Global permissions + post bitmasks (no privilege escalation)
3. **Security-First**: RS256 JWT, single-use refresh tokens, never logs secrets
4. **Clean Architecture**: Easy to extend, test, and maintain
5. **Operational Excellence**: Structured logging, audit trail, rate limiting
6. **Developer-Friendly**: Standardized APIs, comprehensive documentation, simple integration

**Comparison:**
- **vs OAuth2**: CRE8.pw provides hierarchical delegation and post-level access control
- **vs API Keys**: CRE8.pw provides fine-grained permissions and provenance tracking
- **vs Custom Solutions**: CRE8.pw provides battle-tested security patterns and operational tools

---

### Slide 22: Getting Started

**Quick Start (5 minutes):**

1. **Register Owner:**
   ```http
   POST /console/owners
   { "email": "alice@example.com", "password": "SecurePassword123!" }
   ```

2. **Login:**
   ```http
   POST /console/login
   { "email": "alice@example.com", "password": "SecurePassword123!" }
   → Returns: { "data": { "access_token": "...", "refresh_token": "..." } }
   ```

3. **Mint Primary Author Key:**
   ```http
   POST /console/keys/primary
   Authorization: Bearer <owner_jwt>
   { "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write"] }
   → Returns: { "data": { "key_id": "...", "key_public_id": "apub_xyz", "key_secret": "sec_abc" } }
   ```

4. **Exchange ApiKey:**
   ```http
   POST /api/auth/exchange
   Authorization: ApiKey apub_xyz:sec_abc
   → Returns: { "data": { "access_token": "...", "refresh_token": "..." } }
   ```

5. **Create Post:**
   ```http
   POST /api/posts
   Authorization: Bearer <key_jwt>
   { "content": "Hello CRE8.pw!" }
   ```

**That's it! You're ready to build.**

---

### Slide 23: Summary

**CRE8.pw provides:**

✅ **Security**: RS256 JWT, single-use refresh tokens, Argon2id hashing, never logs secrets  
✅ **Fine-Grained Access Control**: Global permissions + post bitmasks  
✅ **Full Accountability**: Complete provenance tracking with bulk revocation  
✅ **Easy Integration**: Simple ApiKey exchange, standardized JSON responses  
✅ **Extensibility**: Clean architecture, easy to add endpoints  
✅ **Operational Excellence**: Structured logging, audit trail, rate limiting  

**Perfect for:**
- Developers who need drop-in authentication and authorization
- Teams that need controlled content sharing
- Organizations that need full accountability and audit trails

**Bottom Line:** Enterprise-grade security with developer-friendly APIs. No compromises.

---

### Slide 24: Questions & Next Steps

**Questions?**

**Next Steps:**
1. Read the documentation: [introduction.md](introduction.md)
2. Try the Quick Start: [introduction.md](introduction.md#quick-start-path)
3. Explore the API: [api-reference.md](../06-api-reference/api-reference.md)
4. Review implementation guide: [implementation-guide.md](../08-implementation/implementation-guide.md)

**Resources:**
- [Master TOC](../../TOC.md) — Top-level index
- [Master SSOT](../../SSOT.md) — SSOT hub
- [Full documentation index](../table-of-contents.md) — Complete catalog
- [API reference](../06-api-reference/api-reference.md)
- [Environment config](../10-reference/environment-configuration.md)
- [Glossary](../03-core-concepts/glossary.md)

**Thank you!**

---

## Key Talking Points for All Pitches

### Security & Simplicity

**Security:**
- RS256 JWT with token typing prevents confusion attacks
- Single-use refresh tokens with automatic rotation and replay detection
- Argon2id hashing for all secrets
- Never logs passwords, ApiKey secrets, refresh tokens, or private keys
- Generic auth errors prevent credential enumeration
- Prepared statements only (no SQL injection)

**Simplicity:**
- Simple ApiKey exchange: `Authorization: ApiKey <public_id>:<secret>`
- Standardized JSON responses with consistent error codes
- Clear error messages with actionable details
- Comprehensive documentation with code examples

### Extensibility

**Clean Architecture:**
- Layered design: Middleware → Controllers → Services → Repositories
- Dependency injection with PHP-DI (autowiring)
- Standardized response envelopes
- Easy to add endpoints following established patterns

**Configuration-Driven:**
- Environment variables for all settings
- Centralized validation schemas
- Flexible rate limiting buckets
- Bootstrap validation prevents misconfiguration

### Ease of Integration

**Simple Authentication:**
- ApiKey exchange returns access + refresh tokens
- Bearer token authentication on all protected endpoints
- Automatic token refresh handling

**Standardized APIs:**
- Consistent response format: `{ "data": {...} }` or `{ "error": {...} }`
- Clear error codes: `401`, `403`, `404`, `422`, `429`
- Validation errors include `details.fields` for field-level errors

**Comprehensive Documentation:**
- 11 canon documents covering all aspects
- 4 appendices with reference materials
- Code examples for all common scenarios
- Quick start guide gets you running in 5 minutes

### Logging Approaches

**Structured JSON Logging:**
- Multiple channels: `api`, `auth`, `security`, `db`
- Includes request context: method, path, status, latency, principal IDs
- Correlation IDs for tracing
- Never logs secrets

**Audit Trail:**
- Required events for all state changes
- Includes actor, subject, action, metadata, IP, user agent
- Append-only records for compliance

**Rate Limiting:**
- Three buckets with different limits
- IP-based (public) or principal-based (authenticated)
- Configurable backing store (memory or database)
- Clear error responses with retry information

---

**End of Elevator Pitches Document**
