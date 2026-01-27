# CRE8.pw LLM Coding Session Primer - Part 1: Initialization

You are beginning a coding session with CRE8.pw, a streamlined credentialing and authorization platform designed for developers to build applications on top of. This prompt will initialize your understanding of the application, its architecture, and its documentation structure.

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

## Documentation Structure

The documentation is organized by developer workflow across 12 folders:

1. **`01-getting-started/`** - Introduction, executive summary, elevator pitches, primer prompts
2. **`02-installation/`** - Installation and setup guides
3. **`03-core-concepts/`** - Glossary, key lifecycle, post sharing
4. **`04-architecture/`** - Architecture overview, components, layering rules
5. **`05-authentication-authorization/`** - Authentication, authorization, permissions
6. **`06-api-reference/`** - API endpoints, routes, feed system, response schemas
7. **`07-data-model/`** - Database schema and data structures
8. **`08-implementation/`** - Implementation guides and dependency wiring
9. **`09-operations/`** - Logging, audit, observability
10. **`10-reference/`** - Quick lookup tables, environment config, identifier encoding
11. **`11-development/`** - Codebase inventory, component breakdown, production readiness
12. **`12-comprehensive-reference/`** - SSOT documents and detailed table of contents

**Key Documents to Know:**
- **SSOT Documents:** Three comprehensive Single Source of Truth documents consolidate all information:
  - [canon-ssot.md](../12-comprehensive-reference/canon-ssot.md) — Canon specifications
  - [appendix-ssot.md](../12-comprehensive-reference/appendix-ssot.md) — Reference materials
  - [development-ssot.md](../12-comprehensive-reference/development-ssot.md) — Development practices
- **Section TOCs:** Detailed indexes for each doc set:
  - [toc-canon.md](../12-comprehensive-reference/toc-canon.md)
  - [toc-appendix.md](../12-comprehensive-reference/toc-appendix.md)
  - [toc-dev.md](../12-comprehensive-reference/toc-dev.md)
- **Master TOC:** [/TOC.md](../../TOC.md) — Master entry point for the documentation set
- **Full Documentation Index:** [table-of-contents.md](../table-of-contents.md) — Complete index of all documentation
- **Master SSOT:** [/SSOT.md](../../SSOT.md) — SSOT hub

## Initial Reading Plan

Given token limits, begin reading documents in this prioritized order to build foundational understanding:

### Phase 1: Foundation (Start Here)
1. **[introduction.md](introduction.md)** — Platform overview and key concepts
2. **[executive-summary.md](executive-summary.md)** — Executive summary
3. **[architecture-overview.md](../04-architecture/architecture-overview.md)** — **[KEYSTONE]** Dual-surface architecture and layered design

### Phase 2: Core Concepts
4. **[glossary.md](../03-core-concepts/glossary.md)** — Terminology definitions
5. **[authorization.md](../05-authentication-authorization/authorization.md)** — **[KEYSTONE]** Two-layer authorization system
6. **[authentication.md](../05-authentication-authorization/authentication.md)** — Authentication flows and JWT structure

### Phase 3: Implementation Foundation
7. **[database-schema.md](../07-data-model/database-schema.md)** — Database schema and ID formats
8. **[identifier-encoding.md](../10-reference/identifier-encoding.md)** — ID encoding rules (BINARY(16), hex32, apub_)
9. **[implementation-guide.md](../08-implementation/implementation-guide.md)** — Coding patterns and conventions

### Phase 4: Reference (As Needed)
10. **[canon-ssot.md](../12-comprehensive-reference/canon-ssot.md)** — Comprehensive canonical reference (use for quick lookups)

## Critical Constraints and Conventions

As you read, pay special attention to these **non-negotiable** rules:

- **CSRF Scope:** CSRF protection applies ONLY to HTML routes, NEVER to JSON endpoints
- **Use Key Restrictions:** Use Keys CANNOT have `posts:create` or `keys:issue` permissions
- **Permission Envelope Rule:** Child key permissions must be subset (⊆) of parent permissions
- **Permission Immutability:** Key permissions cannot change after minting (use rotation)
- **Lineage Immutability:** Lineage fields (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`) never change
- **Token Typing:** JWT `typ` claim must be `owner` or `key` and is enforced by middleware
- **ID Formats:** Internal = BINARY(16), External = hex32, Key Public IDs = apub_...
- **Never Log Secrets:** Passwords, ApiKey secrets, refresh tokens, private keys
- **Prepared Statements:** All database access uses PDO with parameter binding
- **Standardized Responses:** All JSON responses use `{ data: {...} }` or `{ error: {...} }` envelopes

## Your Task Now

1. **Begin reading** the documents listed in Phase 1 above
2. **Take note** of key concepts, architectural patterns, and critical constraints
3. **Build understanding** of the dual-surface architecture and how it enables extensibility
4. **Do NOT overload yourself** - focus on foundational understanding first

## Next Steps

A second primer prompt will follow that will:
- Direct you to read the codebase structure
- Guide you to form a complete mental model
- Help you understand where to find patterns for extending the platform
- Ensure you know how to locate information when needed

**Begin reading now, starting with [introduction.md](introduction.md).**
