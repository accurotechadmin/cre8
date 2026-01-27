# CRE8.pw LLM Coding Session Primer - Part 1: Document Set and Foundation

You are beginning a coding session with CRE8.pw, a streamlined credentialing and authorization platform designed for developers to build applications on top of. This primer will initialize your understanding of the application, its architecture, and its **reorganized documentation structure** located in `/docs/`.

## What is CRE8.pw?

CRE8.pw is a secure, hierarchical credentialing and authorization platform that provides:

- **Dual-Surface Architecture:**
  - **Owner UI Console** (`/console/*`): Administrative interface for registered Owner accounts to manage keys, monitor usage, view lineage, and perform administrative tasks
  - **Key-based API Gateway** (`/api/*`): Programmatic API accessed by third-party clients using Author Keys and Use Keys, allowing users to interact with applications without needing Owner accounts

- **Core Functionality:**
  - Users register an "Owner" account through the Owner UI Console
  - Owners mint "Author Keys" (for content creation) and "Use Keys" (for read/comment access)
  - Third-party clients use these keys to provide access to users without requiring Owner account registration
  - Full provenance tracking: every key traces back to its root Owner
  - Fine-grained access control: global permissions + post-level bitmasks

- **Design Philosophy:**
  - **Extensible and Customizable:** Can be used out-of-the-box or reconfigured (e.g., disable API Gateway, use only Owner UI)
  - **Pattern-Based Development:** New features are added by finding existing patterns and copying/editing/extending them
  - **Developer-Friendly:** Designed as a platform for building applications, not just an end-user application

## Reorganized Documentation Structure

The documentation in `/docs/` is organized by **developer workflow** across 12 numbered folders:

### Folder Organization

1. **`01-getting-started/`** - Introduction, executive summary, elevator pitches, primer prompts
2. **`02-installation/`** - Installation and setup guides
3. **`03-core-concepts/`** - Glossary, key lifecycle, post sharing workflows
4. **`04-architecture/`** - Architecture overview, components, layering rules, technical summary
5. **`05-authentication-authorization/`** - Authentication flows, authorization model, permissions, key capabilities
6. **`06-api-reference/`** - API endpoints, routes inventory, feed system, response schemas
7. **`07-data-model/`** - Database schema and data structures
8. **`08-implementation/`** - Implementation guides, dependency wiring, coding patterns
9. **`09-operations/`** - Logging, audit, observability, troubleshooting
10. **`10-reference/`** - Quick lookup tables, environment config, identifier encoding, codebase inventory
11. **`11-development/`** - Detailed codebase inventory, component breakdown, production readiness
12. **`12-comprehensive-reference/`** - SSOT documents and detailed table of contents

### Key Documents to Know

**SSOT Documents (Single Source of Truth):**
- `12-comprehensive-reference/canon-ssot.md` - Canon specifications (architecture, auth, API, data model)
- `12-comprehensive-reference/appendix-ssot.md` - Reference materials (encoding, config, matrices)
- `12-comprehensive-reference/development-ssot.md` - Development practices (codebase, patterns, workflows)

**Section TOCs:**
- `12-comprehensive-reference/toc-canon.md` - Canon document index
- `12-comprehensive-reference/toc-appendix.md` - Appendix document index
- `12-comprehensive-reference/toc-dev.md` - Development document index

**Master Indexes:**
- `/TOC.md` - Master entry point for the documentation set
- `docs/table-of-contents.md` - Complete index of all documentation with descriptions
- `/SSOT.md` - Master SSOT hub

**Keystone Documents (Critical Understanding):**
- `04-architecture/architecture-overview.md` - **[KEYSTONE]** Dual-surface architecture and layered design
- `05-authentication-authorization/authorization.md` - **[KEYSTONE]** Two-layer authorization system
- `08-implementation/implementation-guide.md` - **[KEYSTONE]** Coding patterns and conventions

## Initial Reading Plan

Given token limits, begin reading documents in this prioritized order to build foundational understanding:

### Phase 1: Foundation (Start Here - Essential Context)

1. **`01-getting-started/introduction.md`** - Platform overview, key concepts, dual-surface architecture
2. **`01-getting-started/executive-summary.md`** - High-level system summary and use cases
3. **`04-architecture/architecture-overview.md`** - **[KEYSTONE]** Request pipeline, middleware ordering, surface separation

**Why this order?** These three documents establish what CRE8.pw is, why it exists, and how requests flow through the system. This is the foundation for everything else.

### Phase 2: Core Concepts and Vocabulary

4. **`03-core-concepts/glossary.md`** - Terminology definitions (Owner, Key types, Surfaces, Permissions, etc.)
5. **`05-authentication-authorization/authorization.md`** - **[KEYSTONE]** Two-layer authorization (permissions + bitmasks)
6. **`05-authentication-authorization/authentication.md`** - Authentication flows, JWT structure, ApiKey exchange

**Why this order?** You need vocabulary before diving deep. Authorization is the core differentiator of CRE8.pw, so understanding it early is critical.

### Phase 3: Data Foundation

7. **`07-data-model/database-schema.md`** - Database schema, ID formats, lineage fields, indexes
8. **`10-reference/identifier-encoding.md`** - ID encoding rules (BINARY(16) internal, hex32 external, apub_ public IDs)
9. **`03-core-concepts/key-lifecycle.md`** - Key minting, rotation, activation/deactivation, lineage tracking

**Why this order?** Understanding the data model helps you see how concepts map to implementation. ID encoding is critical for all database operations.

### Phase 4: Implementation Patterns

10. **`08-implementation/implementation-guide.md`** - **[KEYSTONE]** Coding patterns, layer responsibilities, adding endpoints
11. **`04-architecture/layering-rules.md`** - Quick reference for architectural boundaries
12. **`06-api-reference/response-schemas.md`** - Standardized response formats and error handling

**Why this order?** Now you understand what to build and how the data works. Implementation guide shows you HOW to build it correctly.

### Phase 5: Reference (As Needed)

13. **`12-comprehensive-reference/canon-ssot.md`** - Comprehensive canonical reference (use for quick lookups)
14. **`12-comprehensive-reference/appendix-ssot.md`** - Reference materials consolidation
15. **`12-comprehensive-reference/development-ssot.md`** - Development practices consolidation

**Why this order?** SSOT documents are comprehensive but dense. Use them for reference after you have foundational understanding.

## Critical Constraints and Conventions

As you read, pay special attention to these **non-negotiable** rules that appear throughout the documentation:

### Security Rules
- **CSRF Scope:** CSRF protection applies ONLY to HTML routes, NEVER to JSON endpoints
- **Never Log Secrets:** Passwords, ApiKey secrets, refresh tokens, private keys - never appear in logs
- **Prepared Statements:** All database access uses PDO with parameter binding (no raw SQL interpolation)

### Key Rules
- **Use Key Restrictions:** Use Keys CANNOT have `posts:create` or `keys:issue` permissions
- **Permission Envelope Rule:** Child key permissions must be subset (⊆) of parent permissions
- **Permission Immutability:** Key permissions cannot change after minting (use rotation for changes)
- **Lineage Immutability:** Lineage fields (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`) never change

### Token and ID Rules
- **Token Typing:** JWT `typ` claim must be `owner` or `key` and is enforced by middleware
- **ID Formats:** 
  - Internal storage: `BINARY(16)`
  - External API: `hex32` (32-character lowercase hex)
  - Key Public IDs: `apub_...` format
- **ID Conversion:** Repositories convert hex32 ↔ BINARY(16) at database boundary

### Response Rules
- **Standardized Responses:** All JSON responses use `{ data: {...} }` or `{ error: {...} }` envelopes
- **Error Format:** Errors include `code`, `message`, `details`, and `request_id`

## Documentation Navigation Strategy

The reorganized structure follows a **developer workflow** approach:

1. **Learn** → `01-getting-started/`, `03-core-concepts/`
2. **Install** → `02-installation/`
3. **Understand** → `04-architecture/`, `05-authentication-authorization/`
4. **Use** → `06-api-reference/`, `07-data-model/`
5. **Extend** → `08-implementation/`
6. **Operate** → `09-operations/`
7. **Reference** → `10-reference/`
8. **Develop** → `11-development/`
9. **Deep Dive** → `12-comprehensive-reference/`

### Quick Lookup Guide

- **"What does X mean?"** → `03-core-concepts/glossary.md`
- **"How does authentication work?"** → `05-authentication-authorization/authentication.md`
- **"What permissions exist?"** → `05-authentication-authorization/permissions.md`
- **"What endpoints are available?"** → `06-api-reference/routes-inventory.md`
- **"How do I add a new endpoint?"** → `08-implementation/implementation-guide.md`
- **"What's the database structure?"** → `07-data-model/database-schema.md`
- **"Where is the code for X?"** → `11-development/codebase-inventory.md`
- **"I need everything about X"** → `12-comprehensive-reference/` (check relevant SSOT)

## Your Task Now

1. **Begin reading** the documents listed in Phase 1 above (`introduction.md`, `executive-summary.md`, `architecture-overview.md`)
2. **Take note** of key concepts, architectural patterns, and critical constraints
3. **Build understanding** of the dual-surface architecture and how it enables extensibility
4. **Do NOT overload yourself** - focus on foundational understanding first
5. **Bookmark** the SSOT documents for later reference when you need comprehensive lookups

## Next Steps

A second primer prompt will follow that will:
- Direct you to read the codebase structure
- Guide you to form a complete mental model connecting documentation to code
- Help you understand where to find patterns for extending the platform
- Ensure you know how to locate information when needed

A third primer may follow to verify readiness and provide a practical checklist.

**Begin reading now, starting with `01-getting-started/introduction.md`.**
