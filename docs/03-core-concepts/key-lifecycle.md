# Key Lifecycle & Provenance

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Key issuance workflows, rotation mechanics, lineage tracking, use count/device limits, and accountability chains.

**SSoT Ownership:**
- Key minting processes (Primary, Secondary, Use)
- Key rotation mechanics
- Lineage immutability rules
- Use count and device limit enforcement
- Downstream lineage viewing and bulk operations

---

## 1. Hierarchical Key System Overview

CRE8.pw uses a hierarchical key structure for accountability:

```
Owner (human)
 └─ Primary Author Key (root machine principal)
     ├─ Secondary Author Key (delegated)
     │   ├─ Secondary Author Key (further delegated)
     │   └─ Use Key (read/comment only)
     └─ Use Key (read/comment only)
```

**Every key traces back to its root Primary Author Key** via immutable lineage fields.

---

## 2. Owner Mints Primary Author Key (Console)

### 2.1 Endpoint

```
POST /console/keys/primary
```

**Auth:** Owner JWT (`typ=owner`)
**Required Permission:** `keys:issue`

### 2.2 Request

```json
{
  "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write"],
  "label": "My Content Creation Key"
}
```

### 2.3 Server Process

1. Verify Owner has `keys:issue` permission
2. Generate `key_id` (BINARY(16))
3. Generate `key_public_id` (`apub_...` format)
4. Generate `key_secret` (long random string)
5. Hash `key_secret` with Argon2id → `key_secret_hash`
6. Insert into `keys`:
   - `type = 'primary'`
   - `permissions_json = <requested permissions>`
   - `issued_by_key_id = NULL`
   - `parent_key_id = NULL`
   - `initial_author_key_id = key_id` (self-reference)
7. Insert into `key_public_ids`: `(key_id, key_public_id)`
8. Emit audit event: `keys:mint` with metadata

### 2.4 Response

```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "key_public_id": "apub_8cd1a2b3c4d5e6f7",
    "key_secret": "sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
  }
}
```

**Critical:** `key_secret` is returned ONCE and never again. Client must store securely.

---

## 3. Author Key Mints Secondary Author Key (Gateway)

### 3.1 Endpoint

```
POST /api/keys/{authorKeyId}/secondary
```

**Auth:** Key JWT (`typ=key`)
**Required Permission:** `keys:issue`
**Key Type:** Primary or Secondary Author

### 3.2 Request

```json
{
  "permissions": ["posts:create", "posts:read", "comments:write"],
  "label": "Delegated Content Key"
}
```

### 3.3 Validation

**Envelope Check:** Child permissions ⊆ parent permissions

```php
$parentPermissions = $parentKey->permissions_json; // ["posts:create", "keys:issue", ...]
$requestedPermissions = $request->permissions;     // ["posts:create", "posts:read"]

$forbidden = array_diff($requestedPermissions, $parentPermissions);
if (!empty($forbidden)) {
    throw new ValidationException("Permissions not in parent: " . implode(', ', $forbidden));
}
```

### 3.4 Server Process

1. Verify parent key has `keys:issue`
2. Verify parent key type is `primary` or `secondary`
3. Verify envelope rule
4. Generate new key with:
   - `type = 'secondary'`
   - `issued_by_key_id = <parent key_id>`
   - `parent_key_id = <parent key_id>`
   - `initial_author_key_id = <parent's initial_author_key_id>` (propagate root)
5. Store and return

### 3.5 Lineage Propagation

**Critical:** `initial_author_key_id` is **copied** from parent, ensuring all descendants trace to the same root Primary Author Key.

---

## 4. Author Key Mints Use Key (Gateway)

### 4.1 Endpoint

```
POST /api/keys/{authorKeyId}/use
```

### 4.2 Request

```json
{
  "permissions": ["posts:read", "comments:write"],
  "label": "Share Link for Alice",
  "use_count": 1,
  "device_limit": null
}
```

### 4.3 Validation

**Envelope Check:** Same as Secondary

**Use Key Restrictions:**
```php
$forbidden = ['posts:create', 'keys:issue'];
$invalid = array_intersect($requestedPermissions, $forbidden);
if (!empty($invalid)) {
    throw new ValidationException("Use Keys cannot have: " . implode(', ', $invalid));
}
```

### 4.4 Server Process

Same as Secondary, but:
- `type = 'use'`
- `use_count_limit = <value or null>`
- `device_limit = <value or null>`

### 4.5 Response

```json
{
  "data": {
    "key_id": "c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4",
    "key_public_id": "apub_9de0f1a2b3c4d5e6",
    "key_secret": "sec_b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7",
    "use_count": 1,
    "device_limit": null
  }
}
```

---

## 5. Use Count and Device Limits

### 5.1 Use Count Enforcement

**Field:** `keys.use_count_limit`, `keys.use_count_current`

**Middleware/Service Logic:**
```php
if ($key->use_count_limit !== null) {
    if ($key->use_count_current >= $key->use_count_limit) {
        throw new ForbiddenException('Use limit exceeded', 'use_limit_exceeded');
    }
    // Increment on successful auth or per-request
    KeyRepository::incrementUseCount($key->id);
}
```

**When to Increment:**
- Option A: On every successful ApiKey exchange
- Option B: On every authenticated request (more restrictive)

**Recommended:** Option A (per-exchange) for simplicity.

### 5.2 Device Limit Enforcement

**Table:** `key_devices` (optional, not in core schema)

```sql
CREATE TABLE key_devices (
  id BINARY(16) PRIMARY KEY,
  key_id BINARY(16) NOT NULL,
  device_fingerprint VARCHAR(255) NOT NULL,
  first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(key_id, device_fingerprint)
);
```

**Fingerprint:** Hash of IP + User-Agent (basic) or more sophisticated

**Middleware Logic:**
```php
if ($key->device_limit !== null) {
    $fingerprint = hash('sha256', $ip . $userAgent);
    $deviceCount = KeyDeviceRepository::countDistinct($key->id);

    if (!KeyDeviceRepository::exists($key->id, $fingerprint)) {
        // New device
        if ($deviceCount >= $key->device_limit) {
            throw new ForbiddenException('Device limit exceeded', 'device_limit_exceeded');
        }
        KeyDeviceRepository::register($key->id, $fingerprint);
    }
}
```

---

## 6. Key Rotation

### 6.1 Endpoint

```
POST /console/keys/{keyId}/rotate
```

**Auth:** Owner JWT
**Required Permission:** `keys:rotate`

### 6.2 Process

**Purpose:** Replace a key while preserving lineage

**Steps:**
1. Load old key
2. Generate new key with:
   - Same `type`, `permissions_json`
   - Same lineage fields (`issued_by_key_id`, `parent_key_id`, `initial_author_key_id`)
   - `rotated_from_id = <old key_id>`
3. Update old key:
   - `rotated_to_id = <new key_id>`
   - `retired_at = NOW()`
   - `active = 0`
4. Emit audit event: `keys:rotate`

### 6.3 Response

```json
{
  "data": {
    "old_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "new_key_id": "d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1",
    "new_key_public_id": "apub_a2b3c4d5e6f7g8h9",
    "new_key_secret": "sec_c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8"
  }
}
```

**Client Responsibility:** Replace old ApiKey with new one.

---

## 7. Key Activation/Deactivation

### 7.1 Endpoints

```
POST /console/keys/{keyId}/activate
POST /console/keys/{keyId}/deactivate
```

**Query Param (Deactivate):** `?cascade=true` (optional, deactivate descendants)

### 7.2 Deactivation Process

**Simple (No Cascade):**
```sql
UPDATE keys SET active = 0 WHERE id = ?;
```

**Cascade (Deactivate Lineage):**
```sql
WITH RECURSIVE descendants AS (
  SELECT id FROM keys WHERE id = ?
  UNION ALL
  SELECT k.id FROM keys k
  INNER JOIN descendants d ON k.parent_key_id = d.id
)
UPDATE keys SET active = 0 WHERE id IN (SELECT id FROM descendants);
```

**Use Case:** Deactivate a compromised key and all its children in one operation.

---

## 8. Lineage Immutability

### 8.1 Immutable Fields

Once set, these fields **never change**:
- `issued_by_key_id`
- `parent_key_id`
- `initial_author_key_id`

**Enforced By:** Database constraints (NO UPDATE) or application logic rejecting updates

### 8.2 Why Immutability?

- **Auditability:** Full provenance trail
- **Accountability:** Always know who minted a key
- **Bulk Operations:** Disable entire lineages with confidence

---

## 9. Viewing Downstream Lineage (Owner)

### 9.1 Endpoint

```
GET /console/keys/{keyId}/lineage
```

**Auth:** Owner JWT
**Required Permission:** `keys:read`

### 9.2 Response

```json
{
  "data": {
    "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "type": "primary",
    "label": "My Content Key",
    "children": [
      {
        "key_id": "c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5",
        "type": "secondary",
        "label": "Delegated Key",
        "children": [
          {
            "key_id": "d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7",
            "type": "use",
            "label": "Share Link",
            "children": []
          }
        ]
      }
    ]
  }
}
```

**Recursive Query:** Use CTE to build tree from `parent_key_id` relationships.

---

## 10. Combining Keys: "Keyring Key" Concept

### 10.1 Problem

Owner wants to create a single key representing the combined permissions of multiple keys.

### 10.2 Implementation via Groups

**Approach:** Use Groups to grant access to multiple keys simultaneously

**Steps:**
1. Owner creates Group: `POST /console/groups`
2. Owner adds Keys to Group: `POST /console/groups/{groupId}/members`
3. Owner grants Group access to Post: `POST /console/posts/{postId}/access/grant-group`
4. All Keys in Group inherit access

**Result:** Functionally equivalent to a "combined key" without creating synthetic keys.

### 10.3 Future Enhancement

**Synthetic "Keyring Key":** A special key type that dynamically resolves permissions from a keychain.

**Not implemented in v1.0.0** — Groups provide sufficient functionality.

---

## 11. Accountability and Bulk Operations

### 11.1 Use Case: Revoke Compromised Key

**Scenario:** A Primary Author Key is compromised

**Owner Action:**
```
POST /console/keys/{compromisedKeyId}/deactivate?cascade=true
```

**Effect:**
- Compromised key: `active = 0`
- All Secondary keys minted from it: `active = 0`
- All Use keys minted from it or its descendants: `active = 0`

**Result:** Entire lineage disabled in one operation.

### 11.2 Lineage Traversal Depth

**Recommendation:** Limit recursive descent to prevent performance issues

**Example:** Max depth = 10 levels

```php
const MAX_LINEAGE_DEPTH = 10;

function getDescendants($keyId, $depth = 0) {
    if ($depth >= self::MAX_LINEAGE_DEPTH) {
        throw new Exception('Max lineage depth exceeded');
    }
    // ...recursive query with depth check
}
```

---

## 12. Key Lifecycle Summary

| Action | Who | Where | Permission | Effect |
|---|---|---|---|---|
| Mint Primary | Owner | Console | `keys:issue` | Create root key, lineage NULL |
| Mint Secondary | Author Key | Gateway | `keys:issue` | Create child, propagate lineage |
| Mint Use | Author Key | Gateway | `keys:issue` | Create leaf, enforce restrictions |
| Rotate | Owner | Console | `keys:rotate` | Replace key, preserve lineage |
| Activate/Deactivate | Owner | Console | `keys:state:update` | Toggle active flag ± cascade |
| View Lineage | Owner | Console | `keys:read` | See descendants tree |

---

**Next:** **[post-sharing.md](post-sharing.md)**
