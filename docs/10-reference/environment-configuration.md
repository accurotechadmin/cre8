# Environment Configuration

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Complete `.env` reference with all configuration variables, defaults, and bootstrap validation requirements.

---

## 1. Application Configuration

```bash
APP_NAME=CRE8.pw
APP_ENV=production       # production, development, testing
APP_DEBUG=false          # true for dev, false for production
APP_URL=https://cre8.pw
```

---

## 2. Database Configuration

```bash
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cre8pw
DB_USER=cre8_user
DB_PASS=secure_password_here
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin
DB_SSL_MODE=DISABLED     # or REQUIRED
```

**Bootstrap Validation:** Application MUST fail fast if DB connection fails.

---

## 3. JWT Configuration

```bash
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console  # Console audience (Owner JWTs)
JWT_AUDIENCE_API=https://cre8.pw/api   # Gateway audience (Key JWTs, optional)
JWT_ACCESS_TTL=900         # 15 minutes (seconds)
JWT_REFRESH_TTL=2592000    # 30 days (seconds)
JWT_LEEWAY=10              # Clock skew tolerance (seconds)
```

**Audience Configuration:**
- `JWT_AUDIENCE`: Required. Used for Owner JWTs (Console surface). Defaults to `APP_URL + '/console'` if not set.
- `JWT_AUDIENCE_API`: Optional. Used for Key JWTs (Gateway surface). If not set, defaults to `APP_URL + '/api'`.

**Bootstrap Validation:**
- MUST fail if `JWT_PRIVATE_KEY_PATH` or `JWT_PUBLIC_KEY_PATH` missing
- MUST fail if key files unreadable or malformed
- MUST fail if `JWT_ISSUER` not set
- `JWT_AUDIENCE` and `JWT_AUDIENCE_API` are optional (have fallback defaults)

---

## 4. CORS Configuration

```bash
CORS_ALLOWED_ORIGINS=https://cre8.pw,https://www.cre8.pw
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_EXPOSED_HEADERS=X-Total-Count,X-Page-Number
```

**Format:** Comma-separated values

---

## 5. CSP Configuration

```bash
CSP_DEFAULT_SRC="'self' https://cre8.pw"
```

**Applied to:** HTML responses only

---

## 6. CSRF Configuration

```bash
CSRF_SECRET=random_32_char_secret_here
```

**Used by:** Slim CSRF Guard on HTML routes

---

## 7. Rate Limiting Configuration

```bash
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory    # or database
```

**Format:** `<limit> per <interval>`
**Backing:** `memory` (default) or `database` (persistent)

---

## 8. HTTP Client Configuration

```bash
HTTP_TIMEOUT=30         # Guzzle timeout (seconds)
HTTP_RETRY_MAX=3        # Max retries
```

---

## 9. Logging Configuration

```bash
LOG_CHANNEL=stack       # or daily, single
LOG_LEVEL=info          # debug, info, warning, error, critical
LOG_PATH=/app/logs
```

---

## 10. Hashing Configuration

```bash
APIKEY_HASH_ALGO=argon2id
PASSWORD_MEMORY_COST=65536    # 64 MB
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

**Tuning:** Adjust costs based on server capacity. Higher = more secure but slower.

---

## 11. Bootstrap Validation Checklist

Application MUST validate on startup:

✅ Database connection successful
✅ JWT private/public keys readable and valid
✅ `JWT_ISSUER` and `JWT_AUDIENCE` set
✅ Required directories writable (logs/)
✅ CORS origins parseable
✅ Rate limit configuration valid

**On failure:** Log error and exit with non-zero code (prevent misconfigured deployment).

---

**Next:** **[glossary.md](../03-core-concepts/glossary.md)**
