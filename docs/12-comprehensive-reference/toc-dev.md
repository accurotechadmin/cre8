# CRE8.pw Development Documentation Table of Contents

**Purpose:** This document provides a detailed index of all development documentation files in the CRE8.pw project, with comprehensive descriptions of each file's content, purpose, and key sections.

**Last Updated:** 2026-01-25

---

## Overview

The development documentation is distributed across multiple folders. These documents provide codebase inventories, component breakdowns, installation guides, elevator pitches, and development workflows for building and maintaining CRE8.pw.

**Total Files:** Documents are distributed across:
- `01-getting-started/` (elevator-pitches.md)
- `02-installation/` (installation-guide.md)
- `11-development/` (codebase-inventory.md, component-breakdown.md, component-breakdown.json, production-readiness-issues.md, production-readiness-milestone.md, verified-production-issues.md)
- `12-comprehensive-reference/` (development-ssot.md)

---

## Document Index

### `codebase-inventory.md`
**Location:** `11-development/codebase-inventory.md`

**Codebase Inventory**

**Purpose:** Complete inventory of all codebase components, files, conventions, and patterns for production readiness review and codebase exploration.

**Key Content:**
- **Directory Structure:** Complete file tree with descriptions of each directory
- **Entry Points:** `public/index.php` (public HTTP entry) and `src/bootstrap.php` (application bootstrap)
- **Bootstrap & Configuration:** DI container setup, route registration, validation configuration, environment configuration
- **Controllers:** Complete listing of all controllers (Console and Gateway) with purposes
- **Services:** All service classes with business logic responsibilities
- **Repositories:** All repository classes with data access responsibilities
- **Middleware:** All middleware classes with cross-cutting concerns
- **Security Components:** JWT service, permission catalog, post access bitmask utilities
- **Utilities:** Helper utilities (IDs, ResponseFactory, ErrorFactory, BootstrapValidator, etc.)
- **Exceptions:** Custom exception classes
- **Route Definitions:** Route group organization
- **Database Migrations:** All 13 migration files with purposes
- **Templates:** Console and Gateway template files
- **Tools & Scripts:** Database utilities and contract tests
- **Static Assets:** CSS and other static files
- **Documentation:** Documentation structure
- **Dependencies:** Composer dependencies
- **Conventions & Patterns:** Coding conventions and architectural patterns
- **Environment Configuration:** Environment variable reference
- **Testing & Verification Tools:** Contract tests and verification scripts
- **Critical Integration Points:** Request flow, authentication flow, authorization flow, key lifecycle flow
- **Production Readiness Checklist:** Security, performance, observability, documentation checklists

**When to Use:** Reference when exploring the codebase, understanding file organization, or conducting production readiness reviews. Essential for onboarding new developers.

---

### `component-breakdown.md`
**Location:** `11-development/component-breakdown.md`

**Component Breakdown**

**Purpose:** Extremely granular documentation for individual components, detailing their purpose, dependencies, and methods at the method level.

**Key Content:**
- **Controllers:** For each controller:
  - Purpose and dependencies
  - Method signatures with detailed specifications:
    - Purpose and endpoint mapping
    - Authentication and permission requirements
    - Request attributes used
    - Route parameters, request body, query parameters
    - Process steps (step-by-step)
    - Exception handling
    - Return values and response formats
- **Services:** For each service:
  - Purpose and dependencies
  - Method signatures with business logic details:
    - Authorization checks
    - Business rule enforcement
    - Transaction management
    - Audit event emission
    - Exception handling
- **Repositories:** For each repository:
  - Purpose and dependencies
  - Method signatures with data access details:
    - SQL query patterns
    - ID conversion (hex32 ↔ BINARY(16))
    - Return value formats
- **Middleware:** For each middleware:
  - Purpose and dependencies
  - Process steps and responsibilities
- **Security Components:** JWT service, permission catalog, bitmask utilities
- **Utilities:** Helper functions and their purposes
- **Exceptions:** Exception classes and their usage
- **Route Definitions:** Route-to-controller mapping
- **Database Migrations:** Migration purposes and ordering

**When to Use:** Reference when implementing specific methods or understanding detailed component behavior. Essential for understanding method-level implementation details and API contracts.

---

### `component-breakdown.json`
**Location:** `11-development/component-breakdown.json`

**Component Breakdown (JSON)**

**Purpose:** Machine-readable counterpart to `COMPONENT_BREAKDOWN.md`, offering the same detailed component information in structured JSON format.

**Key Content:**
- Same detailed information as `COMPONENT_BREAKDOWN.md` but in JSON format
- Structured data for programmatic access
- Component metadata: numbers, names, files, purposes
- Method specifications: signatures, parameters, return values, process steps
- Dependency information: constructor dependencies, method dependencies
- Endpoint mappings: HTTP methods, paths, authentication requirements
- Exception handling: exception types and HTTP status codes

**When to Use:** Use for automated documentation generation, API contract validation, code generation, or programmatic analysis of component specifications. Ideal for tooling and automated verification.

---

### `elevator-pitches.md`
**Location:** `01-getting-started/elevator-pitches.md`

**Elevator Pitches**

**Purpose:** Communication materials for presenting CRE8.pw to different audiences, emphasizing security, extensibility, and ease of integration.

**Key Content:**
- **30-Second Elevator Pitch:** Concise problem statement and solution summary
- **2-Minute Elevator Pitch:**
  - Problem statement: Controlled content sharing challenges
  - Solution overview: Hierarchical key system, two-layer authorization, security built-in
  - Easy integration: ApiKey exchange, standardized responses, clean architecture
  - Operational excellence: Structured logging, audit trails, rate limiting
  - Use cases: Single-use keys, delegation, group access, provenance tracking
- **5-Minute Elevator Pitch:**
  - Introduction and core architecture
  - Security model: Authentication and authorization details
  - Extensibility: How to extend the platform
  - Integration simplicity: API design and developer experience
  - Use cases: Detailed scenarios
  - Key differentiators: What makes CRE8.pw unique
- **20-Minute Presentation Outline:**
  - Detailed presentation structure
  - Slide-by-slide breakdown
  - Technical deep-dives
  - Demo scenarios
  - Q&A preparation

**When to Use:** Use when presenting CRE8.pw to stakeholders, potential users, or at conferences. Adapt pitches based on audience (technical vs. business). Essential for marketing and communication.

---

### `installation-guide.md`
**Location:** `02-installation/installation-guide.md`

**Installation Guide**

**Purpose:** Comprehensive, step-by-step guide for local installation and setup of CRE8.pw, from prerequisites through verification.

**Key Content:**
- **Prerequisites:**
  - Required software: PHP 8.3+, Composer, MariaDB 11.4.x, OpenSSL
  - Required PHP extensions: pdo, pdo_mysql, sodium, openssl, json, mbstring
  - System requirements: OS, memory, disk space, network
  - Verification commands for all prerequisites
- **Initial Setup:**
  - Clone or download instructions
  - PHP dependency installation with Composer
  - Installation verification steps
- **Database Configuration:**
  - Database creation with proper charset/collation
  - User creation and privilege grants
  - Connection verification
- **JWT Key Generation:**
  - OpenSSL commands for generating RSA key pair
  - Key file placement and permissions
  - Key verification
- **Environment Configuration:**
  - Copying `.env.example` to `.env`
  - Required environment variables with examples
  - Configuration validation
- **Database Migrations:**
  - Running migrations with `tools/db/migrate.php`
  - Migration verification
  - Schema verification with `tools/db/verify_schema.php`
- **Starting the Application:**
  - PHP built-in server command
  - Alternative server options
  - Server verification
- **Verifying Installation:**
  - Health check endpoint
  - JWKS endpoint verification
  - Database schema verification
- **Quick Start Workflow:**
  - Step-by-step guide for:
    - Registering first owner
    - Creating first Primary Author Key
    - Creating first post
    - Sharing content with others
  - Complete curl command examples
- **Troubleshooting:**
  - Common issues and solutions
  - Prerequisite verification
  - Database connection issues
  - JWT key issues
  - Migration issues
  - Server startup issues

**When to Use:** Follow when setting up CRE8.pw for the first time or in a new environment. Essential for local development setup and onboarding new developers.

---

### `development-ssot.md`
**Location:** `12-comprehensive-reference/development-ssot.md`

**Development Single Source of Truth**

**Purpose:** Comprehensive consolidation of all development-related information, serving as the definitive reference for all development practices, codebase structure, installation procedures, and development workflows.

**Key Content:**
- **Codebase Structure:**
  - Complete directory layout with file descriptions
  - File counts by type (Controllers, Services, Repositories, etc.)
- **Entry Points & Bootstrap:**
  - Public entry point (`public/index.php`) responsibilities
  - Application bootstrap (`src/bootstrap.php`) process
  - Bootstrap validation requirements
- **Component Architecture:**
  - Controllers: HTTP adapters with responsibilities and forbidden operations
  - Services: Business logic layer with authorization and audit responsibilities
  - Repositories: Data access layer with PDO prepared statements
  - Middleware: Cross-cutting concerns
  - Utilities: Helper functions
  - Exceptions: Custom exception classes
- **Development Workflow:**
  - Local development setup steps
  - Quick start workflow with curl examples
  - Adding a new feature: Step-by-step process (plan, database, repository, service, controller, route, test, documentation)
- **Installation & Setup:**
  - Prerequisites: Software, PHP extensions, system requirements
  - Installation steps: Clone, install dependencies, create database, configure environment, generate JWT keys, run migrations, start server
  - Verification: Health checks, JWKS endpoint, schema verification
- **Testing & Verification:**
  - Contract tests: ID format compliance, audience segregation, documentation alignment
  - Manual testing checklist: Authorization, validation, error handling, functionality
  - Production readiness checklist: Security, performance, observability, documentation
- **Code Conventions:**
  - Naming conventions: Files, classes, methods, variables
  - Type declarations: Strict types, type hints, nullable types, array types
  - Code organization: Controller, Service, Repository, Middleware patterns
- **File Organization:**
  - Source code organization by type
  - Configuration organization
  - Import organization standards
- **Dependency Management:**
  - Composer dependencies with versions
  - Dependency injection patterns
  - Container configuration
- **Documentation Structure:**
  - Canon, reference, and development documentation across all folders
- **Production Readiness:**
  - Security checklist: HTTPS, CORS, CSRF, rate limiting, secrets handling
  - Performance checklist: Database indexes, rate limiting, logging, transactions
  - Observability checklist: Structured logging, audit events, correlation IDs, health checks
  - Documentation checklist: API docs, SSOT documents, installation guide, troubleshooting guide
- **Troubleshooting:**
  - Common issues: 401 Unauthorized, 403 Forbidden, 422 Validation Failed, Database errors, Bootstrap failures
  - Debugging tips: Logging, database, JWT, rate limiting

**When to Use:** Use as a comprehensive reference for all development practices. Ideal for quick lookups of development workflows, code conventions, installation procedures, and troubleshooting guides.

---

## Document Relationships

### Setup and Onboarding Flow
1. **Start with:** [installation-guide.md](../02-installation/installation-guide.md) — Get the application running
2. **Then read:** [codebase-inventory.md](../11-development/codebase-inventory.md) — Understand the codebase structure
3. **Then reference:** [component-breakdown.md](../11-development/component-breakdown.md) — Understand component details
4. **Use as reference:** [development-ssot.md](development-ssot.md) — Comprehensive development reference

### Understanding Components
1. **High-level:** [codebase-inventory.md](../11-development/codebase-inventory.md) — File organization and purposes
2. **Detailed:** [component-breakdown.md](../11-development/component-breakdown.md) — Method-level specifications
3. **Programmatic:** [component-breakdown.json](../11-development/component-breakdown.json) — Machine-readable specifications

### Communication
- **Use:** [elevator-pitches.md](../01-getting-started/elevator-pitches.md) — For presentations and stakeholder communication

### Ongoing Development
- **Reference:** [development-ssot.md](development-ssot.md) — For all development practices and workflows

---

## Usage Recommendations

### For New Developers
1. Follow [installation-guide.md](../02-installation/installation-guide.md) to set up the environment
2. Read [codebase-inventory.md](../11-development/codebase-inventory.md) to understand the codebase structure
3. Reference [component-breakdown.md](../11-development/component-breakdown.md) when working on specific components
4. Use [development-ssot.md](development-ssot.md) as ongoing reference

### For Code Reviews
- Reference [development-ssot.md](development-ssot.md) for code conventions
- Use [component-breakdown.md](../11-development/component-breakdown.md) to verify method implementations match specifications
- Check [codebase-inventory.md](../11-development/codebase-inventory.md) for file organization standards

### For Presentations
- Use [elevator-pitches.md](../01-getting-started/elevator-pitches.md) for communication materials
- Adapt pitches based on audience (technical vs. business)

### For Production Deployment
- Follow production readiness checklists in [development-ssot.md](development-ssot.md)
- Verify all items in security, performance, observability, and documentation checklists

---

**Note:** Development documentation provides practical guidance for working with the CRE8.pw codebase. For authoritative specifications, refer to the canon documentation. For reference materials, refer to the appendix documentation.
