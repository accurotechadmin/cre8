# Single Source of Truth (SSOT) — CRE8.pw Development Documentation

**Version:** 1.0.0  
**Last Updated:** 2026-01-25  
**Status:** Canonical  
**Purpose:** Consolidated reference for all development practices, codebase structure, installation procedures, and development workflows

---

## Table of Contents

1. [Codebase Structure](#1-codebase-structure)
2. [Entry Points & Bootstrap](#2-entry-points--bootstrap)
3. [Component Architecture](#3-component-architecture)
4. [Development Workflow](#4-development-workflow)
5. [Installation & Setup](#5-installation--setup)
6. [Testing & Verification](#6-testing--verification)
7. [Code Conventions](#7-code-conventions)
8. [File Organization](#8-file-organization)
9. [Dependency Management](#9-dependency-management)
10. [Documentation Structure](#10-documentation-structure)
11. [Production Readiness](#11-production-readiness)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Codebase Structure

### 1.1 Directory Layout

```
./
├── public/                    # Public web root
│   ├── index.php            # Application entry point
│   └── css/                 # Static assets
│       └── styles.css
├── src/                      # Application source code
│   ├── bootstrap.php        # Application bootstrap
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
│       └── [14 example pages]
├── tools/                  # Utility scripts
│   ├── contract/          # Contract/compliance tests
│   │   ├── README.md
│   │   ├── test_audience_segregation.php
│   │   ├── test_doc_ssot_alignment.php
│   │   └── test_id_format_compliance.php
│   └── db/                # Database utilities
│       ├── migrate.php
│       └── verify_schema.php
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
│       ├── toc-canon.md
│       ├── toc-appendix.md
│       └── toc-dev.md
├── UI/                     # Standalone UI (example client)
│   ├── 404.html
│   ├── app.js
│   ├── DOWNLOAD.html
│   ├── icons.js
│   ├── index.html
│   ├── README.md
│   └── styles.css
├── composer.json           # PHP dependencies
├── .env.example           # Environment template
├── .env.local.example     # Local environment template
├── README.md              # Project README
├── TOC.md                 # Master documentation index
└── SSOT.md                # Master SSOT hub
```

### 1.2 File Counts

**Source Code:**
- Controllers: ~15 files
- Services: ~8 files
- Repositories: ~7 files
- Middleware: ~8 files
- Utilities: ~6 files
- Exceptions: ~6 files
- Security: ~3 files

**Configuration:**
- Route files: 5 files
- Config files: 3 files

**Database:**
- Migrations: 13 files

**Templates:**
- Console templates: ~9 files
- Gateway templates: ~14 files

**Documentation:**
- Canon docs: 13 files (see `toc-canon.md`)
- Appendix docs: 13 files (see `toc-appendix.md`)
- Dev docs: 5 files (see `toc-dev.md`)

---

## 2. Entry Points & Bootstrap

### 2.1 Public Entry Point

**File:** `public/index.php`

**Purpose:** Public HTTP entry point for all requests

**Responsibilities:**
- Load Composer autoloader
- Bootstrap application via `src/bootstrap.php`
- Run Slim application

**Code Structure:**
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = require __DIR__ . '/../src/bootstrap.php';
$app = $bootstrap();

$app->run();
```

**Dependencies:**
- `vendor/autoload.php` (Composer autoloader)
- `src/bootstrap.php` (Application bootstrap)

### 2.2 Application Bootstrap

**File:** `src/bootstrap.php`

**Purpose:** Initialize Slim application, DI container, routes, middleware

**Responsibilities:**
1. Load environment variables (`vlucas/phpdotenv`)
2. Validate bootstrap requirements (`BootstrapValidator`)
3. Build DI container (`PHP-DI`)
4. Create Slim app with container
5. Register route groups (`config/routes.php`)
6. Register error handling middleware (last)

**Bootstrap Validation:**
- Database connection successful
- JWT private/public keys readable and valid
- `JWT_ISSUER` and `JWT_AUDIENCE` set
- Required directories writable (logs/)
- CORS origins parseable
- Rate limit configuration valid

**On Failure:** Log error and exit with non-zero code (prevent misconfigured deployment)

**Dependencies:**
- `config/container.php` (DI container configuration)
- `config/routes.php` (Route group registration)
- `src/Utilities/BootstrapValidator.php` (Bootstrap validation)

---

## 3. Component Architecture

### 3.1 Controllers

**Location:** `src/Controllers/`

**Purpose:** HTTP adapters that extract request data, call services, and shape responses

**Responsibilities:**
- Extract params (route, query, body, headers)
- Call exactly ONE service method
- Shape response using standardized envelopes
- Return PSR-7 response

**Forbidden:**
- Business logic
- Direct database access
- Multi-service orchestration
- Permission enforcement (handled by middleware and services)

**Base Class:** `BaseController.php`
- Provides standardized response methods:
  - `single()` - Single object response
  - `list()` - List response
  - `paginated()` - Paginated list response
  - `created()` - 201 Created response
  - `noContent()` - 204 No Content response
  - `error()` - Error response

**Console Controllers (`src/Controllers/Console/`):**
- `ConsoleController.php` - HTML page rendering
- `KeyController.php` - Key management
- `GroupController.php` - Group management
- `KeychainController.php` - Keychain management
- `PostController.php` - Post admin

**Gateway Controllers (`src/Controllers/Gateway/`):**
- `PostController.php` - Post creation and access
- `CommentController.php` - Comment management
- `KeyController.php` - Key issuance
- `FeedController.php` - Feed endpoints
- `GroupController.php` - Group read-only
- `KeychainController.php` - External keychain management

### 3.2 Services

**Location:** `src/Services/`

**Purpose:** Business logic layer that enforces rules, orchestrates repositories, and emits audit events

**Responsibilities:**
- Enforce global permissions (check JWT permissions)
- Enforce post bitmasks (for post-scoped actions)
- Enforce invariants (key type, lineage, immutability)
- Orchestrate multiple repositories (transactions)
- Emit audit events
- Throw deterministic exceptions

**Forbidden:**
- HTTP concerns (request/response objects)
- Direct SQL queries (use repositories)

**Base Class:** `BaseService.php` (if needed)
- Common service functionality
- Shared helper methods

**Service Files:**
- `AuthService.php` - Authentication and token management
- `KeyService.php` - Key lifecycle management
- `PostService.php` - Post creation and access management
- `CommentService.php` - Comment management
- `FeedService.php` - Feed generation
- `GroupService.php` - Group management
- `KeychainService.php` - Keychain management
- `ConsoleService.php` - Console page data preparation

### 3.3 Repositories

**Location:** `src/Repositories/`

**Purpose:** Data access layer using PDO prepared statements

**Responsibilities:**
- PDO prepared statements exclusively
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs
- Handle database-level errors

**Forbidden:**
- Business logic
- Permission checks
- HTTP concerns
- Logging secrets

**Base Class:** `BaseRepository.php` (if needed)
- Common repository functionality
- ID generation helpers
- Transaction management helpers

**Repository Files:**
- `OwnerRepository.php` - Owner data access
- `KeyRepository.php` - Key data access
- `PostRepository.php` - Post and comment data access
- `GroupRepository.php` - Group data access
- `KeychainRepository.php` - Keychain data access
- `TokenRepository.php` - Refresh token data access
- `AuditRepository.php` - Audit event data access

### 3.4 Middleware

**Location:** `src/Middleware/`

**Purpose:** Cross-cutting concerns (HTTPS, CORS, rate limiting, JWT verification, validation, error handling)

**Responsibilities:**
- HTTPS enforcement, CORS, rate limiting
- JWT verification, token typing
- Validation (request schemas)
- Error normalization

**Forbidden:**
- Business logic
- Database access (except rate limiter storage)

**Middleware Files:**
- `HttpsMiddleware.php` - HTTPS enforcement and HSTS
- `CorsMiddleware.php` - CORS header management
- `RateLimitMiddleware.php` - Rate limiting
- `JwtOwnerMiddleware.php` - Owner JWT verification
- `JwtKeyMiddleware.php` - Key JWT verification
- `ValidationMiddleware.php` - Request validation
- `ErrorHandlingMiddleware.php` - Error normalization
- `CsrfExposeMiddleware.php` - CSRF token exposure (HTML only)

### 3.5 Utilities

**Location:** `src/Utilities/`

**Purpose:** Helper utilities used across the application

**Utility Files:**
- `Ids.php` - ID conversion utilities (hex32 ↔ BINARY(16))
- `ResponseFactory.php` - Standardized response creation
- `ErrorFactory.php` - Standardized error response creation
- `BootstrapValidator.php` - Bootstrap validation
- `SchemaContractVerifier.php` - Schema verification
- `SensitiveDataSanitizer.php` - Log sanitization

### 3.6 Exceptions

**Location:** `src/Exceptions/`

**Purpose:** Custom exceptions for different error scenarios

**Exception Files:**
- `NotFoundException.php` - 404 Not Found
- `ForbiddenException.php` - 403 Forbidden
- `UnauthorizedException.php` - 401 Unauthorized
- `ValidationException.php` - 422 Validation Failed
- `BadRequestException.php` - 400 Bad Request
- `RateLimitException.php` - 429 Too Many Requests

---

## 4. Development Workflow

### 4.1 Local Development Setup

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

### 4.2 Quick Start Workflow

**1. Register Owner:**
```bash
curl -X POST http://localhost:8000/console/owners \
  -H "Content-Type: application/json" \
  -d '{"email": "alice@example.com", "password": "SecurePassword123!"}'
```

**2. Login:**
```bash
curl -X POST http://localhost:8000/console/login \
  -H "Content-Type: application/json" \
  -d '{"email": "alice@example.com", "password": "SecurePassword123!"}'
# Returns: { "access_token": "...", "refresh_token": "..." }
```

**3. Mint Primary Author Key:**
```bash
curl -X POST http://localhost:8000/console/keys/primary \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{"permissions": ["posts:create", "keys:issue", "comments:write"], "label": "My First Key"}'
# Returns: { "key_id": "...", "key_public_id": "apub_...", "key_secret": "sec_..." }
```

**4. Exchange ApiKey:**
```bash
curl -X POST http://localhost:8000/api/auth/exchange \
  -H "Authorization: ApiKey apub_...:sec_..." \
  -H "Content-Type: application/json"
# Returns: { "access_token": "...", "refresh_token": "..." }
```

**5. Create Post:**
```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer <key_access_token>" \
  -H "Content-Type: application/json" \
  -d '{"content": "Hello, CRE8.pw!", "title": "My First Post"}'
# Returns: { "data": { "post_id": "...", "content": "...", ... } }
```

**6. Grant Access:**
```bash
curl -X POST http://localhost:8000/api/posts/{postId}/access \
  -H "Authorization: Bearer <key_access_token>" \
  -H "Content-Type: application/json" \
  -d '{"target_type": "key", "target_id": "...", "permission_mask": 3}'
```

### 4.3 Adding a New Feature

**Step-by-Step Process:**

1. **Plan:**
   - Determine surface (Console HTML, Console JSON, Gateway JSON, Public API)
   - Determine auth requirements (Owner JWT, Key JWT, or public)
   - Determine required permissions
   - Determine post mask bits if applicable

2. **Database:**
   - Create migration if new tables/columns needed
   - Run migration: `php tools/db/migrate.php`
   - Verify schema: `php tools/db/verify_schema.php`

3. **Repository:**
   - Create or extend repository methods
   - Use PDO prepared statements
   - Convert hex32 ↔ BINARY(16) at boundary

4. **Service:**
   - Create or extend service methods
   - Enforce permissions and masks
   - Orchestrate repositories (use transactions)
   - Emit audit events

5. **Controller:**
   - Create or extend controller methods
   - Extract params, call service, shape response

6. **Route:**
   - Add route to appropriate route file
   - Add validation rules to `config/validation.php`

7. **Test:**
   - Test authorization (403 for missing permissions)
   - Test validation (422 for invalid input)
   - Test error responses (401, 404, 429)
   - Verify audit events logged
   - Verify logging works

8. **Documentation:**
   - Update SSOT documents
   - Update API documentation
   - Update installation guide if needed

---

## 5. Installation & Setup

### 5.1 Prerequisites

**Required Software:**
- PHP 8.3 or higher
- Composer (PHP dependency manager)
- MariaDB 11.4.x or MySQL 8.0+
- OpenSSL (for generating JWT keys)

**Required PHP Extensions:**
- `pdo` (for database access)
- `pdo_mysql` (for MariaDB/MySQL)
- `sodium` (for Argon2id password hashing)
- `openssl` (for JWT key generation)
- `json` (usually included)
- `mbstring` (usually included)

**System Requirements:**
- Operating System: Linux, macOS, or Windows (with WSL recommended)
- Memory: Minimum 512MB RAM (1GB+ recommended)
- Disk Space: Minimum 100MB for application files
- Network: Port 8000 (or your chosen port) available for local development

### 5.2 Installation Steps

**1. Clone or Download:**
```bash
cd /path/to/your/projects
git clone <repository-url> cre8.pw
cd cre8.pw
```

**2. Install PHP Dependencies:**
```bash
composer install
```

**3. Create Database:**
```sql
CREATE DATABASE cre8pw CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
CREATE USER 'cre8_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON cre8pw.* TO 'cre8_user'@'localhost';
FLUSH PRIVILEGES;
```

**4. Configure Environment:**
```bash
cp .env.example .env
# Edit .env with your database credentials and other settings
```

**5. Generate JWT Keys:**
```bash
mkdir -p keys
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem
```

**6. Run Migrations:**
```bash
php tools/db/migrate.php
```

**7. Start Server:**
```bash
php -S localhost:8000 -t public
```

### 5.3 Verification

**Health Check:**
```bash
curl http://localhost:8000/health
# Should return: { "status": "ok" }
```

**JWKS Endpoint:**
```bash
curl http://localhost:8000/.well-known/jwks.json
# Should return: { "keys": [...] }
```

**Database Schema:**
```bash
php tools/db/verify_schema.php
# Should verify all tables and indexes exist
```

---

## 6. Testing & Verification

### 6.1 Contract Tests

**ID Format Compliance:**
```bash
php tools/contract/test_id_format_compliance.php
```
- Verifies all IDs use hex32 format externally
- Verifies BINARY(16) format internally
- Verifies key_public_id uses apub_ format

**Audience Segregation:**
```bash
php tools/contract/test_audience_segregation.php
```
- Verifies token typing (`typ=owner` vs `typ=key`)
- Verifies Owner tokens cannot access Gateway endpoints
- Verifies Key tokens cannot access Console endpoints

**Documentation Alignment:**
```bash
php tools/contract/test_doc_ssot_alignment.php
```
- Verifies code matches SSOT documentation
- Verifies routes match API documentation
- Verifies validation rules match documentation

### 6.2 Manual Testing Checklist

**Authorization:**
- ✅ Test with missing permission (should return 403)
- ✅ Test with wrong token type (should return 401)
- ✅ Test with inactive key (should return 401)
- ✅ Test with expired token (should return 401)

**Validation:**
- ✅ Test with invalid input (should return 422 with `details.fields`)
- ✅ Test with missing required fields (should return 422)
- ✅ Test with unknown fields (should return 422 if `rejectUnknown: true`)

**Error Handling:**
- ✅ Test with non-existent resource (should return 404)
- ✅ Test with rate limit exceeded (should return 429)
- ✅ Test with malformed request (should return 400)

**Functionality:**
- ✅ Test successful operations return correct status codes
- ✅ Test response format matches standardized schema
- ✅ Test audit events are logged
- ✅ Test logging works (check log files)

### 6.3 Production Readiness Checklist

**Security:**
- ✅ HTTPS enforced (HSTS headers)
- ✅ CORS configured (allowlist only)
- ✅ CSRF on HTML routes only
- ✅ Rate limiting enabled
- ✅ JWT keys secure (not in code)
- ✅ Secrets not in logs
- ✅ Prepared statements only
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

## 7. Code Conventions

### 7.1 Naming Conventions

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

### 7.2 Type Declarations

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

### 7.3 Code Organization

**Controllers:**
- One controller per domain
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
- One repository per entity
- Methods are CRUD-focused
- All SQL uses prepared statements
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs (never expose BINARY(16))

**Middleware:**
- One middleware per concern
- Middleware should be stateless
- Middleware should be composable
- Middleware should fail fast (throw exceptions, don't continue on error)

---

## 8. File Organization

### 8.1 Source Code Organization

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

### 8.2 Configuration Organization

**Route Files:**
- `config/routes/public_api.php` - Public API routes
- `config/routes/console_html.php` - Console HTML routes
- `config/routes/console_json.php` - Console JSON routes
- `config/routes/gateway_json.php` - Gateway JSON routes
- `config/routes/gateway_html.php` - Gateway HTML routes

**Config Files:**
- `config/container.php` - DI container configuration
- `config/routes.php` - Route group registration
- `config/validation.php` - Validation schemas

### 8.3 Import Organization

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

---

## 9. Dependency Management

### 9.1 Composer Dependencies

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

### 9.2 Dependency Injection

**Container Configuration:**
- File: `config/container.php`
- Framework: PHP-DI
- Pattern: Autowiring for most classes, singletons for expensive resources

**Singletons:**
- `JwtService` (stateless, expensive setup)
- Loggers (multiple channels)
- Rate limiter factories
- Guzzle client factory

**Autowiring:**
- Controllers (autowired with services)
- Services (autowired with repositories)
- Repositories (autowired with PDO)
- Middleware (autowired with dependencies)

---

## 10. Documentation Structure

See [/TOC.md](../../TOC.md) for the master index, [/SSOT.md](../../SSOT.md) for the SSOT hub, and [table-of-contents.md](../table-of-contents.md) for the full catalog. Key groupings:

### 10.1 Canon Documentation

Core specifications: [canon-ssot.md](canon-ssot.md) and individual canon documents in `01-getting-started/`, `03-core-concepts/`, `04-architecture/`, `05-authentication-authorization/`, `06-api-reference/`, `07-data-model/`, `08-implementation/`, `09-operations/`. See [toc-canon.md](toc-canon.md).

### 10.2 Reference Documentation

Matrices, configuration, and reference materials in `03-core-concepts/`, `04-architecture/`, `05-authentication-authorization/`, `06-api-reference/`, `08-implementation/`, `10-reference/`. See [toc-appendix.md](toc-appendix.md).

### 10.3 Development Documentation

Codebase and development guidance: [development-ssot.md](development-ssot.md) (this document), plus [codebase-inventory.md](../11-development/codebase-inventory.md), [component-breakdown.md](../11-development/component-breakdown.md), [installation-guide.md](../02-installation/installation-guide.md), [elevator-pitches.md](../01-getting-started/elevator-pitches.md). See [toc-dev.md](toc-dev.md).

---

## 11. Production Readiness

### 11.1 Security Checklist

- ✅ HTTPS enforced (HSTS headers)
- ✅ CORS configured (allowlist only)
- ✅ CSRF on HTML routes only
- ✅ Rate limiting enabled
- ✅ JWT keys secure (not in code)
- ✅ Secrets not in logs
- ✅ Prepared statements only
- ✅ Input validation on all endpoints
- ✅ Generic auth error messages
- ✅ Token typing enforced
- ✅ Refresh token single-use rotation
- ✅ Permission envelope rule enforced
- ✅ Use Key restrictions enforced

### 11.2 Performance Checklist

- ✅ Database indexes created
- ✅ Rate limiting configured
- ✅ Logging not excessive
- ✅ Transactions used appropriately
- ✅ No N+1 queries
- ✅ Connection pooling (if applicable)
- ✅ Caching strategy (if applicable)

### 11.3 Observability Checklist

- ✅ Structured JSON logging
- ✅ Audit events for state changes
- ✅ Request correlation IDs
- ✅ Error tracking
- ✅ Health check endpoint
- ✅ Metrics collection (if applicable)
- ✅ Distributed tracing (if applicable)

### 11.4 Documentation Checklist

- ✅ API documentation updated
- ✅ SSOT documents current
- ✅ Installation guide complete
- ✅ Troubleshooting guide available
- ✅ Code comments for complex logic
- ✅ README updated

---

## 12. Troubleshooting

### 12.1 Common Issues

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

**Bootstrap Failures:**
- Check all required environment variables are set
- Check JWT key files exist and are readable
- Check database connection works
- Check log files for detailed error messages

### 12.2 Debugging Tips

**Logging:**
- Check log files in `LOG_PATH` directory
- Use structured JSON logs (parse with `jq` for readability)
- Check appropriate channel (`api`, `auth`, `security`, `db`)
- Never log secrets (passwords, ApiKey secrets, refresh tokens)

**Database:**
- Use `php tools/db/verify_schema.php` to verify schema
- Check migration order
- Verify indexes exist
- Check for foreign key constraints

**JWT:**
- Decode JWT at jwt.io to inspect claims
- Verify JWKS endpoint returns correct public key
- Check token expiration (`exp` claim)
- Verify token type (`typ` claim)

**Rate Limiting:**
- Check rate limit configuration in `.env`
- Verify rate limit backing store (memory vs database)
- Check rate limit keying strategy (IP vs principal)
- Review `security` log channel for rate limit hits

---

**End of Development SSOT Document**

This document consolidates all development practices, codebase structure, installation procedures, and development workflows for CRE8.pw. For detailed specifications, refer to the canon documentation ([toc-canon.md](toc-canon.md)) and reference documentation ([toc-appendix.md](toc-appendix.md)).
