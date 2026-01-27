# Post Sharing & Access Control

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Post creation, attachment to Author Keys, access grant mechanics, Use Key sharing workflows, permission mask enforcement.

**SSoT Ownership:**
- Post creation and initial author attachment
- Post access grant/revocation workflows
- Use Key sharing patterns
- Group-based access grants
- Permission mask enforcement rules

---

## 1. Post Creation and Initial Attachment

### 1.1 Endpoint

```
POST /api/posts
```

**Auth:** Key JWT (`typ=key`)
**Required Permission:** `posts:create`
**Key Type:** Primary or Secondary Author (NOT Use)

### 1.2 Request

```json
{
  "content": "This is my first post on CRE8.pw!",
  "title": "Hello World"
}
```

**Required:** `content` (TEXT, 1-10000 chars)
**Optional:** `title` (VARCHAR, 1-255 chars)

### 1.3 Server Process

1. Verify Key JWT has `posts:create` permission
2. Verify key type is `primary` or `secondary` (reject `use`)
3. Generate `post_id` (BINARY(16))
4. Insert into `posts`:
   - `author_key_id = <key_id>` (from JWT)
   - `initial_author_key_id = <key's initial_author_key_id>` (provenance)
   - `content`, `title`
5. Emit audit event: `posts:create`

### 1.4 Response

```json
{
  "data": {
    "post_id": "c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2",
    "author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "initial_author_key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
    "content": "This is my first post on CRE8.pw!",
    "title": "Hello World",
    "created_at": "2026-01-21T12:00:00Z"
  }
}
```

### 1.5 Default Visibility

**Newly created posts have NO access grants by default.**

To make the post visible:
- Author must grant access to themselves, a group, or specific keys
- Or implement auto-grant logic (e.g., grant author VIEW+COMMENT+MANAGE_ACCESS)

---

## 2. Post Visibility Model

Posts are visible to a Key if:

1. Key has global `posts:read` permission, AND
2. `post_access` table has a grant with VIEW mask (0x01) where:
   - `target_type = 'key'` AND `target_id = <key_id>`, OR
   - `target_type = 'group'` AND Key is a member of that group

**Privacy:** Posts without any access grants are invisible to all keys (except via admin endpoints).

---

## 3. Post Access Table

### 3.1 Schema

```sql
CREATE TABLE post_access (
  id BINARY(16) PRIMARY KEY,
  post_id BINARY(16) NOT NULL,
  target_type ENUM('key', 'group') NOT NULL,
  target_id BINARY(16) NOT NULL,
  permission_mask INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(post_id, target_type, target_id)
);
```

### 3.2 Permission Mask Values

| Bit | Hex | Name | Capability |
|---:|---:|---|---|
| 0 | 0x01 | VIEW | Read post |
| 1 | 0x02 | COMMENT | Create comments |
| 3 | 0x08 | MANAGE_ACCESS | Grant/revoke access |

**Presets:**
- `READ_ONLY = 0x01`
- `INTERACT = 0x03` (VIEW + COMMENT)
- `ADMIN = 0x0B` (VIEW + COMMENT + MANAGE_ACCESS)

---

## 4. Granting Access

### 4.1 Grant Access to Key

**Endpoint:** `POST /api/posts/{postId}/access`

**Request:**
```json
{
  "target_type": "key",
  "target_id": "c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4",
  "permission_mask": 3
}
```

**Auth:** Key JWT with:
- `posts:access:manage` permission
- MANAGE_ACCESS mask (0x08) on this post

**Server Process:**
1. Verify requester has MANAGE_ACCESS on post
2. Insert/update `post_access` record
3. Emit audit event: `posts:access:grant`

### 4.2 Grant Access to Group (Console)

**Endpoint:** `POST /console/posts/{postId}/access/grant-group`

**Request:**
```json
{
  "group_id": "d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4",
  "permission_mask": 3
}
```

**Auth:** Owner JWT with `posts:access:manage`

**Effect:** All keys in the group inherit the mask.

---

## 5. Sharing via Use Keys (Complete Workflow)

### 5.1 Scenario

Author wants to share a post with a specific person (Alice) who doesn't have an account.

### 5.2 Steps

**Step 1: Create Post**
```http
POST /api/posts
Authorization: Bearer <author_jwt>

{
  "content": "Check out this exclusive content!",
  "title": "For Alice"
}

→ Returns: { post_id: "abc123..." }
```

**Step 2: Mint Use Key for Alice**
```http
POST /api/keys/b5a1e8c0.../use
Authorization: Bearer <author_jwt>

{
  "permissions": ["posts:read", "comments:write"],
  "label": "Share Link for Alice",
  "use_count": 1,
  "device_limit": null
}

→ Returns: {
  key_id: "use_abc...",
  key_public_id: "apub_xyz...",
  key_secret: "sec_123..."
}
```

**Step 3: Grant Use Key Access to Post**
```http
POST /api/posts/abc123.../access
Authorization: Bearer <author_jwt>

{
  "target_type": "key",
  "target_id": "use_abc...",
  "permission_mask": 3
}

→ Returns: { post_id, target_id, permission_mask }
```

**Step 4: Share Credentials with Alice**

Author sends Alice:
```
ApiKey: apub_xyz...:sec_123...
Post ID: abc123...
```

**Step 5: Alice Exchanges ApiKey**
```http
POST /api/auth/exchange
Authorization: ApiKey apub_xyz...:sec_123...

→ Returns: { access_token, refresh_token }
```

**Step 6: Alice Reads Post**
```http
GET /api/posts/abc123...
Authorization: Bearer <alice_access_token>

→ Returns: { data: { post content } }
```

**Step 7: Alice Comments (Optional)**
```http
POST /api/posts/abc123.../comments
Authorization: Bearer <alice_access_token>

{
  "body": "Thanks for sharing!"
}

→ Returns: { comment_id, body, created_by_key_id }
```

**Step 8: Use Count Exhausted**

After 1 exchange (or 1 request, depending on implementation):
```http
POST /api/auth/exchange (again)
Authorization: ApiKey apub_xyz...:sec_123...

→ Returns: 403 Forbidden { error: { code: "use_limit_exceeded" } }
```

---

## 6. Use Count Enforcement

### 6.1 Tracking

**Fields:**
- `keys.use_count_limit` (INT, nullable)
- `keys.use_count_current` (INT, default 0)

### 6.2 Increment Logic

**Option A (Per-Exchange):**
```php
// In AuthService::exchangeApiKey()
if ($key->use_count_limit !== null) {
    if ($key->use_count_current >= $key->use_count_limit) {
        throw new ForbiddenException('Use limit exceeded', 'use_limit_exceeded');
    }
    KeyRepository::incrementUseCount($key->id);
}
```

**Option B (Per-Request):**
```php
// In JwtKeyMiddleware (every authenticated request)
if ($key->use_count_limit !== null) {
    KeyRepository::incrementUseCount($key->id);
    if ($key->use_count_current > $key->use_count_limit) {
        throw new ForbiddenException('Use limit exceeded', 'use_limit_exceeded');
    }
}
```

**Recommended:** Option A (per-exchange) — simpler, counts "sessions" rather than "actions".

---

## 7. Device Limit Enforcement

### 7.1 Table (Optional)

```sql
CREATE TABLE key_devices (
  id BINARY(16) PRIMARY KEY,
  key_id BINARY(16) NOT NULL,
  device_fingerprint VARCHAR(255) NOT NULL,
  first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(key_id, device_fingerprint)
);
```

### 7.2 Fingerprinting

**Basic:**
```php
$fingerprint = hash('sha256', $request->getHeaderLine('User-Agent') . $ipAddress);
```

**Advanced:**
- Include Accept-Language, Accept-Encoding
- Use client-provided device ID (if trusted)

### 7.3 Enforcement Logic

```php
if ($key->device_limit !== null) {
    $fingerprint = $this->generateFingerprint($request);

    $exists = KeyDeviceRepository::exists($key->id, $fingerprint);
    if (!$exists) {
        $count = KeyDeviceRepository::countDistinct($key->id);
        if ($count >= $key->device_limit) {
            throw new ForbiddenException('Device limit exceeded', 'device_limit_exceeded');
        }
        KeyDeviceRepository::register($key->id, $fingerprint);
    }
}
```

---

## 8. Group-Based Access Grants

### 8.1 Use Case

Owner wants to share a post with all members of a team.

### 8.2 Workflow

**Step 1: Create Group**
```http
POST /console/groups
Authorization: Bearer <owner_jwt>

{
  "name": "Team Alpha"
}

→ Returns: { group_id: "grp_abc..." }
```

**Step 2: Add Team Members**
```http
POST /console/groups/grp_abc.../members
{
  "key_id": "key1..."
}

POST /console/groups/grp_abc.../members
{
  "key_id": "key2..."
}
```

**Step 3: Grant Group Access to Post**
```http
POST /console/posts/post_xyz.../access/grant-group
{
  "group_id": "grp_abc...",
  "permission_mask": 3
}
```

**Result:** All current and future members of "Team Alpha" can VIEW + COMMENT on the post.

---

## 9. Access Revocation

### 9.1 Revoke Key Access

**Endpoint:** `DELETE /api/posts/{postId}/access/{accessId}`

**Or identify by target:**
```http
DELETE /api/posts/{postId}/access?target_type=key&target_id=<key_id>
```

**Effect:** Immediate. Key can no longer view/comment on post.

### 9.2 Revoke Group Access

**Endpoint:** `POST /console/posts/{postId}/access/revoke-group`

**Request:**
```json
{
  "group_id": "grp_abc..."
}
```

**Effect:** All group members lose access.

### 9.3 Remove Key from Group

**Endpoint:** `DELETE /console/groups/{groupId}/members/{keyId}`

**Effect:** Key loses access to all posts granted to that group.

---

## 10. Permission Mask Enforcement (Combined Checks)

### 10.1 Read Post

**Requirements:**
1. Global: `posts:read` permission in JWT
2. Post-scoped: VIEW mask (0x01)

```php
// Service layer
if (!in_array('posts:read', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: posts:read');
}

$mask = PostRepository::getAccessMask($postId, $keyId);
if (!$mask || !($mask & 0x01)) {
    throw new NotFoundException(); // Hide existence
}
```

### 10.2 Create Comment

**Requirements:**
1. Global: `comments:write`
2. Post-scoped: COMMENT mask (0x02)

```php
if (!in_array('comments:write', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: comments:write');
}

$mask = PostRepository::getAccessMask($postId, $keyId);
if (!($mask & 0x01)) {
    throw new NotFoundException(); // Must have VIEW to know post exists
}
if (!($mask & 0x02)) {
    throw new ForbiddenException('Insufficient post access: COMMENT required');
}
```

### 10.3 Grant Access (Manage Post Access)

**Requirements:**
1. Global: `posts:access:manage`
2. Post-scoped: MANAGE_ACCESS mask (0x08)

```php
if (!in_array('posts:access:manage', $jwtPermissions)) {
    throw new ForbiddenException('Missing permission: posts:access:manage');
}

$mask = PostRepository::getAccessMask($postId, $keyId);
if (!($mask & 0x08)) {
    throw new ForbiddenException('Insufficient post access: MANAGE_ACCESS required');
}
```

---

## 11. Example Scenarios

### 11.1 Public-ish Post (Open to All Keys in Organization)

**Setup:**
1. Create "Organization" group with all employee keys
2. Grant group INTERACT (0x03) on post
3. All employees can read + comment

### 11.2 Time-Limited Share

**Setup:**
1. Mint Use Key with `use_count: 10`
2. Grant Use Key INTERACT on post
3. Share ApiKey with recipient
4. After 10 uses, key is exhausted

### 11.3 Single-Device Share

**Setup:**
1. Mint Use Key with `device_limit: 1`
2. Grant Use Key READ_ONLY on post
3. First device to exchange ApiKey "claims" the key
4. Subsequent devices blocked

### 11.4 Cascading Revocation

**Setup:**
1. Grant Group A access to Post
2. Add Key X to Group A
3. Key X can now access Post
4. Remove Key X from Group A
5. Key X loses access to Post immediately

---

**Next:** **[implementation-guide.md](../08-implementation/implementation-guide.md)**
