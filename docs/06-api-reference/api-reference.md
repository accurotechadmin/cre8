# Routes & API Reference

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Complete catalog of all HTTP endpoints, organized by surface, with authentication requirements, required permissions, request/response schemas, and ownership mapping.

**SSoT Ownership:**
- Complete route catalog for all surfaces (Public HTML, Public API, Console JSON, Gateway JSON)
- Required permissions per endpoint
- Controller/Service/Repository ownership
- Request/response examples

---

## 1. Route Conventions and ID Formats

**ID Parameter Rules:**
- All `{...Id}` params (e.g., `{postId}`, `{keyId}`, `{groupId}`) are `hex32` format (32-char lowercase hex)
- Exception: `{keyPublicId}` is `apub_...` format
- Never accept `apub_...` in params named `*Id`

**Response Format:** All JSON endpoints return standardized envelopes (see **[response-schemas.md](response-schemas.md)**)

**SDK Usage:** For building applications, consider using the official CRE8.pw SDK which provides type-safe, developer-friendly interfaces. See **[sdk-specification.md](../11-development/sdk-specification.md)** for complete SDK documentation.

**See:** **[identifier-encoding.md](../10-reference/identifier-encoding.md)** for complete ID format rules

---

## 2. Public HTML Routes (Console UI)

| Method | Path | Purpose | Auth | CSRF |
|---|---|---|---|---|
| GET | `/` | Landing page | None | N/A |
| GET | `/console/register` | Owner registration form | None | Yes |
| GET | `/console/login` | Owner login form | None | Yes |
| GET | `/console/dashboard` | Owner dashboard | Owner session/JWT | Yes |

**Controller:** ConsoleController  
**Service:** ConsoleService (page rendering + data prep)  
**Templates:** Twig/Blade or PHP templates

---

## 3. Public API Routes (JSON, No Auth)

### Infrastructure

| Method | Path | Purpose | Returns |
|---|---|---|---|
| GET | `/health` | Health check | `{ "status": "ok" }` |
| GET | `/.well-known/jwks.json` | RS256 public keys (JWKS) | `{ "keys": [...] }` |

### Authentication

| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| POST | `/api/auth/exchange` | ApiKey → JWT | `Authorization: ApiKey <pub>:<secret>` | `{ access_token, refresh_token, expires_in }` |
| POST | `/api/auth/refresh` | Rotate refresh token | Refresh token in body | `{ access_token, refresh_token }` |

### Owner Management

| Method | Path | Purpose | Body | Returns |
|---|---|---|---|---|
| POST | `/console/owners` | Create Owner | `{ email, password }` | `{ owner_id }` |
| POST | `/console/login` | Owner login | `{ email, password }` | `{ access_token, refresh_token }` |

**Controller:** HealthController, AuthController, ApiKeyController, OwnerController  
**Service:** AuthService, OwnerService  
**Repositories:** OwnerRepository, KeyRepository, TokenRepository

---

## 4. Console JSON Routes (Owner-Protected)

**Auth:** `Authorization: Bearer <owner_jwt>` where `typ=owner`  
**CSRF:** Not required  
**Rate Limiting:** Keyed by `owner_id` (hex32)

### Key Management

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| POST | `/console/keys/primary` | Mint Primary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| GET | `/console/keys` | List Owner's keys | `keys:read` | `{ data: [...keys] }` |
| GET | `/console/keys/{keyId}` | Get key details | `keys:read` | `{ data: {key} }` |
| GET | `/console/keys/{keyId}/lineage` | View key lineage tree | `keys:read` | `{ data: {tree} }` |
| POST | `/console/keys/{keyId}/rotate` | Rotate key | `keys:rotate` | `{ old_key_id, new_key_id, new_key_secret }` |
| POST | `/console/keys/{keyId}/activate` | Activate key | `keys:state:update` | `{ key_id, active: true }` |
| POST | `/console/keys/{keyId}/deactivate` | Deactivate key (optionally cascade) | `keys:state:update` | `{ key_id, active: false }` |

**Request Examples:**

```http
POST /console/keys/primary
Authorization: Bearer <owner_jwt>
Content-Type: application/json

{
  "permissions": ["posts:create", "keys:issue", "posts:read"],
  "label": "My Content Key"
}
```

### Group Management

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| POST | `/console/groups` | Create group | `groups:manage` | `{ group_id, name }` |
| GET | `/console/groups` | List groups | `groups:manage` | `{ data: [...groups] }` |
| GET | `/console/groups/{groupId}` | Get group | `groups:manage` | `{ data: {group} }` |
| POST | `/console/groups/{groupId}/rename` | Rename group | `groups:manage` | `{ group_id, name }` |
| DELETE | `/console/groups/{groupId}` | Delete group | `groups:manage` | `{ deleted: true }` |
| POST | `/console/groups/{groupId}/members` | Add member | `groups:manage` | `{ group_id, key_id }` |
| DELETE | `/console/groups/{groupId}/members/{keyId}` | Remove member | `groups:manage` | `{ deleted: true }` |

### Keychain Management

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| GET | `/console/keychains` | List owner keychains | `keychains:manage` | `{ data: [...keychains] }` |
| POST | `/console/keychains` | Create keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/console/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/console/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

### Post Management (Admin)

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| GET | `/console/posts` | List posts from Owner's keys | `posts:admin:read` | `{ data: [...posts], paging }` |
| GET | `/console/posts/{postId}` | Get post | `posts:admin:read` | `{ data: {post} }` |
| POST | `/console/posts/{postId}/access/grant-group` | Grant group access | `posts:access:manage` | `{ post_id, group_id, permission_mask }` |
| POST | `/console/posts/{postId}/access/revoke-group` | Revoke group access | `posts:access:manage` | `{ deleted: true }` |

**Controller:** KeyController, GroupController, KeychainController, PostController (Console variants)  
**Service:** KeyService, GroupService, KeychainService, PostService  
**Repositories:** KeyRepository, GroupRepository, KeychainRepository, PostRepository, AuditRepository

---

## 5. Gateway JSON Routes (Key-Protected)

**Auth:** `Authorization: Bearer <key_jwt>` where `typ=key`  
**CSRF:** Not required  
**Rate Limiting:** Keyed by `key_id` (hex32)

### Key Issuance

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| POST | `/api/keys/{authorKeyId}/secondary` | Mint Secondary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| POST | `/api/keys/{authorKeyId}/use` | Mint Use Key | `keys:issue` | `{ key_id, key_public_id, key_secret, use_count?, device_limit? }` |

**Request Examples:**

```http
POST /api/keys/b5a1e8c0d9f04c3aa1b2c3d4e5f60718/use
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "permissions": ["posts:read", "comments:write"],
  "label": "Share Link for Alice",
  "use_count": 1,
  "device_limit": null
}
```

**Validation:** Child permissions ⊆ parent; Use Keys cannot have `posts:create` or `keys:issue`

### Posts

| Method | Path | Purpose | Permission + Mask | Returns |
|---|---|---|---|---|
| GET | `/api/posts` | List visible posts | `posts:read` + VIEW | `{ data: [...posts], paging }` |
| GET | `/api/posts/{postId}` | Get post | `posts:read` + VIEW | `{ data: {post} }` |
| POST | `/api/posts` | Create post | `posts:create` | `{ post_id, content, author_key_id }` |
| POST | `/api/posts/{postId}/access` | Grant post access | `posts:access:manage` + MANAGE_ACCESS | `{ post_id, target_type, target_id, permission_mask }` |
| DELETE | `/api/posts/{postId}/access/{accessId}` | Revoke access | `posts:access:manage` + MANAGE_ACCESS | `{ deleted: true }` |

**Request Example:**

```http
POST /api/posts
Authorization: Bearer <key_jwt>
Content-Type: application/json

{
  "content": "This is my first post!",
  "title": "Hello CRE8.pw"
}
```

### Comments

| Method | Path | Purpose | Permission + Mask | Returns |
|---|---|---|---|---|
| GET | `/api/posts/{postId}/comments` | List comments | `posts:read` + VIEW | `{ data: [...comments], paging }` |
| POST | `/api/posts/{postId}/comments` | Create comment | `comments:write` + COMMENT | `{ comment_id, body, created_by_key_id }` |

### Feeds

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| GET | `/api/feed/use/{useKeyId}` | Get Use Key feed | Use Key bearer; path `useKeyId` must match JWT `key_id` | `{ data: [...posts], paging }` |
| GET | `/api/feed/author` | Get Author feed (future) | Author Key bearer | `{ data: [...posts], paging }` |

**Query Params (Pagination):**
- `limit` (default 20, max 100)
- `before_id` (cursor for older posts)
- `since_id` (cursor for newer posts)

### Groups (Read-Only)

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| GET | `/api/groups` | List groups | `groups:read` | `{ data: [...groups] }` |
| GET | `/api/groups/{groupId}` | Get group | `groups:read` | `{ data: {group} }` |
| GET | `/api/groups/{groupId}/members` | List members | `groups:read` | `{ data: [...keys] }` |

### Keychains (External)

| Method | Path | Purpose | Permission | Returns |
|---|---|---|---|---|
| POST | `/api/keychains` | Create external keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/api/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/api/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

**Controller:** KeyController, PostController, CommentController, FeedController, GroupController, KeychainController (Gateway variants)  
**Service:** KeyService, PostService, CommentService, FeedService, GroupService, KeychainService  
**Repositories:** Same as Console + PostRepository, CommentRepository

---

## 6. Controller/Service/Repository Ownership Map

| Domain | Controller | Service | Repository |
|---|---|---|---|
| Health/Infrastructure | HealthController | N/A | N/A |
| Auth/Token | AuthController, ApiKeyController | AuthService | TokenRepository, OwnerRepository, KeyRepository |
| Owners | OwnerController | OwnerService | OwnerRepository |
| Keys | KeyController | KeyService | KeyRepository, AuditRepository |
| Posts | PostController | PostService | PostRepository, AuditRepository |
| Comments | CommentController | CommentService | PostRepository (or CommentRepository) |
| Feeds | FeedController | FeedService | PostRepository |
| Groups | GroupController | GroupService | GroupRepository, AuditRepository |
| Keychains | KeychainController | KeychainService | KeychainRepository, AuditRepository |
| Console Pages | ConsoleController | ConsoleService | Multiple |

---

## 7. Adding a New Endpoint: Checklist

1. ✅ Determine surface (Console HTML, Console JSON, Gateway JSON, Public API)
2. ✅ Determine auth requirements (Owner JWT, Key JWT, or public)
3. ✅ Determine required permissions (refer to **[authorization.md](../05-authentication-authorization/authorization.md)**)
4. ✅ Determine post mask bits if applicable
5. ✅ Add route to `config/routes.php` under correct group
6. ✅ Add validation rules to `config/validation.php` keyed by `"METHOD /pattern"`
7. ✅ Implement controller method (thin adapter)
8. ✅ Implement service method (business logic + audits)
9. ✅ Implement repository methods if new data access needed
10. ✅ Update this document
11. ✅ Test authorization enforcement (both permission string + mask if applicable)
12. ✅ Test error responses (401, 403, 404, 422, 429)
13. ✅ Verify logging/audit events emitted

---

**Next:** Proceed to **[feed-system.md](feed-system.md)** for detailed feed visibility rules and pagination.
