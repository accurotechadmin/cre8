# Glossary

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical

**Scope:** Alphabetical definitions of all key terms used in CRE8.pw documentation.

---

## A

**Access Token**
Short-lived JWT (default 900 seconds) used to authenticate requests to protected endpoints. Contains principal identity, roles, and permissions.

**ADMIN (Bitmask Preset)**
Post access bitmask value 0x0B (VIEW + COMMENT + MANAGE_ACCESS). Grants full post interaction capabilities.

**ApiKey**
Authentication credential consisting of `key_public_id` (`apub_...`) and `key_secret`. Exchanged for access + refresh tokens via `POST /api/auth/exchange`.

**Argon2id**
Password and API key secret hashing algorithm. Industry-standard for credential storage.

**Audit Event**
Append-only record of privileged actions stored in `audit_events` table. Includes actor, subject, action, metadata, and timestamp.

**Author Key**
Machine principal that can create posts and mint child keys. Two types: Primary (root) and Secondary (delegated).

---

## B

**Bearer Token**
HTTP authentication scheme using access tokens. Format: `Authorization: Bearer <access_token>`

**BINARY(16)**
Internal database storage format for primary keys. Never exposed directly; converted to hex32 for external use.

**Bitmask**
Integer representation of post-scoped permissions. Each bit represents a specific capability (VIEW, COMMENT, MANAGE_ACCESS).

---

## C

**COMMENT (Bitmask)**
Bit 1 (0x02) of post access bitmask. Grants ability to create comments on a post.

**Console**
Human-facing surface of CRE8.pw consisting of HTML pages and Owner-protected JSON endpoints. Entry point for Owners to register, login, and manage keys/groups/posts.

**Console HTML**
Browser-facing routes (`/`, `/console/register`, `/console/login`, `/console/dashboard`) that use CSRF protection.

**Console JSON**
Owner-protected JSON API endpoints under `/console/*` that use Bearer JWT auth. No CSRF required.

**CORS**
Cross-Origin Resource Sharing. Security mechanism controlled via env variables (`CORS_ALLOWED_ORIGINS`, etc.).

**CSRF**
Cross-Site Request Forgery protection. Applied ONLY to Console HTML routes. JSON endpoints never require CSRF.

---

## D

**Device Limit**
Optional constraint on Use Keys limiting the number of distinct devices that can use the key. Enforced by tracking device fingerprints.

**DI (Dependency Injection)**
Pattern using PHP-DI container to wire dependencies. All components use constructor injection.

---

## E

**Envelope Rule**
Authorization constraint: child key permissions must be a subset (⊆) of parent key permissions.

**External Keychain**
Keychain created via Gateway API (not owner-scoped). Can be managed by Keys with `keychains:manage` permission.

---

## F

**Feed**
Ordered list of posts visible to a principal. Use Key Feed shows posts accessible to a specific Use Key.

---

## G

**Gateway**
Machine-facing JSON API surface under `/api/*`. Authenticated via Key JWTs. Entry point for programmatic post creation, commenting, and key minting.

**Group**
Owner-created collection of Keys. Used for bulk post access grants.

---

## H

**hex32**
External encoding format for internal IDs: 32-character lowercase hexadecimal string. Used in routes, JSON responses, JWT claims, and logs.

**HSTS**
HTTP Strict Transport Security. Header forcing browsers to use HTTPS.

---

## I

**initial_author_key_id**
Lineage field referencing the root Primary Author Key. Immutable once set. Enables full provenance tracking.

**INTERACT (Bitmask Preset)**
Post access bitmask value 0x03 (VIEW + COMMENT). Grants read and comment capabilities.

**issued_by_key_id**
Lineage field identifying the Key that minted this Key. NULL for Primary Author Keys.

---

## J

**JWT (JSON Web Token)**
RS256-signed access token containing principal identity, roles, and permissions. Used for authentication on all protected endpoints.

**JWKS**
JSON Web Key Set. Public endpoint at `/.well-known/jwks.json` publishing RS256 public keys for token verification.

---

## K

**Keychain**
Collection of Keys for combined authorization. Can be owner-managed (Console) or external (Gateway).

**key_id**
Internal Key identifier (BINARY(16) in DB, hex32 externally). Used in JWTs, routes, logs. Primary identifier for Key principals.

**key_public_id**
External Key identifier in format `apub_...`. Used ONLY for ApiKey exchange. Never used in route params or fields named `*_id`.

**key_secret**
Secret component of ApiKey. Stored as Argon2id hash. Never logged or returned after initial mint.

---

## L

**Lineage**
Provenance chain of key issuance tracked via `issued_by_key_id`, `parent_key_id`, and `initial_author_key_id`.

---

## M

**MANAGE_ACCESS (Bitmask)**
Bit 3 (0x08) of post access bitmask. Grants ability to manage post access grants (add/remove groups/keys).

**Middleware Pipeline**
Ordered chain of PSR-15 middleware processing requests. Four variants: Public API, Console JSON, Gateway JSON, Console HTML.

---

## O

**Owner**
Human principal authenticated via password. Can mint Primary Author Keys and manage groups/keychains via Console. JWT has `typ=owner` and includes `owner_id`.

**owner_id**
Internal Owner identifier (BINARY(16) in DB, hex32 externally). Used in Owner JWTs, rate limiting, and audit logs.

---

## P

**parent_key_id**
Lineage field identifying the immediate parent Key. NULL for Primary Author Keys.

**Permission**
String identifier (e.g., `posts:create`, `keys:issue`) granting a specific capability. Immutable once minted.

**Permission Envelope**
See Envelope Rule.

**Permission Mask**
See Bitmask.

**post_access**
Database table storing post-scoped access grants. Each record grants a target (group or key) a permission_mask for a specific post.

**Primary Author Key**
Root machine principal minted by Owner. Can create posts and mint child keys. Has no parent in lineage tree.

**Principal**
Authentication identity. Either an Owner (human, `typ=owner`) or a Key (machine, `typ=key`).

---

## R

**Rate Limiting**
Request throttling using Symfony rate-limiter. Keyed by IP (public), `owner_id` (Console JSON), or `key_id` (Gateway JSON).

**READ_ONLY (Bitmask Preset)**
Post access bitmask value 0x01 (VIEW only). Grants read-only post access.

**Refresh Token**
Long-lived opaque token (default 30 days) for obtaining new access tokens. Single-use; rotated on every refresh. Stored hashed in `refresh_tokens` table.

**Replay Detection**
Security mechanism preventing reuse of rotated refresh tokens.

**Repository**
Data access layer using PDO prepared statements. Converts between BINARY(16) and hex32. No business logic.

**Rotation (Key)**
Key lifecycle operation creating a new key to replace an old one. Old key retired, new key issued. Lineage tracked via `rotated_from_id`/`rotated_to_id`.

**Rotation (Refresh Token)**
Single-use refresh token pattern. Old token marked rotated, new token issued. Detected replay is a security event.

**Role**
Coarse permission grouping (`owner`, `author`, `use`). Resolved to explicit permissions at token issuance.

**RS256**
RSA + SHA-256 JWT signing algorithm. Requires private/public key pair.

---

## S

**Secondary Author Key**
Delegated machine principal minted by Primary or Secondary Author Key. Can create posts and mint child keys (within permission envelope).

**Service**
Business logic layer. Enforces permissions, manages transactions, emits audit events. Calls repositories.

**Surface**
CRE8.pw has two surfaces: Console (Owner-facing) and Gateway (Key-facing). Separated by route groups and JWT token typing.

**sub (Subject)**
JWT claim identifying the principal. Format: `owner:<owner_id>` or `key:<key_id>`.

---

## T

**Token Typing**
JWT `typ` claim enforcing surface separation. `typ=owner` for Console JSON, `typ=key` for Gateway JSON. Middleware enforces at surface boundaries.

**typ (Token Type)**
Custom JWT claim. Values: `owner` | `key`. Enforced by JwtOwnerMiddleware / JwtKeyMiddleware.

---

## U

**Use Count**
Optional constraint on Use Keys limiting total number of uses (1-time, N-times, or unlimited). Tracked per Use Key.

**Use Key**
Restricted machine principal for read/comment interactions. Cannot create posts or mint keys. Minted by Author Keys with optional use count and device limits.

---

## V

**Validation Middleware**
PSR-15 middleware applying Respect\Validation rules. Configured per-route in `config/validation.php` keyed by `"METHOD /pattern"`.

**VIEW (Bitmask)**
Bit 0 (0x01) of post access bitmask. Grants ability to view/read a post.

---
