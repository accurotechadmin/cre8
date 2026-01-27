# Application Codebase Inventory

This document provides a complete inventory of all files in the application codebase, organized by directory structure with concise descriptions.

## Directory Structure

```
./
├── composer.json
├── TOC.md
├── SSOT.md
├── public/
│   ├── css/
│   │   └── styles.css
│   └── index.php
├── src/
│   ├── bootstrap.php
│   ├── Controllers/
│   │   ├── BaseController.php
│   │   ├── HealthController.php
│   │   ├── JwksController.php
│   │   ├── OwnerController.php
│   │   ├── Console/
│   │   │   ├── ConsoleController.php
│   │   │   ├── GroupController.php
│   │   │   ├── KeychainController.php
│   │   │   ├── KeyController.php
│   │   │   ├── PostController.php
│   │   │   └── RouteCatalogController.php
│   │   └── Gateway/
│   │       ├── CommentController.php
│   │       ├── FeedController.php
│   │       ├── GatewayController.php
│   │       ├── GroupController.php
│   │       ├── KeychainController.php
│   │       ├── KeyController.php
│   │       ├── PostController.php
│   │       └── RouteCatalogController.php
│   ├── Exceptions/
│   │   ├── ForbiddenException.php
│   │   └── NotFoundException.php
│   ├── Middleware/
│   │   ├── CorsMiddleware.php
│   │   ├── CspMiddleware.php
│   │   ├── CsrfExposeMiddleware.php
│   │   ├── ErrorHandlingMiddleware.php
│   │   ├── HttpsMiddleware.php
│   │   ├── JwtKeyMiddleware.php
│   │   ├── JwtOwnerMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── RequestLoggingMiddleware.php
│   │   ├── RouteParameterValidatorMiddleware.php
│   │   ├── UseKeyLimitMiddleware.php
│   │   └── ValidationMiddleware.php
│   ├── Repositories/
│   │   ├── AuditEventRepository.php
│   │   ├── BaseRepository.php
│   │   ├── CommentRepository.php
│   │   ├── GroupMemberRepository.php
│   │   ├── GroupRepository.php
│   │   ├── KeychainMemberRepository.php
│   │   ├── KeychainRepository.php
│   │   ├── KeyDeviceRepository.php
│   │   ├── KeyPublicIdRepository.php
│   │   ├── KeyRepository.php
│   │   ├── OwnerRepository.php
│   │   ├── PostAccessRepository.php
│   │   ├── PostRepository.php
│   │   └── RefreshTokenRepository.php
│   ├── Security/
│   │   ├── JwtService.php
│   │   ├── PermissionCatalog.php
│   │   └── PostAccessBitmask.php
│   ├── Services/
│   │   ├── AuditService.php
│   │   ├── AuthService.php
│   │   ├── BaseService.php
│   │   ├── CommentService.php
│   │   ├── FeedService.php
│   │   ├── GroupService.php
│   │   ├── KeychainService.php
│   │   ├── KeyService.php
│   │   ├── LoggingService.php
│   │   └── PostService.php
│   └── Utilities/
│       ├── BootstrapValidator.php
│       ├── ErrorFactory.php
│       ├── Ids.php
│       ├── ResponseFactory.php
│       ├── SchemaContractVerifier.php
│       └── SensitiveDataSanitizer.php
├── config/
│   ├── container.php
│   ├── routes.php
│   ├── validation.php
│   └── routes/
│       ├── console_html.php
│       ├── console_json.php
│       ├── gateway_html.php
│       ├── gateway_json.php
│       └── public_api.php
├── templates/
│   ├── _permission_helpers.php
│   ├── dashboard.php
│   ├── groups_list.php
│   ├── keychains_list.php
│   ├── keys_list.php
│   ├── landing.php
│   ├── login.php
│   ├── posts_list.php
│   ├── register.php
│   └── gateway/
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
├── tools/
│   ├── contract/
│   │   ├── README.md
│   │   ├── test_audience_segregation.php
│   │   ├── test_doc_ssot_alignment.php
│   │   └── test_id_format_compliance.php
│   └── db/
│       ├── migrate.php
│       └── verify_schema.php
└── UI/
    ├── 404.html
    ├── app.js
    ├── DOWNLOAD.html
    ├── icons.js
    ├── index.html
    ├── README.md
    └── styles.css
```

---

## File Inventory

### Root Files

#### `composer.json`
PHP dependency management file. Defines project metadata, PHP version requirements (^8.3), and all third-party dependencies including Slim framework, JWT libraries, validation, logging, and caching components.

#### `TOC.md`
Master documentation index at the repository root. Points to the full documentation catalog and SSOT entry points.

#### `SSOT.md`
Master SSOT hub at the repository root. Links to the canon, appendix, and development SSOT documents.

---

### `public/` - Public Web Root

#### `public/index.php`
Application entry point for all HTTP requests. Loads Composer autoloader, bootstraps the Slim application, and runs the request/response cycle.

#### `public/css/styles.css`
Stylesheet for public-facing HTML pages and UI components.

---

### `src/` - Application Source Code

#### `src/bootstrap.php`
Application bootstrap file. Initializes Slim application, configures dependency injection container, loads environment variables, validates bootstrap requirements, registers route groups, and sets up error handling middleware.

---

### `src/Controllers/` - HTTP Adapter Layer

#### `src/Controllers/BaseController.php`
Abstract base class for all controllers. Enforces architectural boundaries: controllers are thin HTTP adapters that extract parameters, call services, and shape responses. Provides standardized response methods (single, collection, error).

#### `src/Controllers/HealthController.php`
Handles health check endpoint (`/health`). Returns application status and basic system information for monitoring.

#### `src/Controllers/JwksController.php`
Serves JSON Web Key Set (JWKS) endpoint (`/.well-known/jwks.json`). Provides public keys for JWT verification.

#### `src/Controllers/OwnerController.php`
Handles Owner registration and login endpoints (`POST /console/owners`, `POST /console/login`). Manages authentication flow for owners.

#### `src/Controllers/Console/ConsoleController.php`
Renders HTML pages for Console UI (Owner-facing interface). Handles landing, registration, login, dashboard, and management pages with CSRF protection.

#### `src/Controllers/Console/GroupController.php`
Handles group management endpoints for Console JSON surface (`/console/groups/*`). Owner-scoped group CRUD operations.

#### `src/Controllers/Console/KeychainController.php`
Handles keychain management endpoints for Console JSON surface (`/console/keychains/*`). Owner-scoped keychain CRUD and membership operations.

#### `src/Controllers/Console/KeyController.php`
Handles key management endpoints for Console JSON surface (`/console/keys/*`). Owner-scoped key CRUD, minting, rotation, and lifecycle management.

#### `src/Controllers/Console/PostController.php`
Handles post management endpoints for Console JSON surface (`/console/posts/*`). Owner-scoped post administration and access control.

#### `src/Controllers/Console/RouteCatalogController.php`
Serves route catalog endpoint for Console surface (`/console/routes`). Returns available routes and their documentation for Console API.

#### `src/Controllers/Gateway/GatewayController.php`
Renders HTML pages for Gateway UI (Key-protected example pages). Provides example client pages that demonstrate Gateway API usage.

#### `src/Controllers/Gateway/CommentController.php`
Handles comment endpoints for Gateway JSON surface (`/api/comments/*`). Key-protected comment creation and management.

#### `src/Controllers/Gateway/FeedController.php`
Handles feed endpoints for Gateway JSON surface (`/api/feed/*`). Key-protected feed queries (author-scoped and use-scoped feeds).

#### `src/Controllers/Gateway/GroupController.php`
Handles group endpoints for Gateway JSON surface (`/api/groups/*`). Key-protected group read operations.

#### `src/Controllers/Gateway/KeychainController.php`
Handles keychain endpoints for Gateway JSON surface (`/api/keychains/*`). Key-protected keychain read operations.

#### `src/Controllers/Gateway/KeyController.php`
Handles key endpoints for Gateway JSON surface (`/api/keys/*`). Key-protected key operations including minting secondary keys.

#### `src/Controllers/Gateway/PostController.php`
Handles post endpoints for Gateway JSON surface (`/api/posts/*`). Key-protected post creation, reading, and access management.

#### `src/Controllers/Gateway/RouteCatalogController.php`
Serves route catalog endpoint for Gateway surface (`/api/routes`). Returns available routes and their documentation for Gateway API.

---

### `src/Exceptions/` - Custom Exceptions

#### `src/Exceptions/ForbiddenException.php`
Exception thrown when authorization fails. Used by services to signal permission denied errors.

#### `src/Exceptions/NotFoundException.php`
Exception thrown when a requested resource is not found. Used by services to signal 404 errors.

---

### `src/Middleware/` - Cross-Cutting Concerns

#### `src/Middleware/CorsMiddleware.php`
Applies Cross-Origin Resource Sharing (CORS) headers based on environment configuration. Handles preflight OPTIONS requests efficiently.

#### `src/Middleware/CspMiddleware.php`
Applies Content Security Policy (CSP) headers for HTML routes. Protects against XSS attacks by restricting resource loading.

#### `src/Middleware/CsrfExposeMiddleware.php`
Exposes CSRF token to HTML pages via meta tag or JavaScript variable. Enables client-side forms to include CSRF tokens.

#### `src/Middleware/ErrorHandlingMiddleware.php`
Global error handling middleware. Catches all exceptions, normalizes error responses, logs errors, and returns standardized error envelopes.

#### `src/Middleware/HttpsMiddleware.php`
Enforces HTTPS connections and sets HSTS headers. Redirects HTTP to HTTPS in production environments.

#### `src/Middleware/JwtKeyMiddleware.php`
Verifies Key JWT tokens and enforces `typ=key` claim. Extracts `key_id` and permissions from JWT, attaches to request attributes. Required for all Gateway routes.

#### `src/Middleware/JwtOwnerMiddleware.php`
Verifies Owner JWT tokens and enforces `typ=owner` claim. Extracts `owner_id` and permissions from JWT, attaches to request attributes. Required for all Console JSON routes.

#### `src/Middleware/RateLimitMiddleware.php`
Implements rate limiting using Symfony RateLimiter. Configurable rate limits per route pattern with in-memory or database backing.

#### `src/Middleware/RequestLoggingMiddleware.php`
Logs incoming HTTP requests for observability. Records request method, path, headers, and timing information.

#### `src/Middleware/RouteParameterValidatorMiddleware.php`
Validates route parameters (path variables) against expected formats. Ensures IDs match hex32 format and other constraints.

#### `src/Middleware/UseKeyLimitMiddleware.php`
Enforces use count limits for Use Keys. Tracks and limits API calls per key based on `use_count_limit` and `use_count_current` fields.

#### `src/Middleware/ValidationMiddleware.php`
Validates request body, query parameters, and headers per centralized schemas. Selects validator by "METHOD /pattern" from `config/validation.php`.

---

### `src/Repositories/` - Data Access Layer

#### `src/Repositories/BaseRepository.php`
Abstract base class for all repositories. Enforces architectural boundaries: repositories handle data access using PDO prepared statements and binary ID conversion (BINARY(16) ↔ hex32). Must not contain business logic or authorization checks.

#### `src/Repositories/AuditEventRepository.php`
Data access for `audit_events` table. Handles creation and querying of audit log entries for security and compliance.

#### `src/Repositories/CommentRepository.php`
Data access for `comments` table. Handles comment creation, retrieval, and queries related to posts.

#### `src/Repositories/GroupMemberRepository.php`
Data access for `group_members` table. Manages many-to-many relationship between groups and keys. Handles membership operations.

#### `src/Repositories/GroupRepository.php`
Data access for `groups` table. Handles group CRUD operations and owner-scoped queries.

#### `src/Repositories/KeychainMemberRepository.php`
Data access for `keychain_members` table. Manages many-to-many relationship between keychains and keys. Handles membership operations.

#### `src/Repositories/KeychainRepository.php`
Data access for `keychains` table. Handles keychain CRUD operations and owner-scoped queries.

#### `src/Repositories/KeyDeviceRepository.php`
Data access for `key_devices` table (optional). Tracks device associations for keys, enabling device-based rate limiting and tracking.

#### `src/Repositories/KeyPublicIdRepository.php`
Data access for `key_public_ids` table. Manages mapping between short public IDs and full key IDs. Used for key lookup by public identifier.

#### `src/Repositories/KeyRepository.php`
Data access for `keys` table. Handles key CRUD operations, lineage queries, rotation tracking, and state management. Core repository for key lifecycle.

#### `src/Repositories/OwnerRepository.php`
Data access for `owners` table. Handles owner CRUD operations, password hashing verification, and authentication queries.

#### `src/Repositories/PostAccessRepository.php`
Data access for `post_access` table. Manages post access control entries (ACEs) including bitmask permissions, group grants, and keychain grants.

#### `src/Repositories/PostRepository.php`
Data access for `posts` table. Handles post CRUD operations, access resolution queries, author-scoped queries, and complex feed queries with access control filtering.

#### `src/Repositories/RefreshTokenRepository.php`
Data access for `refresh_tokens` table. Manages refresh token storage, validation, and cleanup for JWT refresh flow.

---

### `src/Security/` - Security Components

#### `src/Security/JwtService.php`
JWT signing and verification service using RS256 algorithm. Supports Owner and Key token types with proper claims structure. Handles token generation, validation, and refresh token management.

#### `src/Security/PermissionCatalog.php`
Central catalog of all permission strings and role definitions. Defines Owner permissions (Console-scoped) and Key permissions (Gateway-scoped). Provides permission validation utilities.

#### `src/Security/PostAccessBitmask.php`
Utilities for managing post access bitmasks. Handles permission bit operations for read, comment, and share permissions. Used in post access control system.

---

### `src/Services/` - Business Logic Layer

#### `src/Services/BaseService.php`
Abstract base class for all services. Enforces architectural boundaries: services contain business logic, authorization checks, and orchestration. Must not access HTTP concerns or write direct SQL queries.

#### `src/Services/AuditService.php`
Service for creating audit log entries. Handles audit event creation with proper categorization and metadata. Used throughout application for security auditing.

#### `src/Services/AuthService.php`
Authentication service. Handles Owner registration, password hashing/verification, login, token generation (access and refresh), and key minting for authentication flows.

#### `src/Services/CommentService.php`
Business logic for comment operations. Handles comment creation with permission checks, post access validation, and comment retrieval.

#### `src/Services/FeedService.php`
Business logic for feed queries. Handles author-scoped feeds (posts by author) and use-scoped feeds (posts accessible to a key). Implements access control filtering.

#### `src/Services/GroupService.php`
Business logic for group management. Handles group CRUD operations, membership management, and permission enforcement for Console JSON surface.

#### `src/Services/KeychainService.php`
Business logic for keychain management. Handles keychain CRUD operations, membership management, and permission enforcement for Console JSON surface.

#### `src/Services/KeyService.php`
Business logic for key operations. Handles key minting with permission envelope validation, rotation, state management (activate/deactivate), and lineage enforcement. Enforces child ⊆ parent permission rule.

#### `src/Services/LoggingService.php`
Centralized logging service. Provides structured logging with PSR-3 LoggerInterface. Handles log formatting, sensitive data sanitization, and log level management.

#### `src/Services/PostService.php`
Business logic for post operations. Handles post creation, reading with access control, access grant management (bitmask, group, keychain), and post administration. Enforces post access bitmasks.

---

### `src/Utilities/` - Utility Classes

#### `src/Utilities/BootstrapValidator.php`
Validates bootstrap requirements and environment configuration. Performs fail-fast checks for database connectivity, JWT keys, and required environment variables.

#### `src/Utilities/ErrorFactory.php`
Factory for creating standardized error responses. Provides consistent error envelope structure across the application.

#### `src/Utilities/Ids.php`
ID encoding utilities. Provides conversion functions between BINARY(16) (internal database format) and hex32 (external API format). Used throughout application for ID format conversion.

#### `src/Utilities/ResponseFactory.php`
Factory for creating standardized success responses. Provides consistent response envelope structure (single object, collection, pagination) across the application.

#### `src/Utilities/SchemaContractVerifier.php`
Verifies API response schemas match documented contracts. Used for testing and validation to ensure API consistency.

#### `src/Utilities/SensitiveDataSanitizer.php`
Sanitizes sensitive data from logs and error messages. Prevents exposure of passwords, tokens, and other sensitive information in logs.

---

### `config/` - Configuration Files

#### `config/container.php`
Dependency injection container configuration. Defines PHP-DI bindings for all application dependencies including PDO, JWT service, rate limiters, loggers, and all repositories/services/controllers.

#### `config/routes.php`
Main route registration file. Registers route groups for each surface: Public API, Console HTML, Console JSON, Gateway JSON, and Gateway HTML.

#### `config/validation.php`
Centralized validation schema definitions. Maps "METHOD /pattern" selectors to Respect\Validation validators for request validation.

#### `config/routes/public_api.php`
Public API route definitions (no authentication). Routes: `/health`, `/.well-known/jwks.json`, `/api/auth/*`, `/console/owners`, `/console/login`.

#### `config/routes/console_html.php`
Console HTML route definitions (Owner-facing pages with CSRF). Routes: `/`, `/console/register`, `/console/login`, `/console/dashboard`, `/console/keys`, `/console/groups`, `/console/keychains`, `/console/posts`.

#### `config/routes/console_json.php`
Console JSON route definitions (Owner-protected endpoints). Routes: `/console/keys/*`, `/console/groups/*`, `/console/keychains/*`, `/console/posts/*`, `/console/routes`.

#### `config/routes/gateway_json.php`
Gateway JSON route definitions (Key-protected endpoints). Routes: `/api/posts/*`, `/api/comments/*`, `/api/keys/*`, `/api/feed/*`, `/api/groups/*`, `/api/keychains/*`, `/api/routes`.

#### `config/routes/gateway_html.php`
Gateway HTML route definitions (Gateway API client example pages with CSRF). Routes: `/gateway/*` (example pages that demonstrate Gateway API usage).

---

### `templates/` - HTML Templates

#### `templates/_permission_helpers.php`
Permission helper functions for templates. Provides utility functions for checking permissions in HTML templates (permissions-aware UI).

#### `templates/dashboard.php`
Owner dashboard template. Main console page showing overview of keys, groups, keychains, and posts.

#### `templates/groups_list.php`
Groups list template for Console UI. Displays owner's groups with management options.

#### `templates/keychains_list.php`
Keychains list template for Console UI. Displays owner's keychains with management options.

#### `templates/keys_list.php`
Keys list template for Console UI. Displays owner's keys with lifecycle management options.

#### `templates/landing.php`
Landing page template. Public-facing homepage with information about the platform.

#### `templates/login.php`
Login page template. Owner authentication form.

#### `templates/posts_list.php`
Posts list template for Console UI. Displays owner's posts with administration options.

#### `templates/register.php`
Registration page template. Owner registration form.

#### `templates/gateway/auth_exchange.php`
Gateway authentication exchange example page. Demonstrates key exchange flow for obtaining access tokens.

#### `templates/gateway/auth_refresh.php`
Gateway token refresh example page. Demonstrates refresh token flow.

#### `templates/gateway/comments_list.php`
Gateway comments list example page. Demonstrates comment API usage.

#### `templates/gateway/feed_author.php`
Gateway author feed example page. Demonstrates author-scoped feed API usage.

#### `templates/gateway/feed_use.php`
Gateway use-scoped feed example page. Demonstrates use-scoped feed API usage.

#### `templates/gateway/group_detail.php`
Gateway group detail example page. Demonstrates group API usage.

#### `templates/gateway/groups_list.php`
Gateway groups list example page. Demonstrates groups API usage.

#### `templates/gateway/keychains_list.php`
Gateway keychains list example page. Demonstrates keychains API usage.

#### `templates/gateway/keys_mint_secondary.php`
Gateway secondary key minting example page. Demonstrates key minting API usage.

#### `templates/gateway/keys_mint_use.php`
Gateway use key minting example page. Demonstrates use key minting API usage.

#### `templates/gateway/post_detail.php`
Gateway post detail example page. Demonstrates post reading API usage.

#### `templates/gateway/post_grant_access.php`
Gateway post access grant example page. Demonstrates post access management API usage.

#### `templates/gateway/posts_list.php`
Gateway posts list example page. Demonstrates posts API usage.

#### `templates/gateway/share_workflow.php`
Gateway sharing workflow example page. Demonstrates complete post sharing workflow with access control.

---

### `tools/` - Development and Maintenance Tools

#### `tools/contract/README.md`
Documentation for contract testing tools. Explains purpose and usage of contract verification scripts.

#### `tools/contract/test_audience_segregation.php`
Contract test for audience segregation. Verifies that Console and Gateway surfaces are properly isolated.

#### `tools/contract/test_doc_ssot_alignment.php`
Contract test for documentation single-source-of-truth alignment. Verifies that code matches documented contracts.

#### `tools/contract/test_id_format_compliance.php`
Contract test for ID format compliance. Verifies that all IDs follow hex32 format requirements.

#### `tools/db/migrate.php`
Database migration runner. Executes migration files in order to set up or update database schema.

#### `tools/db/verify_schema.php`
Database schema verification tool. Validates that database schema matches expected structure.

---

### `UI/` - Frontend Assets

#### `UI/404.html`
404 error page template. Custom error page for not found routes.

#### `UI/app.js`
Main JavaScript application file. Client-side logic for UI interactions and API calls.

#### `UI/DOWNLOAD.html`
Download page template. Provides download links and instructions.

#### `UI/icons.js`
Icon management JavaScript. Handles icon loading and rendering.

#### `UI/index.html`
Main UI entry point. Primary HTML page for the frontend application.

#### `UI/README.md`
Documentation for UI components. Explains UI structure and usage.

#### `UI/styles.css`
Stylesheet for UI components. CSS styling for frontend application.

---

## Summary Statistics

- **Total PHP Files**: 66
- **Total Template Files**: 23
- **Total Configuration Files**: 8
- **Total Tool Files**: 5
- **Total UI Files**: 7
- **Total Files**: 109

### By Category

- **Controllers**: 19 files (Base + 18 specific controllers)
- **Services**: 10 files (Base + 9 domain services)
- **Repositories**: 14 files (Base + 13 data repositories)
- **Middleware**: 12 files
- **Security**: 3 files
- **Utilities**: 6 files
- **Exceptions**: 2 files
- **Templates**: 23 files
- **Configuration**: 8 files
- **Tools**: 5 files
- **UI**: 7 files

---

*Last Updated: 2026-01-23*
