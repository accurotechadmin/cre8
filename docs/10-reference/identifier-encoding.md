# Identifier Encoding Matrix

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Authoritative reference for all identifier formats used in CRE8.pw: internal storage, external encoding, route parameters, JWT claims, logs.

---

## 1. Encoding Conventions

### 1.1 Internal IDs (Database Storage)

**Format:** `BINARY(16)`
**Usage:** All primary keys and foreign keys in database
**Never expose:** Raw binary values outside repository layer

### 1.2 External Encoding (hex32)

**Format:** 32-character lowercase hexadecimal (no hyphens)
**Example:** `b5a1e8c0d9f04c3aa1b2c3d4e5f60718`
**Usage:** Routes, JSON responses, JWT claims, logs, audit metadata

**Conversion:**
```php
// Utilities/Ids.php
function binaryToHex32($binary): string {
    return bin2hex($binary);
}

function hex32ToBinary($hex32): string {
    return hex2bin($hex32);
}
```

### 1.3 Key Public IDs

**Format:** `apub_` prefix + random string
**Example:** `apub_8cd1a2b3c4d5e6f7`
**Storage:** `key_public_ids.key_public_id` (VARCHAR, separate table)
**Usage:** ApiKey exchange ONLY (`Authorization: ApiKey <public_id>:<secret>`)
**NOT used in:** Route params named `*Id`, JSON fields named `*_id`

---

## 2. Route Parameter Rules

### 2.1 Convention

**All route parameters ending in `Id` are hex32**, except when explicitly named `PublicId`:

✅ `{postId}` → hex32
✅ `{keyId}` → hex32
✅ `{groupId}` → hex32
✅ `{authorKeyId}` → hex32

✅ `{keyPublicId}` → apub_...

❌ Never accept `apub_...` in params named `{keyId}` or similar

### 2.2 Examples

```
GET /api/posts/c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2
                 ↑ hex32 (postId)

POST /api/keys/b5a1e8c0d9f04c3aa1b2c3d4e5f60718/secondary
                ↑ hex32 (authorKeyId)

DELETE /console/groups/d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4/members/c9d0e1f2...
                        ↑ hex32 (groupId)            ↑ hex32 (keyId)
```

---

## 3. JWT Claim Rules

### 3.1 Required Principal ID Claims

**Owner tokens (`typ=owner`):**
- **MUST include:** `owner_id` (hex32)
- **MUST NOT include:** `key_id`, `key_public_id`

**Key tokens (`typ=key`):**
- **MUST include:** `key_id` (hex32)
- **MAY include:** `key_public_id` (apub_..., optional, debug/context only)

### 3.2 Subject Claim Convention

**Format:**
- Owner: `sub = "owner:<owner_id>"`
- Key: `sub = "key:<key_id>"`

**Examples:**
```json
// Owner JWT
{
  "sub": "owner:3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b"
}

// Key JWT
{
  "sub": "key:b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_public_id": "apub_8cd1a2b3c4d5e6f7"
}
```

**Note:** `sub` is for readability. Authorization uses explicit `owner_id` / `key_id` claims.

---

## 4. Identifier Matrix

| Identifier | Meaning | DB Storage | External Format | Where Used | Notes |
|---|---|---|---|---|---|
| `owner_id` | Owner principal ID | `owners.id` (BINARY(16)) | hex32 | JWT, request attrs, logs, JSON | Required in Owner JWTs |
| `key_id` | Key principal ID | `keys.id` (BINARY(16)) | hex32 | JWT, request attrs, logs, routes, JSON | Required in Key JWTs |
| `key_public_id` | Key public identifier | `key_public_ids.key_public_id` (VARCHAR) | apub_... | ApiKey exchange, debug logs | Never in `*_id` fields/params |
| `post_id` | Post ID | `posts.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `group_id` | Group ID | `groups.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `comment_id` | Comment ID | `comments.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `keychain_id` | Keychain ID | `keychains.id` (BINARY(16)) | hex32 | Routes, JSON, logs | |
| `refresh_token` | Refresh token secret | `refresh_tokens.token_hash` (VARCHAR, hashed) | opaque string | Refresh requests only | Never log plaintext |
| `request_id` | Correlation ID | N/A | UUID/ULID string | Error responses, logs | Tracing only |

---

## 5. Logging & Audit ID Rules

**Logs MUST use hex32 for all internal IDs:**
```json
{
  "owner_id": "3f2a9c1c4b7b4a2e8b6c1a9d2e3f4a5b",
  "key_id": "b5a1e8c0d9f04c3aa1b2c3d4e5f60718",
  "key_public_id": "apub_8cd1a2b3c4d5e6f7"
}
```

**MAY include `key_public_id` for correlation, but:**
- MUST NEVER log `key_secret`
- MUST NEVER log refresh tokens (plaintext)
- MUST NEVER log passwords

**Audit events** use hex32 for `actor_id` and `subject_id`.

---

## 6. Implementation Guidance

### 6.1 Repository Layer

**Input:** Controllers pass hex32 IDs
**Convert:** Repository converts hex32 → BINARY(16) for queries
**Return:** Repository returns hex32 IDs to services/controllers

```php
class PostRepository {
    public function findById(string $postIdHex32): ?array {
        $postIdBinary = Ids::hex32ToBinary($postIdHex32);

        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$postIdBinary]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return [
            'post_id' => Ids::binaryToHex32($row['id']),
            'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
            'content' => $row['content'],
            // ...
        ];
    }
}
```

### 6.2 ApiKey Exchange

**Only place accepting `key_public_id` from client:**
```
POST /api/auth/exchange
Authorization: ApiKey apub_8cd1a2b3c4d5e6f7:sec_a1b2c3d4e5f6...
```

**Process:**
1. Parse header → extract `key_public_id` and `key_secret`
2. Lookup `key_id` via `key_public_ids.key_public_id`
3. Load key record from `keys` using BINARY(16) `key_id`
4. Verify secret
5. Issue JWT with `key_id` (hex32)

---

**Next:** **[environment-configuration.md](environment-configuration.md)**
