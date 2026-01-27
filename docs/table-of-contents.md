# CRE8.pw Documentation Table of Contents

**Purpose:** This document provides a comprehensive index of all documentation files in the CRE8.pw project, organized by folder with brief descriptions of each file's content and purpose.

**Master Index:** For the top-level entry point, see [`/TOC.md`](../TOC.md) and the master SSOT at [`/SSOT.md`](../SSOT.md).

**Last Updated:** 2026-01-25

---

## Table of Contents

- [Getting Started (`01-getting-started/`)](#getting-started-01-getting-started)
- [Installation (`02-installation/`)](#installation-02-installation)
- [Core Concepts (`03-core-concepts/`)](#core-concepts-03-core-concepts)
- [Architecture (`04-architecture/`)](#architecture-04-architecture)
- [Authentication & Authorization (`05-authentication-authorization/`)](#authentication--authorization-05-authentication-authorization)
- [API Reference (`06-api-reference/`)](#api-reference-06-api-reference)
- [Data Model (`07-data-model/`)](#data-model-07-data-model)
- [Implementation (`08-implementation/`)](#implementation-08-implementation)
- [Operations (`09-operations/`)](#operations-09-operations)
- [Reference (`10-reference/`)](#reference-10-reference)
- [Development (`11-development/`)](#development-11-development)
- [Comprehensive Reference (`12-comprehensive-reference/`)](#comprehensive-reference-12-comprehensive-reference)
- [Documentation Reading Order](#documentation-reading-order)
- [Quick Reference](#quick-reference)
- [Document Maintenance](#document-maintenance)

---

## Getting Started (`01-getting-started/`)

Getting started documentation provides introduction, overview, and onboarding materials for CRE8.pw.

### `introduction.md`
**Introduction and Overview** — Introduces CRE8.pw as a secure, hierarchical content-sharing platform. Provides high-level context about the system's purpose, key concepts (Principals, Surfaces, Authorization Model, Hierarchical Permission Envelope), quick start paths, and system architecture overview. Establishes the foundation for understanding the dual-surface architecture (Console/Gateway) and the key-based authentication model.

### `executive-summary.md`
**Executive Summary** — High-level overview of CRE8.pw system architecture, key features, authentication model, authorization model, and primary use cases. Provides a quick introduction for stakeholders and new team members who need a condensed understanding of the platform.

### `elevator-pitches.md`
**Elevator Pitches** — Communication materials for different audiences, emphasizing security, extensibility, and ease of integration. Provides various length elevator pitches (30-second, 2-minute, 5-minute, and a 20-minute presentation outline) highlighting problem statement, solution, core architecture, security model, extensibility, integration simplicity, use cases, and key differentiators.

### `primer-prompt-1.md`
**LLM Primer - Part 1** — Initialization primer for LLMs beginning a coding session with CRE8.pw. Establishes understanding of the application, architecture, and documentation structure.

### `primer-prompt-2.md`
**LLM Primer - Part 2** — Codebase deep dive primer for LLMs. Guides thorough exploration of the codebase and formation of a complete mental model.

### `LLM-PRIMER-1-Document-Set-and-Foundation.md`
**LLM Coding Session Primer - Part 1** — Document set orientation and foundational context for LLMs.

### `LLM-PRIMER-2-Codebase-Deep-Dive.md`
**LLM Coding Session Primer - Part 2** — Guided deep dive through the codebase and its documentation.

### `LLM-PRIMER-3-Ready-to-Code.md`
**LLM Coding Session Primer - Part 3** — Readiness checklist and reference guidance before coding.

### `README.md`
**Getting Started README** — Overview of CRE8.pw platform, key features, architecture, quick start guide, and navigation to other documentation.

### `simple-applications.md`
**Simple Applications** — Curated list of lightweight applications that map cleanly onto CRE8.pw (posts, Use Keys, comments, feeds): gated content, feedback collection, newsletters, client portals, events, API gating, lightweight membership. Explains why each is “simple” and points to post-sharing and feed docs.

---

## Installation (`02-installation/`)

Installation documentation provides step-by-step setup instructions.

### `installation-guide.md`
**Installation Guide** — Comprehensive, step-by-step guide for local installation and setup of CRE8.pw. Covers prerequisites (PHP, Composer, MariaDB, OpenSSL), initial setup, database creation/configuration, JWT key generation, environment variable setup, database migrations, starting the application, verifying installation, and a quick-start workflow for creating an owner, keys, and posts. Includes a detailed troubleshooting section.

---

## Core Concepts (`03-core-concepts/`)

Core concepts documentation defines fundamental terminology and key workflows.

### `glossary.md`
**Glossary and Terminology** — Alphabetical definitions of key terms used throughout CRE8.pw documentation: Owner, Key (Primary Author, Secondary Author, Use Key), ApiKey, JWT, JWKS, Permission Envelope, Post Bitmask, Lineage, Provenance, Surface (Console, Gateway), and other domain-specific terminology.

### `key-lifecycle.md`
**Key Lifecycle and Provenance** — Describes the complete key lifecycle: minting (Primary, Secondary, Use), lineage tracking (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`), rotation, activation/deactivation (including cascade option), Use Key limits (`use_count`, `device_limit`), and key secret handling (return once, never log). Covers immutability of lineage fields.

### `post-sharing.md`
**Post Sharing and Access Control** — Explains the core sharing workflow: post creation (default private), access grants to keys/groups/keychains, access revocation, and the interaction between permissions and bitmasks. Covers Owner admin workflows, Use Key sharing workflows, and audit event emission for access changes.

---

## Architecture (`04-architecture/`)

Architecture documentation defines system design, components, and layering rules.

### `architecture-overview.md`
**Architecture Overview** — **[KEYSTONE DOCUMENT]** Defines the dual-surface design (Console for Owners, Gateway for Keys), layered architecture (Middleware → Controller → Service → Repository), and middleware pipeline ordering for each surface. Specifies route group ownership, surface separation, responsibility boundaries between layers, and critical CSRF scope rules (HTML routes only).

### `component-architecture.md`
**Component Architecture** — Breakdown of CRE8.pw into major components: Authentication Service, Authorization Service, Key Service, Post Service, Comment Service, Feed Service, Group Service, Keychain Service, Audit Service, and their relationships. Describes component boundaries and interaction patterns.

### `layering-rules.md`
**Architecture Layering Rules** — Concise architectural reference reinforcing the layering rules (Controllers, Services, Repositories, Middleware responsibilities), directory structure, dual-surface separation, token typing, and CSRF scope. Serves as a quick reference for architectural boundaries and responsibility rules.

### `technical-summary.md`
**Technical Summary** — High-level technical overview of CRE8.pw architecture, key concepts, and design decisions.

---

## Authentication & Authorization (`05-authentication-authorization/`)

Authentication and authorization documentation covers identity, access control, and permissions.

### `authentication.md`
**Authentication and Identity** — Describes the hierarchical key-based authentication system with Owners (human, email/password) and Keys (machine, ApiKey). Covers RS256 JWT structure, JWT claims (`iss`, `sub`, `aud`, `typ`, `owner_id`, `key_id`), ApiKey exchange process, Owner registration and login flows, JWKS endpoint, refresh token rotation with replay detection, and Argon2id password hashing.

### `authorization.md`
**Authorization and Permissions** — **[KEYSTONE DOCUMENT]** Defines the two-layer authorization system: Global Permission Strings + Post-Scoped Bitmasks. Covers Permission Envelope Rule (child ⊆ parent), Key Type Restrictions (Use Keys cannot `posts:create` or `keys:issue`), Post Bitmasks (VIEW, COMMENT, MANAGE_ACCESS), Role Definitions (Owner, Author, Use), and visibility vs. access rules (404 vs 403).

### `key-capabilities.md`
**Key Capability Matrix** — Reference table mapping key types (Primary Author, Secondary Author, Use Key) to their capabilities and restrictions. Specifies which permissions each key type can have, Use Key restrictions (`posts:create`, `keys:issue`), capability inheritance rules, and issuance rules.

### `permissions.md`
**Permission Matrix** — Complete catalog of all permission strings used in CRE8.pw, organized by domain (owners, keys, posts, comments, groups, keychains). Maps roles (Owner, Author, Use) to their default permission sets, specifies permission envelope rules, and defines post access bitmasks.

---

## API Reference (`06-api-reference/`)

API reference documentation provides complete endpoint catalogs and API specifications.

### `api-reference.md`
**Routes and API Reference** — Complete inventory of all API endpoints organized by surface (Public HTML, Public API, Console JSON, Gateway JSON). Includes HTTP methods, paths, authentication requirements, request/response schemas, permission requirements, and example request/response structures for each endpoint. Maps controllers, services, and repositories to domains.

### `feed-system.md`
**Feed System** — Specifies the feed system for content discovery, including Use Key feed filtering, cursor-based pagination (`before_id`/`since_id`), visibility resolution across direct grants, groups, and keychains. Covers feed path guard requirements, authorization enforcement for feeds, and Author feed scaffolding.

### `response-schemas.md`
**Response Schemas and Error Handling** — Defines standardized JSON response envelopes (`{ data: {} }`, `{ data: [], paging: {} }`), error envelopes (`{ error: { code, message, details, request_id } }`), HTTP status code mapping (400, 401, 403, 404, 409, 422, 429, 500, 503), validation error format (`details.fields`), permission error format (`details.required`/`details.required_mask`), and generic auth failure messages.

### `routes-inventory.md`
**Route Inventory** — Comprehensive list of all API endpoints organized by surface (Public API, Console JSON, Gateway JSON, Console HTML). Includes HTTP methods, paths, authentication requirements, permission requirements, and brief descriptions. Serves as a quick reference for API coverage.

---

## Data Model (`07-data-model/`)

Data model documentation defines database schema and data structures.

### `database-schema.md`
**Data Model** — Defines the complete database schema with core tables (`owners`, `keys`, `key_public_ids`, `posts`, `comments`, `post_access`, `groups`, `group_members`, `keychains`, `keychain_members`, `refresh_tokens`, `key_devices`, `audit_events`). Specifies MariaDB 11.4.x requirements, `BINARY(16)` internal IDs, `hex32` external IDs, `utf8mb4_bin` collation, lineage fields, required indexes, and migration ordering.

---

## Implementation (`08-implementation/`)

Implementation documentation provides developer guides and patterns.

### `implementation-guide.md`
**Implementation Guide** — Provides practical implementation patterns, dependency injection wiring (PHP-DI), library choices (Slim 4, firebase/php-jwt, Respect\Validation, Monolog, Symfony rate-limiter), and coding conventions. Covers controller/service/repository patterns, validation mapping, middleware implementation, and a checklist for adding new endpoints.

### `dependency-wiring.md`
**Dependency Wiring Guide** — PHP-DI container configuration guide specifying how to wire dependencies for controllers, services, repositories, middleware, utilities, and configuration objects. Includes singleton patterns, factory methods, autowiring rules, and dependency flow diagrams for the CRE8.pw application.

---

## Operations (`09-operations/`)

Operations documentation covers logging, monitoring, and troubleshooting.

### `logging-and-audit.md`
**Logging, Audit, and Observability** — Specifies structured JSON logging with Monolog, log channel separation (`api`, `auth`, `security`, `db`, `guzzle.http`), audit events catalog, audit event structure (`<domain>:<action>` naming convention), correlation IDs, rate limiting configuration, and the critical rule: "never log secrets" (passwords, refresh tokens, key_secret, Authorization headers). Includes troubleshooting guide for common HTTP error codes.

---

## Reference (`10-reference/`)

Reference documentation provides quick lookup tables and configuration references.

### `identifier-encoding.md`
**Identifier Encoding Matrix** — Reference table specifying ID encoding rules: `BINARY(16)` internal storage, `hex32` external representation (32-character lowercase hex), `apub_...` format for key public IDs, route parameter validation rules (`{...Id}` = hex32, `{keyPublicId}` = `apub_...`), JWT claim rules, and logging/audit ID format requirements (hex32 only, never secrets).

### `environment-configuration.md`
**Environment Configuration** — Complete reference for all environment variables: application configuration (APP_NAME, APP_ENV, APP_DEBUG, APP_URL), database configuration (DSN, user, password, charset, collation), JWT configuration (private/public key paths, issuer, audiences, TTL, leeway), CORS allowlist, CSP directives, CSRF secret, rate limit configurations (GENERAL/AUTH/API buckets), HTTP client settings, logging paths/levels, and hashing parameters (Argon2id cost params).

### `document-outlines.md`
**Document Outlines** — Structural metadata for all documentation files, including section hierarchies, cross-references, and document relationships. Helps maintain consistency across documents and supports automated documentation generation and validation.

### `codebase-inventory.md`
**Application Codebase Inventory** — File-by-file inventory of the entire codebase, categorized by type (Controllers, Services, Repositories, Middleware, etc.), with brief descriptions and overall file statistics. Provides an overview of the project's physical structure and codebase organization.

### `docset.json`
**Documentation Set Configuration** — JSON configuration file for documentation tooling and docset generation. Contains metadata about the documentation structure, file relationships, and indexing information for documentation viewers and search tools.

---

## Development (`11-development/`)

Development documentation provides codebase details and production readiness checklists.

### `codebase-inventory.md`
**Codebase Inventory** — Complete inventory of all codebase components, files, conventions, and patterns for production readiness review. Includes directory structure, entry points, bootstrap and configuration details, complete listing of all PHP source files by type, configuration files, migration files, templates, tools, static assets, documentation, dependencies, conventions, environment configuration, and testing/verification tools. Highlights critical integration points and provides a production readiness checklist.

### `component-breakdown.md`
**Component Breakdown** — Extremely granular documentation for individual components, detailing their purpose, dependencies, and methods. For each method, specifies signature, purpose, parameters, return values, related endpoints, authentication/permission requirements, request attributes, body/query parameters, process steps, and exception handling. Provides method-level specifications for controllers, services, repositories, and middleware.

### `component-breakdown.json`
**Component Breakdown (JSON)** — Machine-readable counterpart to `component-breakdown.md`, offering the same detailed, structured component information in JSON format. Facilitates programmatic access and verification of component specifications, enabling automated documentation generation and validation.

### `production-readiness-issues.md`
**Production Readiness Issues** — Initial identification of potential production readiness issues and their status.

### `production-readiness-milestone.md`
**Production Readiness Milestone** — Development task list for fixing specific production readiness issues.

### `verified-production-issues.md`
**Verified Production Issues** — Audit of identified production readiness issues with verification status.

### `sdk-specification.md`
**SDK Specification** — Complete specification for the CRE8.pw Software Development Kit (SDK). The PHP SDK is currently available; Python and Go SDKs are planned for the near future. Provides comprehensive documentation for building applications on CRE8.pw, covering authentication management, API client structure, type definitions, error handling, configuration, usage examples, best practices, versioning, and testing utilities. Includes language-specific implementation details and migration guides from raw HTTP to SDK usage.

---

## Comprehensive Reference (`12-comprehensive-reference/`)

Comprehensive reference documentation provides Single Source of Truth (SSOT) documents and detailed table of contents.

### `canon-ssot.md`
**Canon Single Source of Truth** — Comprehensive consolidation of all information from the canon documents. Integrates core concepts, critical rules, architecture principles, authentication/authorization models, data model, API design, security requirements, implementation patterns, do's and don'ts, error handling, logging, key lifecycle, post sharing, environment configuration, codebase structure, development workflow, troubleshooting, and quick reference tables. Serves as the definitive reference for all canonical specifications.

### `appendix-ssot.md`
**Appendix Single Source of Truth** — Comprehensive consolidation of all information from the appendix documents. Integrates identifier encoding rules, environment configuration reference, glossary, key capability matrix, permission matrix, route inventory, dependency wiring guide, component architecture, documentation structure, complete file structure reference, dependency reference, environment variable reference, and critical rules summary.

### `development-ssot.md`
**Development Single Source of Truth** — Comprehensive consolidation of all development-related information. Integrates codebase structure, entry points and bootstrap, component architecture, development workflow, installation and setup procedures, testing and verification strategies, code conventions, file organization, dependency management, documentation structure, production readiness checklists, and troubleshooting guides. Serves as the single reference for all development practices, codebase structure, installation procedures, and development workflows.

### `toc-canon.md`
**Canon Documentation Table of Contents** — Detailed index of all canonical documentation files with comprehensive descriptions.

### `toc-appendix.md`
**Appendix Documentation Table of Contents** — Detailed index of all appendix documentation files with comprehensive descriptions.

### `toc-dev.md`
**Development Documentation Table of Contents** — Detailed index of all development documentation files with comprehensive descriptions.

---

## Documentation Reading Order

For new developers or LLMs onboarding to CRE8.pw, follow this recommended reading sequence:

### Phase 1: Foundation & Vision
1. `01-getting-started/introduction.md`
2. `01-getting-started/executive-summary.md`
3. `04-architecture/component-architecture.md`

### Phase 2: Core Architecture & Vocabulary
4. `04-architecture/architecture-overview.md` **[KEYSTONE]**
5. `03-core-concepts/glossary.md`

### Phase 3: Data & Identity Foundation
6. `07-data-model/database-schema.md`
7. `10-reference/identifier-encoding.md`

### Phase 4: Identity, Access Control & Lifecycle
8. `05-authentication-authorization/authentication.md`
9. `05-authentication-authorization/authorization.md` **[KEYSTONE]**
10. `03-core-concepts/key-lifecycle.md`

### Phase 5: Core Features & API Surface
11. `03-core-concepts/post-sharing.md`
12. `06-api-reference/feed-system.md`
13. `06-api-reference/api-reference.md`

### Phase 6: Implementation & Operations
14. `08-implementation/implementation-guide.md`
15. `06-api-reference/response-schemas.md`
16. `09-operations/logging-and-audit.md`

### Phase 7: Reference Materials
17. `05-authentication-authorization/key-capabilities.md`
18. `05-authentication-authorization/permissions.md`
19. `06-api-reference/routes-inventory.md`
20. `08-implementation/dependency-wiring.md`
21. `10-reference/environment-configuration.md`
22. `04-architecture/layering-rules.md`
23. `10-reference/codebase-inventory.md`
24. `10-reference/document-outlines.md`

### Phase 8: Development & Setup
25. `02-installation/installation-guide.md`
26. `11-development/codebase-inventory.md`
27. `11-development/component-breakdown.md`
28. `01-getting-started/elevator-pitches.md`

### Phase 9: SSOT Documents (Comprehensive Reference)
29. `12-comprehensive-reference/canon-ssot.md` — Canon SSOT
30. `12-comprehensive-reference/appendix-ssot.md` — Appendix SSOT
31. `12-comprehensive-reference/development-ssot.md` — Development SSOT

---

## Quick Reference

### SSOT Documents
- **Canon SSOT:** `12-comprehensive-reference/canon-ssot.md` — Consolidates all canonical specifications
- **Appendix SSOT:** `12-comprehensive-reference/appendix-ssot.md` — Consolidates all reference materials
- **Dev SSOT:** `12-comprehensive-reference/development-ssot.md` — Consolidates all development practices

### Key Documents by Purpose
- **Architecture:** `04-architecture/architecture-overview.md` + `04-architecture/layering-rules.md`
- **Authentication:** `05-authentication-authorization/authentication.md`
- **Authorization:** `05-authentication-authorization/authorization.md`
- **API Reference:** `06-api-reference/api-reference.md` + `06-api-reference/routes-inventory.md`
- **Data Model:** `07-data-model/database-schema.md`
- **Implementation:** `08-implementation/implementation-guide.md` + `08-implementation/dependency-wiring.md`
- **SDK & Integration:** `11-development/sdk-specification.md` — Official SDK for building applications
- **Codebase Structure:** `11-development/codebase-inventory.md` + `10-reference/codebase-inventory.md`
- **Component Details:** `11-development/component-breakdown.md` + `11-development/component-breakdown.json`
- **Installation:** `02-installation/installation-guide.md`
- **Communication:** `01-getting-started/elevator-pitches.md`

### Document Statistics
- **Getting Started:** 10 files
- **Installation:** 1 file
- **Core Concepts:** 3 files
- **Architecture:** 4 files
- **Authentication & Authorization:** 4 files
- **API Reference:** 4 files
- **Data Model:** 1 file
- **Implementation:** 2 files
- **Operations:** 1 file
- **Reference:** 5 files
- **Development:** 5 files
- **Comprehensive Reference:** 6 files
- **Total Documentation Files:** 46 files

---

## Document Maintenance

**Note:** This TOC is maintained as part of the CRE8.pw documentation. When adding new documents, update `/TOC.md`, `/SSOT.md`, and this file to include them in the appropriate section with a brief description. The TOC should accurately reflect the current state of all documentation files in the project.

**Last Verified:** 2026-01-25
