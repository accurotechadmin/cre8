# CRE8.pw Appendix Documentation Table of Contents

**Purpose:** This document provides a detailed index of all appendix documentation files in the CRE8.pw project, with comprehensive descriptions of each file's content, purpose, and key sections.

**Last Updated:** 2026-01-25

---

## Overview

The appendix documentation is distributed across multiple folders. These documents provide supplementary reference materials, helper matrices, route inventories, and implementation guides that support the canonical documentation.

**Total Files:** Documents are distributed across:
- `03-core-concepts/` (glossary.md)
- `05-authentication-authorization/` (key-capabilities.md, permissions.md)
- `06-api-reference/` (routes-inventory.md)
- `08-implementation/` (dependency-wiring.md)
- `10-reference/` (identifier-encoding.md, environment-configuration.md, document-outlines.md, codebase-inventory.md, docset.json)
- `01-getting-started/` (executive-summary.md)
- `04-architecture/` (component-architecture.md, layering-rules.md)
- `12-comprehensive-reference/` (appendix-ssot.md)

---

## Document Index

### `identifier-encoding.md`
**Location:** `10-reference/identifier-encoding.md`

**Identifier Encoding Matrix**

**Purpose:** Authoritative reference for all identifier formats used throughout CRE8.pw, specifying encoding rules for internal storage, external representation, route parameters, and JWT claims.

**Key Content:**
- Internal storage format: `BINARY(16)` for all primary keys and foreign keys in database
- External encoding format: `hex32` (32-character lowercase hexadecimal) for routes, JSON, JWT claims, logs
- Key public ID format: `apub_...` prefix format, used ONLY for ApiKey exchange
- Route parameter rules: All `{...Id}` params are hex32 except `{keyPublicId}` which is `apub_...`
- JWT claim rules: `owner_id` and `key_id` must be hex32, `key_public_id` optional in Key tokens
- Conversion functions: `binaryToHex32()` and `hex32ToBinary()` utilities
- Identifier matrix table: comprehensive mapping of all identifier types
- Logging and audit ID rules: hex32 only, never log secrets
- Implementation guidance: Repository layer conversion patterns

**When to Use:** Reference when implementing ID conversions, route parameters, or JWT claims. Essential for maintaining ID format consistency.

---

### `environment-configuration.md`
**Location:** `10-reference/environment-configuration.md`

**Environment Configuration Reference**

**Purpose:** Complete reference for all environment variables used in CRE8.pw, including configuration options, validation requirements, and bootstrap checks.

**Key Content:**
- Application configuration: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- Database configuration: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`, `DB_COLLATION`, `DB_SSL_MODE`
- JWT configuration: `JWT_ALGO`, `JWT_PRIVATE_KEY_PATH`, `JWT_PUBLIC_KEY_PATH`, `JWT_ISSUER`, `JWT_AUDIENCE`, `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL`, `JWT_LEEWAY`
- CORS configuration: `CORS_ALLOWED_ORIGINS`, `CORS_ALLOWED_METHODS`, `CORS_ALLOWED_HEADERS`, `CORS_EXPOSED_HEADERS`
- CSP configuration: `CSP_DEFAULT_SRC` for Content Security Policy
- CSRF configuration: `CSRF_SECRET` for CSRF token generation
- Rate limiting configuration: `RATE_LIMIT_GENERAL`, `RATE_LIMIT_AUTH`, `RATE_LIMIT_API`, `RATE_LIMIT_BACKING`
- HTTP client configuration: `HTTP_TIMEOUT`, `HTTP_RETRY_MAX`
- Logging configuration: `LOG_CHANNEL`, `LOG_LEVEL`, `LOG_PATH`
- Hashing configuration: `APIKEY_HASH_ALGO`, `PASSWORD_MEMORY_COST`, `PASSWORD_TIME_COST`, `PASSWORD_PARALLELISM`
- Bootstrap validation checklist: Required environment variables and validation rules

**When to Use:** Reference when configuring the application for different environments (development, staging, production). Essential for deployment and environment setup.

---

### `glossary.md`
**Location:** `03-core-concepts/glossary.md`

**Glossary and Terminology**

**Purpose:** Alphabetical dictionary of key terms, concepts, and domain-specific terminology used throughout CRE8.pw documentation.

**Key Content:**
- Comprehensive alphabetical listing of terms from Access Token to VIEW (Bitmask)
- Definitions for all principal types: Owner, Primary Author Key, Secondary Author Key, Use Key
- Authentication terms: ApiKey, Bearer Token, JWT, JWKS, Refresh Token, RS256
- Authorization terms: Permission, Permission Envelope, Post Bitmask, Role
- System concepts: Console, Gateway, Surface, Lineage, Provenance
- Technical terms: BINARY(16), hex32, Token Typing, CORS, CSRF, HSTS
- Bitmask definitions: VIEW, COMMENT, MANAGE_ACCESS with hex values
- Preset definitions: READ_ONLY, INTERACT, ADMIN bitmask presets

**When to Use:** Reference when encountering unfamiliar terminology. Useful for onboarding new team members and ensuring consistent vocabulary.

---

### `executive-summary.md`
**Location:** `01-getting-started/executive-summary.md`

**Executive Summary**

**Purpose:** High-level overview of CRE8.pw for stakeholders, executives, and new team members who need a condensed understanding without diving into technical details.

**Key Content:**
- Platform overview and value proposition
- Core architecture summary: Dual-surface design, hierarchical key system
- Key features: Fine-grained access control, provenance tracking, secure sharing
- Authentication model overview: Owners and Keys
- Authorization model overview: Two-layer system
- Primary use cases: Content sharing, delegation, audit trails
- Target audience: Developers needing drop-in authentication and authorization

**When to Use:** Share with non-technical stakeholders or use as a quick introduction before diving into detailed documentation.

---

### `docset.json`
**Location:** `10-reference/docset.json`

**Documentation Set Configuration**

**Purpose:** JSON configuration file for documentation tooling, docset generation, and automated documentation processing.

**Key Content:**
- Metadata about documentation structure
- File relationships and dependencies
- Indexing information for documentation viewers
- Search tool configuration
- Documentation versioning information

**When to Use:** Used by documentation tooling and automated systems. Not typically read by developers directly.

---

### `document-outlines.md`
**Location:** `10-reference/document-outlines.md`

**Document Outlines**

**Purpose:** Structural metadata for all documentation files, including section hierarchies, cross-references, and document relationships.

**Key Content:**
- Section hierarchies for all documents
- Cross-references between documents
- Document relationship mapping
- Consistency validation metadata
- Automated documentation generation support

**When to Use:** Used for maintaining documentation consistency and supporting automated documentation tools. Useful for understanding document structure.

---

### `key-capabilities.md`
**Location:** `05-authentication-authorization/key-capabilities.md`

**Key Capability Matrix**

**Purpose:** Reference table mapping key types to their capabilities, restrictions, and inheritance rules.

**Key Content:**
- Complete capability table: Owner, Primary Author, Secondary Author, Use Key capabilities
- Capability categories: Authentication, Key Issuance, Content Creation, Content Access, Access Management, Group Management, Keychain Management, Key Lifecycle, Provenance & Audit
- Key type definitions: Detailed descriptions of each principal type
- Issuance rules: How each key type is minted and by whom
- Permission envelope validation: Examples of valid and invalid child permissions
- Lineage traversal: How to view and manage key hierarchies
- Combining keys: Keyring key concept and group/keychain workflows

**When to Use:** Reference when determining what a key type can or cannot do. Essential for authorization decisions and key minting logic.

---

### `permissions.md`
**Location:** `05-authentication-authorization/permissions.md`

**Permission Matrix**

**Purpose:** Complete catalog of all permission strings used in CRE8.pw, organized by domain with role mappings.

**Key Content:**
- Owner permissions (Console-scoped): `owners:manage`, `keys:issue`, `keys:read`, `keys:rotate`, `keys:state:update`, `groups:manage`, `keychains:manage`, `posts:admin:read`, `posts:access:manage`
- Key permissions (Gateway-scoped): `keys:issue`, `posts:create`, `posts:read`, `comments:write`, `groups:read`, `keychains:manage`, `posts:access:manage`
- Permission envelope rules: Child permissions must be subset of parent
- Post access bitmasks: VIEW (0x01), COMMENT (0x02), MANAGE_ACCESS (0x08) with presets
- Combined authorization: Examples of permission + mask combinations
- Role definitions: Owner, Author, Use roles with implied permissions
- Permission assignment flow: How permissions are assigned through the system

**When to Use:** Reference when implementing permission checks or understanding what permissions are required for specific actions.

---

### `routes-inventory.md`
**Location:** `06-api-reference/routes-inventory.md`

**Route Inventory**

**Purpose:** Comprehensive list of all API endpoints organized by surface, serving as a quick reference for API coverage.

**Key Content:**
- Route organization principles: Surface separation, auth differentiation, CSRF scope, ID formats
- Public HTML routes: Landing, registration, login, dashboard pages
- Public API routes: Health checks, JWKS, authentication endpoints
- Console JSON routes: Owner-protected endpoints for keys, groups, keychains, posts
- Gateway JSON routes: Key-protected endpoints for posts, comments, keys, feeds, groups, keychains
- For each route: Method, path, purpose, authentication requirements, required permissions, return values
- Controller/Service/Repository ownership map
- Route addition checklist

**When to Use:** Quick reference for all available endpoints. Useful for API integration and understanding endpoint coverage.

---

### `dependency-wiring.md`
**Location:** `08-implementation/dependency-wiring.md`

**Dependency Wiring Guide**

**Purpose:** PHP-DI container configuration guide specifying how to wire dependencies for all application components.

**Key Content:**
- Complete Composer dependencies: All required packages with versions
- Dependency flow summary: Visual representation of dependency relationships
- Key dependency integrations: How each major dependency (Slim, PHP-DI, JWT, Validation, CORS, CSRF, Rate Limiting, Logging, Hashing, HTTP Client) is integrated
- Container setup: PDO configuration, singleton patterns, autowiring rules
- Named parameters: Multiple logger configuration
- Dependency injection patterns: Constructor injection, factory methods

**When to Use:** Reference when setting up the DI container or understanding how dependencies are wired. Essential for dependency management and testing.

---

### `component-architecture.md`
**Location:** `04-architecture/component-architecture.md`

**Component Architecture**

**Purpose:** Breakdown of CRE8.pw into major components with their relationships and interaction patterns.

**Key Content:**
- Major components: Authentication Service, Authorization Service, Key Service, Post Service, Comment Service, Feed Service, Group Service, Keychain Service, Audit Service
- Component boundaries: What each component is responsible for
- Interaction patterns: How components communicate
- Service dependencies: Which services depend on which repositories
- Component responsibilities: Clear separation of concerns

**When to Use:** Reference when understanding system architecture at the component level. Useful for planning refactoring or understanding system boundaries.

---

### `layering-rules.md`
**Location:** `04-architecture/layering-rules.md`

**Architecture Layering Rules**

**Purpose:** Concise architectural reference reinforcing the layering rules and responsibility boundaries.

**Key Content:**
- Directory structure: Source code organization
- Layer responsibilities: Controllers (HTTP adapters), Services (business logic), Repositories (data access), Middleware (cross-cutting concerns)
- Forbidden operations: What each layer must not do
- Dual-surface separation: Console vs Gateway boundaries
- Token typing: `typ=owner` vs `typ=key` enforcement
- CSRF scope: HTML routes only

**When to Use:** Quick reference for architectural boundaries. Useful for code reviews and ensuring layering rules are followed.

---

### `codebase-inventory.md`
**Location:** `10-reference/codebase-inventory.md`

**Application Codebase Inventory**

**Purpose:** File-by-file inventory of the entire codebase, categorized by type with brief descriptions.

**Key Content:**
- Complete directory structure with file listings
- Controllers: All controller files with brief descriptions
- Services: All service files with purposes
- Repositories: All repository files with data access responsibilities
- Middleware: All middleware files with concerns
- Utilities: All utility files with helper functions
- Exceptions: All exception classes
- Configuration files: Route definitions, validation schemas, container configuration
- Database migrations: All migration files
- Templates: All template files
- Tools: All utility scripts
- File statistics: Counts by type

**When to Use:** Reference when navigating the codebase or understanding what files exist. Useful for codebase exploration and onboarding.

---

### `appendix-ssot.md`
**Location:** `12-comprehensive-reference/appendix-ssot.md`

**Appendix Single Source of Truth**

**Purpose:** Comprehensive consolidation of all appendix reference materials, serving as the definitive reference.

**Key Content:**
- Identifier encoding rules: Complete ID format specifications with conversion guidance
- Environment configuration reference: All environment variables with descriptions and examples
- Glossary and terminology: Complete term definitions
- Key capability matrix: All key types and their capabilities
- Permission matrix: All permissions and role mappings
- Complete route inventory: All endpoints with details
- Dependency wiring guide: Complete dependency reference
- Component architecture: Component breakdown and relationships
- Documentation structure: Organization of all documentation
- Complete file structure reference: All source code files
- Complete dependency reference: All Composer dependencies
- Complete environment variable reference: All configuration options
- Quick reference tables: Key capabilities, bitmasks, HTTP status codes, ID formats, middleware pipelines, rate limiting buckets, log channels, audit events
- Critical rules summary: Identifier rules, environment configuration rules, permission and authorization rules, route and API rules, dependency wiring rules, logging and security rules

**When to Use:** Use as a comprehensive reference for all appendix information. Ideal for quick lookups of reference materials and ensuring consistency with specifications.

---

## Document Categories

### Reference Matrices
- [identifier-encoding.md](../10-reference/identifier-encoding.md) — ID formats
- [key-capabilities.md](../05-authentication-authorization/key-capabilities.md) — Key capabilities
- [permissions.md](../05-authentication-authorization/permissions.md) — Permissions
- [routes-inventory.md](../06-api-reference/routes-inventory.md) — Routes

### Configuration References
- [environment-configuration.md](../10-reference/environment-configuration.md) — Environment variables
- [dependency-wiring.md](../08-implementation/dependency-wiring.md) — Dependency wiring

### Architecture References
- [component-architecture.md](../04-architecture/component-architecture.md) — Component architecture
- [layering-rules.md](../04-architecture/layering-rules.md) — Layering rules

### Codebase References
- [codebase-inventory.md](../10-reference/codebase-inventory.md) — File inventory

### Supporting Materials
- [glossary.md](../03-core-concepts/glossary.md) — Terminology
- [executive-summary.md](../01-getting-started/executive-summary.md) — Executive summary
- [docset.json](../10-reference/docset.json) — Tooling configuration
- [document-outlines.md](../10-reference/document-outlines.md) — Document structure

### Consolidated Reference
- [appendix-ssot.md](appendix-ssot.md) — All appendix information

---

## Usage Recommendations

### For Quick Reference
- Use [appendix-ssot.md](appendix-ssot.md) for comprehensive reference
- Use specific matrix documents (key-capabilities, permissions, routes-inventory) for focused lookups
- Use [environment-configuration.md](../10-reference/environment-configuration.md) for environment setup

### For Understanding Architecture
- Reference [component-architecture.md](../04-architecture/component-architecture.md) for component boundaries
- Use [layering-rules.md](../04-architecture/layering-rules.md) for layering rules

### For Codebase Navigation
- Use [codebase-inventory.md](../10-reference/codebase-inventory.md) to find files
- Reference [dependency-wiring.md](../08-implementation/dependency-wiring.md) for dependency relationships

### For Terminology
- Use [glossary.md](../03-core-concepts/glossary.md) for term definitions
- Reference [executive-summary.md](../01-getting-started/executive-summary.md) for high-level overview

---

**Note:** Appendix documents support the canonical documentation. The canon documents contain the authoritative specifications. Use appendix documents for quick reference and detailed matrices.
