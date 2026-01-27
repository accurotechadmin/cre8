# CRE8.pw Executive Summary

## Overview

This document set provides a single, authoritative reference for the CRE8.pw platform.

---

## Document Set Structure

### Main Documents

1. [introduction.md](introduction.md) — What is CRE8.pw?
2. [architecture-overview.md](../04-architecture/architecture-overview.md) — System Architecture
3. [authentication.md](../05-authentication-authorization/authentication.md) — Auth System (RS256 JWT, JWKS, Refresh)
4. [authorization.md](../05-authentication-authorization/authorization.md) — Permission System & Bitmasks
5. [api-reference.md](../06-api-reference/api-reference.md) — Complete API Catalog
6. [feed-system.md](../06-api-reference/feed-system.md) — Content Access & Feeds
7. [database-schema.md](../07-data-model/database-schema.md) — Database Schema Contract
8. [key-lifecycle.md](../03-core-concepts/key-lifecycle.md) — Key Management & Lineage
9. [post-sharing.md](../03-core-concepts/post-sharing.md) — Content Sharing Model
10. [implementation-guide.md](../08-implementation/implementation-guide.md) — Developer Manual
11. [logging-and-audit.md](../09-operations/logging-and-audit.md) — Operational Concerns

### Reference Documents

- [identifier-encoding.md](../10-reference/identifier-encoding.md) — ID formats (hex32, apub_, BINARY(16))
- [environment-configuration.md](../10-reference/environment-configuration.md) — Complete .env reference
- [glossary.md](../03-core-concepts/glossary.md) — Alphabetical term definitions

### Developer Tools

- [sdk-specification.md](../11-development/sdk-specification.md) — Complete SDK specification for building applications on CRE8.pw (PHP SDK available now; Python and Go SDKs planned)

---

## Key Design Decisions

1. **Keyrings:** Simplified to groups + keychains; "Keyring Key" concept via group-based access
2. **Master Keys:** Removed entirely; Primary Author Keys are the root
3. **Feed System:** Preserved with proper security gating
4. **Chrome Extension:** Out of scope (third-party concern)
5. **Audience Keys:** Removed (too complex for this iteration)
6. **CSRF:** HTML forms ONLY; never on JSON endpoints
7. **Permissions:** Full-featured robust set combining best of all eras
8. **Post Sharing:** Use Keys with permissions + use counts + device limits
9. **Rate Limiting:** Local memory + DB only (Symfony rate-limiter, no Redis/Memcached)

---

## Core System Concepts

### Principals

**Owners (Human)**
- Authenticate via password → Owner JWT (`typ=owner`)
- Access Console (HTML + JSON)
- Mint Primary Author Keys
- Manage groups, keychains, view downstream lineage

**Keys (Machine)**
- Authenticate via ApiKey → Key JWT (`typ=key`)
- Access Gateway JSON API
- Three types:
  - **Primary Author Key:** Root; mint children; create posts
  - **Secondary Author Key:** Delegated; mint children (subset); create posts
  - **Use Key:** Interaction only; no minting; no posting

### Surfaces

**Console**
- HTML pages (CSRF protected): landing, register, login, dashboard
- JSON endpoints (Owner JWT, no CSRF): key/group/keychain/post management

**Gateway**
- JSON API (Key JWT, no CSRF): posting, commenting, key minting, feeds

### Authorization Model

**Two-Layer Checks:**
1. **Permission strings:** `posts:create`, `keys:issue`, `comments:write`, etc.
2. **Post bitmasks:** VIEW (0x01), COMMENT (0x02), MANAGE_ACCESS (0x08)

**Hierarchical Envelope:**
- Child key permissions ⊆ parent key permissions
- Use Keys NEVER get `posts:create` or `keys:issue`
- Permissions immutable once minted

### Key Lifecycle

1. Owner registers → mints Primary Author Key
2. Primary Author Key → mints Secondary or Use Keys
3. Secondary Author Key → mints Secondary or Use Keys (within envelope)
4. Use Keys → interact with posts (if granted VIEW/COMMENT mask)

### Provenance Tracking

Every key has lineage fields:
- `issued_by_key_id` - immediate issuer
- `parent_key_id` - parent in chain
- `initial_author_key_id` - root Primary (immutable)

Enables:
- Full accountability
- Downstream lineage viewing
- Bulk disablement (disable key + all descendants)

### Post Sharing Model

1. Author creates post (attached to Author Key)
2. Author mints Use Key with desired permissions
3. Author grants Use Key access to post (post_access with bitmask)
4. Use Key bearer can read/comment based on mask
5. Optional: use count limits (1-time, N-times) + device limits

---

## Technology Stack

- **PHP:** 8.3+
- **Framework:** Slim 4.15+
- **DI:** PHP-DI 7.1+
- **Database:** MariaDB 11.4.x (utf8mb4_bin)
- **JWT:** firebase/php-jwt 6.11+ (RS256)
- **Validation:** Respect\Validation 2.4+
- **Logging:** Monolog 3.9+
- **HTTP Client:** Guzzle 7.10+
- **Rate Limiting:** Symfony rate-limiter 7.3+ + cache 7.3+
- **CORS:** neomerx/cors-psr7 3.0+
- **CSRF:** slim/csrf 1.5+ (HTML routes only)
- **Security:** Argon2id for passwords/secrets, RS256 for JWT

---

## Reference Materials

Supplementary reference documents support the core specifications:

### 1. Permission Matrix ([permissions.md](../05-authentication-authorization/permissions.md))
- Complete permission catalog (Owner + Key scoped)
- Post access bitmask definitions (VIEW, COMMENT, MANAGE_ACCESS)
- Combined authorization rules
- Role definitions
- Permission envelope rules

### 2. Key Capability Matrix ([key-capabilities.md](../05-authentication-authorization/key-capabilities.md))
- Capability table: Owner vs Primary vs Secondary vs Use
- Key type definitions with lineage rules
- Issuance workflows (mint primary, secondary, use)
- Permission envelope validation examples
- Groups and keychains

### 3. Route Inventory ([routes-inventory.md](../06-api-reference/routes-inventory.md))
- Complete route catalog organized by surface
- Public HTML, Public API, Console JSON, Gateway JSON
- Each route with: method, path, purpose, auth, required permissions
- Controller/Service/Repository ownership map
- Route addition checklist

### 4. Glossary ([glossary.md](../03-core-concepts/glossary.md))
- Alphabetical term definitions (A–Z)
- Quick reference tables:
  - Permission strings
  - Post access bitmasks
  - Identifier formats
- Cross-references to authoritative documents

### 5. Document Outlines ([document-outlines.md](../10-reference/document-outlines.md))
- Section-by-section breakdown for all core documents
- Structural metadata and relationships
- Ensures comprehensive coverage and consistency

---

## Document Writing Philosophy

Each document will be written to serve two audiences:

1. **Users:** Clear, practical guidance on using CRE8.pw features
2. **Developers:** Precise implementation specifications with SSoT boundaries

**Principles:**
- Written as if application is mature and complete
- Each doc has clear SSoT ownership (no duplication)
- Task-based navigation (I want to X → read docs Y, Z)
- Consistent terminology (refer to Glossary)
- Concrete examples with real formats
- Security-first mindset

---

