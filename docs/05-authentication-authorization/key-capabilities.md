# Helper Draft: Key Capability Matrix

## Key Type Capabilities

| Capability | Owner | Primary Author | Secondary Author | Use Key |
|---|:---:|:---:|:---:|:---:|
| **Authentication** |
| Login via password | ✅ | ❌ | ❌ | ❌ |
| Exchange via ApiKey | ❌ | ✅ | ✅ | ✅ |
| **Key Issuance** |
| Mint Primary Author Key | ✅ | ❌ | ❌ | ❌ |
| Mint Secondary Author Key | ❌ | ✅ | ✅ | ❌ |
| Mint Use Key | ❌ | ✅ | ✅ | ❌ |
| **Content Creation** |
| Create posts | ❌ | ✅ | ✅ | ❌ |
| Create comments | ❌ | ✅* | ✅* | ✅* |
| **Content Access** |
| Read posts | ❌ | ✅* | ✅* | ✅* |
| Read feeds | ❌ | ✅* | ✅* | ✅ |
| **Access Management** |
| Grant post access | ❌ | ✅* | ✅* | ❌ |
| Revoke post access | ❌ | ✅* | ✅* | ❌ |
| **Group Management** |
| Create groups | ✅ | ❌ | ❌ | ❌ |
| Manage group members | ✅ | ❌ | ❌ | ❌ |
| Read groups | ✅ | ✅ | ✅ | ✅ |
| **Keychain Management** |
| Create owner keychain | ✅ | ❌ | ❌ | ❌ |
| Create external keychain | ❌ | ✅* | ✅* | ✅* |
| Manage keychain members | ✅ | ✅* | ✅* | ✅* |
| **Key Lifecycle** |
| View key lineage | ✅ | ❌ | ❌ | ❌ |
| Rotate keys | ✅ | ❌ | ❌ | ❌ |
| Activate/deactivate keys | ✅ | ❌ | ❌ | ❌ |
| **Provenance & Audit** |
| View downstream lineage | ✅ | ❌ | ❌ | ❌ |
| Disable key lineage | ✅ | ❌ | ❌ | ❌ |

**Legend:**
- ✅ = Capability available
- ❌ = Capability not available
- ✅* = Capability available if granted permission + post mask

## Key Type Definitions

### Owner (Human Principal)
**Authentication:** Password → Owner JWT (`typ=owner`)
**JWT Claims:** `owner_id` (hex32), `roles`, `permissions`
**Primary Surface:** Console (HTML + JSON)
**Root Capability:** Mint Primary Author Keys
**Limitations:**
- Cannot directly create posts (must use Author Keys)
- Cannot be used in Gateway API

### Primary Author Key (Root Machine Principal)
**Authentication:** ApiKey exchange → Key JWT (`typ=key`)
**JWT Claims:** `key_id` (hex32), `key_public_id` (apub_...), `roles`, `permissions`
**Minted By:** Owner (Console only)
**Primary Surface:** Gateway JSON
**Key Capabilities:**
- Create posts
- Mint Secondary Author Keys (within permission envelope)
- Mint Use Keys (within permission envelope)
**Lineage Fields:**
- `issued_by_key_id` = NULL
- `parent_key_id` = NULL
- `initial_author_key_id` = self

### Secondary Author Key (Delegated Machine Principal)
**Authentication:** ApiKey exchange → Key JWT (`typ=key`)
**Minted By:** Primary or Secondary Author Key
**Primary Surface:** Gateway JSON
**Key Capabilities:**
- Create posts
- Mint Secondary Author Keys (within permission envelope, ⊆ parent)
- Mint Use Keys (within permission envelope, ⊆ parent)
**Lineage Fields:**
- `issued_by_key_id` = issuing key
- `parent_key_id` = parent key
- `initial_author_key_id` = root Primary Author Key (immutable)

### Use Key (Interaction Principal)
**Authentication:** ApiKey exchange → Key JWT (`typ=key`)
**Minted By:** Primary or Secondary Author Key
**Primary Surface:** Gateway JSON
**Key Capabilities:**
- Read posts (if granted VIEW mask)
- Create comments (if granted COMMENT mask)
- Read feeds
**Restrictions:**
- **CANNOT** create posts
- **CANNOT** mint keys
- **CANNOT** be granted `posts:create` or `keys:issue`
**Use Limits (Optional):**
- Use count limits (1-time, N-times)
- Device limits (restrict by fingerprint/IP)
**Lineage Fields:**
- Same as Secondary (issued_by_key_id, parent_key_id, initial_author_key_id)

## Issuance Rules

### Primary Author Key Mint (Console Only)
```
Owner → POST /console/keys/primary
Body: {
  "permissions": ["posts:create", "keys:issue", "groups:read", ...],
  "label": "optional-label"
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Owner must have `keys:issue` permission
- No parent/lineage (root key)

### Secondary Author Key Mint (Gateway)
```
Author Key → POST /api/keys/{authorKeyId}/secondary
Body: {
  "permissions": ["posts:create", "keys:issue", ...],
  "label": "optional-label"
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Issuer must have `keys:issue`
- Child permissions ⊆ parent permissions
- Cannot include `posts:create` if parent doesn't have it

### Use Key Mint (Gateway)
```
Author Key → POST /api/keys/{authorKeyId}/use
Body: {
  "permissions": ["posts:read", "comments:write"],
  "label": "optional-label",
  "use_count": 1,       // optional: 1, N, or null (unlimited)
  "device_limit": 3     // optional: max devices, or null
}
→ Returns: { key_id, key_public_id, key_secret }
```
**Validation:**
- Issuer must have `keys:issue`
- Child permissions ⊆ parent permissions
- MUST NOT include `posts:create` or `keys:issue`

## Permission Envelope Validation

```
Parent permissions: ["posts:create", "keys:issue", "posts:read", "comments:write"]

✅ Valid child:   ["posts:create", "posts:read"]
✅ Valid child:   ["posts:read", "comments:write"]
❌ Invalid child: ["posts:create", "keys:issue", "groups:manage"] // groups:manage not in parent
❌ Invalid child: ["posts:create", "keys:issue"] // OK for Secondary, but Use Key cannot have these
```

## Lineage Traversal

### Viewing Downstream Lineage (Owner Only)
```
Owner → GET /console/keys/{keyId}/lineage
→ Returns tree of all descendants
```

### Disabling a Lineage (Owner Only)
```
Owner → POST /console/keys/{keyId}/deactivate
Option: cascade=true
→ Deactivates key + all descendants
```

## Combining Keys: "Keyring Key" Concept

**Scenario:** Owner wants to create a combined authorization context from multiple keys

**Implementation:**
1. Owner creates a **Group** containing multiple Keys
2. Owner creates a **Keychain** containing the Group
3. When granting post access:
   ```
   POST /console/posts/{postId}/access/grant-group
   Body: { "group_id": "...", "permission_mask": 0x03 }
   ```
4. All Keys in that Group inherit the access

**Alternative (Future):** "Keyring Key" as a synthetic Key with union permissions
- Not implemented in v1
- Would require special key type and permission resolution logic
