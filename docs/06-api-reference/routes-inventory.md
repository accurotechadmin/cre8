# Helper Draft: Complete Route Inventory

## Route Organization Principles
- **Surface separation:** Console (HTML + JSON) vs Gateway (JSON)
- **Auth differentiation:** Owner JWT (`typ=owner`) vs Key JWT (`typ=key`)
- **CSRF scope:** HTML routes only (no CSRF on JSON)
- **ID formats:** All `{...Id}` params are hex32 unless explicitly `{keyPublicId}`

---

## PUBLIC HTML (Console UI)

| Method | Path | Purpose | Auth | CSRF |
|---|---|---|---|---|
| GET | `/` | Landing page | None | N/A (public) |
| GET | `/console/register` | Owner registration form | None | Yes |
| GET | `/console/login` | Owner login form | None | Yes |
| GET | `/console/dashboard` | Owner dashboard | Owner session/token | Yes |

**Controller:** ConsoleController
**Service:** ConsoleService (page rendering + data prep)

---

## PUBLIC API (JSON, No Auth or Special Auth)

### Health & Infrastructure
| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/health` | Health check | None |
| GET | `/.well-known/jwks.json` | RS256 public keys | None |

**Controller:** HealthController
**Service:** N/A (direct response)

### Authentication
| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| POST | `/api/auth/exchange` | ApiKey → JWT + refresh | `Authorization: ApiKey <public_id>:<secret>` | `{ access_token, refresh_token, expires_in }` |
| POST | `/api/auth/refresh` | Rotate refresh token | Refresh token in body | `{ access_token, refresh_token }` |

**Controller:** ApiKeyController, AuthController
**Service:** AuthService, TokenRepository
**Validation:** Validate ApiKey format, refresh token format

### Owner Management
| Method | Path | Purpose | Auth | Returns |
|---|---|---|---|---|
| POST | `/console/owners` | Create new Owner | None (public registration) | `{ owner_id }` |
| POST | `/console/login` | Owner login | Password in body | `{ access_token, refresh_token }` |

**Controller:** OwnerController, AuthController
**Service:** OwnerRepository, AuthService
**Validation:** Email format, password strength

---

## CONSOLE JSON (Owner-Protected, No CSRF)

**Auth:** `Authorization: Bearer <owner_jwt>` where `typ=owner`
**Rate Limiting:** Keyed by `owner_id` (hex32)

### Key Management (Owner Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| POST | `/console/keys/primary` | Mint Primary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| GET | `/console/keys` | List Owner's keys | `keys:read` | `{ data: [...keys] }` |
| GET | `/console/keys/{keyId}` | Get key details | `keys:read` | `{ data: {key} }` |
| GET | `/console/keys/{keyId}/lineage` | View key lineage tree | `keys:read` | `{ data: {tree} }` |
| POST | `/console/keys/{keyId}/rotate` | Rotate key | `keys:rotate` | `{ old_key_id, new_key_id, new_key_secret }` |
| POST | `/console/keys/{keyId}/activate` | Activate key | `keys:state:update` | `{ key_id, active: true }` |
| POST | `/console/keys/{keyId}/deactivate` | Deactivate key | `keys:state:update` | `{ key_id, active: false }` |

**Controller:** KeyController (Console variant)
**Service:** KeyService
**Repositories:** KeyRepository, AuditRepository

### Group Management (Owner Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| POST | `/console/groups` | Create group | `groups:manage` | `{ group_id, name }` |
| GET | `/console/groups` | List groups | `groups:manage` | `{ data: [...groups] }` |
| GET | `/console/groups/{groupId}` | Get group details | `groups:manage` | `{ data: {group} }` |
| POST | `/console/groups/{groupId}/rename` | Rename group | `groups:manage` | `{ group_id, name }` |
| DELETE | `/console/groups/{groupId}` | Delete group | `groups:manage` | `{ deleted: true }` |
| POST | `/console/groups/{groupId}/members` | Add member | `groups:manage` | `{ group_id, key_id }` |
| DELETE | `/console/groups/{groupId}/members/{keyId}` | Remove member | `groups:manage` | `{ deleted: true }` |

**Controller:** GroupController (Console variant)
**Service:** GroupService
**Repositories:** GroupRepository, AuditRepository

### Keychain Management (Owner Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| GET | `/console/keychains` | List owner keychains | `keychains:manage` | `{ data: [...keychains] }` |
| POST | `/console/keychains` | Create keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/console/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/console/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

**Controller:** KeychainController (Console variant)
**Service:** KeychainService
**Repositories:** KeychainRepository, AuditRepository

### Post Management (Owner Admin Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| GET | `/console/posts` | List posts from Owner's keys | `posts:admin:read` | `{ data: [...posts], paging }` |
| GET | `/console/posts/{postId}` | Get post details | `posts:admin:read` | `{ data: {post} }` |
| POST | `/console/posts/{postId}/access/grant-group` | Grant group access | `posts:access:manage` | `{ post_id, group_id, permission_mask }` |
| POST | `/console/posts/{postId}/access/revoke-group` | Revoke group access | `posts:access:manage` | `{ deleted: true }` |

**Controller:** PostController (Console variant)
**Service:** PostService
**Repositories:** PostRepository, AuditRepository

---

## GATEWAY JSON (Key-Protected)

**Auth:** `Authorization: Bearer <key_jwt>` where `typ=key`
**Rate Limiting:** Keyed by `key_id` (hex32)

### Key Issuance (Gateway Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| POST | `/api/keys/{authorKeyId}/secondary` | Mint Secondary Author Key | `keys:issue` | `{ key_id, key_public_id, key_secret }` |
| POST | `/api/keys/{authorKeyId}/use` | Mint Use Key | `keys:issue` | `{ key_id, key_public_id, key_secret, use_count?, device_limit? }` |

**Controller:** KeyController (Gateway variant)
**Service:** KeyService
**Repositories:** KeyRepository, AuditRepository
**Validation:**
- Body: permissions array, label, use_count (optional), device_limit (optional)
- Envelope check: child permissions ⊆ parent permissions
- Use Key restriction: cannot include `posts:create` or `keys:issue`

### Posts (Gateway Scope)
| Method | Path | Purpose | Required Permission + Mask | Returns |
|---|---|---|---|---|
| GET | `/api/posts` | List visible posts | `posts:read` + `VIEW` | `{ data: [...posts], paging }` |
| GET | `/api/posts/{postId}` | Get post | `posts:read` + `VIEW` | `{ data: {post} }` |
| POST | `/api/posts` | Create post | `posts:create` | `{ post_id, content, author_key_id }` |
| POST | `/api/posts/{postId}/access` | Grant/update post access | `posts:access:manage` + `MANAGE_ACCESS` | `{ post_id, target_type, target_id, permission_mask }` |
| DELETE | `/api/posts/{postId}/access/{accessId}` | Revoke post access | `posts:access:manage` + `MANAGE_ACCESS` | `{ deleted: true }` |

**Controller:** PostController (Gateway variant)
**Service:** PostService
**Repositories:** PostRepository, AuditRepository
**Validation:**
- Create post: body with `content` (required), optional `title`
- Grant access: `target_type` (e.g., "group"), `target_id` (hex32), `permission_mask` (int)

### Comments (Gateway Scope)
| Method | Path | Purpose | Required Permission + Mask | Returns |
|---|---|---|---|---|
| GET | `/api/posts/{postId}/comments` | List comments | `posts:read` + `VIEW` | `{ data: [...comments], paging }` |
| POST | `/api/posts/{postId}/comments` | Create comment | `comments:write` + `COMMENT` | `{ comment_id, body, created_by_key_id, post_id }` |

**Controller:** CommentController
**Service:** CommentService
**Repositories:** PostRepository (includes comments)

### Feeds (Gateway Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| GET | `/api/feed/use/{useKeyId}` | Get Use Key feed | Use Key bearer; `useKeyId` must match `key_id` in JWT | `{ data: [...posts], paging }` |
| GET | `/api/feed/author` | Get Author feed (future) | Author Key bearer | `{ data: [...posts], paging }` |

**Controller:** FeedController
**Service:** FeedService (visibility resolution)
**Repositories:** PostRepository
**Validation:**
- Enforce `useKeyId` path param matches JWT `key_id` (or 404)
- Pagination: `limit`, `before_id`, `since_id`

### Groups (Gateway Read Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| GET | `/api/groups` | List groups | `groups:read` | `{ data: [...groups] }` |
| GET | `/api/groups/{groupId}` | Get group | `groups:read` | `{ data: {group} }` |
| GET | `/api/groups/{groupId}/members` | List members | `groups:read` | `{ data: [...keys] }` |

**Controller:** GroupController (Gateway variant)
**Service:** GroupService
**Repositories:** GroupRepository

### Keychains (Gateway External Scope)
| Method | Path | Purpose | Required Permission | Returns |
|---|---|---|---|---|
| POST | `/api/keychains` | Create external keychain | `keychains:manage` | `{ keychain_id, name }` |
| POST | `/api/keychains/{id}/members` | Add member | `keychains:manage` | `{ keychain_id, key_id }` |
| DELETE | `/api/keychains/{id}/members/{keyId}` | Remove member | `keychains:manage` | `{ deleted: true }` |

**Controller:** KeychainController (Gateway variant)
**Service:** KeychainService
**Repositories:** KeychainRepository

---

## Controller/Service/Repository Ownership Map

| Domain | Controller | Service | Repository |
|---|---|---|---|
| Health/Infrastructure | HealthController | N/A | N/A |
| Auth/Token | AuthController, ApiKeyController | AuthService | TokenRepository, OwnerRepository, KeyRepository |
| Owners | OwnerController | OwnerService (or AuthService) | OwnerRepository |
| Keys | KeyController | KeyService | KeyRepository, AuditRepository |
| Posts | PostController | PostService | PostRepository, AuditRepository |
| Comments | CommentController | CommentService | PostRepository (or CommentRepository) |
| Feeds | FeedController | FeedService | PostRepository |
| Groups | GroupController | GroupService | GroupRepository, AuditRepository |
| Keychains | KeychainController | KeychainService | KeychainRepository, AuditRepository |
| Console Pages | ConsoleController | ConsoleService | Multiple |

---

## Route Addition Checklist

When adding a new endpoint:

1. ✅ Determine surface (Console HTML, Console JSON, Gateway JSON)
2. ✅ Determine auth requirements (Owner JWT, Key JWT, or public)
3. ✅ Determine required permissions (ref: Permission Matrix)
4. ✅ Determine post mask bits if applicable
5. ✅ Add route to `config/routes.php` under correct group
6. ✅ Add validation rules to `config/validation.php` keyed by `"METHOD /pattern"`
7. ✅ Implement controller method (thin adapter)
8. ✅ Implement service method (business logic + audits)
9. ✅ Implement repository methods if new data access needed
10. ✅ Update OpenAPI schema (`openapi.yaml`)
11. ✅ Test authorization enforcement (both permission string + mask if applicable)
12. ✅ Test error responses (401, 403, 404, 422, 429)
13. ✅ Verify logging/audit events emitted
14. ✅ Update this document
