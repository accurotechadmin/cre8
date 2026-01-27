# CRE8.pw LLM Coding Session Primer - Part 3: Ready-to-Code Verification

This is the final primer prompt. You should have already completed Parts 1 and 2. This document provides a verification checklist and practical guidance to ensure you're ready to code effectively with CRE8.pw.

## Purpose of This Primer

This primer serves as:
- **Verification Checklist:** Confirm you understand key concepts
- **Practical Guide:** How to approach coding tasks
- **Quick Reference:** Where to find information when stuck
- **Best Practices:** How to work effectively with CRE8.pw

## Pre-Coding Verification Checklist

Before accepting coding tasks, verify you can answer these questions:

### Architecture Understanding ✓

- [ ] **Can you explain the dual-surface architecture?**
  - Console: Owner-facing, HTML + JSON, CSRF on HTML only
  - Gateway: Key-facing, JSON only, no CSRF
  - **Reference:** `04-architecture/architecture-overview.md`

- [ ] **Can you trace a request through the system?**
  - Entry point → Bootstrap → Middleware → Controller → Service → Repository → Database
  - **Reference:** `04-architecture/architecture-overview.md`

- [ ] **Do you understand layer responsibilities?**
  - Controllers: HTTP adapters only
  - Services: Business logic, permissions, audits
  - Repositories: Data access only
  - **Reference:** `04-architecture/layering-rules.md`

### Authentication & Authorization ✓

- [ ] **Can you explain the two-layer authorization system?**
  - Global permissions (from JWT) + Post-scoped bitmasks (from post_access table)
  - **Reference:** `05-authentication-authorization/authorization.md`

- [ ] **Do you know the key type restrictions?**
  - Use Keys cannot have `posts:create` or `keys:issue`
  - Permission envelope rule: child ⊆ parent
  - **Reference:** `05-authentication-authorization/key-capabilities.md`

- [ ] **Can you explain JWT structure and verification?**
  - RS256, `typ` claim (`owner` or `key`), claims structure
  - **Reference:** `05-authentication-authorization/authentication.md`

### Data Model & IDs ✓

- [ ] **Do you understand ID formats?**
  - Internal: BINARY(16)
  - External: hex32 (32-char lowercase hex)
  - Key Public IDs: apub_...
  - **Reference:** `10-reference/identifier-encoding.md`

- [ ] **Do you know where ID conversion happens?**
  - Repositories convert at database boundary
  - Controllers/Services work with hex32
  - **Reference:** `src/Utilities/Ids.php`, `src/Repositories/*.php`

- [ ] **Can you explain lineage fields?**
  - `issued_by_key_id`, `parent_key_id`, `initial_author_key_id`
  - Immutable after creation
  - **Reference:** `03-core-concepts/key-lifecycle.md`, `07-data-model/database-schema.md`

### Implementation Patterns ✓

- [ ] **Do you know how to add a new endpoint?**
  - Route → Validation → Controller → Service → Repository
  - **Reference:** `08-implementation/implementation-guide.md`

- [ ] **Can you find similar patterns in the codebase?**
  - Look for similar endpoints, copy structure, adapt logic
  - **Reference:** `11-development/component-breakdown.md`

- [ ] **Do you understand validation patterns?**
  - Keyed by "METHOD /pattern" in `config/validation.php`
  - **Reference:** `config/validation.php`, `08-implementation/implementation-guide.md`

### Critical Constraints ✓

- [ ] **Do you know the CSRF scope rule?**
  - HTML routes only, never JSON endpoints
  - **Reference:** `04-architecture/architecture-overview.md`

- [ ] **Do you know what never to log?**
  - Passwords, ApiKey secrets, refresh tokens, private keys
  - **Reference:** `09-operations/logging-and-audit.md`

- [ ] **Do you know the response format rules?**
  - `{ data: {...} }` for success
  - `{ error: { code, message, details, request_id } }` for errors
  - **Reference:** `06-api-reference/response-schemas.md`

## Practical Coding Workflow

When given a coding task, follow this workflow:

### Step 1: Understand the Requirement

1. **Read the task carefully** - What exactly needs to be done?
2. **Identify the domain** - Is this about keys, posts, comments, groups, keychains?
3. **Identify the surface** - Console (Owner) or Gateway (Key)?
4. **Identify the operation** - Create, read, update, delete, list?

### Step 2: Find Relevant Documentation

1. **Check the API reference** - `06-api-reference/api-reference.md` or `06-api-reference/routes-inventory.md`
2. **Check domain-specific docs** - Look in relevant folders (e.g., `05-authentication-authorization/` for auth)
3. **Check implementation guide** - `08-implementation/implementation-guide.md` for patterns
4. **Check SSOT if needed** - `12-comprehensive-reference/` for comprehensive reference

### Step 3: Find Similar Patterns

1. **Search the codebase** - Find similar endpoints or operations
2. **Study the pattern** - Controller → Service → Repository flow
3. **Note the differences** - What's different about your task?
4. **Adapt the pattern** - Copy structure, modify logic

### Step 4: Implement Following Patterns

1. **Add route** - In appropriate `config/routes/*.php` file
2. **Add validation** - In `config/validation.php` (keyed by "METHOD /pattern")
3. **Create/update controller** - Extract params, call service, shape response
4. **Create/update service** - Business logic, permissions, audits
5. **Create/update repository** - Data access, ID conversion

### Step 5: Verify Constraints

Before considering the task complete, verify:

- [ ] **CSRF scope** - Only HTML routes have CSRF
- [ ] **Permission checks** - Service layer checks permissions
- [ ] **ID conversion** - Repository converts IDs at boundary
- [ ] **Response format** - Uses ResponseFactory/ErrorFactory
- [ ] **Error handling** - Proper exceptions, not raw errors
- [ ] **Audit events** - Service emits audit events for state changes
- [ ] **No secrets logged** - Never log passwords, tokens, secrets
- [ ] **Prepared statements** - All SQL uses PDO parameter binding

## Quick Reference Guide

When you need information quickly:

### "How do I..."

| Question | Document | Code Location |
|---|---|---|
| Add a new endpoint? | `08-implementation/implementation-guide.md` | `config/routes/*.php`, `src/Controllers/*/` |
| Check permissions? | `05-authentication-authorization/authorization.md` | `src/Services/*Service.php` |
| Convert IDs? | `10-reference/identifier-encoding.md` | `src/Utilities/Ids.php` |
| Format responses? | `06-api-reference/response-schemas.md` | `src/Utilities/ResponseFactory.php` |
| Handle errors? | `06-api-reference/response-schemas.md` | `src/Utilities/ErrorFactory.php` |
| Emit audit events? | `09-operations/logging-and-audit.md` | `src/Services/*Service.php` |
| Validate input? | `08-implementation/implementation-guide.md` | `config/validation.php` |
| Access database? | `08-implementation/implementation-guide.md` | `src/Repositories/*Repository.php` |

### "What does X mean?"

- **Glossary:** `03-core-concepts/glossary.md`
- **Architecture terms:** `04-architecture/architecture-overview.md`
- **Auth terms:** `05-authentication-authorization/authentication.md`, `authorization.md`
- **API terms:** `06-api-reference/api-reference.md`

### "Where is the code for X?"

- **File inventory:** `11-development/codebase-inventory.md`
- **Component breakdown:** `11-development/component-breakdown.md`
- **Routes:** `06-api-reference/routes-inventory.md`

### "I need everything about X"

- **Master SSOT Hub:** `/SSOT.md`
- **Canon SSOT:** `12-comprehensive-reference/canon-ssot.md`
- **Appendix SSOT:** `12-comprehensive-reference/appendix-ssot.md`
- **Development SSOT:** `12-comprehensive-reference/development-ssot.md`

## Common Pitfalls to Avoid

### ❌ Don't Do This

- **Put business logic in controllers** - Controllers should only call services
- **Check permissions in repositories** - Permissions are checked in services
- **Use raw SQL** - Always use PDO prepared statements
- **Log secrets** - Never log passwords, tokens, or secrets
- **Change lineage fields** - They're immutable after creation
- **Add CSRF to JSON endpoints** - CSRF is HTML routes only
- **Change key permissions** - Use rotation instead
- **Use BINARY(16) in controllers** - Convert to hex32 at repository boundary
- **Return raw database results** - Use ResponseFactory for consistent format
- **Skip validation** - All inputs must be validated

### ✅ Do This Instead

- **Put business logic in services** - Services handle domain rules
- **Check permissions in services** - Services enforce authorization
- **Use PDO prepared statements** - Parameter binding prevents SQL injection
- **Log only safe data** - Never log secrets (see `09-operations/logging-and-audit.md`)
- **Treat lineage as immutable** - Never update after creation
- **CSRF only on HTML** - JSON endpoints don't need CSRF
- **Rotate keys for permission changes** - Create new key with new permissions
- **Convert IDs at boundary** - Repositories handle conversion
- **Use ResponseFactory** - Consistent response format
- **Validate all inputs** - Use validation schemas

## Testing Your Understanding

Try answering these questions without looking at documentation:

1. **What's the difference between Console and Gateway surfaces?**
2. **Where do you check permissions - controller, service, or repository?**
3. **What format are IDs in when they come from the database?**
4. **What format are IDs in when they go to the API?**
5. **Can a Use Key create posts?**
6. **Where does CSRF protection apply?**
7. **What's the response format for a successful API call?**
8. **What's the response format for an error?**
9. **How do you add a new endpoint?**
10. **What fields in the keys table are immutable?**

If you can answer all of these confidently, you're ready to code!

## Final Checklist: Ready to Code?

Before accepting coding tasks, ensure:

- [ ] You've read the foundational documents (Part 1)
- [ ] You've explored the codebase (Part 2)
- [ ] You can answer the verification questions above
- [ ] You know where to find information when stuck
- [ ] You understand the common pitfalls and how to avoid them
- [ ] You can trace a request through the system
- [ ] You know how to add a new endpoint following patterns
- [ ] You understand all critical constraints

## You're Ready!

If you've completed all three primers and can answer the verification questions, you're ready to work effectively with CRE8.pw.

**Remember:**
- Documentation is your friend - use it liberally
- Patterns are your guide - find similar code and adapt it
- Constraints are non-negotiable - respect them always
- When in doubt, check the SSOT documents or ask for clarification

**Happy coding!**
