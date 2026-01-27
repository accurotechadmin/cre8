# CRE8.pw Contract Tests

**Purpose:** Automated compliance tests to enforce invariants and prevent regressions.

## Test Suite

### ID Format Compliance (`test_id_format_compliance.php`)

**TICKET T19.1:** ID format compliance tests

Validates that:
- All route parameters ending in `Id` accept only hex32 format (32-character lowercase hex)
- Route parameter `keyPublicId` accepts only `apub_...` format
- Invalid formats are properly rejected

**Run:**
```bash
php tools/contract/test_id_format_compliance.php
```

**What it tests:**
- hex32 validation (valid/invalid cases)
- Key public ID (`apub_...`) validation (valid/invalid cases)
- Route parameter validation logic (simulates `RouteParameterValidatorMiddleware`)

**Expected output:**
- All tests pass with exit code 0
- Failures are reported with exit code 1

### Audience Segregation (`test_audience_segregation.php`)

**TICKET T19.2:** Audience segregation tests

Validates that:
- Console tokens (aud = `/console`) cannot be used on Gateway routes
- Gateway tokens (aud = `/api`) cannot be used on Console routes
- Tokens with wrong audience are rejected with 401 Unauthorized
- Audience must match exactly (no normalization, no prefix matching)

**Run:**
```bash
php tools/contract/test_audience_segregation.php
```

**What it tests:**
- Audience validation logic (exact match required)
- Middleware audience enforcement (Console vs Gateway)
- Edge cases (trailing slashes, different domains, empty audience)

**Expected output:**
- All tests pass with exit code 0
- Failures are reported with exit code 1

### Doc SSoT Alignment (`test_doc_ssot_alignment.php`)

**TICKET T19.3:** Doc SSoT alignment checks

Validates that:
- Middleware ordering matches canonical order (CORS before auth, CSRF only on HTML, etc.)
- CSRF scope is strictly limited to HTML routes (never JSON)
- Permission names match canonical catalog
- Use Key restrictions match canonical rules (cannot have `posts:create` or `keys:issue`)
- Permission envelope rules match canonical docs (child ⊆ parent)

**Run:**
```bash
php tools/contract/test_doc_ssot_alignment.php
```

**What it tests:**
- Middleware ordering rules (CORS before auth, rate limiting before expensive operations)
- CSRF scope enforcement (HTML routes only, never JSON)
- Permission catalog alignment (Owner permissions, Key permissions, Use Key forbidden)
- Use Key restriction validation
- Permission envelope validation (child permissions must be subset of parent)

**Expected output:**
- All tests pass with exit code 0
- Failures are reported with exit code 1

**Key Rules Verified:**
- CORS must come before JWT auth (for preflight OPTIONS)
- Rate limiting must come before JWT verification
- CSRF must NOT be in JSON pipelines
- CSRF must be in HTML pipelines
- Permission names match canonical catalog exactly
- Use Keys cannot have `posts:create` or `keys:issue`
- Child permissions must be subset of parent permissions
