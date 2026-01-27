# CRE8.pw LLM Coding Session Primer - Part 2: Codebase Deep Dive

This is the second primer prompt. You should have already read the foundational documents from Part 1. Now you will thoroughly explore the codebase and form a complete mental model of CRE8.pw, connecting the documentation you've read to the actual implementation.

## Codebase Structure

The CRE8.pw codebase follows a strict layered architecture pattern as defined in `04-architecture/architecture-overview.md`:

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

**Reference:** See `11-development/codebase-inventory.md` for complete file listing and `11-development/component-breakdown.md` for detailed component specifications.

## Reading the Codebase

Read these files in order to understand implementation patterns. Each section builds on the previous:

### 1. Entry Points and Bootstrap (Start Here)

- **`public/index.php`** - Application entry point (routes to bootstrap)
- **`src/bootstrap.php`** - Application initialization (DI container, routes, middleware pipeline)
- **`config/container.php`** - Dependency injection configuration (see `08-implementation/dependency-wiring.md`)
- **`config/routes.php`** - Route group registration (connects to route files)

**What to understand:** How the application starts, how dependencies are wired, and how routes are registered. This is the foundation of request handling.

### 2. Core Patterns (Read Examples - Pattern Recognition)

- **`src/Controllers/BaseController.php`** - Base controller with response helpers
- **`src/Controllers/Console/KeyController.php`** - Example Console controller (Owner-facing)
- **`src/Controllers/Gateway/PostController.php`** - Example Gateway controller (Key-facing)
- **`src/Services/KeyService.php`** - Example service (business logic, permissions, audits)
- **`src/Repositories/KeyRepository.php`** - Example repository (PDO, ID conversion)
- **`src/Middleware/JwtOwnerMiddleware.php`** - Example middleware (JWT verification for Owners)
- **`src/Middleware/JwtKeyMiddleware.php`** - Example middleware (JWT verification for Keys)

**What to understand:** How each layer operates, what it's responsible for, and what it's forbidden from doing. Notice the patterns - they repeat across all domains.

### 3. Critical Utilities (Foundation Tools)

- **`src/Utilities/Ids.php`** - ID conversion functions (hex32 ↔ BINARY(16)) - **CRITICAL**
- **`src/Utilities/ResponseFactory.php`** - Standardized response creation (`{ data: {} }` format)
- **`src/Utilities/ErrorFactory.php`** - Standardized error responses (`{ error: {} }` format)
- **`src/Security/JwtService.php`** - JWT signing and verification (RS256)
- **`src/Security/PermissionCatalog.php`** - Permission string definitions (see `05-authentication-authorization/permissions.md`)
- **`src/Security/PostAccessBitmask.php`** - Post access bitmask constants (VIEW, COMMENT, MANAGE_ACCESS)

**What to understand:** These utilities are used throughout the codebase. Understanding them helps you understand how the system works.

### 4. Configuration Patterns (How Things Are Configured)

- **`config/validation.php`** - See how validation schemas are defined (keyed by "METHOD /pattern")
- **`config/routes/console_json.php`** - See Console JSON route patterns (Owner-authenticated)
- **`config/routes/gateway_json.php`** - See Gateway JSON route patterns (Key-authenticated)
- **`config/routes/console_html.php`** - See Console HTML route patterns (CSRF-protected)

**What to understand:** How routes are defined, how validation is mapped to routes, and how different surfaces have different route groups.

### 5. Database Patterns (Data Access)

- **`migrations/001_create_owners.php`** - See migration structure and Owner table
- **`migrations/002_create_keys.php`** - See key table structure with lineage fields
- **`migrations/003_create_posts.php`** - See post table structure
- **`migrations/004_create_post_access.php`** - See post access table (bitmasks)

**What to understand:** How the database schema maps to the data model in `07-data-model/database-schema.md`. Notice the lineage fields and how they're structured.

## Forming Your Mental Model

As you read, build understanding of these interconnected concepts:

### 1. Request Flow (The Journey of a Request)

Understand how requests flow through the system:
```
HTTP Request → public/index.php → bootstrap.php → Middleware Pipeline → Controller → Service → Repository → Database
```

Each surface has a distinct middleware pipeline (see `04-architecture/architecture-overview.md`):

- **Public API:** HTTPS → CORS → RateLimit (IP) → Validation → Route
- **Console JSON:** HTTPS → CORS → RateLimit (owner_id) → JwtOwnerMiddleware → Validation → Route
- **Gateway JSON:** HTTPS → CORS → RateLimit (key_id) → JwtKeyMiddleware → Validation → Route
- **Console HTML:** HTTPS → CORS → RateLimit (IP) → CSRF Guard → Route

**Key insight:** The middleware pipeline is different for each surface, enforcing different security rules.

### 2. Layer Responsibilities (What Each Layer Does)

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

**Reference:** See `04-architecture/layering-rules.md` for detailed responsibility boundaries.

### 3. Extensibility Patterns (How to Add Features)

To add new features, follow existing patterns (see `08-implementation/implementation-guide.md`):

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

**Key insight:** Every feature follows the same pattern. Find a similar feature and copy its structure.

### 4. Critical Integration Points (How Pieces Connect)

Understand these key integration points:

- **Authentication:** `JwtOwnerMiddleware` and `JwtKeyMiddleware` verify tokens and attach principal context (see `05-authentication-authorization/authentication.md`)
- **Authorization:** Services check `permissions` array from JWT and `permission_mask` from post_access table (see `05-authentication-authorization/authorization.md`)
- **ID Conversion:** Repositories use `Ids::hex32ToBinary()` and `Ids::binaryToHex32()` at database boundary (see `10-reference/identifier-encoding.md`)
- **Response Formatting:** Controllers use `ResponseFactory` helpers for standardized responses (see `06-api-reference/response-schemas.md`)
- **Error Handling:** `ErrorHandlingMiddleware` catches exceptions and normalizes to standard error format

## Documentation-to-Code Mapping

As you read the codebase, connect it to the documentation:

| Documentation | Code Location | Purpose |
|---|---|---|
| `04-architecture/architecture-overview.md` | `src/bootstrap.php`, `config/routes.php` | Request pipeline, middleware ordering |
| `05-authentication-authorization/authentication.md` | `src/Security/JwtService.php`, `src/Middleware/Jwt*Middleware.php` | JWT handling, token verification |
| `05-authentication-authorization/authorization.md` | `src/Services/*Service.php` | Permission checks, bitmask enforcement |
| `07-data-model/database-schema.md` | `migrations/*.php`, `src/Repositories/*.php` | Database structure, data access |
| `10-reference/identifier-encoding.md` | `src/Utilities/Ids.php`, `src/Repositories/*.php` | ID conversion at boundaries |
| `06-api-reference/api-reference.md` | `config/routes/*.php`, `src/Controllers/*/*.php` | Endpoint definitions |
| `08-implementation/implementation-guide.md` | All code | Coding patterns, conventions |
| `11-development/component-breakdown.md` | All code | Detailed component specifications |

## Documentation Reference Strategy

When you need information during coding:

1. **Quick Lookups:** Use SSOT documents
   - `12-comprehensive-reference/canon-ssot.md` - Canon specifications
   - `12-comprehensive-reference/appendix-ssot.md` - Reference materials
   - `12-comprehensive-reference/development-ssot.md` - Development practices

2. **Detailed Specifications:** Use specific documents
   - Architecture: `04-architecture/architecture-overview.md`
   - Authorization: `05-authentication-authorization/authorization.md`
   - API Reference: `06-api-reference/api-reference.md`
   - Implementation: `08-implementation/implementation-guide.md`

3. **Reference Materials:** Use reference documents
   - Permissions: `05-authentication-authorization/permissions.md`
   - Routes: `06-api-reference/routes-inventory.md`
   - Environment: `10-reference/environment-configuration.md`
   - ID Encoding: `10-reference/identifier-encoding.md`

4. **Codebase Navigation:** Use development documents
   - File inventory: `11-development/codebase-inventory.md`
   - Component details: `11-development/component-breakdown.md`

5. **Master Indexes:** `/TOC.md` (master entry point), `/SSOT.md` (SSOT hub), and `docs/table-of-contents.md` (full catalog)

## Final Mental Model Checklist

Before proceeding with coding tasks, ensure you understand:

- [ ] Dual-surface architecture (Console vs Gateway) - see `04-architecture/architecture-overview.md`
- [ ] Layered architecture (Controller → Service → Repository) - see `04-architecture/layering-rules.md`
- [ ] Two-layer authorization (permissions + bitmasks) - see `05-authentication-authorization/authorization.md`
- [ ] Key types and their restrictions - see `05-authentication-authorization/key-capabilities.md`
- [ ] ID formats (BINARY(16) internal, hex32 external, apub_ for key public IDs) - see `10-reference/identifier-encoding.md`
- [ ] Request flow through middleware pipelines - see `04-architecture/architecture-overview.md`
- [ ] How to add new endpoints (route → validation → controller → service → repository) - see `08-implementation/implementation-guide.md`
- [ ] Critical constraints (CSRF scope, Use Key restrictions, envelope rule, immutability) - see Part 1 primer
- [ ] Where to find patterns for extending the platform - see `08-implementation/implementation-guide.md`
- [ ] Where to look up specifications when needed - see Documentation Reference Strategy above

## Your Task Now

1. **Read the codebase files** listed above, focusing on understanding patterns
2. **Study example implementations** (KeyController, PostController, KeyService, PostService, KeyRepository, PostRepository)
3. **Understand the request flow** by tracing a request from entry point through middleware to controller to service to repository
4. **Identify patterns** for adding new features (look for similarities across existing implementations)
5. **Form your mental model** of how all pieces fit together
6. **Connect code to documentation** - when you see something in code, find where it's documented

## Ready to Code

Once you've completed this primer, you should be able to:
- Answer questions about CRE8.pw architecture and implementation
- Locate relevant documentation quickly
- Follow existing patterns when adding features
- Respect all critical constraints and conventions
- Navigate the codebase effectively
- Connect documentation concepts to code implementation

**You are now ready to work with CRE8.pw. When given coding tasks, always:**
1. Check relevant documentation first (use the reference strategy above)
2. Find similar existing patterns in the codebase
3. Follow the established conventions (see `08-implementation/implementation-guide.md`)
4. Respect all critical constraints (see Part 1 primer)
5. Use the SSOT documents for quick reference

**Begin reading the codebase now, starting with the entry points and bootstrap files.**
