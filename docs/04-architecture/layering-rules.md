# CRE8.pw Architecture Layering Rules

**TICKET T0.1: App skeleton & directory structure**

This document defines the architectural boundaries and responsibility rules for CRE8.pw.

## Directory Structure

```
src/
├── Controllers/          # HTTP adapters
│   ├── Console/         # Console HTML + JSON (Owner-facing)
│   └── Gateway/         # Gateway JSON (Key-protected)
├── Services/            # Business logic
├── Repositories/        # Data access (PDO)
├── Middleware/          # PSR-15 middleware
├── Utilities/           # Helper utilities (Ids, Csrf, etc.)
├── Security/            # Security utilities (hashing, JWT)
└── Validation/          # Validation schemas

config/
├── container.php        # DI container wiring
├── routes.php          # Route group definitions
└── validation.php      # Validation rule mapping

public/
└── index.php           # Application entry point
```

## Layer Responsibilities

### Controllers (HTTP Adapters)

**Responsibilities:**
- Extract params (route, query, body, headers)
- Call exactly ONE service method
- Shape response using standardized envelopes
- Return PSR-7 response

**Forbidden:**
- ❌ Business logic
- ❌ Direct database access
- ❌ Multi-service orchestration
- ❌ Permission enforcement (services handle this)

### Services (Business Logic)

**Responsibilities:**
- Enforce global permissions (check JWT permissions)
- Enforce post bitmasks (for post-scoped actions)
- Enforce invariants (key type, lineage, immutability)
- Orchestrate multiple repositories (transactions)
- Emit audit events
- Throw deterministic exceptions

**Forbidden:**
- ❌ HTTP concerns (request/response objects)
- ❌ Direct SQL queries (use repositories)

### Repositories (Data Access)

**Responsibilities:**
- Prepared SQL queries (PDO prepared statements only)
- Binary ID handling (`BINARY(16)` internal, `hex32` external)
- Data access isolation
- Specialized helpers (access resolution, membership joins, lineage lookups)

**Forbidden:**
- ❌ HTTP concerns
- ❌ Authorization checks
- ❌ Business rules
- ❌ Leaking binary IDs beyond repository boundaries

### Middleware (Cross-Cutting Concerns)

**Responsibilities:**
- HTTPS enforcement and HSTS
- CORS headers
- Rate limiting
- JWT authentication (Owner and Key)
- Request validation
- CSRF protection (HTML routes only)
- Error handling and normalization

**Forbidden:**
- ❌ Business logic
- ❌ Direct database access
- ❌ Authorization checks (services handle this)

## Surface Separation

CRE8.pw uses **dual-surface architecture**:

1. **Console** (`/console/*`) - Owner-facing
   - HTML pages: `/`, `/console/register`, `/console/login`, `/console/dashboard`
   - JSON endpoints: `/console/*` JSON (Owner JWT, `typ=owner`)

2. **Gateway** (`/api/*`) - Key-protected API
   - JSON endpoints: `/api/*` (except `/api/auth/*`)
   - Key JWT (`typ=key`)

3. **Public API** - No authentication
   - `/health`, `/.well-known/jwks.json`, `/api/auth/*`, `/console/owners`, `/console/login`

## Token Typing

All JWTs include a `typ` claim:
- `typ=owner` → Console JSON endpoints (enforced by `JwtOwnerMiddleware`)
- `typ=key` → Gateway JSON endpoints (enforced by `JwtKeyMiddleware`)

This prevents token confusion attacks.

## CSRF Scope

**CRITICAL:** CSRF middleware is **only** applied to HTML routes.

- ✅ **Apply CSRF Guard to:** Console HTML routes (`/`, `/console/register`, `/console/login`, `/console/dashboard`)
- ❌ **Do not apply CSRF Guard to:** Console JSON endpoints, Gateway JSON endpoints, Public API JSON endpoints

**Rationale:** JSON endpoints use token-based authentication (Bearer JWT). CSRF protection is unnecessary and inappropriate for stateless token auth.

## References

- **Architecture:** [architecture-overview.md](architecture-overview.md)
- **Implementation Guide:** [implementation-guide.md](../08-implementation/implementation-guide.md)
- **Master SSOT:** [/SSOT.md](../../SSOT.md)
- **Canon SSOT:** [canon-ssot.md](../12-comprehensive-reference/canon-ssot.md)
