# CRE8.pw LLM Coding Session Primer - Part 2: Codebase Deep Dive

This is the concluding primer prompt. You should have already read the foundational documents from Part 1. Now you will thoroughly explore the codebase and form a complete mental model of CRE8.pw.

## Codebase Structure

The CRE8.pw codebase follows a strict layered architecture pattern:

```
src/
├── Controllers/      # HTTP adapters (extract params, call service, shape response)
│   ├── Console/     # Owner-facing controllers
│   └── Gateway/     # Key-facing controllers
├── Services/        # Business logic (permissions, transactions, audits)
├── Repositories/    # Data access (PDO prepared statements, hex32 ↔ BINARY(16))
├── Middleware/      # Cross-cutting concerns (HTTPS, CORS, JWT, validation, errors)
├── Security/        # JWT service, permission catalog, bitmask utilities
├── Utilities/       # Helper functions (IDs, ResponseFactory, ErrorFactory)
└── Exceptions/      # Custom exceptions (NotFoundException, ForbiddenException, etc.)

config/
├── container.php    # PHP-DI container wiring
├── routes.php       # Route group registration
├── validation.php   # Respect\Validation schemas
└── routes/          # Route group definitions (console_html, console_json, gateway_json, etc.)
```

## Reading the Codebase

Read these files in order to understand implementation patterns:

### 1. Entry Points and Bootstrap
- **`public/index.php`** - Application entry point
- **`src/bootstrap.php`** - Application initialization (DI container, routes, middleware)
- **`config/container.php`** - Dependency injection configuration
- **`config/routes.php`** - Route group registration

### 2. Core Patterns (Read Examples)
- **`src/Controllers/BaseController.php`** - Base controller with response helpers
- **`src/Controllers/Console/KeyController.php`** - Example Console controller
- **`src/Controllers/Gateway/PostController.php`** - Example Gateway controller
- **`src/Services/KeyService.php`** - Example service (business logic, permissions, audits)
- **`src/Repositories/KeyRepository.php`** - Example repository (PDO, ID conversion)
- **`src/Middleware/JwtOwnerMiddleware.php`** - Example middleware (JWT verification)

### 3. Critical Utilities
- **`src/Utilities/Ids.php`** - ID conversion functions (hex32 ↔ BINARY(16))
- **`src/Utilities/ResponseFactory.php`** - Standardized response creation
- **`src/Utilities/ErrorFactory.php`** - Standardized error responses
- **`src/Security/JwtService.php`** - JWT signing and verification
- **`src/Security/PermissionCatalog.php`** - Permission string definitions
- **`src/Security/PostAccessBitmask.php`** - Post access bitmask constants

### 4. Configuration Patterns
- **`config/validation.php`** - See how validation schemas are defined (keyed by "METHOD /pattern")
- **`config/routes/console_json.php`** - See Console JSON route patterns
- **`config/routes/gateway_json.php`** - See Gateway JSON route patterns

### 5. Database Patterns
- **`migrations/001_create_owners.php`** - See migration structure
- **`migrations/002_create_keys.php`** - See key table structure with lineage fields

## Forming Your Mental Model

As you read, build understanding of:

### 1. Request Flow
Understand how requests flow through the system:
```
HTTP Request → public/index.php → bootstrap.php → Middleware Pipeline → Controller → Service → Repository → Database
```

Each surface has a distinct middleware pipeline:
- **Public API:** HTTPS → CORS → RateLimit (IP) → Validation → Route
- **Console JSON:** HTTPS → CORS → RateLimit (owner_id) → JwtOwnerMiddleware → Validation → Route
- **Gateway JSON:** HTTPS → CORS → RateLimit (key_id) → JwtKeyMiddleware → Validation → Route
- **Console HTML:** HTTPS → CORS → RateLimit (IP) → CSRF Guard → Route

### 2. Layer Responsibilities

**Controllers (HTTP Adapters):**
- Extract params (route, query, body, headers)
- Call exactly ONE service method
- Shape response using ResponseFactory
- **FORBIDDEN:** Business logic, database access, permission checks

**Services (Business Logic):**
- Enforce global permissions (check JWT permissions array)
- Enforce post bitmasks (for post-scoped actions)
- Enforce invariants (key type restrictions, envelope rule, immutability)
- Orchestrate repositories (use transactions for multi-step)
- Emit audit events
- **FORBIDDEN:** HTTP concerns, direct SQL queries

**Repositories (Data Access):**
- PDO prepared statements exclusively
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs
- **FORBIDDEN:** Business logic, permission checks, HTTP concerns

### 3. Extensibility Patterns

To add new features, follow existing patterns:

**Adding a New Endpoint:**
1. Add route to appropriate file in `config/routes/` (console_json.php or gateway_json.php)
2. Add validation rules to `config/validation.php` (keyed by "METHOD /pattern")
3. Create/update controller method in `src/Controllers/Console/` or `src/Controllers/Gateway/`
4. Create/update service method in `src/Services/`
5. Create/update repository methods in `src/Repositories/` if needed
6. Follow the pattern: Controller → Service → Repository

**Example Pattern to Follow:**
- Look at `PostController::create()` → `PostService::createPost()` → `PostRepository::create()`
- Notice how permissions are checked in Service layer
- Notice how IDs are converted in Repository layer
- Notice how audit events are emitted in Service layer

### 4. Critical Integration Points

Understand these key integration points:

- **Authentication:** `JwtOwnerMiddleware` and `JwtKeyMiddleware` verify tokens and attach principal context
- **Authorization:** Services check `permissions` array from JWT and `permission_mask` from post_access table
- **ID Conversion:** Repositories use `Ids::hex32ToBinary()` and `Ids::binaryToHex32()` at database boundary
- **Response Formatting:** Controllers use `ResponseFactory` helpers for standardized responses
- **Error Handling:** `ErrorHandlingMiddleware` catches exceptions and normalizes to standard error format

## Documentation Reference Strategy

When you need information:

1. **Quick Lookups:** Use SSOT documents
   - [canon-ssot.md](../12-comprehensive-reference/canon-ssot.md) — Canon specifications
   - [appendix-ssot.md](../12-comprehensive-reference/appendix-ssot.md) — Reference materials
   - [development-ssot.md](../12-comprehensive-reference/development-ssot.md) — Development practices

2. **Detailed Specifications:** Use specific documents
   - Architecture: [architecture-overview.md](../04-architecture/architecture-overview.md)
   - Authorization: [authorization.md](../05-authentication-authorization/authorization.md)
   - API Reference: [api-reference.md](../06-api-reference/api-reference.md)
   - Implementation: [implementation-guide.md](../08-implementation/implementation-guide.md)

3. **Reference Materials:** Use reference documents
   - Permissions: [permissions.md](../05-authentication-authorization/permissions.md)
   - Routes: [routes-inventory.md](../06-api-reference/routes-inventory.md)
   - Environment: [environment-configuration.md](../10-reference/environment-configuration.md)

4. **Codebase Navigation:** Use development documents
   - File inventory: [codebase-inventory.md](../11-development/codebase-inventory.md)
   - Component details: [component-breakdown.md](../11-development/component-breakdown.md)

5. **Master Indexes:** [/TOC.md](../../TOC.md) (master entry point), [/SSOT.md](../../SSOT.md) (SSOT hub), and [table-of-contents.md](../table-of-contents.md) (full catalog)

## Final Mental Model Checklist

Before proceeding with coding tasks, ensure you understand:

- [ ] Dual-surface architecture (Console vs Gateway)
- [ ] Layered architecture (Controller → Service → Repository)
- [ ] Two-layer authorization (permissions + bitmasks)
- [ ] Key types and their restrictions
- [ ] ID formats (BINARY(16) internal, hex32 external, apub_ for key public IDs)
- [ ] Request flow through middleware pipelines
- [ ] How to add new endpoints (route → validation → controller → service → repository)
- [ ] Critical constraints (CSRF scope, Use Key restrictions, envelope rule, immutability)
- [ ] Where to find patterns for extending the platform
- [ ] Where to look up specifications when needed

## Your Task Now

1. **Read the codebase files** listed above, focusing on understanding patterns
2. **Study example implementations** (KeyController, PostController, KeyService, PostService, KeyRepository, PostRepository)
3. **Understand the request flow** by tracing a request from entry point through middleware to controller to service to repository
4. **Identify patterns** for adding new features (look for similarities across existing implementations)
5. **Form your mental model** of how all pieces fit together

## Ready to Code

Once you've completed this primer, you should be able to:
- Answer questions about CRE8.pw architecture and implementation
- Locate relevant documentation quickly
- Follow existing patterns when adding features
- Respect all critical constraints and conventions
- Navigate the codebase effectively

**You are now ready to work with CRE8.pw. When given coding tasks, always:**
1. Check relevant documentation first
2. Find similar existing patterns
3. Follow the established conventions
4. Respect all critical constraints
5. Use the SSOT documents for quick reference

**Begin reading the codebase now, starting with the entry points and bootstrap files.**
