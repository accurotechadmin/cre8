# CRE8.pw Documentation

**Version:** 1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical

---

## What is CRE8.pw?

CRE8.pw is a secure content creation and sharing platform built on a hierarchical key-based authentication system with full provenance tracking. It enables content creators to maintain complete control over their work while selectively sharing access through a sophisticated permission and key management system.

At its core, CRE8.pw provides:

- **Hierarchical Authentication:** Owners mint Primary Author Keys; those keys mint child keys with delegated permissions
- **Fine-Grained Access Control:** Combine global permissions with post-level access bitmasks for precise control
- **Full Provenance Tracking:** Every key traces back to its root, enabling accountability and bulk revocation
- **Dual-Surface Architecture:** Human-facing Console for management; machine-facing Gateway for programmatic access
- **Secure Sharing:** Create single-use or limited-use keys with device restrictions for controlled content distribution

CRE8.pw is designed for developers who need a drop-in authentication and authorization platform that handles complex sharing scenarios while maintaining security and auditability.

---

## Key Concepts at a Glance

### Principals: Owners and Keys

**Owners (Human)**
- Authenticate with email and password
- Access the Console (web interface + JSON API)
- Mint Primary Author Keys
- Manage groups, view lineage, and oversee all downstream key activity

**Keys (Machine)**
- Authenticate with ApiKey (`public_id` + `secret`)
- Access the Gateway (JSON API)
- Three types with different capabilities:
  - **Primary Author Key:** Root key; can create posts and mint child keys
  - **Secondary Author Key:** Delegated key; can create posts and mint child keys (within permission envelope)
  - **Use Key:** Interaction-only; can read and comment but cannot create posts or mint keys

### Two Surfaces

**Console** (`/console/*`)
- HTML pages for Owners: landing, registration, login, dashboard
- JSON endpoints for Owner management: keys, groups, keychains, posts
- CSRF protection on HTML forms only (not on JSON endpoints)

**Gateway** (`/api/*`)
- JSON API for programmatic access
- Key-based authentication using JWT tokens
- Endpoints for posting, commenting, key issuance, and feeds

### Authorization Model

CRE8.pw uses a two-layer authorization system:

1. **Permission Strings:** Global capabilities like `posts:create`, `keys:issue`, `comments:write`
2. **Post Bitmasks:** Post-specific permissions encoded as bits (VIEW, COMMENT, MANAGE_ACCESS)

Every action requires **both** the appropriate permission string AND (for post-scoped actions) the appropriate bitmask.

Example: To comment on a post, a Key needs:
- Global permission: `comments:write`
- Post-level mask bit: `COMMENT` (0x02)

### Hierarchical Permission Envelope

Child keys inherit a **subset** of their parent's permissions. This creates an accountability chain:

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

## Quick Start Path

### For Owners (Human Users)

**Goal:** Create an account, mint a key, and start creating content

1. **Register** → Visit `/console/register` and create an Owner account
2. **Login** → Authenticate and receive access tokens
3. **Mint Primary Author Key** → Use Console to create your first root key
4. **Switch to Gateway** → Use the Primary Author Key to authenticate programmatically
5. **Create Content** → Post via `POST /api/posts` using your Author Key

**Estimated Time:** 5 minutes

### For Developers (API Integration)

**Goal:** Integrate CRE8.pw authentication into your application

**Option 1: Using the SDK (Recommended)**
1. **Install SDK** → `composer require cre8/sdk` (PHP SDK is currently available; Python and Go SDKs planned)
2. **Initialize Client** → Create client with ApiKey credentials
3. **Use SDK Methods** → Type-safe API methods handle authentication automatically

**Option 2: Direct HTTP Integration**
1. **Obtain Primary Author Key** → From an Owner account (via Console)
2. **Exchange for JWT** → `POST /api/auth/exchange` with `Authorization: ApiKey <public_id>:<secret>`
3. **Make Authenticated Requests** → Include `Authorization: Bearer <access_token>` on Gateway endpoints
4. **Handle Token Refresh** → Use refresh tokens to obtain new access tokens when they expire

**Estimated Time:** 5 minutes with SDK, 10 minutes with direct HTTP

**See:** **[sdk-specification.md](../11-development/sdk-specification.md)** for complete SDK documentation

### For Content Sharers

**Goal:** Share a post with a limited-access key

1. **Create Post** → `POST /api/posts` with your Author Key
2. **Mint Use Key** → `POST /api/keys/{authorKeyId}/use` with desired permissions + limits
3. **Grant Access** → `POST /api/posts/{postId}/access` to grant the Use Key VIEW+COMMENT mask
4. **Share Use Key** → Distribute the `key_public_id` and `key_secret` to recipient
5. **Recipient Accesses** → Recipient exchanges ApiKey for JWT and reads/comments on post

---

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        CRE8.pw Platform                      │
│                     (Single Slim 4 App)                      │
├─────────────────────────────────────┬───────────────────────┤
│           CONSOLE                   │       GATEWAY         │
│      (Owner Interface)              │   (API Interface)     │
├─────────────────────────────────────┼───────────────────────┤
│  HTML Pages:                        │  JSON Endpoints:      │
│   - Landing                         │   - Posts             │
│   - Register/Login                  │   - Comments          │
│   - Dashboard                       │   - Key Issuance      │
│                                     │   - Feeds             │
│  JSON Endpoints (Owner JWT):        │                       │
│   - Key Management                  │  Auth: Key JWT        │
│   - Group Management                │  (typ=key)            │
│   - Post Access Control             │                       │
│                                     │                       │
│  Auth: Owner JWT (typ=owner)        │                       │
│  CSRF: HTML forms only              │                       │
└─────────────────────────────────────┴───────────────────────┘
                            │
                            ▼
        ┌──────────────────────────────────────┐
        │     Middleware Pipeline              │
        │  HTTPS → CORS → RateLimit →          │
        │  JWT → Validation → Routing          │
        └──────────────────────────────────────┘
                            │
                            ▼
        ┌──────────────────────────────────────┐
        │   Controller → Service → Repository  │
        │           (Layered Architecture)     │
        └──────────────────────────────────────┘
                            │
                            ▼
        ┌──────────────────────────────────────┐
        │         MariaDB 11.4.x               │
        │       (utf8mb4_bin collation)        │
        └──────────────────────────────────────┘
```

### Technology Stack

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

### Security Posture

CRE8.pw is built with security as a first-class concern:

- **RS256 JWT Signing:** Asymmetric cryptography with JWKS key rotation support
- **Single-Use Refresh Tokens:** Automatic rotation prevents replay attacks
- **Hierarchical Revocation:** Disable a key and all its descendants in one operation
- **Argon2id Hashing:** Industry-standard password and secret hashing
- **Never Log Secrets:** Strict logging policies prevent credential leakage
- **Rate Limiting:** IP-based (public), principal-based (authenticated)
- **Input Validation:** Centralized validation with explicit schemas
- **Prepared Statements:** All database access uses PDO with parameter binding

---

## Document Set Navigation

### By Role

**I am an Owner (human user):**
- Start: **introduction.md** (you are here)
- Setup: **[authentication.md](../05-authentication-authorization/authentication.md)** (registration and login)
- Management: **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)** (minting and managing keys)
- Sharing: **[post-sharing.md](../03-core-concepts/post-sharing.md)** (granting access)

**I am a Developer (building with CRE8.pw):**
- Start: **introduction.md** (you are here)
- Architecture: **[architecture-overview.md](../04-architecture/architecture-overview.md)**
- Authentication: **[authentication.md](../05-authentication-authorization/authentication.md)** (JWT, ApiKey exchange)
- Authorization: **[authorization.md](../05-authentication-authorization/authorization.md)** (permission model)
- API Reference: **[api-reference.md](../06-api-reference/api-reference.md)** (complete endpoint catalog)
- Implementation: **[implementation-guide.md](../08-implementation/implementation-guide.md)** (development patterns)

**I am integrating feeds:**
- **[feed-system.md](../06-api-reference/feed-system.md)** (feed endpoints and visibility)
- **[authorization.md](../05-authentication-authorization/authorization.md)** (access control)

**I am debugging/troubleshooting:**
- **[response-schemas.md](../06-api-reference/response-schemas.md)** (error codes and meanings)
- **[logging-and-audit.md](../09-operations/logging-and-audit.md)** (log channels and troubleshooting)

### By Task

**I want to understand...**
- Authentication flows → **[authentication.md](../05-authentication-authorization/authentication.md)**
- Permission system → **[authorization.md](../05-authentication-authorization/authorization.md)**
- Database schema → **[database-schema.md](../07-data-model/database-schema.md)**
- Key lineage → **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)**
- Post sharing → **[post-sharing.md](../03-core-concepts/post-sharing.md)**

**I need reference for...**
- All API endpoints → **[api-reference.md](../06-api-reference/api-reference.md)**
- Identifier formats → **[identifier-encoding.md](../10-reference/identifier-encoding.md)**
- Environment variables → **[environment-configuration.md](../10-reference/environment-configuration.md)**
- Terminology → **[glossary.md](../03-core-concepts/glossary.md)**

**I am implementing...**
- New features → **[implementation-guide.md](../08-implementation/implementation-guide.md)**
- Error handling → **[response-schemas.md](../06-api-reference/response-schemas.md)**
- Logging/auditing → **[logging-and-audit.md](../09-operations/logging-and-audit.md)**

### Single Source of Truth (SSoT) Map

Each document owns specific aspects of the system:

| Document | Owns |
|----------|------|
| **[architecture-overview.md](../04-architecture/architecture-overview.md)** | Middleware pipelines, CSRF scope, request flow |
| **[authentication.md](../05-authentication-authorization/authentication.md)** | JWT structure, JWKS, refresh lifecycle, ApiKey exchange |
| **[authorization.md](../05-authentication-authorization/authorization.md)** | Permissions catalog, roles, bitmasks, envelope rules |
| **[api-reference.md](../06-api-reference/api-reference.md)** | API endpoint catalog, required permissions |
| **[feed-system.md](../06-api-reference/feed-system.md)** | Feed visibility, pagination, authorization |
| **[database-schema.md](../07-data-model/database-schema.md)** | Database schema, migrations, invariants |
| **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)** | Key issuance, rotation, lineage, provenance |
| **[post-sharing.md](../03-core-concepts/post-sharing.md)** | Access grants, Use Key limits, sharing workflows |
| **[implementation-guide.md](../08-implementation/implementation-guide.md)** | Code patterns, DI wiring, layering rules |
| **[response-schemas.md](../06-api-reference/response-schemas.md)** | Success/error formats, HTTP status codes |
| **[logging-and-audit.md](../09-operations/logging-and-audit.md)** | Log channels, audit events, rate limiting |
| **[identifier-encoding.md](../10-reference/identifier-encoding.md)** | Identifier formats (hex32, apub_, BINARY(16)) |
| **[environment-configuration.md](../10-reference/environment-configuration.md)** | Environment configuration reference |
| **[glossary.md](../03-core-concepts/glossary.md)** | Glossary and terminology |

---

## Terminology Notes

**Principal:** An authenticated entity. Either an Owner (human) or a Key (machine).

**Surface:** Console (Owner-facing) or Gateway (API-facing).

**Permission String:** Global capability identifier (e.g., `posts:create`).

**Bitmask:** Post-scoped permission integer encoding bits (VIEW, COMMENT, MANAGE_ACCESS).

**Lineage:** Provenance chain of key issuance tracked via `issued_by_key_id`, `parent_key_id`, and `initial_author_key_id`.

**Envelope Rule:** Child key permissions must be a subset of parent key permissions.

**hex32:** External identifier format (32-character lowercase hexadecimal).

**apub_...:** Key public identifier format used exclusively for ApiKey exchange.

**SSoT (Single Source of Truth):** The authoritative document for a specific aspect of the system.

For complete terminology, see **[glossary.md](../03-core-concepts/glossary.md)**.

---

## Getting Help

**For system concepts:**
- Review the Quick Start Path above
- Read the document that owns the relevant topic (see SSoT Map)
- Check the **[Glossary](../03-core-concepts/glossary.md)** for term definitions

**For API integration:**
- Start with **[authentication.md](../05-authentication-authorization/authentication.md)** for auth flows
- Reference **[api-reference.md](../06-api-reference/api-reference.md)** for endpoint details
- Use **[response-schemas.md](../06-api-reference/response-schemas.md)** for error codes

**For troubleshooting:**
- Check **[response-schemas.md](../06-api-reference/response-schemas.md)** for error meanings
- Review **[logging-and-audit.md](../09-operations/logging-and-audit.md)** for log channels
- Verify permissions in **[authorization.md](../05-authentication-authorization/authorization.md)**

**For development:**
- Follow **[implementation-guide.md](../08-implementation/implementation-guide.md)** for patterns and best practices
- Reference **[database-schema.md](../07-data-model/database-schema.md)** for schema details
- Use **[environment-configuration.md](../10-reference/environment-configuration.md)** for environment configuration

---

## Document Conventions

Throughout this documentation:

- **Normative** sections define requirements (MUST, SHOULD, etc.)
- **Reference** sections provide examples and non-binding guidance
- **SSoT** markers indicate authoritative ownership
- Code examples use real formats (not placeholders)
- Cross-references link to owning documents

Example formats are realistic:
- Owner ID: `3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b` (hex32)
- Key ID: `b5a1e8c0d9f04c3aa1b2c3d4e5f60718` (hex32)
- Key Public ID: `apub_8cd1a2b3c4d5e6f7` (apub_...)
- Permission: `posts:create`
- Bitmask: `0x03` (VIEW + COMMENT)

---

**Next:** Proceed to **[architecture-overview.md](../04-architecture/architecture-overview.md)** to understand the system architecture and middleware flow.
