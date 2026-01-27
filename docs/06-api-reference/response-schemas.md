# Response Schemas & Error Handling

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Standard success/error response formats, HTTP status code mapping, error code catalog, validation error details.

**SSoT Ownership:**
- Success response envelopes
- Error response schema
- Error code taxonomy and HTTP mapping
- Validation error format

---

## 1. Standard Success Responses

### 1.1 Single Object

```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "type": "primary"
  }
}
```

### 1.2 List (With Paging)

```json
{
  "data": [
    { "post_id": "abc...", "content": "..." },
    { "post_id": "def...", "content": "..." }
  ],
  "paging": {
    "limit": 20,
    "cursor": "def..."
  }
}
```

**Cursor:** Opaque value for next page (typically last item ID).

---

## 2. Standard Error Response

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

**Fields:**
- `code`: Stable, machine-readable error identifier
- `message`: Human-readable summary (safe to display)
- `details`: Structured error-specific data
- `request_id`: Correlation ID (if tracing enabled)

---

## 3. Error Code Taxonomy

| HTTP | Code | When | Required Details |
|---:|---|---|---|
| 400 | `bad_request` | Malformed JSON, invalid header format, invalid state transition | optional |
| 401 | `unauthorized` | Missing/invalid/expired token; refresh replay; invalid ApiKey | optional |
| 403 | `forbidden` | Authenticated but lacks permission/mask | `required` (list permissions) |
| 404 | `not_found` | Resource missing or hidden | optional |
| 409 | `conflict` | Uniqueness violation, state conflict | optional |
| 422 | `validation_failed` | Validation errors | **required** (`details.fields`) |
| 429 | `rate_limited` | Rate limit exceeded | `retry_after_seconds` when known |
| 500 | `internal_error` | Unhandled error | none (log internally) |
| 503 | `service_unavailable` | Dependency outage | optional |

---

## 4. Validation Error Format

**HTTP 422 MUST include `details.fields`:**

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "content": ["Content is required"],
        "permissions": ["Must be a subset of parent permissions"]
      }
    }
  }
}
```

**Format:** `{ "field_name": ["error message 1", "error message 2"] }`

---

## 5. Auth/Security Error Rules

### 5.1 ApiKey Exchange Failures

**Return:** `401 unauthorized`
**Message:** Generic "Invalid credentials"
**Never reveal:** Whether `key_public_id` exists, which part failed

### 5.2 Refresh Token Issues

| Issue | Response |
|---|---|
| Invalid/expired refresh token | `401 unauthorized` |
| Refresh replay (rotated_at NOT NULL) | `401 unauthorized` + security log |

### 5.3 Permission Failures

**Missing global permission:**
```json
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient permissions",
    "details": {
      "required": ["posts:create"]
    }
  }
}
```

**Missing post mask:**
```json
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient post access",
    "details": {
      "required_mask": "COMMENT",
      "required_mask_value": 2
    }
  }
}
```

---

## 6. HTTP Status Code Mapping

| Status | Usage |
|---|---|
| 200 OK | Successful GET, PUT, PATCH |
| 201 Created | Successful POST (resource created) |
| 204 No Content | Successful DELETE |
| 400 Bad Request | Malformed request |
| 401 Unauthorized | Auth failure |
| 403 Forbidden | Authz failure |
| 404 Not Found | Resource missing/hidden |
| 409 Conflict | Uniqueness/state conflict |
| 422 Unprocessable Entity | Validation failure |
| 429 Too Many Requests | Rate limited |
| 500 Internal Server Error | Uncaught exception |
| 503 Service Unavailable | Dependency down |

---

## 7. Error Examples

### 7.1 Unauthorized (Expired Token)

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Token expired",
    "details": {},
    "request_id": null
  }
}
```

### 7.2 Forbidden (Missing Permission)

```json
{
  "error": {
    "code": "forbidden",
    "message": "Insufficient permissions",
    "details": {
      "required": ["keys:issue"]
    },
    "request_id": "req_xyz789"
  }
}
```

### 7.3 Validation Failed (Envelope Violation)

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed",
    "details": {
      "fields": {
        "permissions": ["Permissions not in parent: groups:manage"]
      }
    }
  }
}
```

### 7.4 Rate Limited

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

### 7.5 Internal Error

```json
{
  "error": {
    "code": "internal_error",
    "message": "An unexpected error occurred",
    "details": {},
    "request_id": "req_abc123"
  }
}
```

**Never include:** Stack traces, internal paths, secret values.

---

**Next:** **[logging-and-audit.md](../09-operations/logging-and-audit.md)**
