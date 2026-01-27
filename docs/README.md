# CRE8.pw Documentation

This folder contains the CRE8.pw documentation, structured for intuitive navigation by developers.

## Master Indexes

- **Master TOC (repo root):** [`/TOC.md`](../TOC.md)
- **Master SSOT (repo root):** [`/SSOT.md`](../SSOT.md)
- **Full Documentation Index:** [`table-of-contents.md`](table-of-contents.md)

## Quick Navigation

### 🚀 Getting Started
**Folder:** `01-getting-started/`
- Start here to understand what CRE8.pw is and how to begin
- Introduction, executive summary, elevator pitches
- Primer prompts for LLM onboarding

### 📦 Installation & Setup
**Folder:** `02-installation/`
- Step-by-step installation guide
- Prerequisites and configuration

### 💡 Core Concepts
**Folder:** `03-core-concepts/`
- Glossary of terminology
- Key lifecycle and provenance
- Post sharing and access control

### 🏗️ Architecture
**Folder:** `04-architecture/`
- System architecture overview
- Component architecture
- Layering rules and design patterns
- Technical summary

### 🔐 Authentication & Authorization
**Folder:** `05-authentication-authorization/`
- Authentication mechanisms (JWT, ApiKey)
- Authorization model (permissions, bitmasks)
- Key capabilities matrix
- Permissions reference

### 📡 API Reference
**Folder:** `06-api-reference/`
- Complete API endpoint documentation
- Route inventory
- Feed system documentation
- Response schemas and error handling

### 💾 Data Model
**Folder:** `07-data-model/`
- Database schema
- Entity relationships
- ID encoding rules

### 🛠️ Implementation
**Folder:** `08-implementation/`
- How to extend and customize CRE8.pw
- Implementation patterns and best practices
- Dependency wiring guide

### 📊 Operations
**Folder:** `09-operations/`
- Logging and audit trails
- Observability and monitoring
- Troubleshooting guides

### 📚 Reference
**Folder:** `10-reference/`
- Quick lookup tables
- Environment configuration reference
- Identifier encoding matrix
- Document outlines

### 👨‍💻 Development
**Folder:** `11-development/`
- Codebase structure and inventory
- Component breakdown
- Production readiness checklists
- **SDK Specification** — Official SDK for building applications on CRE8.pw

### 📖 Comprehensive Reference
**Folder:** `12-comprehensive-reference/`
- Single Source of Truth (SSOT) documents
- Complete consolidated references
- Table of contents for each section
  - Canon TOC (`toc-canon.md`)
  - Appendix TOC (`toc-appendix.md`)
  - Development TOC (`toc-dev.md`)

## Document Organization Philosophy

Documents are organized by **developer workflow**:

1. **Learn** → Getting Started, Core Concepts
2. **Install** → Installation & Setup
3. **Understand** → Architecture, Authentication & Authorization
4. **Use** → API Reference, Data Model
5. **Extend** → Implementation
6. **Operate** → Operations
7. **Reference** → Quick lookup materials
8. **Develop** → Codebase details
9. **Deep Dive** → Comprehensive SSOT documents

## Finding What You Need

### "I'm new here"
→ Start with `01-getting-started/introduction.md`

### "How do I install this?"
→ See `02-installation/installation-guide.md`

### "What does X mean?"
→ Check `03-core-concepts/glossary.md`

### "How does the system work?"
→ Read `04-architecture/architecture-overview.md`

### "How do I authenticate?"
→ See `05-authentication-authorization/authentication.md`

### "What endpoints are available?"
→ Check `06-api-reference/api-reference.md`

### "What's the database structure?"
→ See `07-data-model/database-schema.md`

### "How do I add a new feature?"
→ Read `08-implementation/implementation-guide.md`

### "How do I build an app that uses CRE8.pw?"
→ See `11-development/sdk-specification.md` for the official SDK

### "How do I debug issues?"
→ See `09-operations/logging-and-audit.md`

### "What's the environment variable for X?"
→ Check `10-reference/environment-configuration.md`

### "Where is the code for X?"
→ See `11-development/codebase-inventory.md`

### "I need everything about X"
→ Check `/SSOT.md` for the SSOT hub, then the relevant SSOT in `12-comprehensive-reference/`

## File Naming Conventions

- **Lowercase with hyphens:** `authentication.md`, `key-lifecycle.md`
- **Descriptive names:** Clear what the document contains
- **Consistent:** Similar documents use similar naming patterns

## Contributing

When adding new documentation:
1. Place it in the appropriate folder based on its purpose
2. Use lowercase-hyphenated naming
3. Update this README if adding a new category
4. Update [`/TOC.md`](../TOC.md), [`table-of-contents.md`](table-of-contents.md), and the relevant TOC in `12-comprehensive-reference/` with the new file
