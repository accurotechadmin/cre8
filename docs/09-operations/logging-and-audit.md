# Logging, Audit & Observability

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Logging conventions, audit event catalog, rate limiting configuration, troubleshooting guide.

**SSoT Ownership:**
- Log channels and required fields
- "Never log secrets" rules
- Audit event catalog and naming conventions
- Rate limiting configuration
- Troubleshooting guide (401 vs 403 vs 422 vs 429)

---

## 1. Logging Conventions

### 1.1 Format

**All logs MUST be structured JSON.**

Example:
```json
{
  "timestamp": "2026-01-21T12:00:00Z",
  "level": "INFO",
  "channel": "api",
  "message": "Request completed",
  "request_id": "req_abc123",
  "method": "POST",
  "path": "/api/posts",
  "status": 201,
  "owner_id": null,
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "ip": "192.168.1.100",
  "user_agent": "curl/7.64.1",
  "latency_ms": 45
}
```

### 1.2 Never Log Secrets

**NEVER log:**
- Passwords (plaintext)
- ApiKey secrets
- Refresh tokens (plaintext)
- Private keys
- Stack traces (in production)

**Safe to log:**
- `owner_id`, `key_id` (hex32)
- `key_public_id` (apub_...)
- JWT `sub` claim
- IP, User-Agent
- Request paths, timestamps

---

## 2. Log Channels

| Channel | Purpose | Typical Events |
|---|---|---|
| `api` | Request summaries | Method, path, status, latency |
| `auth` | Auth events | Exchange, login, refresh, introspect |
| `security` | Security events | Auth failures, refresh replay, rate limits, CSRF (HTML) |
| `db` | Database errors | Query failures, transaction rollbacks |
| `guzzle.http` | Outbound HTTP | External API calls |

### 2.1 Common Log Fields

**All logs SHOULD include:**
- `timestamp` (ISO 8601)
- `level` (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- `channel`
- `message`
- `request_id` (if available)

**Protected endpoint logs SHOULD include:**
- `method`, `path`, `status`
- `owner_id` OR `key_id` (hex32, never both)
- `key_public_id` (apub_..., optional for correlation)
- `ip`, `user_agent`
- `latency_ms`

---

## 3. Audit Events

### 3.1 Required Audit Events

**Owner lifecycle:**
- `owners:register`
- `owners:login`

**Key lifecycle:**
- `keys:mint` (primary, secondary, use)
- `keys:rotate`
- `keys:activate`, `keys:deactivate`

**Group/keychain:**
- `groups:create`, `groups:rename`, `groups:delete`
- `groups:member:add`, `groups:member:remove`
- `keychains:create`
- `keychains:member:add`, `keychains:member:remove`

**Post lifecycle:**
- `posts:create`
- `posts:update:title`

**Post access:**
- `posts:access:grant`
- `posts:access:revoke`

**Security:**
- `refresh:replay_attempt`
- `apikey:exchange:failed` (optional throttle)

### 3.2 Audit Event Structure

**Table:** `audit_events`

```sql
INSERT INTO audit_events (
  id,
  actor_type,      -- 'owner' or 'key'
  actor_id,        -- BINARY(16)
  action,          -- 'keys:mint', 'posts:access:grant'
  subject_type,    -- 'key', 'post', 'group', etc.
  subject_id,      -- BINARY(16)
  metadata_json,   -- { "permissions": [...], "label": "..." }
  ip,
  user_agent,
  created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW());
```

### 3.3 Action Naming Convention

**Format:** `<domain>:<action>` or `<domain>:<subdomain>:<action>`

**Examples:**
- `keys:mint`
- `keys:rotate`
- `groups:member:add`
- `posts:access:grant`
- `refresh:replay_attempt`

---

## 4. Rate Limiting

### 4.1 Configuration

**Environment Variables:**
```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory
```

**Buckets:**
- `GENERAL`: Default for all endpoints
- `AUTH`: Authentication endpoints (`/api/auth/*`, `/console/login`)
- `API`: Gateway endpoints (`/api/*`)

### 4.2 Keying Strategy

| Surface | Key |
|---|---|
| Public routes | IP address |
| Console JSON | `owner_id` (hex32) |
| Gateway JSON | `key_id` (hex32) |

### 4.3 Backing Store

**Default:** `memory` (Symfony ArrayAdapter)
**Persistent:** `database` (Symfony PDOAdapter)
**Not used:** Redis, Memcached

**Configuration:**
```php
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$storage = new InMemoryStorage();
$limiter = new RateLimiterFactory([
    'id' => 'api',
    'policy' => 'sliding_window',
    'limit' => 60,
    'interval' => '1 minute',
], $storage);
```

### 4.4 On Limit Exceeded

**HTTP 429:**
```json
{
  "error": {
    "code": "rate_limited",
    "message": "Too many requests",
    "details": {
      "retry_after_seconds": 60
    }
  }
}
```

**Log to `security` channel:**
```json
{
  "level": "WARNING",
  "channel": "security",
  "message": "Rate limit exceeded",
  "key_id": "b5a1e8c0...",
  "bucket": "api",
  "ip": "192.168.1.100"
}
```

---

## 5. Troubleshooting Guide

### 5.1 401 Unauthorized

**Possible Causes:**
- Access token expired (`exp` claim in past)
- Invalid JWT signature
- `iss` doesn't match `JWT_ISSUER`
- `aud` doesn't match expected audience
- JWKS `kid` mismatch
- Invalid ApiKey (`key_public_id` or `key_secret` wrong)
- Refresh token replay (already rotated)

**Check:**
1. Decode JWT and inspect claims (use jwt.io)
2. Verify `exp` timestamp
3. Check JWKS endpoint for matching `kid`
4. Inspect `auth` log channel for auth failures
5. For refresh: check `security` logs for `refresh:replay_attempt`

### 5.2 403 Forbidden

**Possible Causes:**
- Token valid but lacks required permission
- Token valid but lacks required post mask bit
- Use Key attempting `posts:create` or `keys:issue`
- Inactive key (`active = 0`)

**Check:**
1. Decode JWT and inspect `permissions` array
2. Compare required permission (see **[authorization.md](../05-authentication-authorization/authorization.md)**)
3. For post-scoped actions, check `post_access` table
4. Verify key `active` status in `keys` table

### 5.3 422 Validation Failed

**Possible Causes:**
- Missing required fields
- Invalid format (email, password strength)
- Permission envelope violation (child ⊄ parent)
- Use Key restriction violation (`posts:create` or `keys:issue` requested)

**Check:**
1. Inspect `details.fields` in error response
2. Review `config/validation.php` for `"METHOD /pattern"` rule
3. For envelope errors, compare child vs parent permissions

### 5.4 429 Rate Limited

**Possible Causes:**
- Too many requests from IP (public routes)
- Too many requests from `owner_id` (Console JSON)
- Too many requests from `key_id` (Gateway JSON)

**Check:**
1. Verify rate limit bucket configuration (`RATE_LIMIT_*`)
2. Inspect `security` logs for rate limit triggers
3. Wait `retry_after_seconds` before retrying
4. For legitimate high volume, consider increasing limits

### 5.5 500 Internal Error

**Possible Causes:**
- Unhandled exception in service/repository
- Database connection failure
- Missing environment configuration
- DI wiring error

**Check:**
1. Inspect `db` log channel for query failures
2. Verify all required env vars are set (see **[environment-configuration.md](../10-reference/environment-configuration.md)**)
3. Check DI container configuration in `config/container.php`
4. Review application error logs (not exposed to client)

---

## 6. Monitoring Recommendations

### 6.1 Metrics to Track

**Request metrics:**
- Total requests per minute
- Response times (p50, p95, p99)
- Error rates by status code
- Rate limit hits

**Auth metrics:**
- ApiKey exchange rate
- Refresh rotation rate
- Refresh replay attempts (security event)
- Invalid auth attempts

**Business metrics:**
- Keys minted per day (by type)
- Posts created per day
- Active keys count
- Use Keys exhausted (use_count or device_limit)

### 6.2 Alerting

**Critical alerts:**
- Error rate > 5% for 5 minutes
- p99 latency > 2 seconds
- Database connection failures
- Refresh replay attempts (possible compromise)

**Warning alerts:**
- Error rate > 1% for 10 minutes
- Rate limit triggers increasing
- Disk space low (for logs, if file-based)

---

**Next:** **[identifier-encoding.md](../10-reference/identifier-encoding.md)**
