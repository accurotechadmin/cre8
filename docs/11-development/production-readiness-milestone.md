# Production Readiness Milestone - Development Task List

**Milestone:** Production Readiness Fixes  
**Date Created:** 2026-01-25  
**Status:** Ready for Development

---

## Overview

This milestone addresses 4 verified production readiness issues:
1. **CRITICAL:** GroupService missing AuditService injection (will cause fatal errors)
2. **MINOR:** Transaction rollback error logging (improves debugging)
3. **MINOR:** Rate limiter fallback behavior (production hardening)
4. **CLEANUP:** TICKET/FIX comments removal (code cleanliness)

**Estimated Total Effort:** 2-3 hours  
**Priority:** Critical issue must be fixed immediately; others can be done in parallel

---

## Issue #1: GroupService Missing AuditService Injection ⚠️ CRITICAL

**Priority:** CRITICAL - Blocks production deployment  
**Estimated Time:** 15 minutes  
**Dependencies:** None

### Task 1.1: Add AuditService to GroupService Constructor
**File:** `src/Services/GroupService.php`

**Steps:**
1. Add `AuditService $auditService` parameter to constructor (line 34-38)
2. Add `private AuditService $auditService` property declaration
3. Verify all 5 audit event calls will work:
   - Line 89: `groups:create`
   - Line 222: `groups:rename`
   - Line 274: `groups:delete`
   - Line 348: `groups:member:add`
   - Line 405: `groups:member:remove`

**Acceptance Criteria:**
- [ ] Constructor includes `AuditService $auditService` parameter
- [ ] Property is declared as `private AuditService $auditService`
- [ ] All 5 audit event calls compile without errors
- [ ] Code follows same pattern as KeychainService, PostService, CommentService

**Verification:**
```bash
# Run PHP syntax check
php -l src/Services/GroupService.php

# Verify DI container can resolve dependencies
# (will be caught by container if missing)
```

---

### Task 1.2: Verify DI Container Configuration
**File:** `config/container.php`

**Steps:**
1. Verify `GroupService::class => DI\autowire()` exists (line 140)
2. Confirm autowiring will resolve AuditService dependency
3. No changes needed if autowiring is enabled (it should auto-resolve)

**Acceptance Criteria:**
- [ ] `GroupService::class => DI\autowire()` is present in container config
- [ ] AuditService is registered in container (line 143)
- [ ] Autowiring will automatically inject AuditService

**Verification:**
- DI container should automatically resolve AuditService via autowiring
- Test by instantiating GroupService through container

---

### Task 1.3: Test GroupService Audit Events
**Files:** Test files or manual testing

**Steps:**
1. Create a test group via `POST /console/groups`
2. Verify audit event is created in `audit_events` table
3. Test all group operations:
   - Create group
   - Rename group
   - Delete group
   - Add member
   - Remove member

**Acceptance Criteria:**
- [ ] Group creation emits audit event
- [ ] Group rename emits audit event
- [ ] Group deletion emits audit event
- [ ] Member addition emits audit event
- [ ] Member removal emits audit event
- [ ] No fatal errors occur

**Verification:**
```bash
# Manual test via API
curl -X POST http://localhost:8000/console/groups \
  -H "Authorization: Bearer <owner_jwt>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Group"}'

# Check audit_events table
SELECT * FROM audit_events WHERE action = 'groups:create' ORDER BY created_at DESC LIMIT 1;
```

---

## Issue #8: Transaction Rollback Error Logging ⚠️ MINOR

**Priority:** MINOR - Improves debugging but not blocking  
**Estimated Time:** 30 minutes  
**Dependencies:** None

### Task 8.1: Identify All Transaction Rollback Locations
**Files:** `src/Services/KeyService.php` and potentially others

**Steps:**
1. Search for all `catch (\Throwable $e)` blocks with `rollBack()` calls
2. Document locations:
   - KeyService::mintPrimaryKey() - line 113-115
   - KeyService::mintSecondaryKey() - line 227-228
   - KeyService::mintUseKey() - line 344-345
   - KeyService::rotateKey() - line 452-453
3. Check if any other services use transactions (PostService, KeychainService, etc.)

**Acceptance Criteria:**
- [ ] All transaction rollback locations identified
- [ ] List of files and line numbers documented
- [ ] Pattern identified (all follow same structure)

**Verification:**
```bash
# Find all transaction rollback patterns
grep -r "rollBack" src/Services/ --include="*.php" -A 3 -B 3
```

---

### Task 8.2: Add Transaction-Specific Logging
**Files:** `src/Services/KeyService.php` (and others if found)

**Steps:**
1. Import LoggingService if not already imported
2. Add logger property to KeyService constructor (or use existing logger)
3. In each catch block before `rollBack()`, add:
   ```php
   // Log transaction failure for debugging
   if ($this->logger !== null) {
       LoggingService::log(
           $this->logger,
           'ERROR',
           'Transaction rollback: ' . $e->getMessage(),
           LoggingService::sanitizeContext([
               'method' => __METHOD__,
               'exception' => get_class($e),
               'key_id' => $keyIdHex32 ?? null,
           ])
       );
   }
   ```
4. Apply same pattern to all transaction rollback locations

**Acceptance Criteria:**
- [ ] All transaction rollback catch blocks include logging
- [ ] Log messages include context (method name, exception type, relevant IDs)
- [ ] Sensitive data is sanitized before logging
- [ ] Logging uses appropriate log level (ERROR)

**Verification:**
- Test by intentionally causing a transaction failure
- Verify log entry appears in logs with proper context
- Ensure no sensitive data (secrets, passwords) is logged

---

### Task 8.3: Verify Logger Injection
**File:** `src/Services/KeyService.php`

**Steps:**
1. Check if KeyService has logger property
2. If not, add `LoggerInterface $logger` to constructor
3. Update DI container if needed
4. Verify logger is available in all transaction methods

**Acceptance Criteria:**
- [ ] KeyService has logger property or uses LoggingService static methods
- [ ] Logger is properly injected via DI
- [ ] All transaction methods can access logger

**Verification:**
- Check constructor for logger dependency
- Verify DI container configuration

---

## Issue #9: Rate Limiter Fallback Behavior ⚠️ MINOR

**Priority:** MINOR - Production hardening  
**Estimated Time:** 20 minutes  
**Dependencies:** None

### Task 9.1: Review Current Fallback Behavior
**File:** `config/container.php` lines 48-78

**Steps:**
1. Review current implementation (lines 68-73)
2. Understand current behavior:
   - Redis connection failure → logs to error_log → falls back to InMemoryStorage
   - Comment says "In production, you might want to throw here instead"
3. Check if APP_ENV is available in container context

**Acceptance Criteria:**
- [ ] Current behavior understood
- [ ] APP_ENV availability confirmed
- [ ] Production vs development behavior requirements clarified

**Verification:**
- Review code and comments
- Check BootstrapValidator for APP_ENV validation

---

### Task 9.2: Implement Production Fail-Fast Behavior
**File:** `config/container.php`

**Steps:**
1. Check APP_ENV in container function
2. If `APP_ENV === 'production'` and Redis connection fails:
   - Log as CRITICAL level (use proper logger if available)
   - Throw RuntimeException instead of falling back
3. If `APP_ENV !== 'production'`:
   - Keep current graceful fallback behavior
   - Log warning (current behavior)

**Implementation:**
```php
} catch (\Exception $e) {
    $env = $_ENV['APP_ENV'] ?? 'production';
    
    if ($env === 'production') {
        // Production: fail fast - Redis is required
        error_log("CRITICAL: Redis connection failed in production: " . $e->getMessage());
        throw new \RuntimeException(
            "Redis connection failed. Rate limiting requires Redis in production environment.",
            0,
            $e
        );
    } else {
        // Development: graceful fallback
        error_log("Redis connection failed: " . $e->getMessage() . ". Falling back to in-memory storage.");
        return new Symfony\Component\RateLimiter\Storage\InMemoryStorage();
    }
}
```

**Acceptance Criteria:**
- [ ] Production environment throws exception on Redis failure
- [ ] Development environment maintains graceful fallback
- [ ] Error message is clear and actionable
- [ ] Original exception is preserved (chained exception)

**Verification:**
```bash
# Test in production mode
APP_ENV=production php -r "require 'config/container.php';"

# Test in development mode (should fallback gracefully)
APP_ENV=development php -r "require 'config/container.php';"
```

---

### Task 9.3: Update Documentation
**Files:** `.env.example`, [environment-configuration.md](../10-reference/environment-configuration.md)

**Steps:**
1. Add note to `.env.example` about Redis requirement in production
2. Update environment configuration docs if needed
3. Document that `RATE_LIMIT_BACKING=redis` is required in production

**Acceptance Criteria:**
- [ ] `.env.example` documents Redis requirement for production
- [ ] Documentation updated if needed
- [ ] Clear guidance on production vs development behavior

**Verification:**
- Review `.env.example` for Redis configuration notes
- Check environment configuration documentation

---

## Issue #10: TICKET/FIX Comments Cleanup ⚠️ CODE CLEANLINESS

**Priority:** LOW - Code cleanliness  
**Estimated Time:** 1-2 hours  
**Dependencies:** None (can be done in parallel)

### Task 10.1: Inventory All TICKET/FIX Comments
**Files:** All PHP files in `src/`

**Steps:**
1. Search for all `TICKET T` comments
2. Search for all `FIX-` comments
3. Create inventory list:
   - File path
   - Line number
   - Comment type (TICKET or FIX)
   - Comment content
4. Categorize:
   - TICKET comments that reference completed work (can be removed)
   - FIX-X COMPLETE comments (can be removed)
   - TICKET comments that reference future work (keep or convert to TODO)
   - FIX comments that are still relevant (keep or convert to proper comments)

**Acceptance Criteria:**
- [ ] Complete inventory of all TICKET/FIX comments
- [ ] Categorized by type and relevance
- [ ] List of files to clean up

**Verification:**
```bash
# Find all TICKET comments
grep -rn "TICKET T" src/ --include="*.php" | wc -l

# Find all FIX comments
grep -rn "FIX-" src/ --include="*.php" | wc -l

# Find FIX-.*COMPLETE comments
grep -rn "FIX-.*COMPLETE" src/ --include="*.php"
```

---

### Task 10.2: Remove Completed TICKET Comments
**Files:** All PHP files in `src/`

**Steps:**
1. For each TICKET comment referencing completed work:
   - Remove the comment if it's just tracking info
   - Convert to proper docblock comment if it contains useful context
   - Keep if it references future work or important context
2. Focus on comments like:
   - `TICKET T7.1: Key minting flows` (completed)
   - `TICKET T17.2: Audit events` (completed)
3. Preserve comments that add context or reference documentation

**Acceptance Criteria:**
- [ ] Completed TICKET comments removed
- [ ] Useful context preserved in proper docblock format
- [ ] No loss of important information
- [ ] Code still compiles and works

**Verification:**
- Run syntax check: `php -l` on all modified files
- Run any existing tests
- Verify no functionality is broken

---

### Task 10.3: Remove FIX-X COMPLETE Comments
**Files:** All PHP files in `src/`

**Steps:**
1. Find all `FIX-X COMPLETE` comments (usually at end of files)
2. Remove these comments as they're development markers
3. Examples:
   - `// FIX-1.2 COMPLETE: Added owner_id column support`
   - `// FIX-2.3 COMPLETE: Implemented Redis-backed rate limiter`
4. If the comment contains important historical context, consider moving to git commit message or changelog

**Acceptance Criteria:**
- [ ] All `FIX-X COMPLETE` comments removed
- [ ] No important historical context lost (consider git history)
- [ ] Code files end cleanly without these markers

**Verification:**
```bash
# Find all FIX-.*COMPLETE comments
grep -rn "FIX-.*COMPLETE" src/ --include="*.php"

# After cleanup, verify none remain
grep -rn "FIX-.*COMPLETE" src/ --include="*.php" | wc -l
# Should return 0
```

---

### Task 10.4: Convert Remaining FIX Comments to Proper Documentation
**Files:** Files with relevant FIX comments

**Steps:**
1. Review remaining FIX comments (not marked COMPLETE)
2. Convert to proper docblock comments if they document important behavior
3. Examples:
   - `// FIX-2.3: Implement Redis-backed storage` → Keep as implementation note or remove if completed
   - `// FIX-1.3: Verify key is owned by owner` → Convert to proper docblock explaining the check

**Acceptance Criteria:**
- [ ] Relevant FIX comments converted to proper documentation
- [ ] Code is self-documenting where possible
- [ ] Important implementation notes preserved

**Verification:**
- Review modified files for proper comment formatting
- Ensure code is readable without development markers

---

### Task 10.5: Clean Up Config Files
**Files:** `config/container.php`, `config/routes/*.php`, `config/validation.php`

**Steps:**
1. Remove TICKET comments from config files
2. Remove FIX-X COMPLETE comments
3. Keep useful comments that explain configuration decisions
4. Ensure config files are clean and production-ready

**Acceptance Criteria:**
- [ ] Config files free of development markers
- [ ] Useful comments preserved
- [ ] Files are production-ready

**Verification:**
- Review all config files
- Ensure no TICKET/FIX markers remain

---

## Testing & Verification Checklist

### Pre-Deployment Testing

**For Issue #1 (GroupService):**
- [ ] Create group via API → verify no fatal error
- [ ] Check audit_events table → verify event created
- [ ] Test all group operations (create, rename, delete, add/remove member)
- [ ] Verify all operations emit audit events

**For Issue #8 (Transaction Logging):**
- [ ] Intentionally cause transaction failure (e.g., invalid data)
- [ ] Verify log entry appears with proper context
- [ ] Verify no sensitive data in logs
- [ ] Test all transaction methods

**For Issue #9 (Rate Limiter):**
- [ ] Test with Redis unavailable in production mode → should throw exception
- [ ] Test with Redis unavailable in development mode → should fallback gracefully
- [ ] Verify error messages are clear
- [ ] Test with Redis available → should work normally

**For Issue #10 (Code Cleanup):**
- [ ] Run syntax check on all modified files: `find src/ -name "*.php" -exec php -l {} \;`
- [ ] Verify no broken functionality
- [ ] Review code for readability
- [ ] Ensure no important context lost

---

## Deployment Checklist

### Before Merging to Main:
- [ ] All tasks completed
- [ ] All acceptance criteria met
- [ ] All tests passing
- [ ] Code reviewed
- [ ] Documentation updated if needed

### Critical Path:
1. **Issue #1 MUST be fixed before any production deployment**
2. Issues #8, #9, #10 can be done in any order or in parallel

---

## File Change Summary

### Files to Modify:

**Issue #1:**
- `src/Services/GroupService.php` - Add AuditService injection

**Issue #8:**
- `src/Services/KeyService.php` - Add transaction logging (4 locations)
- Potentially other service files if they use transactions

**Issue #9:**
- `config/container.php` - Update rate limiter fallback logic
- `.env.example` - Document Redis requirement (optional)

**Issue #10:**
- All PHP files in `src/` - Remove TICKET/FIX comments (~75 files)
- Config files - Clean up comments

---

## Risk Assessment

**Issue #1:** **HIGH RISK** - Will cause fatal errors in production if not fixed  
**Issue #8:** **LOW RISK** - Improvement only, no breaking changes  
**Issue #9:** **MEDIUM RISK** - Changes production behavior (fail-fast), needs testing  
**Issue #10:** **LOW RISK** - Code cleanup only, but verify no important context lost

---

## Notes

- Issue #1 is blocking for production - must be fixed immediately
- Issues #8, #9, #10 are improvements and can be done incrementally
- For Issue #10, consider doing a first pass to identify which comments to keep vs remove
- All changes should be tested in development environment before production deployment
