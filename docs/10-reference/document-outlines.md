# Document Outlines - CRE8.pw Documentation Set

## [introduction.md](../01-getting-started/introduction.md)

### Sections:
1. **What is CRE8.pw?**
   - One-paragraph executive summary
   - Core value proposition: hierarchical auth + provenance + accountability

2. **Key Concepts at a Glance**
   - Owners (humans) and Keys (machines)
   - Two surfaces: Console and Gateway
   - Posts, sharing, and Use Keys

3. **Quick Start Path**
   - Visual flow: Register Owner → Mint Primary Author Key → Create Post → Share via Use Key
   - Expected time: 5 minutes

4. **System Architecture Overview**
   - Dual-surface diagram
   - Technology stack summary
   - Security posture summary

5. **Document Set Navigation**
   - Task-based guide (I want to... → Read documents X, Y)
   - SSoT reference: which doc owns which specs

6. **Terminology Notes**
   - Key terms with definitions (full glossary in [glossary.md](../03-core-concepts/glossary.md))

---

## [architecture-overview.md](../04-architecture/architecture-overview.md)

### Sections:
1. **System Architecture**
   - Single Slim 4 application, two surfaces
   - Layering: Middleware → Controller → Service → Repository
   - Responsibility boundaries

2. **Surfaces and Request Types**
   - Console HTML (browser UI with CSRF)
   - Console JSON (Owner JWT, no CSRF)
   - Gateway JSON (Key JWT, no CSRF)
   - Public API (health, auth, registration)

3. **CSRF Scope (Critical: HTML Only)**
   - Where CSRF applies
   - Why JSON endpoints don't need CSRF
   - CSRF token exposure for HTML/XHR

4. **Middleware Pipelines (Four Variants)**
   - Public API pipeline
   - Console JSON pipeline (Owner)
   - Gateway JSON pipeline (Key)
   - Console HTML pipeline (browser)
   - Ordering rationale

5. **Middleware Catalog and Responsibilities**
   - HttpsMiddleware
   - CorsMiddleware
   - RateLimitMiddleware
   - JwtOwnerMiddleware / JwtKeyMiddleware
   - ValidationMiddleware
   - ErrorHandlingMiddleware

6. **Request Flow Examples**
   - Owner mints Primary Author Key (Console)
   - API client creates post (Gateway)
   - Console dashboard flow (HTML + AJAX)

7. **Validation Selection Rules**
   - How ValidationMiddleware picks validators
   - config/validation.php keying by "METHOD /pattern"

---

## [authentication.md](../05-authentication-authorization/authentication.md)

### Sections:
1. **Principals and Surfaces**
   - Owner: human, password-based, `typ=owner`
   - Key: machine, ApiKey-based, `typ=key`
   - Token typing enforcement

2. **JWT Structure (RS256)**
   - Standard claims: iss, sub, aud, iat, nbf, exp
   - App claims: typ, owner_id/key_id, roles, permissions
   - Subject convention: `owner:<id>` or `key:<id>`
   - Example Owner JWT
   - Example Key JWT

3. **ApiKey Exchange**
   - Format: `Authorization: ApiKey <public_id>:<secret>`
   - Endpoint: `POST /api/auth/exchange`
   - Returns: access_token + refresh_token
   - Security: never reveal whether public_id exists

4. **Owner Login**
   - Endpoint: `POST /console/login`
   - Body: email + password
   - Returns: access_token + refresh_token
   - Password hashing: Argon2id

5. **Refresh Token Lifecycle**
   - Single-use rotation model
   - Endpoint: `POST /api/auth/refresh`
   - Replay detection and security logging
   - Storage: hashed in refresh_tokens table
   - Fields: subject_type, subject_id, token_hash, issued_at, expires_at, rotated_at, replaced_by_id

6. **JWKS Endpoint**
   - Location: `GET /.well-known/jwks.json`
   - Purpose: RS256 public key publishing
   - kid-based key rotation
   - Caching recommendations

7. **Environment Configuration**
   - JWT_ALGO=RS256
   - JWT_PRIVATE_KEY_PATH / JWT_PUBLIC_KEY_PATH
   - JWT_ISSUER / JWT_AUDIENCE
   - JWT_ACCESS_TTL / JWT_REFRESH_TTL
   - JWT_LEEWAY

8. **Security Considerations**
   - Token storage (HttpOnly Secure cookies for Console)
   - Never log secrets
   - Strict issuer/audience checking
   - Key rotation procedures

---

## [authorization.md](../05-authentication-authorization/authorization.md)

### Sections:
1. **Authorization Model Overview**
   - Authentication provides identity
   - Authorization enforces capabilities
   - Two-layer checks: permission strings + post bitmasks

2. **Key Types and Capability Rules**
   - Primary Author Key: root, can mint, can post
   - Secondary Author Key: delegated, can mint subset, can post
   - Use Key: interaction only, no minting, no posting
   - Capability matrix (ref: helper draft)

3. **Permission String Catalog**
   - Core permissions (from v1)
   - Expanded permissions (full feature set)
   - Owner-scoped permissions
   - Key-scoped permissions
   - Table: permission → meaning → used by

4. **Role Definitions**
   - Owner role → implied permissions
   - Author role → implied permissions
   - Use role → implied permissions
   - Role resolution at token issuance

5. **Post Access Bitmasks**
   - Bit definitions: VIEW (0x01), COMMENT (0x02), MANAGE_ACCESS (0x08)
   - Presets: READ_ONLY, INTERACT, ADMIN
   - Stored in post_access.permission_mask

6. **Combined Authorization Checks**
   - Example: Read post requires `posts:read` + VIEW
   - Example: Comment requires `comments:write` + COMMENT
   - Example: Grant access requires `posts:access:manage` + MANAGE_ACCESS

7. **Permission Envelope and Issuance Rules**
   - Child permissions ⊆ parent permissions
   - Use Keys forbidden from `posts:create` and `keys:issue`
   - Immutability: permissions set at mint time
   - Change via rotation

8. **Visibility vs Access (404 vs 403)**
   - When to return 404 (resource hidden)
   - When to return 403 (authenticated but insufficient)

9. **Action/Endpoint Requirements Table**
   - Console JSON: action → required permission
   - Gateway JSON: action → required permission + mask

---

## [api-reference.md](../06-api-reference/api-reference.md)

### Sections:
1. **Conventions and ID Formats**
   - All {..Id} params are hex32 unless {keyPublicId}
   - Route patterns canonical (may be grouped in config/routes.php)
   - Security requirements reference SSoT docs

2. **Public HTML Routes**
   - Landing, register, login, dashboard
   - Table: method, path, purpose, auth, CSRF

3. **Public API Routes (JSON)**
   - Health, JWKS, auth exchange/refresh, owner creation, login
   - Table: method, path, purpose, auth

4. **Console JSON Routes (Owner-Protected)**
   - Keys: mint primary, list, rotate, activate/deactivate, view lineage
   - Groups: CRUD, membership
   - Keychains: CRUD, membership
   - Posts: admin listing, access management
   - Table: method, path, purpose, required permission

5. **Gateway JSON Routes (Key-Protected)**
   - Key issuance: mint secondary, mint use
   - Posts: list, create, access management
   - Comments: list, create
   - Feeds: use feed, author feed (future)
   - Groups: read-only
   - Keychains: external management
   - Table: method, path, purpose, required permission + mask

6. **Controller/Service/Repository Ownership Map**
   - Domain → Controller → Service → Repository

7. **Request/Response Examples**
   - Mint Primary Author Key (Console)
   - ApiKey exchange (Gateway entry)
   - Create post (Gateway)
   - Grant group access (Console)
   - Use Key feed (Gateway)

8. **Adding an Endpoint (Checklist)**
   - Step-by-step guide referencing SSoT docs

---

## [feed-system.md](../06-api-reference/feed-system.md)

### Sections:
1. **Feed Concept**
   - Ordered lists of posts visible to a principal
   - Time-ordered (newest first)
   - Cursor-based pagination

2. **Use Key Feed**
   - Endpoint: `GET /api/feed/use/{useKeyId}`
   - Auth: Use Key JWT where key_id matches {useKeyId}
   - Visibility: posts where Use Key has VIEW mask via post_access
   - Pagination: limit, before_id, since_id
   - Response format: `{ data: [...], paging: {...} }`

3. **Author Key Feed (Future)**
   - Endpoint: `GET /api/feed/author` (or `/api/feed/me`)
   - Auth: Author Key JWT
   - Visibility: posts visible to author via group memberships or direct grants
   - Same pagination model

4. **Feed Authorization**
   - Enforcement: {useKeyId} must match JWT key_id or 404
   - Privacy: never reveal post existence to unauthorized principals
   - Combined check: posts:read permission + VIEW mask

5. **Pagination Mechanics**
   - Cursors: before_id (older posts), since_id (newer posts)
   - Limit: max posts per page (default 20, max 100)
   - Opaque cursors vs transparent IDs

6. **Performance Considerations**
   - Indexes on post_access (post_id, target_type, target_id)
   - Limit deep pagination
   - Consider caching for high-volume feeds

---

## [database-schema.md](../07-data-model/database-schema.md)

### Sections:
1. **Database Baseline**
   - MariaDB 11.4.x
   - Charset: utf8mb4
   - Collation: utf8mb4_bin
   - Access: PDO prepared statements only

2. **ID Formats and Encoding**
   - Internal: BINARY(16)
   - External: hex32 (32-char lowercase hex)
   - Key public IDs: apub_... (separate table)
   - Conversion via Utilities/Ids.php
   - SSoT reference: [identifier-encoding.md](identifier-encoding.md)

3. **Entity Catalog**
   - owners: id, email, password_hash, created_at, updated_at
   - keys: id, type (enum), permissions_json, active, lineage fields, rotation fields, timestamps
   - key_public_ids: id, key_id (FK), key_public_id (unique apub_...), timestamps
   - posts: id, author_key_id, initial_author_key_id, content fields, timestamps
   - comments: id, post_id, created_by_key_id, body, timestamps
   - post_access: id, post_id, target_type, target_id, permission_mask, timestamps
   - groups: id, owner_id, name, timestamps
   - group_members: group_id, key_id (composite unique), timestamps
   - keychains: id, name, owner_id (nullable), timestamps
   - keychain_members: keychain_id, key_id (composite unique), timestamps
   - master_keys: id, metadata, timestamps (future use)
   - master_key_members: master_key_id, key_id, timestamps
   - refresh_tokens: id, subject_type, subject_id, token_hash, issued_at, expires_at, revoked_at, rotated_at, replaced_by_id, ip, user_agent
   - audit_events: id, actor_type, actor_id, action, subject_type, subject_id, metadata_json, ip, user_agent, created_at

4. **Key Lineage and Provenance (Invariants)**
   - issued_by_key_id: issuing key (NULL for primary)
   - parent_key_id: parent key (NULL for primary)
   - initial_author_key_id: root primary author (immutable)
   - Lineage rules for Primary/Secondary/Use
   - Immutability guarantees

5. **Indexes and Performance**
   - Primary keys
   - Unique constraints (email, key_public_id, composite memberships)
   - Foreign keys
   - Performance indexes (post_access, refresh_tokens, etc.)

6. **Migration Ordering**
   - 001_create_owners
   - 002_create_keys
   - 003_create_posts_and_comments
   - 004_create_groups
   - 005_create_keychains
   - 006_create_master_keys
   - 007_create_tokens
   - 008_create_audit_events
   - 009_indexes

7. **Invariants and Triggers (If Applicable)**
   - Example: post must have at least one author target
   - Trigger implementations (if used)

8. **Contract Compatibility Rules**
   - Never change permission/role meanings
   - Never change bitmask meanings
   - Preserve lineage across versions
   - Never store plaintext secrets

---

## [key-lifecycle.md](../03-core-concepts/key-lifecycle.md)

### Sections:
1. **Overview: Hierarchical Key System**
   - Owner at top
   - Primary Author Keys as roots
   - Secondary/Use Keys as descendants
   - Full provenance tracking

2. **Owner Mints Primary Author Key (Console)**
   - Endpoint: `POST /console/keys/primary`
   - Required permission: `keys:issue`
   - Body: permissions array, label
   - Returns: key_id, key_public_id, key_secret
   - Lineage: all NULL (root)

3. **Primary/Secondary Mint Secondary (Gateway)**
   - Endpoint: `POST /api/keys/{authorKeyId}/secondary`
   - Required permission: `keys:issue`
   - Body: permissions array, label
   - Validation: child permissions ⊆ parent permissions
   - Returns: key_id, key_public_id, key_secret
   - Lineage: issued_by, parent, initial_author populated

4. **Primary/Secondary Mint Use Key (Gateway)**
   - Endpoint: `POST /api/keys/{authorKeyId}/use`
   - Required permission: `keys:issue`
   - Body: permissions array, label, use_count (optional), device_limit (optional)
   - Validation: child permissions ⊆ parent, no posts:create/keys:issue
   - Returns: key_id, key_public_id, key_secret, use_count, device_limit
   - Lineage: same as secondary

5. **Use Count and Device Limits**
   - Use count: 1-time, N-times, unlimited (null)
   - Tracking: increment on each use via middleware or service
   - Enforcement: reject when count exhausted
   - Device limit: max distinct devices/IPs/fingerprints
   - Tracking: store device identifiers per key
   - Enforcement: reject when limit reached

6. **Key Rotation**
   - Endpoint: `POST /console/keys/{keyId}/rotate`
   - Required permission: `keys:rotate`
   - Process: create new key, retire old key, update lineage
   - Fields: retired_at, rotated_from_id, rotated_to_id
   - New key inherits permissions (or specifies new)

7. **Key Activation/Deactivation**
   - Endpoints: `POST /console/keys/{keyId}/activate`, `/deactivate`
   - Required permission: `keys:state:update`
   - Field: active (boolean)
   - Effect: deactivated keys cannot authenticate

8. **Lineage Immutability**
   - issued_by, parent, initial_author cannot change
   - Provenance always traceable to root

9. **Combining Keys: "Keyring Key" Concept**
   - Scenario: combine multiple Keys into shared authorization
   - Implementation: Groups + Keychains
   - Owner creates Group containing Keys
   - Post access granted to Group
   - All Keys in Group inherit access
   - Alternative: synthetic "Keyring Key" (future enhancement)

10. **Viewing Downstream Lineage (Owner)**
    - Endpoint: `GET /console/keys/{keyId}/lineage`
    - Returns: tree of all descendants
    - Use case: accountability, audit, bulk operations

11. **Disabling a Lineage**
    - Endpoint: `POST /console/keys/{keyId}/deactivate?cascade=true`
    - Effect: deactivates key + all descendants
    - Use case: revoke compromised key and all children

---

## [post-sharing.md](../03-core-concepts/post-sharing.md)

### Sections:
1. **Post Creation and Initial Attachment**
   - Posts attached to Author Key at creation
   - Endpoint: `POST /api/posts`
   - Required permission: `posts:create`
   - Body: content (required), title (optional)
   - Returns: post_id, author_key_id, initial_author_key_id

2. **Post Visibility Model**
   - Posts visible to Keys/Groups via post_access grants
   - Default: post visible only to creating author
   - Grants required for others to see

3. **Post Access Table (post_access)**
   - Fields: post_id, target_type, target_id, permission_mask
   - target_type: "group" or "key"
   - target_id: group_id or key_id (hex32)
   - permission_mask: bitmask (VIEW, COMMENT, MANAGE_ACCESS)

4. **Sharing via Use Keys**
   - Owner/Author mints Use Key with desired permissions
   - Owner/Author grants post access to Use Key
   - Endpoint: `POST /api/posts/{postId}/access`
   - Body: target_type="key", target_id=<use_key_id>, permission_mask=<int>
   - Use Key can now read/comment based on mask

5. **Use Key Sharing Workflow**
   - Step 1: Mint Use Key with `posts:read`, `comments:write`
   - Step 2: Grant Use Key VIEW+COMMENT mask on post
   - Step 3: Share Use Key (public_id + secret) with recipient
   - Step 4: Recipient exchanges ApiKey for access token
   - Step 5: Recipient reads post and comments

6. **Use Count Enforcement**
   - Track uses in middleware or service
   - Increment use_count_current on each authenticated request
   - Reject when use_count_current >= use_count_limit
   - Return 403 with error code `use_limit_exceeded`

7. **Device Limit Enforcement**
   - Track device fingerprints (IP + User-Agent or more sophisticated)
   - Store in key_devices table: key_id, device_fingerprint, first_seen_at
   - Count distinct devices per key
   - Reject when count >= device_limit
   - Return 403 with error code `device_limit_exceeded`

8. **Group-Based Access Grants**
   - Endpoint: `POST /console/posts/{postId}/access/grant-group` (Console)
   - Or: `POST /api/posts/{postId}/access` with target_type="group" (Gateway)
   - Body: group_id, permission_mask
   - Effect: all Keys in Group inherit mask

9. **Access Revocation**
   - Endpoint: `DELETE /console/posts/{postId}/access/{accessId}` (Console)
   - Or: `DELETE /api/posts/{postId}/access/{accessId}` (Gateway)
   - Effect: target loses access immediately

10. **Permission Mask Enforcement**
    - Services check: global permission + post mask bit
    - Example: read post requires `posts:read` + VIEW
    - Example: comment requires `comments:write` + COMMENT
    - Missing permission → 403 forbidden
    - Missing mask bit → 403 forbidden or 404 not_found (depending on visibility)

---

## [implementation-guide.md](../08-implementation/implementation-guide.md)

### Sections:
1. **Project Structure**
   - public/, config/, migrations/, src/ hierarchy
   - Controllers, Services, Repositories, Middleware, Utilities

2. **Technology Stack**
   - PHP 8.3, Slim 4, PHP-DI, Monolog, Guzzle, firebase/php-jwt, Respect\Validation, Symfony rate-limiter, etc.
   - Package versions and constraints

3. **Layering Rules**
   - Controllers: thin HTTP adapters
   - Services: business logic, transactions, audits
   - Repositories: PDO prepared statements only
   - Middleware: cross-cutting concerns
   - Utilities: shared helpers

4. **Dependency Injection (config/container.php)**
   - PDO with charset/collation
   - Monolog loggers + channels
   - JWT signer/verifier (RS256 keys, issuer, audience, leeway)
   - Rate limiter factories
   - Argon2id hashing utility
   - Repositories, services, controllers
   - Middleware instances
   - Guzzle client factory

5. **Validation Wiring (config/validation.php)**
   - Keyed by "METHOD /pattern"
   - Respect\Validation rules
   - Reject unknown fields
   - Return 422 with details.fields

6. **Controller Patterns**
   - Extract params (all {..Id} are hex32)
   - Call one service method
   - Return standardized response
   - Never embed SQL
   - Never perform multi-repo orchestration

7. **Service Patterns**
   - Enforce permissions + post mask bits
   - Enforce invariants (key type, lineage, immutability)
   - Multi-repo transactions
   - Emit audit events
   - Deterministic domain errors

8. **Repository Patterns**
   - PDO prepared statements exclusively
   - Convert hex32 ↔ BINARY(16) via Utilities/Ids.php
   - Return arrays/DTOs
   - Never enforce permissions
   - Never log secrets

9. **Outbound HTTP Conventions (Guzzle)**
   - GuzzleClientFactory produces pre-configured clients
   - Timeouts, retries, logging
   - Never propagate refresh tokens
   - Redact sensitive headers in logs

10. **Local Development Runbook**
    - Copy .env.example to .env
    - Generate RS256 keys
    - Configure database
    - Run migrations 001-009
    - Smoke tests: health, JWKS, owner register, login, mint primary

11. **Implementation Checklist (Adding a Capability)**
    - Update permissions/masks (SSoT 03)
    - Update route map (SSoT 04)
    - Add validators (config/validation.php)
    - Implement controller (thin)
    - Implement service (rules + audit)
    - Implement repo + migrations (SSoT 06)
    - Update OpenAPI (openapi.yaml)
    - Ensure response/error format (SSoT 10)
    - Add logs and audit events

12. **Configuration Surface (.env.example)**
    - App, DB, JWT, CORS, CSP, CSRF, Rate limits, HTTP client, Logging, Hashing
    - Required vs optional settings
    - Bootstrap validation

---

## [response-schemas.md](../06-api-reference/response-schemas.md)

### Sections:
1. **Response Conventions**
   - All JSON endpoints use standard envelopes
   - HTML endpoints may render pages

2. **Standard Success Bodies**
   - Single object: `{ "data": {...} }`
   - List: `{ "data": [...], "paging": {...} }`
   - Pagination: cursor-based (limit, cursor)

3. **Standard Error Body**
   - `{ "error": { "code", "message", "details", "request_id" } }`
   - code: stable, machine-usable
   - message: safe, human-readable
   - details: structured (validation fields, retry metadata, etc.)
   - request_id: for tracing (when enabled)

4. **Error Taxonomy Table**
   - HTTP | code | when | required details
   - 400 bad_request
   - 401 unauthorized
   - 403 forbidden (include required permissions)
   - 404 not_found
   - 409 conflict
   - 422 validation_failed (requires details.fields)
   - 429 rate_limited (include retry_after_seconds)
   - 500 internal_error
   - 503 service_unavailable

5. **Validation Error Details**
   - 422 must include `{ "fields": { "field": ["message1"] } }`

6. **Auth and Security Error Rules**
   - ApiKey exchange failures: 401, never reveal public_id existence
   - Refresh replay: 401 + security log
   - Missing permissions: 403 + details.required

7. **Error Mapping Examples**
   - Domain exceptions → HTTP codes
   - Service errors → ErrorHandlingMiddleware → standardized body

---

## [logging-and-audit.md](../09-operations/logging-and-audit.md)

### Sections:
1. **Logging Conventions**
   - Structured JSON
   - Include request_id if available
   - Never log secrets (passwords, key_secret, refresh_tokens, private keys)

2. **Log Channels**
   - api: request summaries, outcomes
   - auth: exchange, issue, refresh, login events
   - security: auth failures, refresh replay, CSRF failures (HTML only)
   - db: query/transaction errors
   - guzzle.http: outbound request summaries

3. **Common Log Fields**
   - timestamp, level, channel, message
   - request_id
   - method, path, status
   - owner_id or key_id (hex32, never binary)
   - key_public_id (apub_..., optional for correlation)
   - ip, user_agent

4. **Audit Events Catalog**
   - Key lifecycle: mint, rotate, activate/deactivate, retire
   - Group/keychain: create, rename, delete, member add/remove
   - Post access: grant, revoke
   - Security: refresh replay, invalid exchange attempts

5. **Audit Event Structure (audit_events table)**
   - actor_type, actor_id
   - subject_type, subject_id
   - action (stable string, colon-delimited)
   - metadata_json
   - ip, user_agent
   - created_at

6. **Audit Event Naming Conventions**
   - Format: `domain:action` or `domain:subdomain:action`
   - Examples: `keys:mint`, `keys:rotate`, `groups:member:add`, `posts:access:grant`

7. **Rate Limiting**
   - Symfony rate-limiter
   - Backing: local memory + DB persistence (no Redis/Memcached)
   - Buckets: GENERAL, AUTH, API
   - Keying: IP (public), owner_id (Console), key_id (Gateway)
   - Env config: RATE_LIMIT_GENERAL, RATE_LIMIT_AUTH, RATE_LIMIT_API, RATE_LIMIT_BACKING
   - On limit: 429 + retry_after_seconds

8. **Troubleshooting Guide**
   - 401 unauthorized: check token exp, issuer/audience, typ, JWKS kid, refresh replay
   - 403 forbidden: compare required permissions vs JWT permissions; check post mask bits
   - 422 validation_failed: check config/validation.php rule for "METHOD /pattern"
   - 429 rate_limited: confirm keying and env values
   - 500 internal_error: inspect db logs, missing env config, DI wiring

---

## [identifier-encoding.md](identifier-encoding.md)

### Sections:
1. **Encoding Conventions**
   - Internal: BINARY(16)
   - External: hex32 (32-char lowercase hex)
   - Key public IDs: apub_... (not a primary key)

2. **Route Parameter Rules**
   - {..Id} params are hex32
   - {keyPublicId} is apub_...
   - Never accept apub_ in *_id fields

3. **JWT Claim Rules**
   - owner_id, key_id: hex32
   - key_public_id: apub_... (optional, debug only)
   - sub: "owner:<owner_id>" or "key:<key_id>"

4. **Identifier Matrix Table**
   - Identifier | DB storage | External format | Where used | Notes
   - owner_id, key_id, key_public_id, post_id, group_id, comment_id, refresh_token, request_id

5. **Logging/Audit ID Rules**
   - All IDs in logs: hex32
   - MAY include key_public_id for correlation
   - NEVER log secrets

6. **Implementation Guidance**
   - Utilities/Ids.php for conversions
   - ApiKey exchange only place accepting key_public_id from client
   - Repositories convert at boundary (hex32 → BINARY(16) → hex32)

---

## [environment-configuration.md](environment-configuration.md)

### Sections:
1. **Purpose**
   - .env.example is SSoT for runtime config
   - This appendix provides annotated reference

2. **App Configuration**
   - APP_NAME, APP_ENV, APP_DEBUG, APP_URL

3. **Database Configuration**
   - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
   - DB_CHARSET=utf8mb4, DB_COLLATION=utf8mb4_bin
   - DB_SSL_MODE

4. **JWT Configuration**
   - JWT_ALGO=RS256
   - JWT_PRIVATE_KEY_PATH, JWT_PUBLIC_KEY_PATH
   - JWT_ISSUER, JWT_AUDIENCE
   - JWT_ACCESS_TTL (default 900), JWT_REFRESH_TTL (default 2592000)
   - JWT_LEEWAY

5. **CORS Configuration**
   - CORS_ALLOWED_ORIGINS (comma-separated)
   - CORS_ALLOWED_METHODS
   - CORS_ALLOWED_HEADERS
   - CORS_EXPOSED_HEADERS

6. **CSP Configuration**
   - CSP_DEFAULT_SRC

7. **CSRF Configuration**
   - CSRF_SECRET (HTML routes only)

8. **Rate Limiting Configuration**
   - RATE_LIMIT_GENERAL (format: "100 per minute")
   - RATE_LIMIT_AUTH
   - RATE_LIMIT_API
   - RATE_LIMIT_BACKING (default: memory)

9. **HTTP Client Configuration**
   - HTTP_TIMEOUT (default 30)
   - HTTP_RETRY_MAX (default 3)

10. **Logging Configuration**
    - LOG_CHANNEL (default: stack)
    - LOG_LEVEL (default: info)

11. **Hashing Configuration**
    - APIKEY_HASH_ALGO=argon2id
    - PASSWORD_MEMORY_COST
    - PASSWORD_TIME_COST
    - PASSWORD_PARALLELISM

12. **Bootstrap Validation**
    - Application MUST fail fast if critical settings missing
    - Critical: DB_*, JWT_PRIVATE_KEY_PATH, JWT_PUBLIC_KEY_PATH, JWT_ISSUER, JWT_SECRET (if HMAC)

---

## [glossary.md](../03-core-concepts/glossary.md)

### Sections:
1. **Alphabetical Terms**
   - (Use glossary draft created above)

2. **Quick Reference: Permission Strings**
   - Owner permissions
   - Author permissions
   - Use permissions

3. **Quick Reference: Post Access Bitmask**
   - Bit table
   - Presets

4. **Quick Reference: Identifier Formats**
   - Table with examples

5. **SSoT Cross-Reference**
   - Topic → SSoT document mapping

