# Architecture & Request Pipeline

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** This document is the authoritative specification for request lifecycle, middleware ordering, validation selection, and cross-cutting security concerns (HTTPS/HSTS, CORS, rate limiting, and CSRF scope).

**SSoT Ownership:**
- Middleware ordering for all four pipeline variants (Public / Console JSON / Gateway JSON / Console HTML)
- CSRF application scope and operational rules (HTML routes only)
- Validation middleware selection rules (`METHOD /pattern`) and failure behavior
- Definitions of middleware responsibilities (what each middleware is allowed/required to do)

---

## 1. High-Level Architecture

CRE8.pw runs as a single Slim 4 application with strict separation of concerns across two surfaces: Console (Owner-facing) and Gateway (API-facing).

**Request Flow:**
```
public/index.php → Middleware Pipeline → Slim Routing → Controller → Service → Repository (PDO)
```

### Responsibility Boundaries (Normative)

| Layer | Responsibilities | Forbidden |
|---|---|---|
| **Middleware** | Cross-cutting concerns (HTTPS, CORS, auth verification, rate limiting, validation, error normalization) | Business logic, database access, multi-step workflows |
| **Controllers** | HTTP adapters (parse request, call service, shape response) | Business logic, direct database access, multi-repo coordination |
| **Services** | Domain rules, authorization checks, transactions, audit emission | HTTP concerns, middleware logic |
| **Repositories** | Prepared SQL, data access | HTTP concerns, authorization checks, business rules |

Full implementation patterns are defined in **[implementation-guide.md](../08-implementation/implementation-guide.md)**.

---

## 2. Surfaces and Request Types

CRE8.pw exposes four distinct request types:

### 2.1 Console HTML (Browser UI)
- **Routes:** `GET /`, `GET /console/register`, `GET /console/login`, `GET /console/dashboard`
- **Authentication:** Session-based or Owner JWT (for dashboard)
- **CSRF:** **Required** (Slim CSRF Guard)
- **Response:** HTML pages

### 2.2 Console JSON (Owner-Protected)
- **Routes:** `/console/*` JSON endpoints
- **Authentication:** Owner JWT (`typ=owner`)
- **CSRF:** **Not required** (JSON endpoints never require CSRF)
- **Response:** JSON (standardized envelopes)

### 2.3 Gateway JSON (Key-Protected)
- **Routes:** `/api/*` endpoints (except `/api/auth/*`)
- **Authentication:** Key JWT (`typ=key`)
- **CSRF:** **Not required**
- **Response:** JSON (standardized envelopes)

### 2.4 Public API (No Authentication)
- **Routes:** `/health`, `/.well-known/jwks.json`, `/api/auth/*`, `/console/owners`, `/console/login`
- **Authentication:** None (or basic auth for specific endpoints)
- **CSRF:** Not applicable
- **Response:** JSON or specific formats (JWKS)

**Surface Separation:** Enforced by route groups and JWT token typing (`typ=owner` vs `typ=key`).

**Identifier Formats:** All principal and resource IDs exposed in JSON/routes/logs use lowercase 32-character hex (`hex32`). Key public IDs (`apub_...`) are used only for ApiKey exchange. See **[identifier-encoding.md](../10-reference/identifier-encoding.md)**.

---

## 3. CSRF Scope (Normative: HTML Only)

### 3.1 Critical Rule

**CSRF middleware is only applied to HTML routes.**

- ✅ **Apply CSRF Guard to:** Console HTML routes (`/`, `/console/register`, `/console/login`, `/console/dashboard`)
- ❌ **Do not apply CSRF Guard to:** Console JSON endpoints, Gateway JSON endpoints, Public API JSON endpoints

### 3.2 Rationale

JSON endpoints use token-based authentication (Bearer JWT). CSRF protection is unnecessary and inappropriate for stateless token auth. CSRF is only relevant for browser-based session/cookie authentication used in HTML form submissions.

### 3.3 CSRF Token Exposure (HTML Routes Only)

Console HTML routes may need CSRF tokens for forms or AJAX requests. The following mechanisms exist:

**Slim\Csrf\Guard:**
- Generates and validates CSRF token pairs
- Stores tokens in session/token store

**CsrfExposeMiddleware** (runs after CSRF Guard):
- Exposes tokens as request attributes (`csrf_name`, `csrf_value`)
- Exposes tokens as response headers (`X-CSRF-Name`, `X-CSRF-Value`) for XHR usage

**Utilities/Csrf.php:**
- `Csrf::hiddenFields($request)` → Returns HTML hidden inputs for server-rendered forms
- `Csrf::headerArray($request)` → Returns associative array for AJAX header injection

### 3.4 Compatibility Note

The Console dashboard may send `X-CSRF-*` headers on JSON AJAX calls out of legacy habit. The server **must not require** these headers for Console JSON endpoints. If present, they may be ignored.

---

## 4. Canonical Middleware Pipelines (Normative)

### 4.1 Public API Pipeline (No Principal)

**Applies to:**
- `/health`
- `/.well-known/jwks.json`
- `/api/auth/exchange`
- `/api/auth/refresh`
- `/console/owners` (Owner registration)
- `/console/login` (Owner login)

**Middleware Order:**
1. **HttpsMiddleware** — Enforce HTTPS; set HSTS headers
2. **CorsMiddleware** — Apply CORS headers per env allowlist
3. **RateLimitMiddleware** — IP-based keying (buckets: `GENERAL` or `AUTH`)
4. **Body Parsing Middleware** — Parse JSON + form data
5. **ValidationMiddleware** — If validator configured for `"METHOD /pattern"`
6. **Routing Middleware** — Slim route dispatch
7. **Controller → Service → Repository**
8. **ErrorHandlingMiddleware** — Normalize errors to standard schema, log

### 4.2 Console JSON Pipeline (Owner Principal)

**Applies to:** Owner-protected `/console/*` JSON endpoints

**Middleware Order:**
1. **HttpsMiddleware** — Enforce HTTPS; set HSTS
2. **CorsMiddleware** — Apply CORS headers
3. **RateLimitMiddleware** — Principal keying: `owner_id` (hex32, derived from JWT)
4. **JwtOwnerMiddleware** — Verify RS256; enforce `typ=owner`; attach `owner_id`, `roles`, `permissions`
5. **Body Parsing Middleware**
6. **ValidationMiddleware**
7. **Routing Middleware** → Controller → Service → Repository
8. **ErrorHandlingMiddleware**

### 4.3 Gateway JSON Pipeline (Key Principal)

**Applies to:** `/api/*` endpoints (except `/api/auth/*`)

**Middleware Order:**
1. **HttpsMiddleware**
2. **CorsMiddleware**
3. **RateLimitMiddleware** — Principal keying: `key_id` (hex32, derived from JWT)
4. **JwtKeyMiddleware** — Verify RS256; enforce `typ=key`; attach `key_id`, `roles`, `permissions`
5. **Body Parsing Middleware**
6. **ValidationMiddleware**
7. **Routing Middleware** → Controller → Service → Repository
8. **ErrorHandlingMiddleware**

### 4.4 Console HTML Pipeline (Browser UI)

**Applies to:** Console HTML pages (`/`, `/console/register`, `/console/login`, `/console/dashboard`)

**Middleware Order:**
1. **HttpsMiddleware**
2. **CorsMiddleware** (often strict allowlist)
3. **RateLimitMiddleware** — IP-based or session-based keying
4. **Slim\Csrf\Guard** — **HTML routes only**
5. **CsrfExposeMiddleware** (optional) — Expose tokens as headers/attributes
6. **Render HTML** (Twig, Blade, or native PHP templates)
7. **ErrorHandlingMiddleware** — HTML-friendly error pages

---

## 5. Middleware Responsibilities (Normative)

### 5.1 HttpsMiddleware

**Purpose:** Enforce HTTPS and apply HTTP Strict Transport Security (HSTS).

**Responsibilities:**
- Redirect HTTP → HTTPS (if behind proxy, respect `X-Forwarded-Proto` or similar)
- Apply HSTS headers in production (`Strict-Transport-Security: max-age=31536000; includeSubDomains`)
- Must not add domain logic or data transformation

**Configuration:**
- Environment variable: `APP_ENV` (apply HSTS only in `production`)

### 5.2 CorsMiddleware (neomerx/cors-psr7)

**Purpose:** Apply Cross-Origin Resource Sharing headers.

**Responsibilities:**
- Apply env-driven allowlist:
  - `CORS_ALLOWED_ORIGINS` (comma-separated)
  - `CORS_ALLOWED_METHODS`
  - `CORS_ALLOWED_HEADERS`
  - `CORS_EXPOSED_HEADERS`
- Handle preflight OPTIONS requests efficiently (short-circuit before expensive middleware)
- Return `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, etc.

**Configuration:**
- See **[environment-configuration.md](../10-reference/environment-configuration.md)** for CORS settings

### 5.3 RateLimitMiddleware (symfony/rate-limiter)

**Purpose:** Throttle requests to prevent abuse.

**Responsibilities:**
- Apply separate buckets:
  - `GENERAL` — General endpoints (default: 100 per minute)
  - `AUTH` — Authentication endpoints (default: 10 per minute)
  - `API` — Gateway endpoints (default: 60 per minute)
- Keying strategy:
  - **Public routes:** IP address
  - **Console JSON:** `owner_id` (hex32, from Owner JWT claim)
  - **Gateway JSON:** `key_id` (hex32, from Key JWT claim)
- On limit exceeded:
  - Return `429 Too Many Requests`
  - Use standardized error schema (see **[response-schemas.md](../06-api-reference/response-schemas.md)**)
  - Include `retry_after_seconds` in `details` when possible
  - Log to `security` channel

**Backing Store:**
- Configured via `RATE_LIMIT_BACKING` (default: `memory`)
- Production may use `database` for persistence across restarts
- Redis/Memcached not used in this implementation

**Configuration:**
```
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory
```

### 5.4 JwtOwnerMiddleware / JwtKeyMiddleware

**Purpose:** Verify JWT, enforce token typing, attach principal context.

**Responsibilities:**
- Verify JWT signature (RS256 using public key from `JWT_PUBLIC_KEY_PATH`)
- Verify standard claims: `exp`, `nbf` (with `JWT_LEEWAY`), `iss`, `aud`
- Enforce token typing:
  - **JwtOwnerMiddleware** requires `typ=owner`
  - **JwtKeyMiddleware** requires `typ=key`
- Attach trusted request attributes:
  - `owner_id` or `key_id` (both as `hex32`; see **[identifier-encoding.md](../10-reference/identifier-encoding.md)**)
  - `roles` (array)
  - `permissions` (array)
- On failure:
  - Return `401 Unauthorized` with standardized error
  - Log to `auth` channel

**Must not:**
- Perform domain authorization checks (permission enforcement happens in services)
- Modify tokens or issue new tokens (that's the responsibility of AuthService)

**Configuration:**
- `JWT_ALGO=RS256`
- `JWT_PUBLIC_KEY_PATH` (path to public key PEM)
- `JWT_ISSUER` (must match `iss` claim)
- `JWT_AUDIENCE` (must match `aud` claim)
- `JWT_LEEWAY` (seconds of clock skew tolerance, default: 10)

### 5.5 ValidationMiddleware (Respect\Validation)

**Purpose:** Validate request body, query, and headers per centralized schemas.

**Responsibilities:**
- Select validator by `"METHOD /pattern"` from `config/validation.php`
- Validate body/query/headers per Respect\Validation rules
- On failure:
  - Return `422 Unprocessable Entity`
  - Include `details.fields` with field-level errors
  - Use standardized error schema (see **[response-schemas.md](../06-api-reference/response-schemas.md)**)

**Configuration:**
- Centralized in `config/validation.php`
- Format:
  ```php
  [
    "POST /api/posts" => [
      'body' => v::key('content', v::stringType()->notEmpty()),
      'rejectUnknown' => true,
    ],
  ]
  ```

**Validation Failure Example:**
```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "content": ["Content is required"],
        "invalid_field": ["Unknown field"]
      }
    }
  }
}
```

### 5.6 ErrorHandlingMiddleware

**Purpose:** Catch exceptions and normalize to standardized error responses.

**Responsibilities:**
- Catch typed exceptions from controllers/services/repos/middleware
- Map to HTTP status codes (400, 401, 403, 404, 422, 429, 500, 503)
- Return standardized error body:
  ```json
  {
    "error": {
      "code": "string",
      "message": "safe human-readable summary",
      "details": {},
      "request_id": "string-or-null"
    }
  }
  ```
- Log to appropriate channels (`api`, `auth`, `security`, `db`)
- Never leak sensitive details (stack traces, internal paths, secret values)

**See:** **[response-schemas.md](../06-api-reference/response-schemas.md)** for complete error taxonomy.

---

## 6. Validation Selection Rules (Normative)

Validation is configured centrally in `config/validation.php` as a map:

**Key Format:** `"METHOD /pattern"`

**Example:**
```php
return [
  "POST /api/posts" => [
    'body' => v::key('content', v::stringType()->notEmpty()->length(1, 10000))
             ->key('title', v::optional(v::stringType()->length(1, 255))),
    'rejectUnknown' => true,
  ],
  "POST /console/keys/primary" => [
    'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
             ->key('label', v::optional(v::stringType()->length(1, 100))),
    'rejectUnknown' => true,
  ],
];
```

**Guidelines:**
- Every endpoint with a request body **should** have a validator
- Prefer `rejectUnknown: true` for security (prevent mass assignment vulnerabilities)
- Validation errors are always `422` with `details.fields`

---

## 7. Request Flow Examples (Reference)

### 7.1 Owner Mints a Primary Author Key (Console JSON)

**Request:**
```http
POST /console/keys/primary
Authorization: Bearer <owner_jwt>
Content-Type: application/json

{
  "permissions": ["posts:create", "keys:issue", "comments:write"],
  "label": "My First Primary Key"
}
```

**Pipeline:**
1. HttpsMiddleware → Enforce HTTPS
2. CorsMiddleware → Apply CORS headers
3. RateLimitMiddleware → Check bucket for `owner_id`
4. JwtOwnerMiddleware → Verify token, enforce `typ=owner`, attach `owner_id`
5. Body Parsing → Parse JSON body
6. ValidationMiddleware → Validate per `"POST /console/keys/primary"` rule
7. Routing → KeyController::mintPrimary()
8. KeyController → KeyService::mintPrimary($ownerId, $permissions, $label)
9. KeyService:
   - Verify `owner_id` has `keys:issue` permission
   - Generate key_id (BINARY(16)), key_public_id (apub_...), key_secret
   - Hash key_secret with Argon2id
   - Store in `keys` and `key_public_ids` tables
   - Emit audit event (`keys:mint`)
10. Response:
```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
  }
}
```

### 7.2 API Client Creates a Post (Gateway JSON)

**Request:**
```http
POST /api/posts
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "content": "This is my first post on CRE8.pw!"
}
```

**Pipeline:**
1. HttpsMiddleware
2. CorsMiddleware
3. RateLimitMiddleware → Check bucket for `key_id`
4. JwtKeyMiddleware → Verify token, enforce `typ=key`, attach `key_id`, `permissions`
5. Body Parsing
6. ValidationMiddleware → Validate per `"POST /api/posts"` rule
7. Routing → PostController::create()
8. PostController → PostService::createPost($keyId, $content)
9. PostService:
   - Verify `key_id` has `posts:create` permission
   - Verify key is active and type is `primary` or `secondary` (not `use`)
   - Generate post_id (BINARY(16))
   - Store in `posts` table with `author_key_id` and `initial_author_key_id`
   - Emit audit event (`posts:create`)
10. Response:
```json
{
  "data": {
    "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
    "author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "content": "This is my first post on CRE8.pw!",
    "created_at": "2026-01-21T10:30:00Z"
  }
}
```

### 7.3 Console Dashboard Flow (HTML + AJAX)

**Browser Flow:**
```
1. User navigates to /console/login
   → HTTPS → CORS → RateLimit(IP) → CSRF Guard → Render HTML form

2. User submits login form (POST /console/login)
   → HTTPS → CORS → RateLimit(IP) → CSRF Guard (validates token) → AuthService
   → Returns { access_token, refresh_token }

3. Browser stores tokens (cookie or localStorage)

4. User navigates to /console/dashboard
   → HTTPS → CORS → RateLimit(IP) → CSRF Guard → Render dashboard HTML
   → Dashboard loads, exposes CSRF tokens via CsrfExposeMiddleware

5. Dashboard makes AJAX call to POST /console/keys/primary
   Headers:
     Authorization: Bearer <owner_token>
     X-CSRF-Name: csrf_name_12345  // From CsrfExposeMiddleware
     X-CSRF-Value: csrf_value_67890 // From CsrfExposeMiddleware

   → Console JSON pipeline (no CSRF validation on JSON endpoint)
   → Returns { key_id, key_public_id, key_secret }
```

**Note:**
- `X-CSRF-*` headers sent by dashboard AJAX are **not required** for Console JSON endpoints
- CSRF Guard only validates HTML form POSTs (step 2)
- JSON endpoints (step 5) use Bearer token auth and do not require CSRF

---

## 8. Middleware Order Rationale

### 8.1 Why HTTPS First?

HTTPS enforcement must happen before any sensitive data (tokens, passwords) is processed. Redirecting HTTP → HTTPS early prevents credentials from being transmitted in cleartext.

### 8.2 Why CORS Early?

CORS preflight (OPTIONS) requests should short-circuit before expensive operations like JWT verification. Preflight responses are cheap and don't require authentication.

### 8.3 Why Rate Limiting Before Auth?

Prevents brute-force attacks on authentication endpoints. An attacker should hit rate limits before consuming resources for JWT verification or database lookups.

However, principal-based rate limiting (keyed by `owner_id` or `key_id`) requires JWT verification first, so rate limiting is placed:
- **Before JWT** for IP-based limits (public routes)
- **After JWT** for principal-based limits (protected routes)

### 8.4 Why Validation After Auth?

Validation can be expensive (regex, deep object traversal). Only authenticated principals should consume validation resources. Additionally, validation errors may need to reference principal context for auditing.

---

## 9. Error Flow and Logging

**When an error occurs at any middleware or layer:**

1. Exception is thrown with typed exception class (e.g., `ValidationException`, `UnauthorizedException`)
2. ErrorHandlingMiddleware catches exception
3. Maps exception to HTTP status + standardized error code
4. Logs to appropriate channel with context:
   - `api` channel: request summary (method, path, status, latency)
   - `auth` channel: auth failures (invalid token, expired, wrong `typ`)
   - `security` channel: rate limit hits, CSRF failures (HTML only), suspicious activity
   - `db` channel: database errors
5. Returns JSON error response (or HTML error page for Console HTML routes)

**Log Fields (Structured JSON):**
- `timestamp`
- `level` (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- `channel` (api, auth, security, db)
- `message`
- `request_id` (if tracing enabled)
- `method`, `path`, `status`
- `owner_id` or `key_id` (hex32) if principal is authenticated
- `ip`, `user_agent`

**Never logged:**
- Passwords
- ApiKey secrets
- Refresh tokens
- Private keys
- Stack traces (in production logs)

**See:** **[logging-and-audit.md](../09-operations/logging-and-audit.md)** for complete logging conventions.

---

## 10. Summary: Four Pipelines at a Glance

| Pipeline | Routes | Auth | CSRF | Rate Limit Key | Typical Use |
|---|---|---|---|---|---|
| **Public API** | `/health`, `/.well-known/jwks.json`, `/api/auth/*`, `/console/owners`, `/console/login` | None (or basic) | No | IP | Health checks, auth, registration |
| **Console JSON** | `/console/keys/*`, `/console/groups/*`, etc. | Owner JWT (`typ=owner`) | **No** | `owner_id` | Owner management tasks |
| **Gateway JSON** | `/api/posts`, `/api/comments`, `/api/keys/{id}/use`, etc. | Key JWT (`typ=key`) | **No** | `key_id` | Programmatic posting, key issuance |
| **Console HTML** | `/`, `/console/register`, `/console/login`, `/console/dashboard` | Session or Owner JWT | **Yes** | IP or session | Browser UI for Owners |

---

## 11. Adding a New Endpoint: Middleware Checklist

When adding a new endpoint:

1. ✅ Determine which pipeline applies (Public / Console JSON / Gateway JSON / Console HTML)
2. ✅ Add route to `config/routes.php` under the appropriate group
3. ✅ If JSON endpoint with body, add validation rule to `config/validation.php`
4. ✅ If protected, ensure JWT middleware is in pipeline and token typing is correct
5. ✅ If Console HTML route with form, ensure CSRF Guard is applied
6. ✅ Verify rate limit bucket is appropriate (IP vs principal)
7. ✅ Implement controller (thin adapter, see **[implementation-guide.md](../08-implementation/implementation-guide.md)**)
8. ✅ Test all middleware behaviors:
   - HTTPS enforcement
   - CORS headers
   - Rate limiting (hit the limit and verify 429)
   - Auth (invalid token → 401, missing permission → 403)
   - Validation (invalid body → 422)
   - Error handling (unhandled exception → 500 with safe message)

---

**Next:** Proceed to **[authentication.md](../05-authentication-authorization/authentication.md)** to understand JWT structure, ApiKey exchange, and refresh token lifecycle.
