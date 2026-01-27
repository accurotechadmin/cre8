# CRE8.pw Codebase Inventory

**Version:** 1.0.0  
**Last Updated:** 2026-01-25  
**Purpose:** Complete inventory of all codebase components, files, conventions, and patterns for production readiness review

---

## Table of Contents

1. [Directory Structure](#1-directory-structure)
2. [Entry Points](#2-entry-points)
3. [Bootstrap & Configuration](#3-bootstrap--configuration)
4. [Controllers](#4-controllers)
5. [Services](#5-services)
6. [Repositories](#6-repositories)
7. [Middleware](#7-middleware)
8. [Security Components](#8-security-components)
9. [Utilities](#9-utilities)
10. [Exceptions](#10-exceptions)
11. [Route Definitions](#11-route-definitions)
12. [Database Migrations](#12-database-migrations)
13. [Templates](#13-templates)
14. [Tools & Scripts](#14-tools--scripts)
15. [Static Assets](#15-static-assets)
16. [Documentation](#16-documentation)
17. [Dependencies](#17-dependencies)
18. [Conventions & Patterns](#18-conventions--patterns)
19. [Environment Configuration](#19-environment-configuration)
20. [Testing & Verification Tools](#20-testing--verification-tools)
21. [Complete File Inventory](#21-complete-file-inventory)
22. [Critical Integration Points](#22-critical-integration-points)
23. [Database Schema Summary](#23-database-schema-summary)
24. [Critical Rules & Constraints](#24-critical-rules--constraints)
25. [Integration Checklist](#25-integration-checklist)
26. [Production Readiness Checklist](#26-production-readiness-checklist)
27. [Known Issues & Fixes](#27-known-issues--fixes)
28. [Summary Statistics](#28-summary-statistics)
29. [Next Steps for Review](#29-next-steps-for-review)

---

## 1. Directory Structure

```
./
├── public/                    # Public web root
│   ├── index.php            # Application entry point
│   └── css/
│       └── styles.css       # Application styles
│
├── src/                      # Application source code
│   ├── bootstrap.php        # Application bootstrap (DI, routes, middleware)
│   ├── Controllers/         # HTTP adapters
│   │   ├── BaseController.php
│   │   ├── Console/         # Owner-facing controllers
│   │   ├── Gateway/         # Key-facing controllers
│   │   ├── HealthController.php
│   │   ├── JwksController.php
│   │   └── OwnerController.php
│   ├── Services/            # Business logic layer
│   │   ├── BaseService.php
│   │   └── [Domain]Service.php
│   ├── Repositories/        # Data access layer
│   │   ├── BaseRepository.php
│   │   └── [Entity]Repository.php
│   ├── Middleware/          # PSR-15 middleware
│   │   └── [Name]Middleware.php
│   ├── Security/            # Security utilities
│   │   ├── JwtService.php
│   │   ├── PermissionCatalog.php
│   │   └── PostAccessBitmask.php
│   ├── Utilities/          # Helper utilities
│   │   ├── BootstrapValidator.php
│   │   ├── ErrorFactory.php
│   │   ├── Ids.php
│   │   ├── ResponseFactory.php
│   │   ├── SchemaContractVerifier.php
│   │   └── SensitiveDataSanitizer.php
│   ├── Exceptions/          # Custom exceptions
│   │   ├── ForbiddenException.php
│   │   └── NotFoundException.php
│   └── Validation/         # Validation schemas (empty, uses config/validation.php)
│
├── config/                  # Configuration files
│   ├── container.php       # DI container wiring
│   ├── routes.php          # Route group registration
│   ├── validation.php     # Validation schemas (Respect\Validation)
│   └── routes/             # Route group definitions
│       ├── console_html.php
│       ├── console_json.php
│       ├── gateway_html.php
│       ├── gateway_json.php
│       └── public_api.php
│
├── migrations/              # Database migrations
│   ├── 001_create_owners.php
│   ├── 002_create_keys.php
│   ├── 003_create_key_public_ids.php
│   ├── 004_create_posts_and_comments.php
│   ├── 005_create_post_access.php
│   ├── 006_create_groups.php
│   ├── 007_create_keychains.php
│   ├── 008_create_refresh_tokens.php
│   ├── 009_create_audit_events.php
│   ├── 010_add_label_to_keys.php
│   ├── 011_add_lookup_hash_to_refresh_tokens.php
│   ├── 012_add_owner_id_to_keys.php
│   └── 013_create_key_devices.php
│
├── templates/              # PHP templates (HTML rendering)
│   ├── _permission_helpers.php
│   ├── dashboard.php
│   ├── groups_list.php
│   ├── keychains_list.php
│   ├── keys_list.php
│   ├── landing.php
│   ├── login.php
│   ├── posts_list.php
│   ├── register.php
│   └── gateway/           # Gateway example pages
│       ├── auth_exchange.php
│       ├── auth_refresh.php
│       ├── comments_list.php
│       ├── feed_author.php
│       ├── feed_use.php
│       ├── group_detail.php
│       ├── groups_list.php
│       ├── keychains_list.php
│       ├── keys_mint_secondary.php
│       ├── keys_mint_use.php
│       ├── post_detail.php
│       ├── post_grant_access.php
│       ├── posts_list.php
│       └── share_workflow.php
│
├── tools/                  # Utility scripts
│   ├── contract/          # Contract/compliance tests
│   │   ├── README.md
│   │   ├── test_audience_segregation.php
│   │   ├── test_doc_ssot_alignment.php
│   │   └── test_id_format_compliance.php
│   └── db/                # Database utilities
│       ├── migrate.php
│       └── verify_schema.php
│
├── docs/                   # Documentation
│   ├── 01-getting-started/    # Getting started (10 files)
│   ├── 02-installation/       # Installation (1 file)
│   ├── 03-core-concepts/      # Core concepts (3 files)
│   ├── 04-architecture/       # Architecture (4 files)
│   ├── 05-authentication-authorization/  # Auth (4 files)
│   ├── 06-api-reference/      # API reference (4 files)
│   ├── 07-data-model/         # Data model (1 file)
│   ├── 08-implementation/     # Implementation (2 files)
│   ├── 09-operations/         # Operations (1 file)
│   ├── 10-reference/          # Reference (5 files)
│   ├── 11-development/        # Development (5 files)
│   └── 12-comprehensive-reference/  # SSOT documents (6 files)
│
├── UI/                     # Standalone UI (example client)
│   ├── 404.html
│   ├── app.js
│   ├── DOWNLOAD.html
│   ├── icons.js
│   ├── index.html
│   ├── README.md
│   └── styles.css
│
├── composer.json           # PHP dependencies
├── .env.example           # Environment template
├── .env.local.example     # Local environment template
├── README.md              # Project README
├── TOC.md                 # Master documentation index
└── SSOT.md                # Master SSOT hub
```

---

## 2. Entry Points

### 2.1 Public Entry Point

**File:** `public/index.php`
- **Purpose:** Public HTTP entry point for all requests
- **Responsibilities:**
  - Load autoloader
  - Bootstrap application via `src/bootstrap.php`
  - Run Slim application
- **Dependencies:** `vendor/autoload.php`, `src/bootstrap.php`

### 2.2 Application Bootstrap

**File:** `src/bootstrap.php`
- **Purpose:** Initialize Slim application, DI container, routes, middleware
- **Responsibilities:**
  - Load environment variables (vlucas/phpdotenv)
  - Validate bootstrap requirements (BootstrapValidator)
  - Build DI container (PHP-DI)
  - Create Slim app with container
  - Register route groups
  - Register error handling middleware (last)
- **Dependencies:** `config/container.php`, `config/routes.php`

---

## 3. Bootstrap & Configuration

### 3.1 Dependency Injection Container

**File:** `config/container.php`
- **Purpose:** PHP-DI container configuration and wiring
- **Key Bindings:**
  - PSR-7 ResponseFactory
  - PDO (database connection with utf8mb4_bin)
  - Rate limiter storage (memory or Redis)
  - Rate limiter factories (GENERAL, AUTH, API buckets)
  - CORS settings (neomerx/cors-psr7)
  - CSRF Guard (slim/csrf, HTML routes only)
  - HTTP Client (Guzzle)
  - Loggers (Monolog, multiple channels)
  - JWT Service (singleton)
  - Hashing Service (Argon2id)
  - All Repositories (autowired with PDO)
  - All Services (autowired with repositories)
  - All Controllers (autowired with services)
  - All Middleware (autowired with dependencies)
- **Conventions:**
  - Singletons: JwtService, Loggers, Rate limiters
  - Autowiring: Controllers, Services, Repositories
  - Named parameters: Multiple loggers (`logger.api`, `logger.auth`, etc.)

### 3.2 Route Registration

**File:** `config/routes.php`
- **Purpose:** Register all route groups
- **Route Groups:**
  - Public API (`routes/public_api.php`)
  - Console HTML (`routes/console_html.php`)
  - Console JSON (`routes/console_json.php`)
  - Gateway JSON (`routes/gateway_json.php`)
  - Gateway HTML (`routes/gateway_html.php`)

### 3.3 Validation Configuration

**File:** `config/validation.php`
- **Purpose:** Centralized validation schemas (Respect\Validation)
- **Format:** `"METHOD /pattern" => ['body' => Validator, 'rejectUnknown' => bool]`
- **Coverage:** All endpoints with request bodies
- **Conventions:**
  - Keys match route pattern exactly
  - `rejectUnknown: true` for security (prevent mass assignment)
  - Field-level validation with clear error messages

### 3.4 Environment Configuration

**Files:** `.env.example`, `.env.local.example`
- **Purpose:** Environment variable templates
- **Sections:**
  - Application (APP_NAME, APP_ENV, APP_DEBUG, APP_URL)
  - Database (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_COLLATION)
  - JWT (JWT_ALGO, JWT_PRIVATE_KEY_PATH, JWT_PUBLIC_KEY_PATH, JWT_ISSUER, JWT_AUDIENCE, JWT_ACCESS_TTL, JWT_REFRESH_TTL, JWT_LEEWAY)
  - CORS (CORS_ALLOWED_ORIGINS, CORS_ALLOWED_METHODS, CORS_ALLOWED_HEADERS, CORS_EXPOSED_HEADERS)
  - CSRF (CSRF_SECRET)
  - Rate Limiting (RATE_LIMIT_GENERAL, RATE_LIMIT_AUTH, RATE_LIMIT_API, RATE_LIMIT_BACKING)
  - HTTP Client (HTTP_TIMEOUT, HTTP_RETRY_MAX)
  - Logging (LOG_CHANNEL, LOG_LEVEL, LOG_PATH)
  - Hashing (APIKEY_HASH_ALGO, PASSWORD_MEMORY_COST, PASSWORD_TIME_COST, PASSWORD_PARALLELISM)
  - Redis (REDIS_HOST, REDIS_PORT, REDIS_PASSWORD) - for rate limiting

---

## 4. Controllers

### 4.1 Base Controller

**File:** `src/Controllers/BaseController.php`
- **Purpose:** Base class for all controllers
- **Responsibilities:**
  - Provides standardized response methods (`single()`, `list()`, `paginated()`, `created()`, `noContent()`, `error()`)
  - Enforces architectural boundaries (HTTP adapters only)
- **Conventions:**
  - All controllers extend BaseController
  - Controllers are thin adapters (extract params, call service, shape response)
  - No business logic, no database access, no authorization checks

### 4.2 Console Controllers (Owner-Facing)

**Directory:** `src/Controllers/Console/`

**Files:**
- **ConsoleController.php** - HTML page rendering (landing, register, login, dashboard)
- **KeyController.php** - Key management (mint primary, list, view, lineage, rotate, activate/deactivate)
- **GroupController.php** - Group management (CRUD, members)
- **KeychainController.php** - Keychain management (CRUD, members)
- **PostController.php** - Post admin (list, view, access grants)
- **RouteCatalogController.php** - Route catalog endpoint (debug/development)

**Conventions:**
- Auth: Owner JWT (`typ=owner`)
- Surface: Console JSON endpoints
- Rate Limiting: Keyed by `owner_id`
- CSRF: Not required (JSON endpoints)

### 4.3 Gateway Controllers (Key-Facing)

**Directory:** `src/Controllers/Gateway/`

**Files:**
- **PostController.php** - Post creation and access management
- **CommentController.php** - Comment creation and listing
- **KeyController.php** - Key issuance (secondary, use)
- **FeedController.php** - Feed endpoints (use key feed, author feed)
- **GroupController.php** - Group read-only access
- **KeychainController.php** - External keychain management
- **GatewayController.php** - Gateway HTML example pages
- **RouteCatalogController.php** - Route catalog endpoint (debug/development)

**Conventions:**
- Auth: Key JWT (`typ=key`)
- Surface: Gateway JSON endpoints
- Rate Limiting: Keyed by `key_id`
- CSRF: Not required (JSON endpoints)

### 4.4 Public Controllers

**Files:**
- **HealthController.php** - Health check endpoint (`GET /health`)
- **JwksController.php** - JWKS endpoint (`GET /.well-known/jwks.json`)
- **OwnerController.php** - Owner registration and ApiKey exchange

**Conventions:**
- Auth: None (public endpoints)
- Rate Limiting: IP-based

---

## 5. Services

### 5.1 Base Service

**File:** `src/Services/BaseService.php`
- **Purpose:** Base class for all services
- **Responsibilities:**
  - Enforces architectural boundaries (business logic only)
- **Conventions:**
  - All services extend BaseService
  - Services contain business logic, authorization checks, transactions
  - No HTTP concerns, no direct SQL queries

### 5.2 Domain Services

**Directory:** `src/Services/`

**Files:**
- **AuthService.php** - Authentication (Owner login, ApiKey exchange, refresh token rotation)
- **KeyService.php** - Key lifecycle (mint, rotate, activate/deactivate, lineage)
- **PostService.php** - Post creation and access management
- **CommentService.php** - Comment creation
- **FeedService.php** - Feed visibility resolution and pagination
- **GroupService.php** - Group management (CRUD, members)
- **KeychainService.php** - Keychain management (CRUD, members)
- **AuditService.php** - Audit event emission
- **LoggingService.php** - Structured logging (multiple channels)

**Conventions:**
- Services enforce global permissions (check JWT permissions)
- Services enforce post bitmasks (for post-scoped actions)
- Services enforce invariants (key type, lineage, immutability)
- Services orchestrate multiple repositories (transactions)
- Services emit audit events
- Services throw deterministic exceptions (ForbiddenException, NotFoundException)

---

## 6. Repositories

### 6.1 Base Repository

**File:** `src/Repositories/BaseRepository.php`
- **Purpose:** Base class for all repositories
- **Responsibilities:**
  - Provides PDO instance
  - Provides ID conversion helpers (`hex32ToBinary()`, `binaryToHex32()`, `generateBinaryId()`, `generateHex32Id()`)
- **Conventions:**
  - All repositories extend BaseRepository
  - Repositories use PDO prepared statements exclusively
  - Repositories convert hex32 ↔ BINARY(16) at boundary
  - No business logic, no permission checks, no HTTP concerns

### 6.2 Entity Repositories

**Directory:** `src/Repositories/`

**Files:**
- **OwnerRepository.php** - Owner data access
- **KeyRepository.php** - Key data access (CRUD, lineage queries, use count)
- **KeyPublicIdRepository.php** - Key public ID lookup (ApiKey exchange)
- **PostRepository.php** - Post data access (CRUD, visibility queries, feed queries)
- **PostAccessRepository.php** - Post access grants (CRUD, mask resolution)
- **CommentRepository.php** - Comment data access
- **GroupRepository.php** - Group data access
- **GroupMemberRepository.php** - Group membership data access
- **KeychainRepository.php** - Keychain data access
- **KeychainMemberRepository.php** - Keychain membership data access
- **RefreshTokenRepository.php** - Refresh token data access (CRUD, rotation tracking)
- **KeyDeviceRepository.php** - Key device tracking (device limits)
- **AuditEventRepository.php** - Audit event data access

**Conventions:**
- All methods use PDO prepared statements
- Input: hex32 IDs from controllers/services
- Convert: hex32 → BINARY(16) for queries
- Output: hex32 IDs to services/controllers
- Return: Arrays or null (no DTOs currently)

---

## 7. Middleware

### 7.1 Security Middleware

**Directory:** `src/Middleware/`

**Files:**
- **HttpsMiddleware.php** - HTTPS enforcement and HSTS headers
- **CorsMiddleware.php** - CORS headers (neomerx/cors-psr7)
- **CspMiddleware.php** - Content Security Policy headers (HTML routes)
- **JwtOwnerMiddleware.php** - Owner JWT verification (`typ=owner`)
- **JwtKeyMiddleware.php** - Key JWT verification (`typ=key`)
- **CsrfExposeMiddleware.php** - CSRF token exposure (HTML routes)

**Conventions:**
- JWT middleware enforces token typing (`typ=owner` vs `typ=key`)
- JWT middleware verifies `iss`, `aud`, `exp`, `nbf` claims
- JWT middleware attaches principal IDs and permissions to request attributes
- CSRF middleware only applied to HTML routes

### 7.2 Rate Limiting Middleware

**Files:**
- **RateLimitMiddleware.php** - Request throttling (Symfony rate-limiter)
  - Buckets: GENERAL (100/min), AUTH (10/min), API (60/min)
  - Keying: IP (public), `owner_id` (Console), `key_id` (Gateway)
  - Backing: Memory (default) or Redis (production)

**Conventions:**
- Rate limiting before JWT verification (for IP-based limits)
- Rate limiting after JWT verification (for principal-based limits)
- Returns 429 with `retry_after_seconds` when exceeded
- Logs to `security` channel

### 7.3 Validation & Request Processing Middleware

**Files:**
- **ValidationMiddleware.php** - Request body/query validation (Respect\Validation)
- **RouteParameterValidatorMiddleware.php** - Route parameter format validation (hex32, apub_)
- **RequestLoggingMiddleware.php** - Request/response logging (structured JSON)
- **UseKeyLimitMiddleware.php** - Use key limit enforcement (use_count, device_limit)

**Conventions:**
- Validation middleware selects rules by `"METHOD /pattern"`
- Validation errors return 422 with `details.fields`
- Route parameter validator enforces hex32 format for `{...Id}` params
- Request logging middleware logs to `api` channel

### 7.4 Error Handling Middleware

**Files:**
- **ErrorHandlingMiddleware.php** - Exception catching and error normalization
  - Maps exceptions to HTTP status codes
  - Returns standardized error envelopes
  - Logs to appropriate channels
  - Never leaks secrets or stack traces

**Conventions:**
- Registered last (catches all exceptions)
- Maps typed exceptions to error codes
- Uses ErrorFactory for response creation
- Logs to appropriate channels (`api`, `auth`, `security`, `db`)

---

## 8. Security Components

### 8.1 JWT Service

**File:** `src/Security/JwtService.php`
- **Purpose:** RS256 JWT signing and verification
- **Responsibilities:**
  - Sign Owner tokens (`signOwnerToken()`)
  - Sign Key tokens (`signKeyToken()`)
  - Verify tokens (`verify()`)
  - Generate JWKS response (`getJwks()`)
- **Conventions:**
  - RS256 algorithm only
  - Enforces token typing (`typ=owner` vs `typ=key`)
  - Validates `iss`, `aud`, `exp`, `nbf` claims
  - Includes `kid` in header for key rotation

### 8.2 Permission Catalog

**File:** `src/Security/PermissionCatalog.php`
- **Purpose:** Permission strings catalog and validation
- **Responsibilities:**
  - Defines Owner permissions (Console-scoped)
  - Defines Key permissions (Gateway-scoped)
  - Validates Use Key restrictions
  - Validates permission envelope (child ⊆ parent)
- **Conventions:**
  - Permission format: `<resource>:<action>` or `<resource>:<subresource>:<action>`
  - Use Keys cannot have `posts:create` or `keys:issue`
  - Envelope rule enforced at mint time

### 8.3 Post Access Bitmask

**File:** `src/Security/PostAccessBitmask.php`
- **Purpose:** Post access bitmask constants and helpers
- **Responsibilities:**
  - Defines bitmask constants (VIEW, COMMENT, MANAGE_ACCESS)
  - Defines presets (READ_ONLY, INTERACT, ADMIN)
  - Provides helper methods (`hasView()`, `hasComment()`, `hasManageAccess()`)
  - Validates mask values
- **Conventions:**
  - VIEW = 0x01 (bit 0)
  - COMMENT = 0x02 (bit 1)
  - MANAGE_ACCESS = 0x08 (bit 3)
  - Presets combine multiple bits

---

## 9. Utilities

### 9.1 ID Encoding Utilities

**File:** `src/Utilities/Ids.php`
- **Purpose:** ID format conversion (BINARY(16) ↔ hex32)
- **Methods:**
  - `hex32ToBinary()` - Convert hex32 → BINARY(16)
  - `binaryToHex32()` - Convert BINARY(16) → hex32
  - `generateBinaryId()` - Generate random BINARY(16)
  - `generateHex32Id()` - Generate random hex32
  - `isValidHex32()` - Validate hex32 format
  - `isValidKeyPublicId()` - Validate apub_ format
- **Conventions:**
  - hex32: 32-character lowercase hex (no hyphens)
  - apub_: `apub_` prefix + random string
  - Used throughout application for ID conversion

### 9.2 Response Factory

**File:** `src/Utilities/ResponseFactory.php`
- **Purpose:** Standardized response envelope creation
- **Methods:**
  - `single()` - Single object response (`{ "data": {...} }`)
  - `list()` - List response (`{ "data": [...] }`)
  - `paginated()` - Paginated list (`{ "data": [...], "paging": {...} }`)
  - `created()` - 201 Created response
  - `noContent()` - 204 No Content response
- **Conventions:**
  - All responses use standardized envelopes
  - Success responses wrap data in `data` key
  - Pagination includes `paging` object with `limit` and `cursor`

### 9.3 Error Factory

**File:** `src/Utilities/ErrorFactory.php`
- **Purpose:** Standardized error response creation
- **Error Codes:**
  - `bad_request` (400)
  - `unauthorized` (401)
  - `forbidden` (403)
  - `not_found` (404)
  - `conflict` (409)
  - `validation_failed` (422)
  - `rate_limited` (429)
  - `internal_error` (500)
  - `service_unavailable` (503)
- **Methods:**
  - `create()` - Generic error response
  - `validationFailed()` - 422 with `details.fields`
  - `badRequest()` - 400
  - `unauthorized()` - 401 (generic message)
  - `forbidden()` - 403 with required permissions/masks
  - `notFound()` - 404
  - `rateLimited()` - 429 with `retry_after_seconds`
  - `internalError()` - 500 (safe message)
- **Conventions:**
  - All errors use standardized envelope: `{ "error": { "code", "message", "details", "request_id" } }`
  - Validation errors include `details.fields`
  - Permission errors include `details.required` or `details.required_mask`

### 9.4 Bootstrap Validator

**File:** `src/Utilities/BootstrapValidator.php`
- **Purpose:** Environment configuration validation (fail-fast)
- **Validations:**
  - Database configuration (DB_HOST, DB_NAME, DB_USER, DB_PASS)
  - JWT configuration (key paths, issuer, audience)
  - CORS configuration (parseable origins)
  - Rate limit configuration (valid format)
  - Log path (exists and writable)
  - Required env vars (APP_NAME, APP_ENV, APP_URL)
- **Conventions:**
  - Fails fast on misconfiguration
  - Never logs secrets in error messages
  - Validates file existence and readability

### 9.5 Sensitive Data Sanitizer

**File:** `src/Utilities/SensitiveDataSanitizer.php`
- **Purpose:** Sanitize sensitive data before logging
- **Sensitive Keys:**
  - `key_secret`, `password`, `password_hash`, `refresh_token`, `access_token`, `private_key`, `secret`, `apikey_secret`
- **Methods:**
  - `sanitize()` - Recursively sanitize array
  - `isSensitiveKey()` - Check if key is sensitive
- **Conventions:**
  - Replaces sensitive values with `[REDACTED]`
  - Used before logging to prevent secret leakage

### 9.6 Schema Contract Verifier

**File:** `src/Utilities/SchemaContractVerifier.php`
- **Purpose:** Verify database schema matches expected contract
- **Usage:** `tools/db/verify_schema.php`
- **Conventions:**
  - Checks table existence
  - Checks column types and constraints
  - Checks indexes
  - Checks foreign keys

---

## 10. Exceptions

### 10.1 Custom Exception Classes

**Directory:** `src/Exceptions/`

**Files:**
- **ForbiddenException.php** - 403 Forbidden (missing permissions/masks)
  - Properties: `requiredPermissions`, `requiredMask`
  - Used when resource exists but action is forbidden
- **NotFoundException.php** - 404 Not Found (resource missing or hidden)
  - Used when resource doesn't exist OR principal lacks VIEW mask

**Conventions:**
- Extend `RuntimeException`
- Include HTTP status code in constructor
- ForbiddenException includes required permissions/masks for error details

---

## 11. Route Definitions

### 11.1 Route Group Files

**Directory:** `config/routes/`

**Files:**
- **public_api.php** - Public API routes (no auth)
  - Routes: `/health`, `/.well-known/jwks.json`, `/api/auth/*`, `/console/owners`, `/console/login`
  - Pipeline: HTTPS → CORS → RateLimit → BodyParsing → RouteParamValidator → Validation
- **console_html.php** - Console HTML routes (CSRF required)
  - Routes: `/`, `/console/register`, `/console/login`, `/console/dashboard`
  - Pipeline: HTTPS → CORS → RateLimit → CSRF Guard → CSP → CSRF Expose
- **console_json.php** - Console JSON routes (Owner JWT)
  - Routes: `/console/keys/*`, `/console/groups/*`, `/console/keychains/*`, `/console/posts/*`
  - Pipeline: HTTPS → CORS → RateLimit → JwtOwnerMiddleware → BodyParsing → RouteParamValidator → Validation
- **gateway_json.php** - Gateway JSON routes (Key JWT)
  - Routes: `/api/posts/*`, `/api/comments/*`, `/api/keys/*`, `/api/feed/*`, `/api/groups/*`, `/api/keychains/*`
  - Pipeline: HTTPS → CORS → RateLimit → JwtKeyMiddleware → BodyParsing → RouteParamValidator → Validation
- **gateway_html.php** - Gateway HTML routes (example pages, CSRF required)
  - Routes: `/gateway/*` (example pages that call `/api/*` JSON endpoints)
  - Pipeline: HTTPS → CORS → RateLimit → CSRF Guard → CSP → CSRF Expose

**Conventions:**
- Route groups enforce surface separation
- Middleware order is critical (see pipeline definitions)
- CSRF only on HTML routes
- JWT middleware enforces token typing

---

## 12. Database Migrations

### 12.1 Migration Files

**Directory:** `migrations/`

**Files (in order):**
1. **001_create_owners.php** - Owners table
2. **002_create_keys.php** - Keys table (with lineage fields)
3. **003_create_key_public_ids.php** - Key public IDs table
4. **004_create_posts_and_comments.php** - Posts and comments tables
5. **005_create_post_access.php** - Post access grants table
6. **006_create_groups.php** - Groups and group_members tables
7. **007_create_keychains.php** - Keychains and keychain_members tables
8. **008_create_refresh_tokens.php** - Refresh tokens table
9. **009_create_audit_events.php** - Audit events table
10. **010_add_label_to_keys.php** - Add label column to keys
11. **011_add_lookup_hash_to_refresh_tokens.php** - Add lookup_hash for refresh token lookup
12. **012_add_owner_id_to_keys.php** - Add owner_id to keys (for owner tracking)
13. **013_create_key_devices.php** - Key devices table (device limits)

**Conventions:**
- Migrations return array with `up` and `down` functions
- All tables use `BINARY(16)` for IDs
- All tables use `utf8mb4_bin` collation
- Migrations track applied state in `migrations` table
- Must be run in order (numbered sequence)

### 12.2 Migration Runner

**File:** `tools/db/migrate.php`
- **Purpose:** Run database migrations
- **Usage:** `php tools/db/migrate.php up|down`
- **Conventions:**
  - Tracks applied migrations in `migrations` table
  - Runs migrations in order
  - Supports `up` and `down` operations

---

## 13. Templates

### 13.1 Console Templates

**Directory:** `templates/`

**Files:**
- **landing.php** - Landing page
- **register.php** - Owner registration form
- **login.php** - Owner login form
- **dashboard.php** - Owner dashboard
- **keys_list.php** - Keys list page
- **groups_list.php** - Groups list page
- **keychains_list.php** - Keychains list page
- **posts_list.php** - Posts list page
- **_permission_helpers.php** - Permission helper functions for templates

**Conventions:**
- PHP templates (no Twig/Blade)
- CSRF tokens exposed via CsrfExposeMiddleware
- Use `_permission_helpers.php` for permission display

### 13.2 Gateway Templates

**Directory:** `templates/gateway/`

**Files:**
- **auth_exchange.php** - ApiKey exchange example page
- **auth_refresh.php** - Refresh token example page
- **posts_list.php** - Posts list example page
- **post_detail.php** - Post detail example page
- **post_grant_access.php** - Grant access example page
- **comments_list.php** - Comments list example page
- **keys_mint_secondary.php** - Mint secondary key example page
- **keys_mint_use.php** - Mint use key example page
- **feed_use.php** - Use key feed example page
- **feed_author.php** - Author feed example page
- **groups_list.php** - Groups list example page
- **group_detail.php** - Group detail example page
- **keychains_list.php** - Keychains list example page
- **share_workflow.php** - Sharing workflow example page

**Conventions:**
- Example pages demonstrating Gateway API usage
- CSRF protection (HTML routes)
- Call `/api/*` JSON endpoints via AJAX

---

## 14. Tools & Scripts

### 14.1 Database Tools

**Directory:** `tools/db/`

**Files:**
- **migrate.php** - Migration runner
  - Usage: `php tools/db/migrate.php up|down`
  - Tracks applied migrations
  - Runs migrations in order
- **verify_schema.php** - Schema contract verification
  - Usage: `php tools/db/verify_schema.php`
  - Verifies schema matches expected contract
  - Uses SchemaContractVerifier utility

### 14.2 Contract Tests

**Directory:** `tools/contract/`

**Files:**
- **test_id_format_compliance.php** - Verify ID format compliance (hex32, apub_)
- **test_audience_segregation.php** - Verify audience claim segregation
- **test_doc_ssot_alignment.php** - Verify code aligns with documentation
- **README.md** - Contract test documentation

**Conventions:**
- Contract tests verify code compliance with specifications
- Run as part of development workflow

---

## 15. Static Assets

### 15.1 CSS

**Files:**
- **public/css/styles.css** - Application styles
- **UI/styles.css** - Standalone UI styles

### 15.2 Standalone UI

**Directory:** `UI/`

**Files:**
- **index.html** - Standalone UI entry point
- **app.js** - UI application logic
- **icons.js** - Icon definitions
- **styles.css** - UI styles
- **404.html** - 404 error page
- **DOWNLOAD.html** - Download page
- **README.md** - UI documentation

**Conventions:**
- Standalone UI is example/demo client
- Not part of core application
- Demonstrates Gateway API integration

---

## 16. Documentation

### 16.1 Getting Started Documentation

**Directory:** `01-getting-started/`

**Files:**
1. **introduction.md** - What is CRE8.pw?
2. **executive-summary.md** - Executive summary
3. **elevator-pitches.md** - Communication materials
4. **primer-prompt-1.md** - LLM onboarding part 1
5. **primer-prompt-2.md** - LLM onboarding part 2
6. **README.md** - Getting started guide

### 16.2 Core Concepts Documentation

**Directory:** `03-core-concepts/`

**Files:**
- **glossary.md** - Terminology definitions
- **key-lifecycle.md** - Key management and lifecycle
- **post-sharing.md** - Post sharing workflows

### 16.3 Architecture Documentation

**Directory:** `04-architecture/`

**Files:**
- **architecture-overview.md** - System architecture
- **component-architecture.md** - Component breakdown
- **layering-rules.md** - Layering rules
- **technical-summary.md** - Technical summary

### 16.4 Authentication & Authorization Documentation

**Directory:** `05-authentication-authorization/`

**Files:**
- **authentication.md** - JWT, ApiKey exchange, refresh tokens
- **authorization.md** - Permission system, bitmasks
- **key-capabilities.md** - Key capabilities reference
- **permissions.md** - Permission matrix

### 16.5 API Reference Documentation

**Directory:** `06-api-reference/`

**Files:**
- **api-reference.md** - Complete API catalog
- **feed-system.md** - Feed visibility and pagination
- **response-schemas.md** - Response formats
- **routes-inventory.md** - Route catalog

### 16.6 Data Model Documentation

**Directory:** `07-data-model/`

**Files:**
- **database-schema.md** - Database schema

### 16.7 Implementation Documentation

**Directory:** `08-implementation/`

**Files:**
- **implementation-guide.md** - Developer manual
- **dependency-wiring.md** - Dependency wiring guide

### 16.8 Operations Documentation

**Directory:** `09-operations/`

**Files:**
- **logging-and-audit.md** - Operational concerns

### 16.9 Reference Documentation

**Directory:** `10-reference/`

**Files:**
- **identifier-encoding.md** - ID formats
- **environment-configuration.md** - Complete .env reference
- **document-outlines.md** - Document outlines
- **codebase-inventory.md** - File inventory
- **docset.json** - JSON metadata

### 16.10 Development Documentation

**Directory:** `11-development/`

**Files:**
- **codebase-inventory.md** - Complete codebase inventory
- **component-breakdown.md** - Component breakdown
- **component-breakdown.json** - Component breakdown (JSON)
- **production-readiness-issues.md** - Production issues
- **production-readiness-milestone.md** - Development milestones
- **verified-production-issues.md** - Verified issues

### 16.11 Comprehensive Reference Documentation

**Directory:** `12-comprehensive-reference/`

**Files:**
- **canon-ssot.md** - Canon SSOT (consolidated reference)
- **appendix-ssot.md** - Appendix SSOT (consolidated reference)
- **development-ssot.md** - Development SSOT (consolidated reference)
- **toc-canon.md** - Canon TOC
- **toc-appendix.md** - Appendix TOC
- **toc-dev.md** - Development TOC

### 16.12 Installation Documentation

**Directory:** `02-installation/`

**Files:**
- **installation-guide.md** — Installation instructions

**Key entries:**
- **README.md** — Project README (in 01-getting-started)
- **installation-guide.md** — Installation instructions (02-installation)
- **elevator-pitches.md** — Elevator pitches (01-getting-started)
- **/TOC.md** — Master documentation index (repo root)
- **/SSOT.md** — Master SSOT hub (repo root)
- **table-of-contents.md** — Full documentation catalog (docs root)

---

## 17. Dependencies

### 17.1 Composer Dependencies

**File:** `composer.json`

**Core Framework:**
- `slim/slim` ^4.15 - Slim Framework
- `slim/psr7` ^1.7 - PSR-7 implementation
- `php-di/php-di` ^7.1 - Dependency injection

**Security:**
- `firebase/php-jwt` ^6.11 - RS256 JWT signing/verification
- `ext-sodium` - Argon2id hashing
- `slim/csrf` ^1.5 - CSRF protection (HTML routes only)

**Infrastructure:**
- `respect/validation` ^2.4 - Input validation
- `monolog/monolog` ^3.9 - Structured logging
- `symfony/rate-limiter` ^7.3 - Rate limiting
- `symfony/cache` ^7.3 - Rate limiter storage
- `neomerx/cors-psr7` ^3.0 - CORS handling
- `guzzlehttp/guzzle` ^7.10 - HTTP client
- `vlucas/phpdotenv` ^5.6 - Environment variables

**Database:**
- `ext-pdo` - PDO extension (MariaDB)

**PHP Version:** ^8.3

### 17.2 Autoloading

**PSR-4 Namespace:** `App\` → `src/`

**Namespaces:**
- `App\Controllers\` → `src/Controllers/`
- `App\Services\` → `src/Services/`
- `App\Repositories\` → `src/Repositories/`
- `App\Middleware\` → `src/Middleware/`
- `App\Security\` → `src/Security/`
- `App\Utilities\` → `src/Utilities/`
- `App\Exceptions\` → `src/Exceptions/`

---

## 18. Conventions & Patterns

### 18.1 Code Conventions

**PHP Standards:**
- **Strict Types:** All files use `declare(strict_types=1)`
- **Type Hints:** All parameters and return types are typed
- **PSR-12:** Coding style standard
- **PSR-4:** Autoloading standard
- **PSR-7:** HTTP message interfaces
- **PSR-15:** Middleware interfaces
- **PSR-11:** Container interfaces
- **PSR-3:** Logger interfaces

**Naming Conventions:**
- **Classes:** PascalCase (e.g., `PostController`)
- **Methods:** camelCase (e.g., `createPost()`)
- **Variables:** camelCase (e.g., `$keyIdHex32`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `VIEW`, `COMMENT`)
- **Files:** Match class name (e.g., `PostController.php`)

### 18.2 Architectural Patterns

**Layered Architecture:**
- **Middleware** → **Controllers** → **Services** → **Repositories** → **Database**
- Clear separation of concerns
- Each layer has defined responsibilities and prohibitions

**Dependency Injection:**
- Constructor injection only
- PHP-DI autowiring for most components
- Named parameters for multiple loggers
- Singletons for expensive resources (JwtService, Loggers)

**Repository Pattern:**
- One repository per entity
- PDO prepared statements exclusively
- ID conversion at boundary (hex32 ↔ BINARY(16))
- No business logic

**Service Pattern:**
- One service per domain
- Business logic and authorization checks
- Orchestrates multiple repositories
- Emits audit events

**Controller Pattern:**
- Thin HTTP adapters
- Extract params, call service, shape response
- No business logic, no database access

### 18.3 ID Format Conventions

**Internal Storage:**
- All primary/foreign keys: `BINARY(16)`
- Never exposed outside repository layer

**External Representation:**
- All IDs: `hex32` (32-character lowercase hex)
- Exception: Key public IDs use `apub_...` format

**Route Parameters:**
- All `{...Id}` params: hex32 format
- Exception: `{keyPublicId}` uses apub_ format
- Never accept apub_ in params named `*Id`

**JWT Claims:**
- `owner_id`: hex32 (Owner JWTs)
- `key_id`: hex32 (Key JWTs)
- `key_public_id`: apub_... (optional, debug/correlation only)

### 18.4 Response Format Conventions

**Success Responses:**
- Single object: `{ "data": {...} }`
- List: `{ "data": [...] }`
- Paginated: `{ "data": [...], "paging": { "limit": 20, "cursor": "..." } }`

**Error Responses:**
- Standardized: `{ "error": { "code": "...", "message": "...", "details": {...}, "request_id": "..." } }`
- Validation errors: `details.fields` with field-level errors
- Permission errors: `details.required` or `details.required_mask`

### 18.5 Logging Conventions

**Structured JSON Logging:**
- Format: `{ "timestamp": "...", "level": "...", "channel": "...", "message": "...", ... }`
- Channels: `api`, `auth`, `security`, `db`, `guzzle.http`
- Never logs: passwords, ApiKey secrets, refresh tokens, private keys, stack traces (production)

**Audit Events:**
- Format: `{ "actor_type": "...", "actor_id": "...", "action": "...", "subject_type": "...", "subject_id": "...", "metadata_json": {...}, ... }`
- Required for all state-changing operations
- Action naming: `<domain>:<action>` or `<domain>:<subdomain>:<action>`

### 18.6 Security Conventions

**Authentication:**
- RS256 JWT only (no HS256)
- Token typing enforced (`typ=owner` vs `typ=key`)
- Generic auth errors (never reveal existence)
- Single-use refresh tokens with rotation

**Authorization:**
- Two-layer checks (global permission + post mask)
- Envelope rule enforced (child ⊆ parent)
- Use Key restrictions enforced (no `posts:create` or `keys:issue`)
- Visibility rules (404 vs 403)

**Input Validation:**
- Centralized schemas in `config/validation.php`
- `rejectUnknown: true` for security
- Field-level error messages

**Secrets:**
- Argon2id hashing for passwords and secrets
- Never log secrets
- Never return secrets after initial mint

---

## 19. Environment Configuration

### 19.1 Required Environment Variables

**Application:**
- `APP_NAME` - Application name
- `APP_ENV` - Environment (production, development, testing)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Application URL

**Database:**
- `DB_HOST` - Database host
- `DB_NAME` - Database name
- `DB_USER` - Database user
- `DB_PASS` - Database password
- `DB_PORT` - Database port (default: 3306)
- `DB_CHARSET` - Database charset (default: utf8mb4)
- `DB_COLLATION` - Database collation (default: utf8mb4_bin)

**JWT:**
- `JWT_ALGO` - JWT algorithm (RS256)
- `JWT_PRIVATE_KEY_PATH` - Private key PEM file path
- `JWT_PUBLIC_KEY_PATH` - Public key PEM file path
- `JWT_ISSUER` - JWT issuer claim
- `JWT_AUDIENCE` - JWT audience claim
- `JWT_ACCESS_TTL` - Access token TTL (seconds, default: 900)
- `JWT_REFRESH_TTL` - Refresh token TTL (seconds, default: 2592000)
- `JWT_LEEWAY` - Clock skew tolerance (seconds, default: 10)

**CORS:**
- `CORS_ALLOWED_ORIGINS` - Comma-separated origins
- `CORS_ALLOWED_METHODS` - Comma-separated methods
- `CORS_ALLOWED_HEADERS` - Comma-separated headers
- `CORS_EXPOSED_HEADERS` - Comma-separated exposed headers

**CSRF:**
- `CSRF_SECRET` - CSRF secret (32+ characters)

**Rate Limiting:**
- `RATE_LIMIT_GENERAL` - General limit (format: "100 per minute")
- `RATE_LIMIT_AUTH` - Auth limit (format: "10 per minute")
- `RATE_LIMIT_API` - API limit (format: "60 per minute")
- `RATE_LIMIT_BACKING` - Backing store (memory or redis)

**Redis (for rate limiting):**
- `REDIS_HOST` - Redis host (default: 127.0.0.1)
- `REDIS_PORT` - Redis port (default: 6379)
- `REDIS_PASSWORD` - Redis password (optional)

**HTTP Client:**
- `HTTP_TIMEOUT` - Guzzle timeout (seconds, default: 30)
- `HTTP_RETRY_MAX` - Max retries (default: 3)

**Logging:**
- `LOG_CHANNEL` - Log channel (stack, daily, single)
- `LOG_LEVEL` - Log level (debug, info, warning, error, critical)
- `LOG_PATH` - Log directory path

**Hashing:**
- `APIKEY_HASH_ALGO` - Hash algorithm (argon2id)
- `PASSWORD_MEMORY_COST` - Argon2id memory cost (default: 65536)
- `PASSWORD_TIME_COST` - Argon2id time cost (default: 4)
- `PASSWORD_PARALLELISM` - Argon2id parallelism (default: 1)

### 19.2 Bootstrap Validation

**File:** `src/Utilities/BootstrapValidator.php`

**Validations:**
- Database config (required vars, connection test)
- JWT config (key files exist and readable, PEM format)
- CORS config (parseable origins)
- Rate limit config (valid format)
- Log path (exists and writable)
- Required env vars (APP_NAME, APP_ENV, APP_URL)

**Fail-Fast:**
- Application exits with non-zero code on validation failure
- Prevents misconfigured deployments
- Clear error messages (never includes secrets)

---

## 20. Testing & Verification Tools

### 20.1 Contract Tests

**Directory:** `tools/contract/`

**Files:**
- **test_id_format_compliance.php**
  - Verifies ID format compliance (hex32, apub_)
  - Checks route parameters, JWT claims, JSON responses
- **test_audience_segregation.php**
  - Verifies audience claim segregation
  - Ensures Owner tokens use Console audience, Key tokens use Gateway audience
- **test_doc_ssot_alignment.php**
  - Verifies code aligns with documentation
  - Checks SSoT boundaries are respected

### 20.2 Database Verification

**File:** `tools/db/verify_schema.php`
- **Purpose:** Verify database schema matches expected contract
- **Usage:** `php tools/db/verify_schema.php`
- **Checks:**
  - Table existence
  - Column types and constraints
  - Indexes
  - Foreign keys
  - Collation (utf8mb4_bin)

---

## 21. Complete File Inventory

### 21.1 PHP Source Files (66 files)

**Controllers (18 files):**
- `src/Controllers/BaseController.php`
- `src/Controllers/HealthController.php`
- `src/Controllers/JwksController.php`
- `src/Controllers/OwnerController.php`
- `src/Controllers/Console/ConsoleController.php`
- `src/Controllers/Console/GroupController.php`
- `src/Controllers/Console/KeychainController.php`
- `src/Controllers/Console/KeyController.php`
- `src/Controllers/Console/PostController.php`
- `src/Controllers/Console/RouteCatalogController.php`
- `src/Controllers/Gateway/CommentController.php`
- `src/Controllers/Gateway/FeedController.php`
- `src/Controllers/Gateway/GatewayController.php`
- `src/Controllers/Gateway/GroupController.php`
- `src/Controllers/Gateway/KeychainController.php`
- `src/Controllers/Gateway/KeyController.php`
- `src/Controllers/Gateway/PostController.php`
- `src/Controllers/Gateway/RouteCatalogController.php`

**Services (9 files):**
- `src/Services/BaseService.php`
- `src/Services/AuditService.php`
- `src/Services/AuthService.php`
- `src/Services/CommentService.php`
- `src/Services/FeedService.php`
- `src/Services/GroupService.php`
- `src/Services/KeychainService.php`
- `src/Services/KeyService.php`
- `src/Services/LoggingService.php`
- `src/Services/PostService.php`

**Repositories (13 files):**
- `src/Repositories/BaseRepository.php`
- `src/Repositories/AuditEventRepository.php`
- `src/Repositories/CommentRepository.php`
- `src/Repositories/GroupMemberRepository.php`
- `src/Repositories/GroupRepository.php`
- `src/Repositories/KeychainMemberRepository.php`
- `src/Repositories/KeychainRepository.php`
- `src/Repositories/KeyDeviceRepository.php`
- `src/Repositories/KeyPublicIdRepository.php`
- `src/Repositories/KeyRepository.php`
- `src/Repositories/OwnerRepository.php`
- `src/Repositories/PostAccessRepository.php`
- `src/Repositories/PostRepository.php`
- `src/Repositories/RefreshTokenRepository.php`

**Middleware (11 files):**
- `src/Middleware/CorsMiddleware.php`
- `src/Middleware/CspMiddleware.php`
- `src/Middleware/CsrfExposeMiddleware.php`
- `src/Middleware/ErrorHandlingMiddleware.php`
- `src/Middleware/HttpsMiddleware.php`
- `src/Middleware/JwtKeyMiddleware.php`
- `src/Middleware/JwtOwnerMiddleware.php`
- `src/Middleware/RateLimitMiddleware.php`
- `src/Middleware/RequestLoggingMiddleware.php`
- `src/Middleware/RouteParameterValidatorMiddleware.php`
- `src/Middleware/UseKeyLimitMiddleware.php`
- `src/Middleware/ValidationMiddleware.php`

**Security (3 files):**
- `src/Security/JwtService.php`
- `src/Security/PermissionCatalog.php`
- `src/Security/PostAccessBitmask.php`

**Utilities (6 files):**
- `src/Utilities/BootstrapValidator.php`
- `src/Utilities/ErrorFactory.php`
- `src/Utilities/Ids.php`
- `src/Utilities/ResponseFactory.php`
- `src/Utilities/SchemaContractVerifier.php`
- `src/Utilities/SensitiveDataSanitizer.php`

**Exceptions (2 files):**
- `src/Exceptions/ForbiddenException.php`
- `src/Exceptions/NotFoundException.php`

**Bootstrap (1 file):**
- `src/bootstrap.php`

### 21.2 Configuration Files (6 files)

- `config/container.php`
- `config/routes.php`
- `config/validation.php`
- `config/routes/console_html.php`
- `config/routes/console_json.php`
- `config/routes/gateway_html.php`
- `config/routes/gateway_json.php`
- `config/routes/public_api.php`

### 21.3 Migration Files (13 files)

- `migrations/001_create_owners.php`
- `migrations/002_create_keys.php`
- `migrations/003_create_key_public_ids.php`
- `migrations/004_create_posts_and_comments.php`
- `migrations/005_create_post_access.php`
- `migrations/006_create_groups.php`
- `migrations/007_create_keychains.php`
- `migrations/008_create_refresh_tokens.php`
- `migrations/009_create_audit_events.php`
- `migrations/010_add_label_to_keys.php`
- `migrations/011_add_lookup_hash_to_refresh_tokens.php`
- `migrations/012_add_owner_id_to_keys.php`
- `migrations/013_create_key_devices.php`

### 21.4 Template Files (23 files)

**Console Templates (9 files):**
- `templates/_permission_helpers.php`
- `templates/dashboard.php`
- `templates/groups_list.php`
- `templates/keychains_list.php`
- `templates/keys_list.php`
- `templates/landing.php`
- `templates/login.php`
- `templates/posts_list.php`
- `templates/register.php`

**Gateway Templates (14 files):**
- `templates/gateway/auth_exchange.php`
- `templates/gateway/auth_refresh.php`
- `templates/gateway/comments_list.php`
- `templates/gateway/feed_author.php`
- `templates/gateway/feed_use.php`
- `templates/gateway/group_detail.php`
- `templates/gateway/groups_list.php`
- `templates/gateway/keychains_list.php`
- `templates/gateway/keys_mint_secondary.php`
- `templates/gateway/keys_mint_use.php`
- `templates/gateway/post_detail.php`
- `templates/gateway/post_grant_access.php`
- `templates/gateway/posts_list.php`
- `templates/gateway/share_workflow.php`

### 21.5 Tool Scripts (5 files)

- `tools/db/migrate.php`
- `tools/db/verify_schema.php`
- `tools/contract/test_audience_segregation.php`
- `tools/contract/test_doc_ssot_alignment.php`
- `tools/contract/test_id_format_compliance.php`

### 21.6 Static Assets (3 files)

- `public/css/styles.css`
- `public/index.php`
- `UI/` directory (standalone UI, not core application)

### 21.7 Documentation Files

**Canon:** Core specifications (introduction, architecture, authentication, authorization, API reference, data model, implementation, operations) plus canon-ssot.

**Reference:** Glossary, identifier encoding, environment configuration, permissions, key capabilities, routes inventory, dependency wiring, component architecture, layering rules, document outlines, codebase inventory, docset.json, plus appendix-ssot.

**Development:** Codebase inventory, component breakdown, installation guide, elevator pitches, production-readiness materials, plus development-ssot.

**Navigation:** /TOC.md, /SSOT.md, docs/README.md, table-of-contents, toc-canon, toc-appendix, toc-dev.

---

## 22. Critical Integration Points

### 22.1 Request Flow

**Entry:** `public/index.php`
↓
**Bootstrap:** `src/bootstrap.php`
  - Load env vars
  - Validate bootstrap
  - Build DI container
  - Register routes
↓
**Route Matching:** `config/routes.php` → `config/routes/*.php`
↓
**Middleware Pipeline:** (varies by surface)
  - HttpsMiddleware
  - CorsMiddleware
  - RateLimitMiddleware
  - JwtMiddleware (if protected)
  - BodyParsingMiddleware
  - RouteParameterValidatorMiddleware
  - ValidationMiddleware
  - CSRF Guard (HTML routes only)
↓
**Controller:** Extract params, call service
↓
**Service:** Business logic, authorization, audit
↓
**Repository:** Data access (PDO prepared statements)
↓
**Response:** Standardized envelope via ResponseFactory
↓
**Error Handling:** ErrorHandlingMiddleware (catches exceptions)

### 22.2 Authentication Flow

**Owner Login:**
1. `POST /console/login` (public_api.php)
2. OwnerController::login()
3. AuthService::loginOwner()
4. OwnerRepository::findByEmail()
5. Verify password (Argon2id)
6. JwtService::signOwnerToken()
7. Generate refresh token
8. RefreshTokenRepository::createRefreshToken()
9. Return tokens

**ApiKey Exchange:**
1. `POST /api/auth/exchange` (public_api.php)
2. OwnerController::exchange()
3. AuthService::exchangeApiKey()
4. KeyPublicIdRepository::findByPublicId()
5. KeyRepository::findById()
6. Verify secret (Argon2id)
7. Check active status
8. Check use_count/device_limit (UseKeyLimitMiddleware)
9. JwtService::signKeyToken()
10. Generate refresh token
11. RefreshTokenRepository::createRefreshToken()
12. Return tokens

**Refresh Token Rotation:**
1. `POST /api/auth/refresh` (public_api.php)
2. AuthController::refresh()
3. AuthService::refreshToken()
4. RefreshTokenRepository::findByHash()
5. Verify not rotated (replay detection)
6. Mark as rotated
7. Generate new tokens
8. RefreshTokenRepository::createRefreshToken()
9. Return new tokens

### 22.3 Authorization Flow

**Post Creation:**
1. `POST /api/posts` (gateway_json.php)
2. PostController::create()
3. PostService::createPost()
4. KeyRepository::findById() - Load key
5. Verify `posts:create` permission
6. Verify key type (not `use`)
7. PostRepository::create()
8. AuditService::log() - Emit audit event
9. Return post

**Post Access Check:**
1. `GET /api/posts/{postId}` (gateway_json.php)
2. PostController::show()
3. PostService::getPost()
4. Verify `posts:read` permission (global)
5. PostAccessRepository::getAccessMask() - Check VIEW mask
6. If no VIEW mask → NotFoundException (404)
7. If VIEW mask but no COMMENT → ForbiddenException (403) for comment action
8. Return post

### 22.4 Key Lifecycle Flow

**Mint Primary Key:**
1. `POST /console/keys/primary` (console_json.php)
2. KeyController::mintPrimary()
3. KeyService::mintPrimary()
4. Verify Owner has `keys:issue` permission
5. Generate key_id, key_public_id, key_secret
6. Hash key_secret (Argon2id)
7. KeyRepository::create() - Insert key
8. KeyPublicIdRepository::create() - Insert public ID
9. AuditService::log() - Emit audit event
10. Return key (secret returned once)

**Mint Secondary/Use Key:**
1. `POST /api/keys/{authorKeyId}/secondary` or `/use` (gateway_json.php)
2. KeyController::mintSecondary() or mintUse()
3. KeyService::mintSecondary() or mintUse()
4. Verify parent has `keys:issue` permission
5. Verify envelope rule (child ⊆ parent)
6. Verify Use Key restrictions (if Use Key)
7. Generate key with lineage fields
8. KeyRepository::create()
9. KeyPublicIdRepository::create()
10. AuditService::log()
11. Return key

---

## 23. Database Schema Summary

### 23.1 Core Tables

**owners:**
- `id` (BINARY(16), PK)
- `email` (VARCHAR(255), UNIQUE)
- `password_hash` (VARCHAR(255), Argon2id)
- `created_at`, `updated_at` (TIMESTAMP)

**keys:**
- `id` (BINARY(16), PK)
- `type` (ENUM: primary, secondary, use)
- `key_secret_hash` (VARCHAR(255), Argon2id)
- `permissions_json` (JSON)
- `active` (BOOLEAN)
- `label` (VARCHAR(255), nullable)
- `owner_id` (BINARY(16), FK owners.id, nullable)
- Lineage: `issued_by_key_id`, `parent_key_id`, `initial_author_key_id` (all BINARY(16), FK keys.id)
- Rotation: `rotated_from_id`, `rotated_to_id` (BINARY(16), FK keys.id, nullable)
- `retired_at` (TIMESTAMP, nullable)
- Limits: `use_count_limit`, `use_count_current`, `device_limit` (INT, nullable)
- `created_at`, `updated_at` (TIMESTAMP)

**key_public_ids:**
- `id` (BINARY(16), PK)
- `key_id` (BINARY(16), FK keys.id, UNIQUE)
- `key_public_id` (VARCHAR(64), UNIQUE, apub_ format)
- `created_at` (TIMESTAMP)

**posts:**
- `id` (BINARY(16), PK)
- `author_key_id` (BINARY(16), FK keys.id)
- `initial_author_key_id` (BINARY(16), FK keys.id)
- `title` (VARCHAR(255), nullable)
- `content` (TEXT)
- `created_at`, `updated_at` (TIMESTAMP)

**comments:**
- `id` (BINARY(16), PK)
- `post_id` (BINARY(16), FK posts.id)
- `created_by_key_id` (BINARY(16), FK keys.id)
- `body` (TEXT)
- `created_at` (TIMESTAMP)

**post_access:**
- `id` (BINARY(16), PK)
- `post_id` (BINARY(16), FK posts.id)
- `target_type` (ENUM: key, group)
- `target_id` (BINARY(16))
- `permission_mask` (INT)
- `created_at` (TIMESTAMP)
- UNIQUE INDEX: `(post_id, target_type, target_id)`

**groups:**
- `id` (BINARY(16), PK)
- `owner_id` (BINARY(16), FK owners.id)
- `name` (VARCHAR(255))
- `created_at`, `updated_at` (TIMESTAMP)

**group_members:**
- `group_id` (BINARY(16), FK groups.id)
- `key_id` (BINARY(16), FK keys.id)
- `created_at` (TIMESTAMP)
- PRIMARY KEY: `(group_id, key_id)`

**keychains:**
- `id` (BINARY(16), PK)
- `owner_id` (BINARY(16), FK owners.id, nullable)
- `name` (VARCHAR(255))
- `created_at`, `updated_at` (TIMESTAMP)

**keychain_members:**
- `keychain_id` (BINARY(16), FK keychains.id)
- `key_id` (BINARY(16), FK keys.id)
- `created_at` (TIMESTAMP)
- PRIMARY KEY: `(keychain_id, key_id)`

**refresh_tokens:**
- `id` (BINARY(16), PK)
- `subject_type` (ENUM: owner, key)
- `subject_id` (BINARY(16))
- `token_hash` (VARCHAR(255), Argon2id)
- `lookup_hash` (VARCHAR(255), SHA-256, indexed)
- `issued_at` (TIMESTAMP)
- `expires_at` (TIMESTAMP)
- `revoked_at` (TIMESTAMP, nullable)
- `rotated_at` (TIMESTAMP, nullable)
- `replaced_by_id` (BINARY(16), FK refresh_tokens.id, nullable)
- `ip` (VARCHAR(45), nullable)
- `user_agent` (VARCHAR(255), nullable)

**key_devices:**
- `id` (BINARY(16), PK)
- `key_id` (BINARY(16), FK keys.id)
- `device_fingerprint` (VARCHAR(255))
- `first_seen_at` (TIMESTAMP)
- UNIQUE INDEX: `(key_id, device_fingerprint)`

**audit_events:**
- `id` (BINARY(16), PK)
- `actor_type` (ENUM: owner, key)
- `actor_id` (BINARY(16))
- `action` (VARCHAR(100))
- `subject_type` (VARCHAR(50), nullable)
- `subject_id` (BINARY(16), nullable)
- `metadata_json` (JSON, nullable)
- `ip` (VARCHAR(45), nullable)
- `user_agent` (VARCHAR(255), nullable)
- `created_at` (TIMESTAMP)

### 23.2 Indexes

**Critical Indexes:**
- `keys`: `type`, `active`, `initial_author_key_id`, `owner_id`
- `posts`: `author_key_id`, `initial_author_key_id`, `created_at DESC`
- `post_access`: `(post_id, target_type, target_id)` UNIQUE
- `refresh_tokens`: `lookup_hash`, `(subject_type, subject_id)`
- `key_devices`: `(key_id, device_fingerprint)` UNIQUE
- `audit_events`: `(actor_type, actor_id, created_at)`, `(subject_type, subject_id)`

---

## 24. Critical Rules & Constraints

### 24.1 Security Rules

1. **CSRF Scope:** HTML routes only, never JSON endpoints
2. **Token Typing:** Strict enforcement of `typ=owner` vs `typ=key`
3. **Refresh Token Single-Use:** Automatic rotation, replay detection
4. **Never Log Secrets:** Passwords, ApiKey secrets, refresh tokens, private keys
5. **Generic Auth Errors:** Never reveal existence of email or `key_public_id`
6. **Prepared Statements:** All database access uses PDO parameter binding
7. **Argon2id Hashing:** All passwords and secrets use Argon2id
8. **RS256 JWT:** Asymmetric signing only, no HS256

### 24.2 Authorization Rules

1. **Envelope Rule:** Child permissions ⊆ Parent permissions (enforced at mint time)
2. **Use Key Restrictions:** Cannot have `posts:create` or `keys:issue`
3. **Permission Immutability:** Cannot change after minting (use rotation)
4. **Lineage Immutability:** Lineage fields never change after creation
5. **Combined Checks:** Global permission + post mask both required
6. **Visibility Rules:** 404 when hiding existence, 403 when revealing lack of permission

### 24.3 ID Format Rules

1. **Internal Storage:** BINARY(16) for all primary/foreign keys
2. **External Representation:** hex32 (32-char lowercase hex) for routes, JSON, JWT claims
3. **Key Public IDs:** apub_... format, used ONLY for ApiKey exchange
4. **Route Parameters:** All `{...Id}` are hex32 except `{keyPublicId}`
5. **Conversion:** Repository layer converts hex32 ↔ BINARY(16)

### 24.4 Response Format Rules

1. **Standardized Envelopes:** All JSON endpoints use `{ "data": {...} }` or `{ "error": {...} }`
2. **Error Codes:** Stable, machine-readable codes
3. **Validation Errors:** Always include `details.fields`
4. **Permission Errors:** Always include `details.required` or `details.required_mask`
5. **Never Leak:** Stack traces, internal paths, secret values

### 24.5 Logging Rules

1. **Structured JSON:** All logs use structured JSON format
2. **Channels:** Use appropriate channel (api, auth, security, db)
3. **Never Log Secrets:** Passwords, ApiKey secrets, refresh tokens, private keys
4. **Audit Events:** Required for all state-changing operations
5. **Correlation IDs:** Include `request_id` when available

---

## 25. Integration Checklist

### 25.1 Controller Checklist

For each controller method:
- [ ] Extends BaseController
- [ ] Extracts params from request (route, query, body, headers)
- [ ] Calls exactly ONE service method
- [ ] Uses ResponseFactory for success responses
- [ ] Uses ErrorFactory for error responses
- [ ] Handles exceptions appropriately
- [ ] No business logic
- [ ] No database access
- [ ] No authorization checks

### 25.2 Service Checklist

For each service method:
- [ ] Extends BaseService
- [ ] Enforces global permissions (check JWT permissions)
- [ ] Enforces post bitmasks (for post-scoped actions)
- [ ] Enforces invariants (key type, lineage, immutability)
- [ ] Orchestrates repositories (transactions if needed)
- [ ] Emits audit events (for state changes)
- [ ] Throws deterministic exceptions
- [ ] No HTTP concerns
- [ ] No direct SQL queries

### 25.3 Repository Checklist

For each repository method:
- [ ] Extends BaseRepository
- [ ] Uses PDO prepared statements exclusively
- [ ] Converts hex32 → BINARY(16) for queries
- [ ] Converts BINARY(16) → hex32 for returns
- [ ] Returns arrays or null
- [ ] No business logic
- [ ] No permission checks
- [ ] No HTTP concerns

### 25.4 Middleware Checklist

For each middleware:
- [ ] Implements PSR-15 MiddlewareInterface
- [ ] Processes request and calls handler
- [ ] Attaches attributes to request (if needed)
- [ ] Returns ResponseInterface
- [ ] No business logic
- [ ] No direct database access (except rate limiter storage)
- [ ] Logs appropriately (if needed)

### 25.5 Route Checklist

For each route:
- [ ] Defined in correct route group file
- [ ] Has validation rule in `config/validation.php` (if has body)
- [ ] Has correct middleware pipeline
- [ ] Has correct authentication (if protected)
- [ ] Has correct CSRF protection (HTML routes only)
- [ ] Uses correct ID format (hex32 or apub_)
- [ ] Returns standardized response envelope

---

## 26. Production Readiness Checklist

### 26.1 Security

- [ ] HTTPS enforced (HttpsMiddleware)
- [ ] HSTS headers set (production)
- [ ] CORS configured (allowlist)
- [ ] CSRF on HTML routes only
- [ ] RS256 JWT signing
- [ ] Token typing enforced
- [ ] Single-use refresh tokens
- [ ] Replay detection working
- [ ] Argon2id hashing
- [ ] Never logs secrets
- [ ] Generic auth errors
- [ ] Prepared statements only
- [ ] Input validation on all endpoints
- [ ] Rate limiting configured
- [ ] Use key limits enforced

### 26.2 Architecture

- [ ] Layered architecture enforced
- [ ] Dependency injection configured
- [ ] Middleware pipelines correct
- [ ] Route groups organized
- [ ] Response envelopes standardized
- [ ] Error handling normalized
- [ ] ID formats consistent

### 26.3 Database

- [ ] Migrations run in order
- [ ] Schema matches contract
- [ ] Indexes created
- [ ] Foreign keys defined
- [ ] Collation correct (utf8mb4_bin)
- [ ] Prepared statements only

### 26.4 Logging & Observability

- [ ] Structured JSON logging
- [ ] Multiple channels configured
- [ ] Audit events emitted
- [ ] Never logs secrets
- [ ] Correlation IDs included
- [ ] Rate limit hits logged

### 26.5 Configuration

- [ ] Environment variables documented
- [ ] Bootstrap validation working
- [ ] Fail-fast on misconfiguration
- [ ] JWT keys readable
- [ ] Log path writable
- [ ] Database connection working

---

## 27. Known Issues & Fixes

### 27.1 Fix Roadmap

**File:** `11-development/production-readiness-issues.md` (if exists)

**13 Production Issues:**
- FIX-0.1: Missing `}` in PostAccessRepository.php
- FIX-0.2: `PDO` → `\PDO` in BaseRepository.php (13 repos inherit)
- FIX-0.3: JwksController constructor parameter
- FIX-0.4: FeedController `success()` → `single()`
- FIX-1.1: Refresh token lookup hash (SHA-256)
- FIX-1.2: Add owner_id to keys table
- FIX-1.3: Owner check pattern in services
- FIX-1.4: Remove audience check from JwtService (middleware handles)
- FIX-2.1: Add JWT middleware to console_html.php
- FIX-2.2: Extract fields in ValidationMiddleware
- FIX-2.3: Redis rate limiter in container.php
- FIX-2.4: key_devices table migration
- FIX-2.5: Add RequestLogging middleware

**Status:** See `11-development/verified-production-issues.md` (if exists)

---

## 28. Summary Statistics

**Total PHP Files:** 66
- Controllers: 18
- Services: 10
- Repositories: 14
- Middleware: 12
- Security: 3
- Utilities: 6
- Exceptions: 2
- Bootstrap: 1

**Total Configuration Files:** 8
- Container: 1
- Routes: 6
- Validation: 1

**Total Migration Files:** 13

**Total Template Files:** 23
- Console: 9
- Gateway: 14

**Total Tool Scripts:** 5

**Total Documentation Files:** 37+

**Total Dependencies:** 13 Composer packages

**Total Environment Variables:** 30+ configuration variables

---

## 29. Next Steps for Review

This inventory provides a complete catalog of all codebase components. For production readiness review:

1. **Review each component** against its responsibilities and prohibitions
2. **Verify integration points** between components
3. **Test critical paths** (authentication, authorization, key lifecycle, post sharing)
4. **Verify security rules** are enforced (CSRF scope, token typing, secret handling)
5. **Check ID format compliance** throughout (hex32, apub_, BINARY(16))
6. **Verify response format consistency** (standardized envelopes)
7. **Test error handling** (all error codes, proper status mapping)
8. **Verify logging** (structured JSON, appropriate channels, no secrets)
9. **Check audit events** (all state changes logged)
10. **Verify database schema** (matches contract, indexes present)

**Study Guide:** A methodical study guide will be developed next to guide systematic review of each component.

---

**End of Codebase Inventory**

This inventory catalogs all components, files, conventions, and patterns in the CRE8.pw codebase. Use this as a reference for production readiness review and systematic component analysis.
