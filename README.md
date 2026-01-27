# CRE8.pw

**A versatile authentication and authorization credentialing platform.** Drop it into your application as an access layer: Owners register and mint Keys; holders of those Keys get appropriate API access without creating user accounts. Primary and Secondary Author Keys create content and mint Use Keys; Use Keys act as access credentials for specific posts.

---

## Table of Contents

- [Quick Overview](#quick-overview)
- [What CRE8.pw Provides](#what-cre8pw-provides)
- [Dual-Surface Architecture](#dual-surface-architecture)
- [Key Concepts](#key-concepts)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Extensibility & Customization](#extensibility--customization)
- [API at a Glance](#api-at-a-glance)
- [Configuration](#configuration)
- [Documentation](#documentation)
- [Next Steps](#next-steps)

---

## Quick Overview

| Topic | Description |
|-------|-------------|
| **What** | Hierarchical key-based auth/authz platform with full provenance tracking |
| **Surfaces** | **Console** (Owner UI + JSON) and **Gateway** (Key-gated JSON API) |
| **Stack** | PHP 8.3+, Slim 4, MariaDB, RS256 JWT, Argon2id |
| **Install** | `composer install` → configure `.env` → `php tools/db/migrate.php up` → `php -S localhost:8000 -t public` |

Out of the box, CRE8.pw gives you: Owner registration and login, Primary Author Key minting from the Console, and a Gateway API where Keys authenticate via ApiKey→JWT. Authors create Posts and mint Use Keys; they grant those Use Keys access to specific posts. Recipients use a Use Key to read and comment **without registering**. The system is **customizable and extensible**: find existing patterns in the codebase and copy, adapt, and extend them to add new modules.

---

## What CRE8.pw Provides

- **Dual-surface system**
  - **Owner UI Console** (`/console/*`): HTML pages (landing, register, login, dashboard) and JSON endpoints for key/group/keychain/post management. Owner JWT (`typ=owner`).
  - **Key-gated API Gateway** (`/api/*`): JSON-only API for posting, commenting, key issuance, feeds. Key JWT (`typ=key`) via ApiKey exchange.
- **Hierarchical keys**
  - **Primary Author Keys**: Root keys; create posts and mint Secondary Author Keys or Use Keys. Minted by Owners in the Console.
  - **Secondary Author Keys**: Delegated; create posts and mint child keys within a **permission envelope** (child permissions ⊆ parent).
  - **Use Keys**: Read and comment only; **cannot** create posts or mint keys. Act as “access passwords” to posts you explicitly grant.
- **Fine-grained access**
  - **Permission strings** (e.g. `posts:create`, `keys:issue`, `comments:write`) plus **post-level bitmasks** (VIEW, COMMENT, MANAGE_ACCESS). Both apply where relevant.
- **Provenance & control**
  - Every key has lineage (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`). Bulk revocation: disable a key and all descendants.
- **Security**
  - RS256 JWT, Argon2id for passwords/secrets, HTTPS/HSTS, CORS allowlist, rate limiting, CSRF on **HTML routes only** (never on JSON), prepared statements throughout.

---

## Dual-Surface Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CRE8.pw (Single Slim 4 App)               │
├──────────────────────────────┬──────────────────────────────────┤
│         CONSOLE              │            GATEWAY                │
│   (Owner Interface)          │      (Key API Interface)          │
├──────────────────────────────┼──────────────────────────────────┤
│ HTML: /, register, login,    │ JSON: /api/posts, /api/comments,  │
│       dashboard, keys,       │       /api/keys/*, /api/feed,     │
│       groups, keychains,     │       /api/groups, /api/keychains │
│       posts                  │                                  │
│ JSON: /console/keys/*,       │ Auth: Key JWT (typ=key)           │
│       /console/groups/*,     │       via ApiKey exchange         │
│       /console/posts/*, …    │                                  │
│ Auth: Owner JWT (typ=owner)  │ CSRF: Not used (token-based)      │
│ CSRF: HTML forms only        │                                  │
└──────────────────────────────┴──────────────────────────────────┘
                                      │
                    Middleware → Controller → Service → Repository → MariaDB
```

- **Console**: Owners manage keys, groups, keychains, and post access. Dashboard and JSON endpoints.
- **Gateway**: Clients use Keys (ApiKey → JWT) to create posts, comment, mint Use Keys, grant/revoke access, and use feeds. No Owner account required for Key holders.

---

## Key Concepts

### Principals

| Principal | Auth | Surface | Capabilities |
|-----------|------|---------|--------------|
| **Owner** | Email + password → Owner JWT | Console (HTML + JSON) | Register, login, mint Primary Author Keys, manage keys/groups/keychains/posts |
| **Key** | ApiKey (`public_id` + `secret`) → Key JWT | Gateway (JSON) | Depends on key type and permissions |

### Key Types

| Type | Minted By | Can Create Posts | Can Mint Keys | Typical Use |
|------|-----------|------------------|---------------|-------------|
| **Primary Author** | Owner (Console) | ✅ | ✅ | Root API key for your app |
| **Secondary Author** | Primary or Secondary | ✅ | ✅ (within envelope) | Delegated apps or services |
| **Use** | Primary or Secondary | ❌ | ❌ | Per-post access for end users |

### Permissions & Envelope

- Permissions are set when minting **Secondary** or **Use** keys. **Envelope rule:** child permissions must be a **subset** of the parent’s.
- **Use Keys** cannot have `posts:create` or `keys:issue`.
- Permissions are **immutable** after minting; use **key rotation** to change them.

### Post Sharing

1. Author creates a post (Primary or Secondary Key).
2. Author mints a Use Key with desired permissions (e.g. `posts:read`, `comments:write`).
3. Author grants that Use Key access to the post (`POST /api/posts/{postId}/access`) with a **bitmask** (VIEW, COMMENT, MANAGE_ACCESS).
4. Recipient receives `key_public_id` + `key_secret`, exchanges for a Key JWT, then reads/comments on the post **without registering**.

---

## Quick Start

### As an Owner (Human User)

1. **Register** → `POST /console/owners` or visit `/console/register`.
2. **Login** → `POST /console/login` → receive `access_token` and `refresh_token`.
3. **Mint Primary Author Key** → `POST /console/keys/primary` with `Authorization: Bearer <access_token>`.
4. Use the **Gateway** with that key: exchange ApiKey for JWT, then `POST /api/posts`, etc.

**Rough time:** ~5 minutes.

### As a Developer (API Integration)

1. **Obtain** a Primary Author Key (from an Owner, via Console).
2. **Exchange** → `POST /api/auth/exchange` with `Authorization: ApiKey <key_public_id>:<key_secret>`.
3. **Call API** → `Authorization: Bearer <access_token>` on `/api/*` endpoints.
4. **Refresh** → use refresh tokens when access tokens expire.

**Rough time:** ~10 minutes to first API call.

### As a Content Sharer

1. **Create post** → `POST /api/posts` with your Author Key.
2. **Mint Use Key** → `POST /api/keys/{authorKeyId}/use` with permissions and optional use/device limits.
3. **Grant access** → `POST /api/posts/{postId}/access` (e.g. `target_type: "key"`, `target_id`, `permission_mask`).
4. **Share** → send `key_public_id` and `key_secret` to the recipient.
5. **Recipient** → exchanges ApiKey for JWT, then reads/comments on the post.

---

## Installation

### Prerequisites

- **PHP 8.3+** (extensions: `pdo`, `pdo_mysql`, `sodium`, `openssl`, `json`, `mbstring`)
- **Composer**
- **MariaDB 11.4+** or MySQL 8.0+ (`utf8mb4`, `utf8mb4_bin`)
- **OpenSSL** (for JWT keys)

### Steps

```bash
# 1. Clone or copy the project, then install dependencies
composer install

# 2. Create database and user (example)
mysql -u root -p -e "
  CREATE DATABASE cre8pw CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
  CREATE USER 'cre8_user'@'localhost' IDENTIFIED BY 'your_secure_password';
  GRANT ALL ON cre8pw.* TO 'cre8_user'@'localhost';
  FLUSH PRIVILEGES;
"

# 3. Create JWT key pair
mkdir -p keys && chmod 700 keys
openssl genrsa -out keys/private.pem 2048 && chmod 600 keys/private.pem
openssl rsa -in keys/private.pem -pubout -out keys/public.pem

# 4. Configure environment
cp .env.example .env
# Edit .env: DB_*, JWT_* paths, APP_URL, JWT_ISSUER, JWT_AUDIENCE, etc.

# 5. Run migrations
php tools/db/migrate.php up

# 6. (Optional) Create logs directory
mkdir -p logs && chmod 755 logs

# 7. Start dev server
php -S localhost:8000 -t public
```

- **Health check:** `curl http://localhost:8000/health`
- **JWKS:** `curl http://localhost:8000/.well-known/jwks.json`

Full details, troubleshooting, and production notes: **[docs-organized/02-installation/installation-guide.md](docs-organized/02-installation/installation-guide.md)**.

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| **Runtime** | PHP 8.3+ |
| **Framework** | Slim 4.15+ |
| **DI** | PHP-DI 7.1+ |
| **Database** | MariaDB 11.4+ / MySQL 8.0+, PDO |
| **Auth** | RS256 JWT (firebase/php-jwt), Argon2id (passwords/secrets) |
| **Validation** | Respect\Validation 2.4+ |
| **HTTP** | Guzzle 7.10+, neomerx/cors-psr7 |
| **Security** | slim/csrf (HTML only), Symfony rate-limiter |
| **Logging** | Monolog 3.9+ |
| **Config** | vlucas/phpdotenv |

---

## Project Structure

```
├── public/
│   └── index.php              # Entry point
├── src/
│   ├── Controllers/           # HTTP adapters (Console/, Gateway/)
│   ├── Services/              # Business logic, permissions, audit
│   ├── Repositories/          # Data access, ID conversion
│   ├── Middleware/            # HTTPS, CORS, JWT, rate limit, validation, errors
│   ├── Security/              # JWT, PermissionCatalog, PostAccessBitmask
│   ├── Utilities/             # Ids, ResponseFactory, ErrorFactory
│   └── Exceptions/
├── config/
│   ├── container.php          # DI wiring
│   ├── routes.php             # Route group registration
│   ├── validation.php         # "METHOD /path" → validation rules
│   └── routes/                # console_html, console_json, gateway_json, public_api
├── migrations/                # Database migrations
├── templates/                 # HTML views
├── tools/db/
│   ├── migrate.php            # Migration runner
│   └── verify_schema.php
├── docs-organized/            # Structured documentation
├── .env.example
└── composer.json
```

---

## Extensibility & Customization

CRE8.pw is built to be **customizable and copy-paste friendly**. To add behavior:

1. **Find a similar feature** (e.g. posts, comments, groups) in `src/Controllers`, `src/Services`, `src/Repositories`, and `config/routes/`.
2. **Reuse the pattern**: Route → Validation → Controller → Service → Repository.
3. **Add** your route in `config/routes/console_json.php` or `gateway_json.php`, validation in `config/validation.php`, then controller/service/repository code.

**Rules to follow:**

- **Controllers**: Extract params, call **one** service method, return via `ResponseFactory` / `ErrorFactory`. No business logic or DB access.
- **Services**: Permissions, invariants, transactions, audit events. No HTTP or SQL.
- **Repositories**: PDO prepared statements only; convert hex32 ↔ BINARY(16) at the DB boundary.

**Reference:** [docs-organized/08-implementation/implementation-guide.md](docs-organized/08-implementation/implementation-guide.md) and [docs-organized/11-development/component-breakdown.md](docs-organized/11-development/component-breakdown.md).

---

## API at a Glance

| Purpose | Method | Path | Auth |
|--------|--------|------|------|
| Health | `GET` | `/health` | — |
| JWKS | `GET` | `/.well-known/jwks.json` | — |
| Register owner | `POST` | `/console/owners` | — |
| Owner login | `POST` | `/console/login` | — |
| ApiKey → JWT | `POST` | `/api/auth/exchange` | ApiKey |
| Refresh token | `POST` | `/api/auth/refresh` | — |
| Mint primary key | `POST` | `/console/keys/primary` | Owner JWT |
| List keys | `GET` | `/console/keys` | Owner JWT |
| Mint secondary | `POST` | `/api/keys/{id}/secondary` | Key JWT |
| Mint use key | `POST` | `/api/keys/{id}/use` | Key JWT |
| Create post | `POST` | `/api/posts` | Key JWT |
| Grant access | `POST` | `/api/posts/{postId}/access` | Key JWT |
| Create comment | `POST` | `/api/posts/{postId}/comments` | Key JWT |

Responses: success `{ "data": { ... } }`, errors `{ "error": { "code", "message", "details", "request_id" } }`.

Full catalog: [docs-organized/06-api-reference/api-reference.md](docs-organized/06-api-reference/api-reference.md), [docs-organized/06-api-reference/routes-inventory.md](docs-organized/06-api-reference/routes-inventory.md).

---

## Configuration

Copy `.env.example` to `.env` and set at least:

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Base URL (e.g. `http://localhost:8000`) |
| `APP_ENV` | `development` or `production` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connection |
| `JWT_PRIVATE_KEY_PATH`, `JWT_PUBLIC_KEY_PATH` | Paths to PEM files |
| `JWT_ISSUER`, `JWT_AUDIENCE` | JWT `iss` / `aud` (e.g. `APP_URL`, `APP_URL/console`) |
| `CORS_ALLOWED_ORIGINS` | Allowed origins for CORS |
| `CSRF_SECRET` | Secret for CSRF (HTML routes) |
| `RATE_LIMIT_*` | Rate limit policy |
| `LOG_PATH`, `LOG_LEVEL` | Logging |

Complete reference: [docs-organized/10-reference/environment-configuration.md](docs-organized/10-reference/environment-configuration.md).

---

## Documentation

Documentation lives in **`docs-organized/`**, grouped by workflow:

| Need | Start Here |
|------|------------|
| **What is CRE8.pw?** | [01-getting-started/introduction.md](docs-organized/01-getting-started/introduction.md) |
| **Simple app ideas?** | [01-getting-started/simple-applications.md](docs-organized/01-getting-started/simple-applications.md) |
| **Install & run** | [02-installation/installation-guide.md](docs-organized/02-installation/installation-guide.md) |
| **Terms & concepts** | [03-core-concepts/glossary.md](docs-organized/03-core-concepts/glossary.md) |
| **Architecture** | [04-architecture/architecture-overview.md](docs-organized/04-architecture/architecture-overview.md) |
| **Auth & permissions** | [05-authentication-authorization/authentication.md](docs-organized/05-authentication-authorization/authentication.md), [authorization.md](docs-organized/05-authentication-authorization/authorization.md) |
| **Endpoints & routes** | [06-api-reference/api-reference.md](docs-organized/06-api-reference/api-reference.md), [routes-inventory.md](docs-organized/06-api-reference/routes-inventory.md) |
| **Data model** | [07-data-model/database-schema.md](docs-organized/07-data-model/database-schema.md) |
| **Implement & extend** | [08-implementation/implementation-guide.md](docs-organized/08-implementation/implementation-guide.md) |
| **Ops & troubleshooting** | [09-operations/logging-and-audit.md](docs-organized/09-operations/logging-and-audit.md) |
| **Config & IDs** | [10-reference/environment-configuration.md](docs-organized/10-reference/environment-configuration.md), [identifier-encoding.md](docs-organized/10-reference/identifier-encoding.md) |
| **Codebase layout** | [11-development/codebase-inventory.md](docs-organized/11-development/codebase-inventory.md) |
| **Full index** | [docs-organized/table-of-contents.md](docs-organized/table-of-contents.md) |

---

## Next Steps

- **Use the API**: Register an owner, mint a Primary Author Key, create posts, mint Use Keys, grant access, and have a recipient access posts with a Use Key.
- **Explore the codebase**: Follow Controller → Service → Repository for a few flows (e.g. keys, posts).
- **Extend**: Add a new endpoint using existing patterns; see the [implementation guide](docs-organized/08-implementation/implementation-guide.md).
- **Production**: Use HTTPS, set `APP_ENV=production`, point the web server at `public/`, harden DB and JWT keys, and review [logging-and-audit](docs-organized/09-operations/logging-and-audit.md).

---

CRE8.pw — *hierarchical credentialing for creators and developers.*
