Below is a **single linear runbook** you can paste sequentially into a coding LLM, **Prompt 1 → Prompt 52**, covering **all milestones**.

Each prompt assumes the previous prompt’s code drop is present. I’ve written them so the LLM always knows:

* the **current baseline** (“assume prior prompts exist”)
* the **scope** (what to implement now)
* the **constraints** (surfaces, IDs, tokens, no-secrets logging)
* the **deliverables** (files + scripts + smoke tests)

---
1) Product Purpose & Core Model (What CRE8.pw Is)
CRE8.pw is a secure content creation and sharing platform built around a hierarchical key system with provenance, enabling controlled sharing via fine-grained permissions + per-post bitmasks and dual surfaces (Console + Gateway).

Principals are Owners (human) and Keys (machine); Keys are Primary Author, Secondary Author, or Use keys with distinct capabilities and delegation rules.

2) Surfaces, Pipelines, and Layered Architecture
The system is a single Slim 4 application with strict layering: Middleware → Controllers → Services → Repositories, each with defined responsibilities and prohibitions.

Four pipelines are canonical:

Public API (no auth): /health, /.well-known/jwks.json, /api/auth/*, /console/owners, /console/login

Console JSON (Owner JWT, typ=owner)

Gateway JSON (Key JWT, typ=key)

Console HTML (CSRF applied to HTML only)

CSRF applies only to HTML routes; JSON endpoints never require CSRF (even if some legacy headers are present).

3) Identity & Authentication (JWT + ApiKey)
JWTs are RS256-signed, include typ claim for surface separation, and must validate iss, aud, exp, and nbf. Owner tokens include owner_id; Key tokens include key_id (and optionally key_public_id).

ApiKey exchange uses Authorization: ApiKey <public_id>:<secret> and returns access + refresh tokens; failures are always generic 401 to avoid credential enumeration.

Refresh tokens are single-use rotated with replay detection (rotated_at), stored hashed, and replay triggers a security log event.

JWKS endpoint publishes public keys with kid rotation overlap to allow safe key rotation without invalidating current tokens.

4) Authorization Model (Permissions + Bitmasks + Visibility Rules)
Two-layer authz: global permission strings + post-scoped bitmasks are both required for post-scoped actions (e.g., comment requires comments:write + COMMENT mask).

Use Keys can never have posts:create or keys:issue (absolute restriction), and all child keys must be a subset of parent permissions (envelope rule).

Visibility vs access: return 404 when a principal lacks VIEW (to hide existence), and 403 when the resource is visible but action is forbidden.

5) Data Model & Identifier Rules
All internal IDs use BINARY(16); external IDs are hex32 strings; key_public_id is apub_... and only used for ApiKey exchange.

Route params ending in Id are always hex32; {keyPublicId} is the only apub-form parameter.

The schema includes owners, keys, key_public_ids, posts, comments, post_access, groups, group_members, keychains, keychain_members, refresh_tokens, audit_events with required indexes and migration order constraints.

6) Key Lifecycle & Provenance
Primary keys are minted by Owners; Secondary/Use keys are minted by Author keys with lineage propagation and envelope enforcement. Key permissions are immutable; changes require rotation.

Rotation preserves lineage while retiring the old key; deactivation can cascade to descendants for bulk revocation.

Use Keys can have optional use_count or device_limit constraints enforced at auth time.

7) Posts, Sharing, Comments, and Feeds
Posts are created by author keys (posts:create), and are private by default until access is granted via post_access.

Access grants are stored as bitmasks for key/group targets; combined permission + mask checks govern read/comment/manage access.

Use Key sharing workflow: create post → mint use key → grant access → recipient exchanges ApiKey → read/comment; use limits stop further exchange if exhausted.

Feeds: Use Key feed requires posts:read + VIEW, and path {useKeyId} must match JWT key_id or return 404. Cursor pagination uses before_id/since_id.

8) Response & Error Contracts
All JSON endpoints return a standardized success envelope or error envelope, with 422 including details.fields. Standard error taxonomy maps codes to statuses (401, 403, 404, 422, 429, etc.).

9) Logging, Audit, and Observability
Logs must be structured JSON, use defined channels, and never include secrets (passwords, key secrets, refresh tokens, private keys).

Audit events are required for owner/key lifecycle, groups/keychains, posts/access, and security events like refresh replay detection.

10) Environment Configuration ([environment-configuration.md](../10-reference/environment-configuration.md))
A full .env specification governs app, DB, JWT, CORS/CSP/CSRF, rate limits, logging, hashing, and HTTP client settings. Startup must fail fast if critical configuration is missing or invalid.

11) Developer Roadmap, Milestones, and Test Philosophy ([11-development/](../11-development/))
The development folder contains production readiness checklists, codebase inventories, and component breakdowns, ensuring the product is implemented safely and in alignment with the canonical docs.

The roadmap is an execution plan to implement everything: skeleton, env hardening, schema/migrations, middleware, auth, permissions, key lifecycle, posts/comments/groups/keychains, feeds, UI, logging/audit, and compliance tests.

The prompt runbooks (prompt_1-58.md, prompt_milestones.md) provide a step-by-step LLM implementation sequence, enforcing specs like CSRF HTML-only, token typing, and ID formats at each milestone.

The smoke test constraints file defines a reusable “smoke test contract” with standardized questions covering reachability, correctness, security, observability, and basic performance sanity.

UI deliverables are mapped into a full page inventory for Owner Console and a Gateway Client example app, with UX hints for each endpoint and flow (including sharing).

12) Total Understanding (Condensed Executive Summary)
CRE8.pw is a security-first, provenance-aware content platform that separates Owner (human) and Key (machine) surfaces. Owners mint Primary keys; keys mint descendant keys within a permission envelope, enabling controlled sharing with precise post-level access control. Permissions are immutable; access is enforced by global permissions + per-post masks, with strict 404/403 visibility rules. RS256 JWTs with typ and aud enforce surface separation, while refresh tokens are single-use rotated to prevent replay. All identifiers are BINARY(16) internally and hex32 externally, with apub_... only for ApiKey exchange. The platform emphasizes operational safety: standardized response envelopes, strict logging/audit rules, rate limits, CORS, HTTPS/HSTS, and CSRF only for HTML. The dev docs outline a granular, test-driven roadmap with smoke tests and compliance checks to ensure the implementation matches the canonical documentation and remains extensible and secure.
---
