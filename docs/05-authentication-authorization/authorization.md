# Authorization & Permissions

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** This document is the authoritative specification for the authorization model: permission strings catalog, role definitions, key type capabilities, post access bitmasks, hierarchical permission envelope rules, and combined authorization checks.

**SSoT Ownership:**
- Complete permission strings catalog with meanings and scopes
- Role definitions and implied permissions
- Key type capability rules (what each key type can/cannot do)
- Post access bitmask definitions (VIEW, COMMENT, MANAGE_ACCESS)
- Permission envelope rules (child ⊆ parent)
- Combined authorization check requirements (permission + bitmask)
- 404 vs 403 rules (visibility vs access)

---

## 1. Authorization Model Overview

CRE8.pw uses a **two-layer authorization system**:

1. **Global Permission Strings:** Capabilities that apply across the system (e.g., `posts:create`, `keys:issue`)
2. **Post-Scoped Bitmasks:** Fine-grained permissions for individual posts (VIEW, COMMENT, MANAGE_ACCESS)

**Key Principle:** Most actions require **both** a global permission AND (for post-scoped actions) an appropriate bitmask.

**Example:**
- To comment on a post, a Key needs:
  - Global: `comments:write` permission
  - Post-scoped: `COMMENT` bitmask (0x02) granted via `post_access` table

---

## 2. Key Types and Capability Rules

### 2.1 Key Type Definitions

| Key Type | Can Mint Keys | Can Create Posts | Can Comment | Can Read Feeds | Minted By |
|---|:---:|:---:|:---:|:---:|---|
| **Primary Author** | ✅ | ✅ | ✅* | ✅* | Owner (Console) |
| **Secondary Author** | ✅ | ✅ | ✅* | ✅* | Primary or Secondary (Gateway) |
| **Use** | ❌ | ❌ | ✅* | ✅ | Primary or Secondary (Gateway) |

*If granted permission + bitmask

### 2.2 Permission Restrictions by Key Type (Normative)

**Use Keys MUST NEVER be granted:**
- `posts:create`
- `keys:issue`

These permissions are **forbidden** for Use Keys. Any attempt to mint a Use Key with these permissions must be rejected with `422 validation_failed`.

**Primary and Secondary Author Keys:**
- May have any permissions granted by their parent (subject to envelope rule)
- Typically include `posts:create` and `keys:issue` for authoring capabilities

---

## 3. Permission Strings Catalog (Normative)

### 3.1 Owner Permissions (Console-Scoped)

| Permission | Meaning | Typical Endpoints |
|---|---|---|
| `owners:manage` | Manage owner profile and settings | `PATCH /console/owners/me` |
| `keys:issue` | Mint Primary Author Keys (Owner context) | `POST /console/keys/primary` |
| `keys:read` | List and view keys in owner scope | `GET /console/keys`, `GET /console/keys/{keyId}` |
| `keys:rotate` | Rotate keys (retire + replace) | `POST /console/keys/{keyId}/rotate` |
| `keys:state:update` | Activate/deactivate keys | `POST /console/keys/{keyId}/activate`, `/deactivate` |
| `groups:manage` | Full CRUD on groups + membership | `POST /console/groups`, `POST /console/groups/{groupId}/members` |
| `keychains:manage` | Manage owner keychains + membership | `POST /console/keychains`, `POST /console/keychains/{id}/members` |
| `posts:admin:read` | Admin view of posts from owner's keys | `GET /console/posts` |
| `posts:access:manage` | Grant/revoke group access to posts | `POST /console/posts/{postId}/access/grant-group` |

### 3.2 Key Permissions (Gateway-Scoped)

| Permission | Meaning | Typical Endpoints |
|---|---|---|
| `keys:issue` | Mint Secondary Author or Use Keys | `POST /api/keys/{authorKeyId}/secondary`, `/use` |
| `posts:create` | Create new posts | `POST /api/posts` |
| `posts:read` | Read/list visible posts (requires VIEW mask) | `GET /api/posts`, `GET /api/posts/{postId}` |
| `comments:write` | Write comments on posts (requires COMMENT mask) | `POST /api/posts/{postId}/comments` |
| `groups:read` | Read groups (read-only access) | `GET /api/groups`, `GET /api/groups/{groupId}` |
| `keychains:manage` | Manage external keychains + membership | `POST /api/keychains`, `POST /api/keychains/{id}/members` |
| `posts:access:manage` | Manage post access grants (requires MANAGE_ACCESS mask) | `POST /api/posts/{postId}/access` |

### 3.3 Permission Naming Conventions

Permissions follow the pattern: `<resource>:<action>`

Examples:
- `posts:create` — create posts
- `keys:issue` — issue (mint) keys
- `groups:manage` — full management (CRUD)
- `groups:read` — read-only access

---

## 4. Role Definitions (Normative)

Roles are coarse groupings of permissions. At token issuance time, roles are resolved to explicit `permissions` arrays in the JWT.

### 4.1 Owner Role

**Assigned to:** Owners (human principals)

**Implied Permissions:**
```json
[
  "owners:manage",
  "keys:issue",
  "keys:read",
  "keys:rotate",
  "keys:state:update",
  "groups:manage",
  "keychains:manage",
  "posts:admin:read",
  "posts:access:manage"
]
```

**Typical JWT:**
```json
{
  "typ": "owner",
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "roles": ["owner"],
  "permissions": ["owners:manage", "keys:issue", ...]
}
```

### 4.2 Author Role (Primary/Secondary Keys)

**Assigned to:** Primary Author Keys, Secondary Author Keys

**Typical Permissions:**
```json
[
  "keys:issue",
  "posts:create",
  "posts:read",
  "comments:write",
  "groups:read",
  "keychains:manage",
  "posts:access:manage"
]
```

**Note:** Actual permissions vary by mint-time specification and parent envelope.

**Typical JWT:**
```json
{
  "typ": "key",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "roles": ["author"],
  "permissions": ["posts:create", "keys:issue", "posts:read", "comments:write"]
}
```

### 4.3 Use Role (Use Keys)

**Assigned to:** Use Keys

**Typical Permissions:**
```json
[
  "posts:read",
  "comments:write",
  "groups:read"
]
```

**Forbidden Permissions:**
- `posts:create`
- `keys:issue`

**Typical JWT:**
```json
{
  "typ": "key",
  "key_id": "c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4",
  "roles": ["use"],
  "permissions": ["posts:read", "comments:write"]
}
```

---

## 5. Post Access Bitmasks (Normative)

### 5.1 Bitmask Definitions

Post-scoped permissions are encoded as integer bitmasks stored in `post_access.permission_mask`.

| Bit Position | Hex Value | Name | Meaning |
|---:|---:|---|---|
| 0 | 0x01 | VIEW | View/read the post |
| 1 | 0x02 | COMMENT | Create comments on the post |
| 3 | 0x08 | MANAGE_ACCESS | Manage post access grants/revocations |

**Unused bits (2, 4-31):** Reserved for future use.

### 5.2 Bitmask Presets

Common combinations:

| Preset | Hex Value | Bits | Meaning |
|---|---:|---|---|
| READ_ONLY | 0x01 | VIEW | Read-only access |
| INTERACT | 0x03 | VIEW + COMMENT | Read and comment |
| ADMIN | 0x0B | VIEW + COMMENT + MANAGE_ACCESS | Full interaction and management |

### 5.3 Bitmask Operations

**Checking if a mask grants a specific capability:**
```php
if ($permissionMask & 0x01) {
    // Has VIEW
}
if ($permissionMask & 0x02) {
    // Has COMMENT
}
if ($permissionMask & 0x08) {
    // Has MANAGE_ACCESS
}
```

**Granting multiple capabilities:**
```php
$mask = 0x01 | 0x02;  // VIEW + COMMENT = 0x03 (INTERACT)
```

---

## 6. Combined Authorization Checks (Normative)

Most post-scoped actions require **both** a global permission AND a post bitmask.

### 6.1 Read Post

**Requirements:**
- Global: `posts:read` permission in JWT
- Post-scoped: `VIEW` bitmask (0x01) in `post_access` for the principal or a group the principal belongs to

**Enforcement (Service Layer):**
```php
// Verify global permission
if (!in_array('posts:read', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: posts:read');
}

// Verify post-scoped mask
$mask = PostRepository::getAccessMask($postId, $keyId);
if (!($mask & 0x01)) {
    throw new NotFoundException(); // or ForbiddenException (see 404 vs 403 below)
}
```

### 6.2 Create Comment

**Requirements:**
- Global: `comments:write`
- Post-scoped: `COMMENT` (0x02)

**Enforcement:**
```php
if (!in_array('comments:write', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: comments:write');
}

$mask = PostRepository::getAccessMask($postId, $keyId);
if (!($mask & 0x02)) {
    throw new ForbiddenException('Insufficient post access: COMMENT required');
}
```

### 6.3 Grant Post Access

**Requirements:**
- Global: `posts:access:manage`
- Post-scoped: `MANAGE_ACCESS` (0x08)

**Enforcement:**
```php
if (!in_array('posts:access:manage', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: posts:access:manage');
}

$mask = PostRepository::getAccessMask($postId, $keyId);
if (!($mask & 0x08)) {
    throw new ForbiddenException('Insufficient post access: MANAGE_ACCESS required');
}
```

### 6.4 Create Post (No Bitmask Required)

**Requirements:**
- Global: `posts:create`
- Key type: `primary` or `secondary` (not `use`)

**Enforcement:**
```php
if (!in_array('posts:create', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: posts:create');
}

if ($key->type === 'use') {
    throw new ForbiddenException('Use Keys cannot create posts');
}
```

---

## 7. Permission Envelope and Issuance Rules (Normative)

### 7.1 Envelope Rule

**Child permissions ⊆ Parent permissions**

When minting a child key (Secondary or Use), the child's permissions must be a **subset** of the parent's permissions.

**Example:**
```
Parent: ["posts:create", "keys:issue", "posts:read", "comments:write"]

✅ Valid child:   ["posts:create", "posts:read"]
✅ Valid child:   ["posts:read", "comments:write"]
❌ Invalid child: ["posts:create", "keys:issue", "groups:manage"]
                  // "groups:manage" not in parent
```

### 7.2 Use Key Restrictions

In addition to the envelope rule, Use Keys have absolute restrictions:

**MUST NOT include:**
- `posts:create`
- `keys:issue`

**Validation:**
```php
if ($keyType === 'use') {
    $forbidden = ['posts:create', 'keys:issue'];
    $invalid = array_intersect($requestedPermissions, $forbidden);
    if (!empty($invalid)) {
        throw new ValidationException("Use Keys cannot have: " . implode(', ', $invalid));
    }
}
```

### 7.3 Enforcement at Mint Time

**Endpoint:** `POST /api/keys/{authorKeyId}/secondary` or `/use`

**Request:**
```json
{
  "permissions": ["posts:read", "comments:write"],
  "label": "Read-Only Key"
}
```

**Service Validation:**
1. Load parent key permissions from `keys.permissions_json`
2. Verify all requested permissions are in parent permissions (envelope rule)
3. If key type is `use`, verify no forbidden permissions
4. If validation passes, create key with specified permissions

---

## 8. Visibility vs Access (404 vs 403 Rules)

### 8.1 When to Return 404 Not Found

**Use 404 when:**
- The resource does not exist, OR
- The principal lacks the permission/mask to even know the resource exists

**Example:**
```php
// User requests GET /api/posts/abc123
// Post exists but user lacks VIEW mask
// → Return 404 (hide existence)
```

**Rationale:** Prevents information leakage. An authenticated user should not be able to discover the existence of posts they cannot access.

### 8.2 When to Return 403 Forbidden

**Use 403 when:**
- The resource exists and the principal can see it (has VIEW), BUT
- The principal lacks permission for the attempted action

**Example:**
```php
// User requests POST /api/posts/abc123/comments
// User has VIEW mask but lacks COMMENT mask
// → Return 403 with details.required = ["COMMENT mask"]
```

**Rationale:** User knows the post exists (can see it), so revealing that they lack comment permission is not a security issue.

### 8.3 Implementation Pattern

```php
// Step 1: Check if post exists and principal has VIEW
$mask = PostRepository::getAccessMask($postId, $keyId);
if (!$mask || !($mask & 0x01)) {
    throw new NotFoundException(); // Hide existence
}

// Step 2: Post is visible; check action-specific permission
if ($action === 'comment' && !($mask & 0x02)) {
    throw new ForbiddenException('Insufficient post access: COMMENT required');
}
```

---

## 9. Permission Immutability and Rotation

### 9.1 Permissions Are Immutable

Once a key is minted, its permissions **cannot be changed**.

**Stored in:** `keys.permissions_json` (JSON array)

**To change permissions:**
1. Rotate the key (see **[key-lifecycle.md](../03-core-concepts/key-lifecycle.md)**)
2. Create new key with desired permissions
3. Retire old key (`retired_at` timestamp)
4. Update lineage references

### 9.2 Why Immutability?

- **Auditability:** Permission changes would complicate provenance tracking
- **Predictability:** Keys behave consistently over their lifetime
- **Simplicity:** No need to track permission history

---

## 10. Example Authorization Scenarios

### 10.1 Scenario: Owner Mints Primary Author Key

**Actor:** Owner with `owner_id = 3f2a9c...`

**Action:** `POST /console/keys/primary`

**Request:**
```json
{
  "permissions": ["posts:create", "keys:issue", "posts:read"],
  "label": "My Content Key"
}
```

**Authorization Check:**
- Owner JWT has `keys:issue` permission? ✅
- No envelope check (Primary keys have no parent)
- Permissions valid for Primary Author? ✅ (no Use Key restrictions)

**Result:** Key created with specified permissions.

### 10.2 Scenario: Primary Key Mints Use Key

**Actor:** Primary Author Key with permissions `["posts:create", "keys:issue", "posts:read", "comments:write"]`

**Action:** `POST /api/keys/{primaryKeyId}/use`

**Request:**
```json
{
  "permissions": ["posts:read", "comments:write"],
  "use_count": 1,
  "device_limit": null
}
```

**Authorization Check:**
- Primary Key JWT has `keys:issue`? ✅
- Requested permissions ⊆ parent permissions? ✅
- Use Key restrictions violated? ❌ (no `posts:create` or `keys:issue` requested)

**Result:** Use Key created with single-use limit.

### 10.3 Scenario: Use Key Attempts to Create Post

**Actor:** Use Key with permissions `["posts:read", "comments:write"]`

**Action:** `POST /api/posts`

**Authorization Check:**
- Use Key JWT has `posts:create`? ❌

**Result:** `403 Forbidden` with `error.code = "forbidden"`, `details.required = ["posts:create"]`

### 10.4 Scenario: Key Comments on Post

**Actor:** Use Key with permissions `["posts:read", "comments:write"]`

**Action:** `POST /api/posts/abc123/comments`

**Authorization Check (Step 1 - Global):**
- Use Key JWT has `comments:write`? ✅

**Authorization Check (Step 2 - Post-Scoped):**
- Load access mask from `post_access` where `target_id = key_id` or `target_type = group` and key is member
- Mask includes COMMENT (0x02)?
  - If yes: ✅ Comment created
  - If no: `403 Forbidden` (missing COMMENT mask)
  - If no access at all: `404 Not Found` (hide post existence)

---

## 11. Action → Required Permission Reference Table

### 11.1 Console JSON (Owner)

| Action | Endpoint | Required Permission | Required Mask |
|---|---|---|---|
| Mint Primary Author Key | `POST /console/keys/primary` | `keys:issue` | N/A |
| List Keys | `GET /console/keys` | `keys:read` | N/A |
| Rotate Key | `POST /console/keys/{keyId}/rotate` | `keys:rotate` | N/A |
| Activate/Deactivate Key | `POST /console/keys/{keyId}/activate` | `keys:state:update` | N/A |
| Create Group | `POST /console/groups` | `groups:manage` | N/A |
| Add Group Member | `POST /console/groups/{groupId}/members` | `groups:manage` | N/A |
| Grant Group Access to Post | `POST /console/posts/{postId}/access/grant-group` | `posts:access:manage` | N/A |

### 11.2 Gateway JSON (Key)

| Action | Endpoint | Required Permission | Required Mask |
|---|---|---|---|
| Mint Secondary Author Key | `POST /api/keys/{keyId}/secondary` | `keys:issue` | N/A |
| Mint Use Key | `POST /api/keys/{keyId}/use` | `keys:issue` | N/A |
| Create Post | `POST /api/posts` | `posts:create` | N/A |
| Read Post | `GET /api/posts/{postId}` | `posts:read` | VIEW (0x01) |
| Create Comment | `POST /api/posts/{postId}/comments` | `comments:write` | COMMENT (0x02) |
| Grant Post Access | `POST /api/posts/{postId}/access` | `posts:access:manage` | MANAGE_ACCESS (0x08) |
| Read Groups | `GET /api/groups` | `groups:read` | N/A |
| Manage External Keychain | `POST /api/keychains` | `keychains:manage` | N/A |

---

## 12. Troubleshooting Authorization Issues

**403 Forbidden - Missing Global Permission:**
- Check JWT `permissions` array
- Compare required permission (see table above) vs actual permissions
- Verify permission was granted at mint time
- If permission missing, consider rotating key with new permissions

**403 Forbidden - Missing Post Mask:**
- Query `post_access` table for grants to this principal or their groups
- Verify `permission_mask` includes required bit (VIEW, COMMENT, or MANAGE_ACCESS)
- If missing, grant access via `POST /console/posts/{postId}/access/grant-group` or similar

**404 Not Found - Post Exists but Not Visible:**
- Principal lacks VIEW mask
- Post exists but `post_access` has no matching grants
- Solution: Grant VIEW mask to principal or a group they belong to

**422 Validation Failed - Envelope Violation:**
- Child key requested permissions not in parent permissions
- Check parent key's `permissions_json`
- Adjust child permission request to be subset of parent

**422 Validation Failed - Use Key Restriction:**
- Use Key requested `posts:create` or `keys:issue`
- These permissions are forbidden for Use Keys
- Change key type to `secondary` if authoring capability is needed

---

**Next:** Proceed to **[api-reference.md](../06-api-reference/api-reference.md)** for the complete API endpoint catalog with required permissions per route.
