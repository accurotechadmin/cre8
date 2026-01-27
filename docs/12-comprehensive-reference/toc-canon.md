# CRE8.pw Canon Documentation Table of Contents

**Purpose:** This document provides a detailed index of all canonical documentation files in the CRE8.pw project, with comprehensive descriptions of each file's content, purpose, and key sections.

**Last Updated:** 2026-01-25

---

## Overview

The canon documentation is distributed across multiple folders in this document set. These documents define the authoritative requirements and design for CRE8.pw. This TOC provides a consolidated index of all canonical specifications.

**Total Files:** Documents are distributed across:
- `01-getting-started/` (introduction.md)
- `03-core-concepts/` (key-lifecycle.md, post-sharing.md)
- `04-architecture/` (architecture-overview.md)
- `05-authentication-authorization/` (authentication.md, authorization.md)
- `06-api-reference/` (api-reference.md, feed-system.md, response-schemas.md)
- `07-data-model/` (database-schema.md)
- `08-implementation/` (implementation-guide.md)
- `09-operations/` (logging-and-audit.md)
- `12-comprehensive-reference/` (canon-ssot.md)

---

## Document Index

### `introduction.md`
**Location:** `01-getting-started/introduction.md`

**Introduction and Platform Overview**

**Purpose:** Provides the foundational introduction to CRE8.pw, establishing context and key concepts for all subsequent documentation.

**Key Content:**
- Platform overview: hierarchical key-based authentication, fine-grained access control, full provenance tracking
- Key concepts: Principals (Owners and Keys), Two Surfaces (Console and Gateway), Authorization Model, Hierarchical Permission Envelope
- Quick start paths for Owners and Developers
- System architecture overview
- Use cases and target audience

**When to Read:** First document for anyone new to CRE8.pw. Establishes vocabulary and mental models used throughout all other documentation.

---

### `architecture-overview.md`
**Location:** `04-architecture/architecture-overview.md`

**Architecture and Request Pipeline** — **[KEYSTONE DOCUMENT]**

**Purpose:** Defines the fundamental architectural structure of CRE8.pw, including dual-surface design, layered architecture, and middleware pipeline specifications.

**Key Content:**
- Dual-surface architecture: Console (Owner-facing) vs Gateway (Key-facing)
- Layered architecture: Middleware → Controller → Service → Repository with strict responsibility boundaries
- Four distinct middleware pipelines: Public API, Console JSON, Gateway JSON, Console HTML
- Middleware ordering and responsibilities for each pipeline
- Critical CSRF scope rules (HTML routes only, never JSON endpoints)
- Route group organization and surface separation
- Technology stack overview

**When to Read:** Essential reading for understanding how requests flow through the system. Required before implementing any new features.

---

### `authentication.md`
**Location:** `05-authentication-authorization/authentication.md`

**Authentication and Identity System**

**Purpose:** Describes the complete authentication system, including Owner and Key principals, JWT structure, ApiKey exchange, and refresh token lifecycle.

**Key Content:**
- Principal types: Owners (human, email/password) and Keys (machine, ApiKey)
- RS256 JWT structure: standard claims (`iss`, `sub`, `aud`, `iat`, `nbf`, `exp`) and application claims (`typ`, `owner_id`, `key_id`, `permissions`)
- Token typing: `typ=owner` vs `typ=key` for surface separation
- ApiKey exchange process: `Authorization: ApiKey <public_id>:<secret>` → JWT + refresh token
- Owner registration and login flows
- Refresh token lifecycle: single-use rotation with replay detection
- JWKS endpoint: public key publishing and key rotation support
- Argon2id password and secret hashing

**When to Read:** Before implementing authentication features or integrating with the API. Essential for understanding how principals authenticate.

---

### `authorization.md`
**Location:** `05-authentication-authorization/authorization.md`

**Authorization and Permissions** — **[KEYSTONE DOCUMENT]**

**Purpose:** Defines the two-layer authorization system combining global permissions with post-scoped bitmasks, including key type restrictions and envelope rules.

**Key Content:**
- Two-layer authorization: Global Permission Strings + Post-Scoped Bitmasks
- Permission strings catalog: Owner permissions (Console-scoped) and Key permissions (Gateway-scoped)
- Post access bitmasks: VIEW (0x01), COMMENT (0x02), MANAGE_ACCESS (0x08)
- Key types: Primary Author Key, Secondary Author Key, Use Key with capability matrices
- Permission Envelope Rule: Child permissions must be subset (⊆) of parent permissions
- Use Key restrictions: Cannot have `posts:create` or `keys:issue` permissions
- Permission immutability: Permissions cannot change after minting (use rotation)
- Visibility vs. Access rules: 404 (hide existence) vs 403 (reveal lack of permission)
- Role definitions: Owner, Author, Use roles with implied permissions

**When to Read:** Critical for understanding authorization decisions. Required before implementing any permission checks or key minting logic.

---

### `api-reference.md`
**Location:** `06-api-reference/api-reference.md`

**Routes and API Reference**

**Purpose:** Provides a complete catalog of all HTTP endpoints across all surfaces, with detailed request/response schemas and permission requirements.

**Key Content:**
- Complete route inventory organized by surface:
  - Public HTML (landing, registration, login, dashboard)
  - Public API (health, JWKS, auth endpoints)
  - Console JSON (Owner-protected endpoints for keys, groups, keychains, posts)
  - Gateway JSON (Key-protected endpoints for posts, comments, keys, feeds, groups, keychains)
- For each endpoint: HTTP method, path, authentication requirements, required permissions, request body schemas, response schemas, example requests/responses
- Controller/Service/Repository ownership mapping
- Route addition checklist

**When to Read:** Reference document for API integration. Use when implementing client applications or adding new endpoints.

---

### `feed-system.md`
**Location:** `06-api-reference/feed-system.md`

**Feed System**

**Purpose:** Specifies the feed system for content discovery, including visibility rules, pagination mechanics, and authorization enforcement.

**Key Content:**
- Feed concept: ordered lists of posts visible to a principal, sorted newest-first
- Use Key feed endpoint: `GET /api/feed/use/{useKeyId}` with path param enforcement
- Visibility resolution: direct grants, group membership, keychain membership
- Cursor-based pagination: `before_id` and `since_id` parameters
- Author feed scaffolding (future feature)
- Feed path guard requirements: `{useKeyId}` must match JWT `key_id`
- SQL sketches for feed queries

**When to Read:** When implementing feed endpoints or understanding content discovery mechanisms.

---

### `database-schema.md`
**Location:** `07-data-model/database-schema.md`

**Data Model**

**Purpose:** Defines the complete database schema with all tables, relationships, constraints, and data formats.

**Key Content:**
- Database requirements: MariaDB 11.4.x, `utf8mb4_bin` collation
- ID formats: `BINARY(16)` internal storage, `hex32` external representation
- Core entities: `owners`, `keys`, `key_public_ids`, `posts`, `comments`, `post_access`, `groups`, `group_members`, `keychains`, `keychain_members`, `refresh_tokens`, `key_devices`, `audit_events`
- Table schemas: columns, types, constraints, foreign keys
- Lineage invariants: `issued_by_key_id`, `parent_key_id`, `initial_author_key_id` immutability rules
- Required indexes for performance
- Migration ordering requirements

**When to Read:** Before implementing repositories or database queries. Essential reference for data access layer development.

---

### `key-lifecycle.md`
**Location:** `03-core-concepts/key-lifecycle.md`

**Key Lifecycle and Provenance**

**Purpose:** Describes the complete key lifecycle from minting through rotation, activation/deactivation, and use limit enforcement.

**Key Content:**
- Key minting processes:
  - Primary Author Key (Owner → Console)
  - Secondary Author Key (Author Key → Gateway)
  - Use Key (Author Key → Gateway) with restrictions
- Lineage tracking: `issued_by_key_id`, `parent_key_id`, `initial_author_key_id` fields
- Key rotation: retire old key, issue new key with same permissions and lineage
- Activation/deactivation: individual and cascade options
- Use Key limits: `use_count_limit` and `device_limit` enforcement
- Key secret handling: return once on mint, never log, never return after initial mint
- Lineage immutability: lineage fields never change after creation

**When to Read:** When implementing key management features, rotation workflows, or use limit enforcement.

---

### `post-sharing.md`
**Location:** `03-core-concepts/post-sharing.md`

**Post Sharing and Access Control**

**Purpose:** Explains how posts are created, shared, and accessed through the two-layer authorization system.

**Key Content:**
- Post creation: default private (no access grants), requires `posts:create` permission
- Post visibility model: posts visible only if granted VIEW mask in `post_access` table
- Access grants: to keys directly, to groups (bulk access), to keychains
- Access revocation: removing grants from `post_access` table
- Use Key sharing workflow: complete step-by-step process
- Permission mask enforcement: VIEW, COMMENT, MANAGE_ACCESS bits
- Owner admin workflows: viewing posts from owner's keys, granting group access

**When to Read:** When implementing post creation, access management, or sharing workflows.

---

### `implementation-guide.md`
**Location:** `08-implementation/implementation-guide.md`

**Implementation Guide**

**Purpose:** Provides practical implementation patterns, coding conventions, and development workflows for building CRE8.pw.

**Key Content:**
- Project structure: directory layout and organization
- Technology stack: PHP 8.3+, Slim Framework 4.15+, MariaDB 11.4.x, PHP-DI, firebase/php-jwt, Respect\Validation, Monolog, Symfony rate-limiter
- Layering rules: Controller, Service, Repository, Middleware responsibilities and prohibitions
- Dependency Injection: PHP-DI container wiring, autowiring, singletons
- Validation configuration: centralized validation schemas in `config/validation.php`
- Route configuration: route group registration
- Local development setup: prerequisites, installation steps
- Checklist for adding new endpoints

**When to Read:** When starting development or implementing new features. Essential for understanding coding patterns and conventions.

---

### `response-schemas.md`
**Location:** `06-api-reference/response-schemas.md`

**Response Schemas and Error Handling**

**Purpose:** Defines standardized JSON response formats for success and error cases, including HTTP status code mapping.

**Key Content:**
- Standard success responses:
  - Single object: `{ data: {...} }`
  - List with paging: `{ data: [...], paging: {...} }`
- Standard error response: `{ error: { code, message, details, request_id } }`
- HTTP status code mapping: 200, 201, 204, 400, 401, 403, 404, 409, 422, 429, 500, 503
- Error code taxonomy: `bad_request`, `unauthorized`, `forbidden`, `not_found`, `validation_failed`, `rate_limited`, etc.
- Validation errors: `details.fields` object with field-level errors
- Permission errors: `details.required` (permissions) and `details.required_mask` (bitmasks)
- Generic auth error messages: never reveal whether email or `key_public_id` exists

**When to Read:** When implementing API responses or error handling. Essential for maintaining consistent API contracts.

---

### `logging-and-audit.md`
**Location:** `09-operations/logging-and-audit.md`

**Logging, Audit, and Observability**

**Purpose:** Specifies logging conventions, audit event requirements, rate limiting, and observability practices.

**Key Content:**
- Structured JSON logging: Monolog with consistent format
- Log channels: `api`, `auth`, `security`, `db`, `guzzle.http`
- Critical rule: never log secrets (passwords, ApiKey secrets, refresh tokens, private keys)
- Audit events catalog: required events for all state-changing operations
- Audit event structure: `<domain>:<action>` naming convention, actor/subject tracking
- Correlation IDs: request tracking across logs
- Rate limiting: buckets (GENERAL, AUTH, API), keying strategies (IP, owner_id, key_id), backing stores
- Troubleshooting guide: common HTTP error codes and diagnostic steps

**When to Read:** When implementing logging, audit events, or troubleshooting production issues.

---

### `canon-ssot.md`
**Location:** `12-comprehensive-reference/canon-ssot.md`

**Canon Single Source of Truth**

**Purpose:** Comprehensive consolidation of all information from the canon documents, serving as the definitive reference for all canonical specifications.

**Key Content:**
- Core concepts: Platform overview, Principals, Surfaces, Authorization Model, Hierarchical Permission Envelope
- Critical rules and constraints: CSRF scope, Use Key restrictions, Permission Envelope Rule, Permission/Lineage immutability, Visibility vs Access, Token typing, Refresh token single-use
- Architecture and design principles: Technology stack, layering rules, middleware ordering, database baseline
- Authentication and identity: JWT structure, ApiKey exchange, Owner login, Refresh token lifecycle, JWKS endpoint
- Authorization and permissions: Permission strings catalog, Post access bitmasks, Combined authorization checks, Key type capabilities
- Data model and identifiers: ID formats, Core entities, Critical indexes
- API design and responses: Standard success/error responses, HTTP status code mapping, Error code taxonomy
- Security requirements: Never log secrets, Authentication security, HTTPS & HSTS, CORS, Rate limiting, Input validation, Prepared statements
- Implementation patterns: Controller, Service, Repository patterns with code examples, Dependency injection
- Do's and don'ts: Comprehensive lists for authentication, authorization, API design, database, logging, security
- Error handling: Error response format, Validation errors, Permission failures, Rate limiting errors
- Logging and observability: Log format, Log channels, Audit events, Rate limiting
- Key lifecycle and provenance: Key minting, Rotation, Activation/deactivation, Use count and device limits
- Post sharing and access control: Post creation, Visibility, Granting access, Use Key sharing workflow
- Environment configuration: JWT, Hashing, Database, CORS, Rate limiting, Application configuration
- Troubleshooting guide: Common issues (401, 403, 422, 429, 500) with diagnostic steps
- Codebase structure and conventions: Directory structure, Naming conventions, Code organization rules, File organization, Import organization, Type declarations, Error handling patterns
- Development workflow: Local development setup, Quick start workflow, Adding a new feature, Testing checklist
- Summary: Critical rules organized by category (Security, Code Quality, API Design, Development)

**When to Read:** Use as a comprehensive reference when you need quick access to any canonical specification. Ideal for quick lookups and ensuring compliance with all rules and patterns.

---

## Reading Recommendations

### For New Developers
1. Start with [introduction.md](../01-getting-started/introduction.md) to understand the platform
2. Read [architecture-overview.md](../04-architecture/architecture-overview.md) for architectural foundation
3. Study [authorization.md](../05-authentication-authorization/authorization.md) for authorization model
4. Reference [api-reference.md](../06-api-reference/api-reference.md) for API integration
5. Use [canon-ssot.md](canon-ssot.md) as ongoing reference

### For API Integration
1. [introduction.md](../01-getting-started/introduction.md) for platform overview
2. [authentication.md](../05-authentication-authorization/authentication.md) for authentication flows
3. [api-reference.md](../06-api-reference/api-reference.md) for endpoint specifications
4. [response-schemas.md](../06-api-reference/response-schemas.md) for response formats

### For Implementation
1. [architecture-overview.md](../04-architecture/architecture-overview.md) for architecture
2. [implementation-guide.md](../08-implementation/implementation-guide.md) for coding patterns
3. [database-schema.md](../07-data-model/database-schema.md) for database schema
4. [canon-ssot.md](canon-ssot.md) for comprehensive reference

---

**Note:** All canon documents are authoritative specifications. The SSOT document consolidates all information but does not replace the detailed specifications in individual documents. When in doubt, refer to the specific document for the most detailed information.
