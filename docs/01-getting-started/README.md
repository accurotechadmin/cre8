# CRE8.pw

**A streamlined credentialing and authorization platform designed for developers to build applications on top of.**

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-Active-green.svg)]()

---

## What is CRE8.pw?

CRE8.pw is a streamlined credentialing and authorization platform designed as a foundation for developers to build their own applications. It provides a dual-surface architecture consisting of the **Owner UI Console** and the **Key-based API Gateway**, enabling developers to create applications where users can register Owner accounts and mint Author Keys and Use Keys for programmatic access.

### Platform Philosophy

CRE8.pw is **totally extensible and customizable**. Developers can:
- Use it **out-of-the-box** to offer the full UI/API package
- **Reconfigure** it to disable the API Gateway and use only Owner UI accounts
- **Extend** it by finding existing patterns and copying/editing/adding new features

The platform is intentionally designed so that developers can easily add new features by following the patterns that already exist in the codebase.

### Key Features

- 🏗️ **Dual-Surface Platform** - Owner UI Console for administrative tasks; Key-based API Gateway for third-party client access
- 🔐 **Hierarchical Key-Based Authentication** - Owners mint Primary Author Keys; those keys mint child keys (Author Keys and Use Keys) with delegated permissions
- 🎯 **Fine-Grained Access Control** - Combine global permissions with post-level access bitmasks for precise control
- 🔍 **Full Provenance Tracking** - Every key traces back to its root, enabling accountability and bulk revocation
- 🔑 **Secure Sharing** - Create single-use or limited-use keys with device restrictions for controlled content distribution
- 📊 **Complete Audit Trail** - All key operations, access grants, and content changes are logged for compliance
- 🔧 **Extensible & Customizable** - Pattern-based development allows easy extension by copying and adapting existing code
- ⚡ **Modern PHP Stack** - Built on PHP 8.3+ with Slim Framework, following PSR standards
- 🔒 **Enterprise Security** - RS256 JWT, single-use refresh tokens, Argon2id hashing, rate limiting, input validation

---

## Architecture Overview

CRE8.pw uses a **dual-surface platform architecture** designed for developers to build applications:

### Owner UI Console (`/console/*`)
- **Purpose:** Administrative interface for registered Owner accounts
- **Authentication:** Owner JWT (`typ=owner`) from email/password login
- **Use Case:** Administrative tasks only - monitoring Key usage, viewing lineage, minting/deactivating Keys
- **Features:** 
  - HTML pages for registration, login, dashboard
  - JSON API for key/group/keychain/post management
  - CSRF protection on HTML forms only (never on JSON endpoints)
- **Typical Users:** Application administrators who manage the credentialing system

### Key-based API Gateway (`/api/*`)
- **Purpose:** Programmatic API accessed by third-party clients using Author Keys and Use Keys
- **Authentication:** Key JWT (`typ=key`) from ApiKey exchange
- **Use Case:** Primary application interface - allows users to access applications without needing Owner account registration
- **Features:**
  - JSON API for posting, commenting, key issuance, feeds
  - Token-based authentication (no CSRF needed)
  - Rate limiting by principal (`key_id`)
- **Typical Users:** End users accessing applications through third-party clients (mobile apps, web apps, etc.)

### How It Works Together

1. **Developers** integrate CRE8.pw into their applications
2. **Users** register Owner accounts through the Owner UI Console
3. **Owners** mint Author Keys (for content creation) and Use Keys (for read/comment access)
4. **Third-party clients** use these keys to provide access to end users without requiring Owner account registration
5. **End users** interact with applications through the API Gateway using Author Keys or Use Keys
6. **Owners** use the Console for administrative oversight and key management

---

## Key Concepts

### Principals: Owners and Keys

**Owners (Human Administrators)**
- Register through the Owner UI Console with email and password
- Access the Console for administrative tasks only
- Mint Primary Author Keys (root keys for their applications)
- Manage groups, view lineage, and oversee all downstream key activity
- Monitor Key usage and deactivate Keys when needed
- **Note:** Owners typically do not use the API Gateway directly; they manage the system through the Console

**Keys (Machine Credentials for Application Access)**
- Authenticate with ApiKey (`public_id` + `secret`) exchanged for JWT tokens
- Access the API Gateway (JSON API) through third-party clients
- Enable end users to access applications without Owner account registration
- Three types with different capabilities:
  - **Primary Author Key:** Root key minted by Owner; can create posts and mint child keys
  - **Secondary Author Key:** Delegated key minted by Author Keys; can create posts and mint child keys (within permission envelope)
  - **Use Key:** Interaction-only key; can read and comment but cannot create posts or mint keys

### Two-Layer Authorization

CRE8.pw uses a sophisticated two-layer authorization system:

1. **Permission Strings:** Global capabilities like `posts:create`, `keys:issue`, `comments:write`
2. **Post Bitmasks:** Post-specific permissions encoded as bits (VIEW, COMMENT, MANAGE_ACCESS)

Every action requires **both** the appropriate permission string AND (for post-scoped actions) the appropriate bitmask.

**Example:** To comment on a post, a Key needs:
- Global permission: `comments:write`
- Post-level mask bit: `COMMENT` (0x02)

### Hierarchical Permission Envelope

Child keys inherit a **subset** of their parent's permissions, creating an accountability chain:

```
Owner
 └─ Primary Author Key [posts:create, keys:issue, comments:write]
     ├─ Secondary Author Key [posts:create, comments:write]
     │   └─ Use Key [comments:write]
     └─ Use Key [comments:write]
```

**Key Rules:**
- Child permissions ⊆ Parent permissions (envelope rule)
- Permissions are immutable once minted (rotate key to change)
- Use Keys cannot have `posts:create` or `keys:issue`

### Provenance Tracking

Every key tracks its lineage:
- `issued_by_key_id` - immediate issuer
- `parent_key_id` - parent in chain
- `initial_author_key_id` - root Primary (immutable)

This enables:
- Full accountability for all actions
- Downstream lineage viewing
- Bulk disablement (disable key + all descendants)

---

## Technology Stack

### Core
- **PHP:** 8.3+ (strict types, modern features)
- **Framework:** Slim 4.15+ (PSR-7, PSR-15)
- **DI Container:** PHP-DI 7.1+ (autowiring)
- **Database:** MariaDB 11.4.x (utf8mb4_bin collation)

### Security
- **JWT:** firebase/php-jwt 6.11+ (RS256 asymmetric signing)
- **Hashing:** Argon2id (passwords and API key secrets via ext-sodium)
- **HTTPS:** Enforced with HSTS headers
- **CORS:** Configurable allowlist (neomerx/cors-psr7)
- **CSRF:** HTML routes only (slim/csrf)

### Infrastructure
- **Validation:** Respect\Validation 2.4+ (declarative schemas)
- **Logging:** Monolog 3.9+ (structured JSON, multiple channels)
- **Rate Limiting:** Symfony rate-limiter 7.3+ (IP and principal-based)
- **HTTP Client:** Guzzle 7.10+ (outbound requests)
- **Environment:** vlucas/phpdotenv 5.6+ (configuration management)

---

## Prerequisites

Before installing CRE8.pw, ensure you have:

- **PHP 8.3+** with extensions:
  - `pdo` (for database access)
  - `pdo_mysql` (for MariaDB/MySQL)
  - `sodium` (for Argon2id password hashing)
  - `openssl` (for JWT key generation)
  - `json` (usually included)
  - `mbstring` (usually included)
- **Composer** (PHP dependency manager)
- **MariaDB 11.4.x** or MySQL 8.0+ (must support `utf8mb4` charset and `utf8mb4_bin` collation)
- **OpenSSL** (for generating JWT keys)

**System Requirements:**
- Operating System: Linux, macOS, or Windows (with WSL recommended for Windows)
- Memory: Minimum 512MB RAM (1GB+ recommended)
- Disk Space: Minimum 100MB for application files
- Network: Port 8000 (or your chosen port) available for local development

---

## Quick Start

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url> cre8.pw
   cd cre8.pw
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Create database:**
   ```sql
   CREATE DATABASE cre8pw CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
   CREATE USER 'cre8_user'@'localhost' IDENTIFIED BY 'your_secure_password';
   GRANT ALL PRIVILEGES ON cre8pw.* TO 'cre8_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and settings
   ```

5. **Generate JWT keys:**
   ```bash
   mkdir -p keys
   openssl genrsa -out keys/private.pem 2048
   openssl rsa -in keys/private.pem -pubout -out keys/public.pem
   chmod 600 keys/private.pem
   ```

6. **Run database migrations:**
   ```bash
   php tools/db/migrate.php
   ```

7. **Start the development server:**
   ```bash
   php -S localhost:8000 -t public
   ```

8. **Verify installation:**
   ```bash
   curl http://localhost:8000/health
   # Should return: {"status":"ok"}
   ```

**📖 For detailed installation instructions, see [installation-guide.md](../02-installation/installation-guide.md)**

### First Steps

1. **Register an Owner:**
   ```bash
   curl -X POST http://localhost:8000/console/owners \
     -H "Content-Type: application/json" \
     -d '{"email": "owner@example.com", "password": "SecurePass123!"}'
   ```

2. **Login to get Owner JWT:**
   ```bash
   curl -X POST http://localhost:8000/console/login \
     -H "Content-Type: application/json" \
     -d '{"email": "owner@example.com", "password": "SecurePass123!"}'
   # Returns: {"access_token": "...", "refresh_token": "..."}
   ```

3. **Mint a Primary Author Key:**
   ```bash
   curl -X POST http://localhost:8000/console/keys/primary \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_OWNER_JWT" \
     -d '{"permissions": ["posts:create", "keys:issue", "comments:write"], "label": "My First Key"}'
   # Returns: {"key_id": "...", "key_public_id": "apub_...", "key_secret": "sec_..."}
   ```

4. **Exchange ApiKey for Key JWT:**
   ```bash
   curl -X POST http://localhost:8000/api/auth/exchange \
     -H "Authorization: ApiKey YOUR_KEY_PUBLIC_ID:YOUR_KEY_SECRET"
   # Returns: {"access_token": "...", "refresh_token": "..."}
   ```

5. **Create your first post:**
   ```bash
   curl -X POST http://localhost:8000/api/posts \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_KEY_JWT" \
     -d '{"content": "Hello CRE8.pw!", "title": "My First Post"}'
   ```

---

## Use Cases

### Application Developers
- **Build applications on top of CRE8.pw** - Use it as a credentialing and authorization platform
- **Extend the platform** - Add new features by following existing patterns (copy, edit, add)
- **Customize deployment** - Use out-of-the-box UI/API package or reconfigure (e.g., disable API Gateway, use only Owner UI)
- **Integrate third-party clients** - Build mobile apps, web apps, or other clients that access the API Gateway
- **Implement hierarchical permission systems** - Leverage the built-in key hierarchy and permission envelope system
- **Maintain audit trails** - Built-in compliance and accountability features

### Application Administrators (Owners)
- Register Owner accounts through the Owner UI Console
- Mint Author Keys and Use Keys for application access
- Monitor Key usage, view lineage, and track all key activity
- Deactivate Keys and revoke access when needed
- Manage groups and keychains for bulk access control
- Perform administrative oversight through the Console

### End Users (Accessing Applications)
- Access applications through third-party clients using Author Keys or Use Keys
- No need to register Owner accounts - access is provided through keys
- Use Author Keys for content creation and key delegation
- Use Use Keys for read/comment access to shared content
- Interact with applications seamlessly through the API Gateway

### Organizations
- Control access to sensitive content through fine-grained permissions
- Delegate content creation to team members (Secondary Author Keys)
- Track all content access and modifications with complete audit trails
- Implement fine-grained sharing policies with post-level bitmasks
- Bulk revoke access through key hierarchies (disable key + all descendants)

---

## Security Features

- **RS256 JWT Signing:** Asymmetric cryptography with JWKS key rotation support
- **Token Typing:** Prevents token confusion attacks (`typ=owner` vs `typ=key`)
- **Single-Use Refresh Tokens:** Automatic rotation prevents replay attacks
- **Hierarchical Revocation:** Disable a key and all its descendants in one operation
- **Argon2id Hashing:** Industry-standard password and secret hashing
- **Never Log Secrets:** Strict logging policies prevent credential leakage
- **Rate Limiting:** IP-based (public), principal-based (authenticated)
- **Input Validation:** Centralized validation with explicit schemas and `rejectUnknown` protection
- **Prepared Statements:** All database access uses PDO with parameter binding
- **HTTPS Enforcement:** HSTS headers in production
- **CORS Protection:** Configurable allowlist for cross-origin requests
- **CSRF Protection:** HTML routes only (stateless token auth for JSON)

---

## API Endpoints Overview

### Public API (No Authentication)
- `GET /health` - Health check endpoint
- `GET /.well-known/jwks.json` - JWKS public key endpoint for JWT verification
- `POST /console/owners` - Owner registration
- `POST /console/login` - Owner login (returns Owner JWT)
- `POST /api/auth/exchange` - ApiKey → JWT exchange
- `POST /api/auth/refresh` - Refresh token rotation

### Console JSON (Owner JWT Required)
- `POST /console/keys/primary` - Mint Primary Author Key
- `GET /console/keys` - List Owner's keys
- `GET /console/keys/{keyId}` - Get key details
- `GET /console/keys/{keyId}/lineage` - View key lineage tree
- `POST /console/keys/{keyId}/rotate` - Rotate key
- `POST /console/groups` - Create group
- `GET /console/posts` - List Owner's posts (admin view)
- `POST /console/posts/{postId}/access/grant-group` - Grant group access to post

### Gateway JSON (Key JWT Required)
- `POST /api/posts` - Create post
- `GET /api/posts/{postId}` - Get post (requires VIEW mask)
- `POST /api/posts/{postId}/access` - Grant access to post (requires MANAGE_ACCESS mask)
- `POST /api/posts/{postId}/comments` - Create comment (requires COMMENT mask)
- `POST /api/keys/{authorKeyId}/secondary` - Mint Secondary Author Key
- `POST /api/keys/{authorKeyId}/use` - Mint Use Key
- `GET /api/feed/use/{useKeyId}` - Get Use Key feed

**📖 See [API Reference](../06-api-reference/api-reference.md) for complete documentation**

---

## Project Structure

```
./
├── public/              # Public web root
│   ├── index.php       # Application entry point
│   └── css/            # Static assets
│       └── styles.css
├── src/                # Application source code
│   ├── bootstrap.php   # Application bootstrap (DI, routes, middleware)
│   ├── Controllers/    # HTTP adapters
│   │   ├── Console/    # Owner-facing controllers
│   │   └── Gateway/    # Key-facing controllers
│   ├── Services/       # Business logic layer
│   ├── Repositories/   # Data access layer (PDO)
│   ├── Middleware/     # PSR-15 middleware
│   ├── Security/       # JWT, hashing utilities
│   ├── Utilities/      # Helper utilities
│   └── Exceptions/     # Custom exceptions
├── config/             # Configuration files
│   ├── container.php   # DI container wiring
│   ├── routes.php      # Route group definitions
│   ├── validation.php  # Validation schemas
│   └── routes/         # Route group files
├── migrations/         # Database migrations (13 files)
├── templates/          # PHP templates (HTML rendering)
│   └── gateway/        # Gateway example pages
├── tools/              # Utility scripts
│   ├── db/             # Database utilities
│   └── contract/       # Contract/compliance tests
├── docs/               # Documentation
│   ├── 01-getting-started/          # Getting started (10 files)
│   ├── 02-installation/            # Installation (1 file)
│   ├── 03-core-concepts/           # Core concepts (3 files)
│   ├── 04-architecture/            # Architecture (4 files)
│   ├── 05-authentication-authorization/  # Auth (4 files)
│   ├── 06-api-reference/           # API reference (4 files)
│   ├── 07-data-model/              # Data model (1 file)
│   ├── 08-implementation/          # Implementation (2 files)
│   ├── 09-operations/              # Operations (1 file)
│   ├── 10-reference/               # Reference (5 files)
│   ├── 11-development/             # Development (5 files)
│   └── 12-comprehensive-reference/ # SSOT documents (6 files)
├── keys/               # JWT keys (not in repo, create locally)
├── logs/               # Application logs (not in repo)
├── composer.json       # PHP dependencies
├── .env.example        # Environment template
├── README.md           # This file
├── TOC.md              # Master documentation index
└── SSOT.md             # Master SSOT hub
```

---

## Documentation

CRE8.pw has comprehensive documentation organized into three categories:

### Getting Started
- **[Installation Guide](../02-installation/installation-guide.md)** — Complete step-by-step setup instructions
- **[Introduction](introduction.md)** — Overview and key concepts
- **[Architecture](../04-architecture/architecture-overview.md)** — System design and request flow
- **[Simple Applications](simple-applications.md)** — Example apps built easily on CRE8.pw (gated content, feedback, newsletters, portals, etc.)

### LLM Coding Session Primers
For AI assistants and LLMs beginning a coding session with CRE8.pw:
- **[LLM Primer Part 1: Document Set and Foundation](LLM-PRIMER-1-Document-Set-and-Foundation.md)** — Initialization primer for understanding the documentation structure and foundational concepts
- **[LLM Primer Part 2: Codebase Deep Dive](LLM-PRIMER-2-Codebase-Deep-Dive.md)** — Codebase exploration and pattern recognition guide
- **[LLM Primer Part 3: Ready-to-Code](LLM-PRIMER-3-Ready-to-Code.md)** — Verification checklist and practical coding workflow guide

### Core Documentation
- **[Authentication](../05-authentication-authorization/authentication.md)** — JWT, ApiKey exchange, refresh tokens
- **[Authorization](../05-authentication-authorization/authorization.md)** — Permission system and bitmasks
- **[API Reference](../06-api-reference/api-reference.md)** — Complete endpoint catalog
- **[Data Model](../07-data-model/database-schema.md)** — Database schema and relationships

### Advanced Topics
- **[Key Lifecycle](../03-core-concepts/key-lifecycle.md)** — Key issuance, rotation, lineage
- **[Post Sharing](../03-core-concepts/post-sharing.md)** — Content sharing workflows
- **[Feed System](../06-api-reference/feed-system.md)** — Content discovery and feeds
- **[Implementation Guide](../08-implementation/implementation-guide.md)** — Developer patterns and best practices
- **[Logging & Audit](../09-operations/logging-and-audit.md)** — Observability and compliance

### Reference Materials
- **[Environment Configuration](../10-reference/environment-configuration.md)** — Complete `.env` reference
- **[Glossary](../03-core-concepts/glossary.md)** — Terminology definitions
- **[Route Inventory](../06-api-reference/routes-inventory.md)** — Quick API reference
- **[Permission Matrix](../05-authentication-authorization/permissions.md)** — Permission catalog
- **[Key Capability Matrix](../05-authentication-authorization/key-capabilities.md)** — Key type capabilities

### Development Documentation
- **[Codebase Inventory](../11-development/codebase-inventory.md)** — Complete file inventory
- **[Component Breakdown](../11-development/component-breakdown.md)** — Detailed component specifications
- **[Elevator Pitches](elevator-pitches.md)** — Communication materials

**📚 [Master TOC](../../TOC.md)** — Top-level index of the documentation set
**📗 [Master SSOT](../../SSOT.md)** — SSOT hub for authoritative references
**📘 [Full Documentation Index](../table-of-contents.md)** — Detailed table of contents for all documentation

---

## Development

### Code Standards

- **PSR-12** coding style
- **Strict types** (`declare(strict_types=1)`) in all files
- **Type hints** for all parameters and return types
- **Layered architecture** (Controller → Service → Repository)
- **Prepared statements** for all database queries
- **Structured JSON logging** (never log secrets)

### Running Database Tools

```bash
# Run migrations
php tools/db/migrate.php

# Verify database schema
php tools/db/verify_schema.php
```

### Contract Tests

```bash
# Test ID format compliance
php tools/contract/test_id_format_compliance.php

# Test audience segregation (token typing)
php tools/contract/test_audience_segregation.php

# Test documentation alignment
php tools/contract/test_doc_ssot_alignment.php
```

### Adding New Features

CRE8.pw is designed for **pattern-based development**. To add new features:

1. **Find existing patterns** - Look for similar functionality in the codebase
2. **Copy the pattern** - Identify the controller, service, and repository files for similar features
3. **Edit and adapt** - Modify the copied code to implement your new feature
4. **Follow the structure:**
   - Determine surface (Console HTML, Console JSON, Gateway JSON, Public API)
   - Determine auth requirements (Owner JWT, Key JWT, or public)
   - Determine required permissions (refer to Permission Matrix)
   - Add route to appropriate route file in `config/routes/`
   - Add validation rules to `config/validation.php`
   - Implement controller method (thin adapter) - follow `BaseController` patterns
   - Implement service method (business logic + audits) - follow existing service patterns
   - Implement repository methods if needed - follow existing repository patterns
5. **Test authorization enforcement**
6. **Update documentation**

**Key Principle:** The platform is intentionally designed so developers can extend it by finding existing patterns and copying/editing/adding them. Look at `PostController`, `PostService`, and `PostRepository` as examples of the pattern.

**📖 See [Implementation Guide](../08-implementation/implementation-guide.md) for detailed patterns**

---

## Response Formats

### Success Response

**Single Object:**
```json
{
  "data": {
    "post_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "content": "Post content...",
    "title": "Post title"
  }
}
```

**List with Paging:**
```json
{
  "data": [
    {"post_id": "...", "content": "..."},
    {"post_id": "...", "content": "..."}
  ],
  "paging": {
    "limit": 20,
    "cursor": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718"
  }
}
```

### Error Response

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "email": ["Email is required"],
        "password": ["Password must be at least 8 characters"]
      }
    },
    "request_id": "req_abc123"
  }
}
```

**📖 See [Response Schemas](../06-api-reference/response-schemas.md) for complete documentation**

---

## License

**Proprietary** - All rights reserved

---

## Status

**Active Development** - CRE8.pw is actively maintained and developed.

### Current Version
- **Version:** 1.0.0
- **Last Updated:** 2026-01-25

---

## Support

For issues, questions, or contributions:

- Review the [master TOC](../../TOC.md) and the [full documentation index](../table-of-contents.md)
- Check the [Installation Guide](../02-installation/installation-guide.md) for setup issues
- Review application logs in `logs/` directory
- Check the [Troubleshooting Guide](../09-operations/logging-and-audit.md#troubleshooting-guide) for common issues

---

## Extensibility and Customization

CRE8.pw is **totally extensible and customizable**:

### Out-of-the-Box Usage
- Deploy the full dual-surface platform (Owner UI Console + API Gateway)
- Users register Owner accounts and mint keys
- Third-party clients access the API Gateway with keys
- Complete credentialing and authorization system ready to use

### Customization Options
- **Disable API Gateway:** Reconfigure to use only Owner UI accounts
- **Extend functionality:** Add new features by following existing patterns
- **Customize surfaces:** Modify Console or Gateway behavior as needed
- **Add new domains:** Extend beyond posts/comments to your application's needs

### Pattern-Based Development
The platform is designed so developers can:
1. Find similar functionality in the codebase
2. Copy the controller/service/repository pattern
3. Edit and adapt for new features
4. Add routes and validation following existing conventions

**Example:** To add a new resource type, study how `PostController` → `PostService` → `PostRepository` work together, then copy and adapt that pattern.

---

## Acknowledgments

CRE8.pw is built with security and developer experience in mind, using modern PHP practices and industry-standard libraries. It's designed as a platform foundation that developers can extend and customize for their specific needs.

**Built with:**
- [Slim Framework](https://www.slimframework.com/) - HTTP microframework
- [PHP-DI](https://php-di.org/) - Dependency injection container
- [firebase/php-jwt](https://github.com/firebase/php-jwt) - JWT signing and verification
- [Respect\Validation](https://respect-validation.readthedocs.io/) - Validation library
- [Monolog](https://github.com/Seldaek/monolog) - Logging library
- [Symfony Rate Limiter](https://symfony.com/doc/current/components/rate_limiter.html) - Rate limiting

---

**Ready to build on CRE8.pw?** Check out the [Installation Guide](../02-installation/installation-guide.md) to set up your own CRE8.pw platform instance!
