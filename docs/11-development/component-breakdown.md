# CRE8.pw Component Breakdown

**Version:** 1.0.0  
**Last Updated:** 2026-01-25  
**Purpose:** Detailed breakdown of every component's internal structure, methods, parameters, dependencies, and interfaces

---

## Table of Contents

1. [Controllers](#1-controllers)
2. [Services](#2-services)
3. [Repositories](#3-repositories)
4. [Middleware](#4-middleware)
5. [Security Components](#5-security-components)
6. [Utilities](#6-utilities)
7. [Exceptions](#7-exceptions)
8. [Route Definitions](#8-route-definitions)
9. [Database Migrations](#9-database-migrations)
10. [Configuration Files](#10-configuration-files)
11. [Summary](#summary)

---

## 1. Controllers

### 1.1 BaseController

**File:** `src/Controllers/BaseController.php`

**Purpose:** Base class providing standardized response methods

**Dependencies:**
- `ResponseFactoryInterface` (constructor injection)
- `App\Utilities\ResponseFactory` (static utility)
- `App\Utilities\ErrorFactory` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory)`
- **Purpose:** Initialize controller with response factory
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
- **Returns:** void
- **Side Effects:** None

#### `single(mixed $data, int $statusCode = 200): ResponseInterface`
- **Purpose:** Create single object response
- **Parameters:**
  - `$data`: Response data (wrapped in `data` key)
  - `$statusCode`: HTTP status code (default: 200)
- **Returns:** `ResponseInterface` with JSON body `{"data": {...}}`
- **Uses:** `ResponseFactoryUtil::single()`

#### `list(array $data, int $statusCode = 200): ResponseInterface`
- **Purpose:** Create list response
- **Parameters:**
  - `$data`: Array of items (wrapped in `data` key)
  - `$statusCode`: HTTP status code (default: 200)
- **Returns:** `ResponseInterface` with JSON body `{"data": [...]}`
- **Uses:** `ResponseFactoryUtil::list()`

#### `paginated(array $data, int $limit, ?string $cursor = null, int $statusCode = 200): ResponseInterface`
- **Purpose:** Create paginated list response
- **Parameters:**
  - `$data`: Array of items
  - `$limit`: Number of items per page
  - `$cursor`: Opaque cursor value for next page (optional)
  - `$statusCode`: HTTP status code (default: 200)
- **Returns:** `ResponseInterface` with JSON body `{"data": [...], "paging": {"limit": N, "cursor": "..."}}`
- **Uses:** `ResponseFactoryUtil::paginated()`

#### `created(mixed $data): ResponseInterface`
- **Purpose:** Create 201 Created response
- **Parameters:**
  - `$data`: Created resource data
- **Returns:** `ResponseInterface` with status 201 and JSON body `{"data": {...}}`
- **Uses:** `ResponseFactoryUtil::created()`

#### `noContent(): ResponseInterface`
- **Purpose:** Create 204 No Content response
- **Parameters:** None
- **Returns:** `ResponseInterface` with status 204 and empty body
- **Uses:** `ResponseFactoryUtil::noContent()`

#### `error(string $code, string $message, array $details = [], ?int $statusCode = null): ResponseInterface`
- **Purpose:** Create JSON error response
- **Parameters:**
  - `$code`: Error code (from ErrorFactory constants)
  - `$message`: Error message
  - `$details`: Additional error details (default: [])
  - `$statusCode`: HTTP status code (optional, mapped from error code if not provided)
- **Returns:** `ResponseInterface` with JSON body `{"error": {"code": "...", "message": "...", "details": {...}}}`
- **Uses:** `ErrorFactory::create()`

---

### 1.2 Gateway PostController

**File:** `src/Controllers/Gateway/PostController.php`

**Purpose:** Handle post creation and access management endpoints for Gateway JSON surface

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `PostService` (constructor, private)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, PostService $postService)`
- **Purpose:** Initialize controller
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$postService`: Post service instance
- **Returns:** void
- **Side Effects:** Calls `parent::__construct($responseFactory)`

#### `create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Create a new post
- **Endpoint:** `POST /api/posts`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:create`
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware)
- **Request Body Expected:**
  - `content` (string, required, 1-10000 chars)
  - `title` (string, optional, 1-255 chars)
- **Process:**
  1. Extract `key_id` from request attributes
  2. Validate `key_id` exists
  3. Parse request body
  4. Validate `content` field exists and is string
  5. Call `PostService::createPost($keyIdHex32, $content, $title)`
  6. Return 201 Created response with post data
- **Exception Handling:**
  - `ForbiddenException` → 403 Forbidden with required permissions/mask
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow (handled by ErrorHandlingMiddleware)
- **Returns:** `ResponseInterface` with status 201 and JSON body `{"data": {post object}}`

#### `grantAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Grant access to a post
- **Endpoint:** `POST /api/posts/{postId}/access`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:access:manage`
- **Required Mask:** `MANAGE_ACCESS` (0x08) on the post
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware)
  - `permissions` (from JwtKeyMiddleware)
- **Route Parameters:**
  - `postId` (hex32)
- **Request Body Expected:**
  - `target_type` (string, required, 'key' or 'group')
  - `target_id` (string, required, hex32)
  - `permission_mask` (int, required, valid bitmask)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Extract `postId` from route parameters
  4. Validate `postId` exists and is string
  5. Parse request body
  6. Validate `target_type`, `target_id`, `permission_mask` fields
  7. Call `PostService::grantAccess($postIdHex32, $keyIdHex32, $permissions, $targetType, $targetIdHex32, $permissionMask)`
  8. Return 201 Created response with access grant data
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - `ForbiddenException` → 403 Forbidden with required permissions/mask
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 201 and JSON body `{"data": {access object}}`

#### `list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** List posts accessible to a key
- **Endpoint:** `GET /api/posts`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:read`
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware)
  - `permissions` (from JwtKeyMiddleware)
- **Query Parameters:**
  - `limit` (int, optional, default: 20, max: 100)
  - `before_id` (string, optional, hex32 cursor)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Verify `posts:read` permission in `$permissions` array
  4. Parse query parameters (`limit`, `before_id`)
  5. Normalize `limit` (default: 20, clamp to 1-100)
  6. Call `PostService::listPosts($keyIdHex32, $permissions, $limit, $beforeId)`
  7. Return 200 OK response with list of posts
- **Exception Handling:**
  - All exceptions → rethrow (handled by ErrorHandlingMiddleware)
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": [post objects]}`

#### `get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Get post details
- **Endpoint:** `GET /api/posts/{postId}`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:read`
- **Required Mask:** `VIEW` (0x01) on the post
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware)
  - `permissions` (from JwtKeyMiddleware)
- **Route Parameters:**
  - `postId` (hex32)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Extract `postId` from route parameters
  4. Validate `postId` exists and is string
  5. Call `PostService::getPost($postIdHex32, $keyIdHex32, $permissions)`
  6. Return 200 OK response with post data
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - `ForbiddenException` → 403 Forbidden with required permissions/mask
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {post object}}`

#### `revokeAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Revoke access to a post
- **Endpoint:** `DELETE /api/posts/{postId}/access/{targetType}/{targetId}`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:access:manage`
- **Required Mask:** `MANAGE_ACCESS` (0x08) on the post
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware)
  - `permissions` (from JwtKeyMiddleware)
- **Route Parameters:**
  - `postId` (hex32)
  - `targetType` (string, 'key' or 'group')
  - `targetId` (hex32)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Extract route parameters (`postId`, `targetType`, `targetId`)
  4. Validate all route parameters exist and are strings
  5. Call `PostService::revokeAccess($postIdHex32, $keyIdHex32, $permissions, $targetType, $targetIdHex32)`
  6. Return 204 No Content response
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - `ForbiddenException` → 403 Forbidden with required permissions/mask
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 204 and empty body

---

### 1.3 Gateway FeedController

**File:** `src/Controllers/Gateway/FeedController.php`

**Purpose:** Handle feed endpoints for Gateway JSON surface

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `FeedService` (constructor, private)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, FeedService $feedService)`
- **Purpose:** Initialize controller
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$feedService`: Feed service instance
- **Returns:** void
- **Side Effects:** Calls `parent::__construct($responseFactory)`

#### `getUseFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Get Use Key feed
- **Endpoint:** `GET /api/feed/use/{useKeyId}`
- **Auth:** Key JWT (`typ=key`)
- **Required Permission:** `posts:read`
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware, stored as `$jwtKeyIdHex32`)
  - `permissions` (from JwtKeyMiddleware)
- **Route Parameters:**
  - `useKeyId` (hex32)
- **Query Parameters:**
  - `limit` (int, optional, default: 20, max: 100)
  - `before_id` (string, optional, hex32 cursor for older posts)
  - `since_id` (string, optional, hex32 cursor for newer posts)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Extract `useKeyId` from route parameters
  4. Validate `useKeyId` exists and is string
  5. Parse query parameters (`limit`, `before_id`, `since_id`)
  6. Validate that `before_id` and `since_id` are not both provided
  7. Call `FeedService::getUseKeyFeed($useKeyIdHex32, $jwtKeyIdHex32, $permissions, $limit, $beforeIdHex32, $sinceIdHex32)`
  8. Return 200 OK response with feed data
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found (includes path guard mismatch)
  - `ForbiddenException` → 403 Forbidden with required permissions
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"data": [posts], "paging": {...}}}`

#### `getAuthorFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Get Author Key feed
- **Endpoint:** `GET /api/feed/author`
- **Auth:** Author Key JWT (`typ=key`, Primary or Secondary)
- **Required Permission:** `posts:read`
- **Request Attributes Used:**
  - `key_id` (from JwtKeyMiddleware, stored as `$authorKeyIdHex32`)
  - `permissions` (from JwtKeyMiddleware)
- **Query Parameters:**
  - `limit` (int, optional, default: 20, max: 100)
  - `before_id` (string, optional, hex32 cursor for older posts)
  - `since_id` (string, optional, hex32 cursor for newer posts)
- **Process:**
  1. Extract `key_id` and `permissions` from request attributes
  2. Validate `key_id` exists
  3. Parse query parameters (`limit`, `before_id`, `since_id`)
  4. Validate that `before_id` and `since_id` are not both provided
  5. Call `FeedService::getAuthorFeed($authorKeyIdHex32, $permissions, $limit, $beforeIdHex32, $sinceIdHex32)`
  6. Return 200 OK response with feed data
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found (if key not found or not Author Key)
  - `ForbiddenException` → 403 Forbidden with required permissions
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"data": [posts], "paging": {...}}}`

---

### 1.4 Console KeyController

**File:** `src/Controllers/Console/KeyController.php`

**Purpose:** Handle key management endpoints for Console JSON surface

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `KeyService` (constructor, private)
- `KeyRepository` (constructor, private)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, KeyService $keyService, KeyRepository $keyRepository)`
- **Purpose:** Initialize controller
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$keyService`: Key service instance
  - `$keyRepository`: Key repository instance
- **Returns:** void
- **Side Effects:** Calls `parent::__construct($responseFactory)`

#### `mintPrimary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Mint a Primary Author Key
- **Endpoint:** `POST /console/keys/primary`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:issue`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Request Body Expected:**
  - `permissions` (array<string>, required)
  - `label` (string, optional)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Parse request body
  4. Validate `permissions` is array
  5. Validate `label` is string if provided
  6. Call `KeyService::mintPrimaryKey($ownerIdHex32, $keyPermissions, $label)`
  7. Return 201 Created response with key data (includes `key_secret` - returned only once)
- **Exception Handling:**
  - `ForbiddenException` → 403 Forbidden with required permissions
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 201 and JSON body `{"data": {"key_id": "...", "key_public_id": "...", "key_secret": "..."}}`

#### `list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** List Owner's keys
- **Endpoint:** `GET /console/keys`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:read`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Call `KeyService::listKeys($ownerIdHex32, $permissions)` (service enforces `keys:read` permission)
  4. Return 200 OK response with list of keys
- **Exception Handling:**
  - `ForbiddenException` → 403 Forbidden with required permissions
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": [key objects]}`

#### `get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Get key details
- **Endpoint:** `GET /console/keys/{keyId}`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:read`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Route Parameters:**
  - `keyId` (hex32)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Extract `keyId` from route parameters
  4. Validate `keyId` exists and is string
  5. Call `KeyService::getKey($keyIdHex32, $ownerIdHex32, $permissions)` (service enforces `keys:read` permission and ownership verification)
  6. Return 200 OK response with key data
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found (if key not found or not owned by owner)
  - `ForbiddenException` → 403 Forbidden with required permissions
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {key object}}`

#### `getLineage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** View key lineage tree
- **Endpoint:** `GET /console/keys/{keyId}/lineage`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:read`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Route Parameters:**
  - `keyId` (hex32)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Verify `keys:read` permission in `$permissions` array
  4. Extract `keyId` from route parameters
  5. Validate `keyId` exists and is string
  6. Load key via `KeyRepository::findById($keyIdHex32)`
  7. If key not found, return 404
  8. Get lineage tree via `KeyRepository::getLineageTree($keyIdHex32)`
  9. Return 200 OK response with lineage data
- **Exception Handling:**
  - All exceptions → rethrow (handled by ErrorHandlingMiddleware)
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"lineage": [key objects]}}`

#### `rotate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Rotate a key
- **Endpoint:** `POST /console/keys/{keyId}/rotate`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:rotate`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Route Parameters:**
  - `keyId` (hex32)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Verify `keys:rotate` permission in `$permissions` array
  4. Extract `keyId` from route parameters
  5. Validate `keyId` exists and is string
  6. Call `KeyService::rotateKey($keyIdHex32, $ownerIdHex32, 'owner')`
  7. Return 200 OK response with rotation result (includes `new_key_secret` - returned only once)
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - `InvalidArgumentException` → 400 Bad Request
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"old_key_id": "...", "new_key_id": "...", "new_key_public_id": "...", "new_key_secret": "..."}}`

#### `activate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Activate a key
- **Endpoint:** `POST /console/keys/{keyId}/activate`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:state:update`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Route Parameters:**
  - `keyId` (hex32)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Verify `keys:state:update` permission in `$permissions` array
  4. Extract `keyId` from route parameters
  5. Validate `keyId` exists and is string
  6. Call `KeyService::activateKey($keyIdHex32, $ownerIdHex32, 'owner')`
  7. Load key via `KeyRepository::findById($keyIdHex32)` to verify
  8. Return 200 OK response with key status
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"key_id": "...", "active": true}}`

#### `deactivate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface`
- **Purpose:** Deactivate a key
- **Endpoint:** `POST /console/keys/{keyId}/deactivate`
- **Auth:** Owner JWT (`typ=owner`)
- **Required Permission:** `keys:state:update`
- **Request Attributes Used:**
  - `owner_id` (from JwtOwnerMiddleware)
  - `permissions` (from JwtOwnerMiddleware)
- **Route Parameters:**
  - `keyId` (hex32)
- **Request Body Expected:**
  - `cascade` (bool, optional, default: false)
- **Process:**
  1. Extract `owner_id` and `permissions` from request attributes
  2. Validate `owner_id` exists
  3. Verify `keys:state:update` permission in `$permissions` array
  4. Extract `keyId` from route parameters
  5. Validate `keyId` exists and is string
  6. Parse request body for `cascade` option (default: false)
  7. Call `KeyService::deactivateKey($keyIdHex32, $cascade, $ownerIdHex32, 'owner')`
  8. Return 200 OK response with deactivation result
- **Exception Handling:**
  - `NotFoundException` → 404 Not Found
  - Other exceptions → rethrow
- **Returns:** `ResponseInterface` with status 200 and JSON body `{"data": {"key_id": "...", "active": false, "keys_deactivated": N}}`

---

## 2. Services

### 2.1 BaseService

**File:** `src/Services/BaseService.php`

**Purpose:** Base class for all services (currently empty, enforces architectural boundaries)

**Dependencies:** None

**Methods:** None (abstract base class)

**Purpose:** Enforces architectural boundaries - services contain business logic, authorization checks, transactions. Services MUST NOT access HTTP concerns or write direct SQL queries.

---

### 2.2 PostService

**File:** `src/Services/PostService.php`

**Purpose:** Handles post creation and access management with authorization checks

**Dependencies:**
- `PostRepository` (constructor, private)
- `PostAccessRepository` (constructor, private)
- `KeyRepository` (constructor, private)
- `GroupRepository` (constructor, private)
- `GroupMemberRepository` (constructor, private)
- `AuditService` (constructor, private)
- `PermissionCatalog` (static utility)
- `PostAccessBitmask` (static utility)
- `Ids` (static utility)

**Methods:**

#### `__construct(PostRepository $postRepo, PostAccessRepository $postAccessRepo, KeyRepository $keyRepo, GroupRepository $groupRepo, GroupMemberRepository $groupMemberRepo, AuditService $auditService)`
- **Purpose:** Initialize service with repositories
- **Parameters:** All repositories and audit service
- **Returns:** void
- **Side Effects:** None

#### `createPost(string $authorKeyIdHex32, string $content, ?string $title = null): array`
- **Purpose:** Create a new post
- **Requirements:**
  - Key must have `posts:create` permission
  - Key type must be `primary` or `secondary` (NOT `use`)
  - Post is created with NO access grants by default (private)
- **Parameters:**
  - `$authorKeyIdHex32`: Author key ID from JWT (hex32)
  - `$content`: Post content (1-10000 chars)
  - `$title`: Post title (optional, 1-255 chars)
- **Process:**
  1. Validate content length (1-10000 chars using `mb_strlen()`)
  2. Validate title length if provided (1-255 chars)
  3. Load author key via `KeyRepository::findById($authorKeyIdHex32)`
  4. Verify key exists (throw ForbiddenException if not)
  5. Verify key is active (`active = true`)
  6. Verify key has `posts:create` permission in `permissions` array
  7. Verify key type is `primary` or `secondary` (NOT `use`)
  8. Get `initial_author_key_id` from key (for provenance)
  9. Generate post ID via `Ids::generateHex32Id()`
  10. Create post via `PostRepository::create([...])`
  11. Load created post via `PostRepository::findById($postIdHex32)`
  12. Emit audit event via `AuditService::emit()` (actor: key, action: 'posts:create', subject: post)
  13. Return post data array
- **Returns:** `array{post_id: string, author_key_id: string, initial_author_key_id: string, content: string, title: string|null, created_at: string}`
- **Throws:**
  - `ForbiddenException` if key lacks permission or is Use Key
  - `InvalidArgumentException` if validation fails
  - `RuntimeException` if post creation fails

#### `grantAccess(string $postIdHex32, string $requesterKeyIdHex32, array $requesterPermissions, string $targetType, string $targetIdHex32, int $permissionMask): array`
- **Purpose:** Grant access to a post (key or group)
- **Requirements:**
  - Requester must have `posts:access:manage` permission
  - Requester must have MANAGE_ACCESS mask (0x08) on the post
  - Upsert behavior (insert or update existing grant)
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$requesterKeyIdHex32`: Requester key ID from JWT (hex32)
  - `$requesterPermissions`: Requester permissions from JWT
  - `$targetType`: Target type ('key' or 'group')
  - `$targetIdHex32`: Target ID (hex32)
  - `$permissionMask`: Permission mask (must be valid)
- **Process:**
  1. Validate `target_type` is 'key' or 'group'
  2. Validate permission mask via `PostAccessBitmask::isValid($permissionMask)`
  3. Reject zero mask (must grant at least one permission)
  4. Verify post exists via `PostRepository::findById($postIdHex32)`
  5. Verify requester has `posts:access:manage` permission
  6. Get requester's groups via `GroupMemberRepository::findGroupsForKey($requesterKeyIdHex32)`
  7. Resolve requester's access mask via `PostAccessRepository::resolveAccessMask($postIdHex32, $requesterKeyIdHex32, $requesterGroups)`
  8. Verify requester has MANAGE_ACCESS mask via `PostAccessBitmask::hasManageAccess($requesterMask)`
  9. Verify target exists (key via `KeyRepository::findById()` or group via `GroupRepository::findById()`)
  10. Generate access ID via `Ids::generateHex32Id()`
  11. Upsert access grant via `PostAccessRepository::upsert([...])`
  12. Emit audit event via `AuditService::emit()` (actor: key, action: 'posts:access:grant', subject: post)
  13. Return access grant data array
- **Returns:** `array{access_id: string, post_id: string, target_type: string, target_id: string, permission_mask: int}`
- **Throws:**
  - `NotFoundException` if post or target not found
  - `ForbiddenException` if requester lacks permission or MANAGE_ACCESS mask
  - `InvalidArgumentException` if validation fails

#### `revokeAccess(string $postIdHex32, string $requesterKeyIdHex32, array $requesterPermissions, string $targetType, string $targetIdHex32): void`
- **Purpose:** Revoke access to a post
- **Requirements:**
  - Requester must have `posts:access:manage` permission
  - Requester must have MANAGE_ACCESS mask (0x08) on the post
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$requesterKeyIdHex32`: Requester key ID from JWT (hex32)
  - `$requesterPermissions`: Requester permissions from JWT
  - `$targetType`: Target type ('key' or 'group')
  - `$targetIdHex32`: Target ID (hex32)
- **Process:**
  1. Validate `target_type` is 'key' or 'group'
  2. Verify post exists via `PostRepository::findById($postIdHex32)`
  3. Verify requester has `posts:access:manage` permission
  4. Get requester's groups via `GroupMemberRepository::findGroupsForKey($requesterKeyIdHex32)`
  5. Resolve requester's access mask via `PostAccessRepository::resolveAccessMask($postIdHex32, $requesterKeyIdHex32, $requesterGroups)`
  6. Verify requester has MANAGE_ACCESS mask via `PostAccessBitmask::hasManageAccess($requesterMask)`
  7. Revoke access grant via `PostAccessRepository::revoke($postIdHex32, $targetType, $targetIdHex32)`
  8. Emit audit event via `AuditService::emit()` (actor: key, action: 'posts:access:revoke', subject: post)
- **Returns:** void
- **Throws:**
  - `NotFoundException` if post not found
  - `ForbiddenException` if requester lacks permission or MANAGE_ACCESS mask
  - `InvalidArgumentException` if validation fails

#### `listPosts(string $keyIdHex32, array $keyPermissions, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** List posts accessible to a key (Gateway)
- **Requirements:**
  - Key must have `posts:read` permission
  - Returns posts visible to the key via direct grants or group memberships
  - Only includes posts with READ mask (0x01)
- **Parameters:**
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
  - `$limit`: Limit (default: 20, max: 100)
  - `$beforeIdHex32`: Cursor for pagination (post ID before this)
- **Process:**
  1. Verify key has `posts:read` permission
  2. Get groups the key belongs to via `GroupMemberRepository::findGroupsForKey($keyIdHex32)`
  3. Find visible posts via `PostRepository::findVisiblePostsForUseKey($keyIdHex32, $groupIdsHex32, $limit, $beforeIdHex32)`
  4. Return posts array
- **Returns:** `array<array>` List of posts
- **Throws:**
  - `ForbiddenException` if key lacks permission

#### `getPost(string $postIdHex32, string $keyIdHex32, array $keyPermissions): array`
- **Purpose:** Get post details (Gateway)
- **Requirements:**
  - Key must have `posts:read` permission
  - Key must have READ mask (0x01) on the post
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
- **Process:**
  1. Verify key has `posts:read` permission
  2. Load post via `PostRepository::findById($postIdHex32)`
  3. If post not found, throw NotFoundException
  4. Get key's groups via `GroupMemberRepository::findGroupsForKey($keyIdHex32)`
  5. Resolve access mask via `PostAccessRepository::resolveAccessMask($postIdHex32, $keyIdHex32, $groupIdsHex32)`
  6. Verify key has VIEW mask via `PostAccessBitmask::hasView($accessMask)`
  7. If no VIEW mask, throw NotFoundException (hide existence)
  8. Return post data array
- **Returns:** `array` Post data
- **Throws:**
  - `NotFoundException` if post not found or key lacks access
  - `ForbiddenException` if key lacks permission

#### `listPostsByOwner(string $ownerIdHex32, array $ownerPermissions, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** List posts owned by an owner
- **Requirements:**
  - Owner must have `posts:admin:read` permission
  - Returns posts created by any key owned by the owner (via initial_author_key_id)
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$limit`: Limit (default: 20)
  - `$beforeIdHex32`: Cursor for pagination (post ID before this)
- **Process:**
  1. Verify owner has `posts:admin:read` permission
  2. Find owner's primary keys via `KeyRepository::findByOwner($ownerIdHex32)`
  3. Extract primary key IDs array
  4. Find posts by owner via `PostRepository::findByOwner($primaryKeyIds, $limit, $beforeIdHex32)`
  5. Determine next cursor (last post ID if there are results)
  6. Return posts with paging info
- **Returns:** `array{posts: array, paging: array{limit: int, before_id: string|null, next_cursor: string|null}}`
- **Throws:**
  - `ForbiddenException` if owner lacks permission

#### `getPostForOwner(string $postIdHex32, string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** Get post details for owner admin view
- **Requirements:**
  - Owner must have `posts:admin:read` permission
  - Post must be owned by the owner (via initial_author_key_id)
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `posts:admin:read` permission
  2. Load post via `PostRepository::findById($postIdHex32)`
  3. If post not found, throw NotFoundException
  4. Find owner's primary keys via `KeyRepository::findByOwner($ownerIdHex32)`
  5. Extract primary key IDs array
  6. Verify post's `initial_author_key_id` matches one of owner's primary keys
  7. If not owned, throw NotFoundException (hide existence)
  8. Return post data array
- **Returns:** `array` Post data
- **Throws:**
  - `NotFoundException` if post not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `grantGroupAccess(string $postIdHex32, string $ownerIdHex32, array $ownerPermissions, string $groupIdHex32, int $permissionMask): array`
- **Purpose:** Grant group access to a post (Console variant)
- **Requirements:**
  - Owner must have `posts:access:manage` permission
  - Post must be owned by the owner (via initial_author_key_id)
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$groupIdHex32`: Group ID (hex32)
  - `$permissionMask`: Permission mask (must be valid)
- **Process:**
  1. Validate permission mask via `PostAccessBitmask::isValid($permissionMask)`
  2. Reject zero mask
  3. Verify owner has `posts:access:manage` permission
  4. Load post and verify ownership (via initial_author_key_id matching owner's primary keys)
  5. Verify group exists and is owned by owner
  6. Generate access ID via `Ids::generateHex32Id()`
  7. Upsert access grant via `PostAccessRepository::upsert([...])`
  8. Emit audit event via `AuditService::emit()` (actor: owner, action: 'posts:access:grant-group', subject: post)
  9. Return access grant data array
- **Returns:** `array{access_id: string, post_id: string, group_id: string, permission_mask: int}`
- **Throws:**
  - `NotFoundException` if post or group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission
  - `InvalidArgumentException` if validation fails

#### `revokeGroupAccess(string $postIdHex32, string $ownerIdHex32, array $ownerPermissions, string $groupIdHex32): void`
- **Purpose:** Revoke group access to a post (Console variant)
- **Requirements:**
  - Owner must have `posts:access:manage` permission
  - Post must be owned by the owner (via initial_author_key_id)
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$groupIdHex32`: Group ID (hex32)
- **Process:**
  1. Verify owner has `posts:access:manage` permission
  2. Load post and verify ownership (via initial_author_key_id matching owner's primary keys)
  3. Verify group exists and is owned by owner
  4. Revoke access grant via `PostAccessRepository::revoke($postIdHex32, 'group', $groupIdHex32)`
  5. Emit audit event via `AuditService::emit()` (actor: owner, action: 'posts:access:revoke', subject: post)
- **Returns:** void
- **Throws:**
  - `NotFoundException` if post or group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

---

### 2.3 CommentService

**File:** `src/Services/CommentService.php`

**Purpose:** Handles comment creation and listing with authorization checks

**Dependencies:**
- `CommentRepository` (constructor, private)
- `PostRepository` (constructor, private)
- `PostAccessRepository` (constructor, private)
- `GroupMemberRepository` (constructor, private)
- `AuditService` (constructor, private)
- `PostAccessBitmask` (static utility)
- `Ids` (static utility)

**Methods:**

#### `__construct(CommentRepository $commentRepo, PostRepository $postRepo, PostAccessRepository $postAccessRepo, GroupMemberRepository $groupMemberRepo, AuditService $auditService)`
- **Purpose:** Initialize service with repositories
- **Parameters:** All repositories and audit service
- **Returns:** void
- **Side Effects:** None

#### `createComment(string $postIdHex32, string $authorKeyIdHex32, array $authorPermissions, string $body): array`
- **Purpose:** Create a new comment
- **Requirements:**
  - Key must have `comments:write` permission
  - Key must have COMMENT mask (0x02) on the post
  - Post must exist and be visible to the key
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$authorKeyIdHex32`: Author key ID from JWT (hex32)
  - `$authorPermissions`: Author permissions from JWT
  - `$body`: Comment body (1-5000 chars)
- **Process:**
  1. Validate body length (1-5000 chars using `mb_strlen()`)
  2. Verify post exists via `PostRepository::findById($postIdHex32)`
  3. Verify key has `comments:write` permission
  4. Get key's groups via `GroupMemberRepository::findGroupsForKey($authorKeyIdHex32)`
  5. Resolve access mask via `PostAccessRepository::resolveAccessMask($postIdHex32, $authorKeyIdHex32, $keyGroups)`
  6. Check if key has VIEW access (to verify post is visible)
  7. If no VIEW mask, throw NotFoundException (hide existence)
  8. Check if key has COMMENT mask via `PostAccessBitmask::hasComment($accessMask)`
  9. If no COMMENT mask, throw ForbiddenException
  10. Generate comment ID via `Ids::generateHex32Id()`
  11. Create comment via `CommentRepository::create([...])`
  12. Load created comment via `CommentRepository::findById($commentIdHex32)`
  13. Emit audit event via `AuditService::emit()` (actor: key, action: 'comments:create', subject: comment)
  14. Return comment data array
- **Returns:** `array{comment_id: string, post_id: string, created_by_key_id: string, body: string, created_at: string}`
- **Throws:**
  - `NotFoundException` if post not found or not visible
  - `ForbiddenException` if key lacks permission or COMMENT mask
  - `InvalidArgumentException` if validation fails

#### `listComments(string $postIdHex32, string $viewerKeyIdHex32, array $viewerPermissions, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** List comments for a post
- **Requirements:**
  - Key must have `posts:read` permission
  - Key must have VIEW mask (0x01) on the post
  - Post must exist and be visible to the key
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$viewerKeyIdHex32`: Viewer key ID from JWT (hex32)
  - `$viewerPermissions`: Viewer permissions from JWT
  - `$limit`: Limit (default: 20, max: 100)
  - `$beforeIdHex32`: Cursor for pagination (comment ID before this)
- **Process:**
  1. Validate limit (1-100)
  2. Verify post exists via `PostRepository::findById($postIdHex32)`
  3. Verify key has `posts:read` permission
  4. Get key's groups via `GroupMemberRepository::findGroupsForKey($viewerKeyIdHex32)`
  5. Resolve access mask via `PostAccessRepository::resolveAccessMask($postIdHex32, $viewerKeyIdHex32, $keyGroups)`
  6. Check if key has VIEW access
  7. If no VIEW mask, throw NotFoundException (hide existence)
  8. Find comments via `CommentRepository::findByPost($postIdHex32, $limit, $beforeIdHex32)`
  9. Determine next cursor (last comment ID if there are results)
  10. Return comments with paging info
- **Returns:** `array{comments: array, paging: array{limit: int, before_id: string|null, next_cursor: string|null}}`
- **Throws:**
  - `NotFoundException` if post not found or not visible
  - `ForbiddenException` if key lacks permission or VIEW mask
  - `InvalidArgumentException` if limit validation fails

---

### 2.4 FeedService

**File:** `src/Services/FeedService.php`

**Purpose:** Handles feed operations for Gateway JSON surface

**Dependencies:**
- `PostRepository` (constructor, private)
- `GroupMemberRepository` (constructor, private)
- `KeyRepository` (constructor, private)

**Methods:**

#### `__construct(PostRepository $postRepo, GroupMemberRepository $groupMemberRepo, KeyRepository $keyRepo)`
- **Purpose:** Initialize service with repositories
- **Parameters:** Post, GroupMember, and Key repositories
- **Returns:** void
- **Side Effects:** None

#### `getUseKeyFeed(string $useKeyIdHex32, string $jwtKeyIdHex32, array $keyPermissions, int $limit = 20, ?string $beforeIdHex32 = null, ?string $sinceIdHex32 = null): array`
- **Purpose:** Get Use Key feed
- **Requirements:**
  - Key must have `posts:read` permission
  - Path `useKeyId` must match JWT `key_id` (enforced here - returns 404 on mismatch)
  - Returns posts visible to the Use Key via direct grants or group memberships
  - Only includes posts with VIEW mask (0x01)
- **Parameters:**
  - `$useKeyIdHex32`: Use Key ID from path (hex32)
  - `$jwtKeyIdHex32`: Key ID from JWT (hex32) - must match useKeyIdHex32
  - `$keyPermissions`: Key permissions from JWT
  - `$limit`: Limit (default 20, max 100)
  - `$beforeIdHex32`: Cursor for older posts (post ID)
  - `$sinceIdHex32`: Cursor for newer posts (post ID)
- **Process:**
  1. Validate limit (1-100)
  2. **Path Guard:** Validate that path key ID matches JWT key ID (throw NotFoundException if mismatch - hides existence)
  3. Verify key has `posts:read` permission
  4. Verify key exists via `KeyRepository::findById($useKeyIdHex32)`
  5. Get groups the key belongs to via `GroupMemberRepository::findGroupsForKey($useKeyIdHex32)`
  6. Find visible posts via `PostRepository::findVisiblePostsForUseKey($useKeyIdHex32, $groupIdsHex32, $limit, $beforeIdHex32, $sinceIdHex32)`
  7. Determine cursor (last post ID in result set, or null if empty)
  8. Return feed data with paging info
- **Returns:** `array{data: array<array>, paging: array{limit: int, cursor: string|null}}`
- **Throws:**
  - `NotFoundException` if key not found or path/JWT mismatch (path guard)
  - `ForbiddenException` if key lacks permission
  - `InvalidArgumentException` if validation fails

#### `getAuthorFeed(string $authorKeyIdHex32, array $keyPermissions, int $limit = 20, ?string $beforeIdHex32 = null, ?string $sinceIdHex32 = null): array`
- **Purpose:** Get Author Key feed
- **Requirements:**
  - Key must be an Author Key (Primary or Secondary)
  - Key must have `posts:read` permission
  - Returns posts authored by the Author Key or its descendants, OR posts visible via group memberships
- **Parameters:**
  - `$authorKeyIdHex32`: Author Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
  - `$limit`: Limit (default 20, max 100)
  - `$beforeIdHex32`: Cursor for older posts (post ID)
  - `$sinceIdHex32`: Cursor for newer posts (post ID)
- **Process:**
  1. Validate limit (1-100)
  2. Verify key has `posts:read` permission
  3. Verify key exists and is an Author Key (not Use Key) via `KeyRepository::findById($authorKeyIdHex32)`
  4. Get groups the key belongs to via `GroupMemberRepository::findGroupsForKey($authorKeyIdHex32)`
  5. Get `initial_author_key_id` from key
  6. Find posts authored by keys with same initial_author_key_id via `PostRepository::findByInitialAuthor($initialAuthorKeyIdHex32, $limit * 2, $beforeIdHex32, $sinceIdHex32)`
  7. Find posts visible via group memberships (if key belongs to groups) via `PostRepository::findVisiblePostsForGroups($groupIdsHex32, $limit * 2, $beforeIdHex32, $sinceIdHex32)`
  8. Merge and deduplicate posts (by post_id)
  9. Sort by created_at DESC and limit
  10. Determine cursor (last post ID in result set, or null if empty)
  11. Return feed data with paging info
- **Returns:** `array{data: array<array>, paging: array{limit: int, cursor: string|null}}`
- **Throws:**
  - `NotFoundException` if key not found or not an Author Key
  - `ForbiddenException` if key lacks permission
  - `InvalidArgumentException` if validation fails

---

### 2.5 AuthService

**File:** `src/Services/AuthService.php`

**Purpose:** Handles Owner authentication, password hashing, and token generation

**Dependencies:**
- `OwnerRepository` (constructor, private)
- `RefreshTokenRepository` (constructor, private)
- `KeyRepository` (constructor, private)
- `KeyPublicIdRepository` (constructor, private)
- `KeyDeviceRepository` (constructor, private)
- `JwtService` (constructor, private)
- `AuditService` (constructor, private)
- `Ids` (static utility)

**Methods:**

#### `__construct(OwnerRepository $ownerRepository, RefreshTokenRepository $refreshTokenRepository, KeyRepository $keyRepository, KeyPublicIdRepository $keyPublicIdRepository, KeyDeviceRepository $keyDeviceRepository, JwtService $jwtService, AuditService $auditService)`
- **Purpose:** Initialize service with repositories and JWT service
- **Parameters:** All repositories, JWT service, and audit service
- **Returns:** void
- **Side Effects:** None

#### `registerOwner(string $email, string $password): string`
- **Purpose:** Register a new owner
- **Parameters:**
  - `$email`: Owner email
  - `$password`: Plaintext password
- **Process:**
  1. Validate email format via `filter_var($email, FILTER_VALIDATE_EMAIL)`
  2. Validate password strength (min 8 characters)
  3. Check uniqueness via `OwnerRepository::findByEmail($email)`
  4. If email exists, throw InvalidArgumentException (generic message - don't reveal existence)
  5. Hash password with Argon2id via `hashPassword($password)`
  6. Generate owner ID via `Ids::generateHex32Id()`
  7. Insert owner via `OwnerRepository::create($ownerIdHex32, $email, $passwordHash)`
  8. Emit audit event via `AuditService::emit()` (actor: owner, action: 'owners:register', subject: owner)
  9. Return owner ID (hex32)
- **Returns:** `string` Owner ID (hex32)
- **Throws:**
  - `InvalidArgumentException` if email already exists or validation fails

#### `loginOwner(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array`
- **Purpose:** Login owner and generate tokens
- **Parameters:**
  - `$email`: Owner email
  - `$password`: Plaintext password
  - `$ip`: Client IP address (optional)
  - `$userAgent`: User agent string (optional)
- **Process:**
  1. Lookup owner by email via `OwnerRepository::findByEmail($email)`
  2. If owner not found, throw InvalidArgumentException (generic message - don't reveal existence)
  3. Verify password via `verifyPassword($password, $owner['password_hash'])`
  4. If password invalid, throw InvalidArgumentException (generic message)
  5. Generate Owner JWT via `JwtService::signOwnerToken()` with owner permissions and /console audience
  6. Generate refresh token via `generateRefreshToken()`
  7. Create lookup hash (SHA-256) and token hash (Argon2id)
  8. Store refresh token via `RefreshTokenRepository::create([...])`
  9. Emit audit event via `AuditService::emit()` (actor: owner, action: 'owners:login', subject: owner)
  10. Return tokens array
- **Returns:** `array{access_token: string, refresh_token: string, expires_in: int}`
- **Throws:**
  - `InvalidArgumentException` if credentials are invalid (generic message)

#### `refreshToken(string $refreshToken, ?string $ip = null, ?string $userAgent = null): array`
- **Purpose:** Refresh access token using refresh token
- **Parameters:**
  - `$refreshToken`: Plaintext refresh token
  - `$ip`: Client IP address (optional)
  - `$userAgent`: User agent string (optional)
- **Process:**
  1. Lookup token by SHA-256 hash via `RefreshTokenRepository::findByLookupHash($lookupHash)`
  2. If token not found, throw InvalidArgumentException (generic message)
  3. Verify token using password_verify (checks Argon2id hash)
  4. Verify expires_at is in the future
  5. Verify revoked_at is NULL
  6. Verify rotated_at is NULL (single-use enforcement)
  7. If rotated_at is not NULL, emit audit event for replay attempt and throw InvalidArgumentException
  8. Generate new refresh token ID
  9. Generate new access JWT based on subject_type (owner or key)
  10. Generate new refresh token
  11. Store new refresh token via `RefreshTokenRepository::create([...])`
  12. Mark old token as rotated via `RefreshTokenRepository::markRotated($tokenData['token_id'], $newRefreshTokenIdHex32)`
  13. Emit audit event via `AuditService::emit()` (actor: subject, action: 'refresh_token:rotate', subject: refresh_token)
  14. Return new tokens array
- **Returns:** `array{access_token: string, refresh_token: string, expires_in: int}`
- **Throws:**
  - `InvalidArgumentException` if refresh token is invalid, expired, revoked, or already rotated

#### `exchangeApiKey(string $keyPublicId, string $keySecret, ?string $ip = null, ?string $userAgent = null): array`
- **Purpose:** Exchange ApiKey for JWT tokens
- **Parameters:**
  - `$keyPublicId`: Key public ID (apub_...)
  - `$keySecret`: Plaintext key secret
  - `$ip`: Client IP address (optional)
  - `$userAgent`: User agent string (optional)
- **Process:**
  1. Lookup key_id via key_public_ids table via `KeyPublicIdRepository::findKeyIdByPublicId($keyPublicId)`
  2. If key_public_id not found, throw InvalidArgumentException (generic message)
  3. Load key record via `KeyRepository::findById($keyIdHex32)`
  4. If key not found, throw InvalidArgumentException (generic message)
  5. Verify key_secret against key_secret_hash using Argon2id via `verifyPassword($keySecret, $key['key_secret_hash'])`
  6. If secret invalid, emit audit event for failed exchange and throw InvalidArgumentException
  7. Verify key is active=1
  8. **For Use Keys:** Enforce use_count_limit and device_limit
     - Check use_count_current < use_count_limit
     - Increment use count via `KeyRepository::incrementUseCount($keyIdHex32)`
     - Check device_limit (generate fingerprint from IP + User-Agent)
     - Check if device exists via `KeyDeviceRepository::exists($keyIdHex32, $fingerprint)`
     - If new device, check device count < device_limit
     - Register new device via `KeyDeviceRepository::register($keyIdHex32, $fingerprint)`
  9. Load permissions from keys.permissions_json
  10. Determine roles based on key type
  11. Generate Key JWT via `JwtService::signKeyToken()` with key permissions and /api audience
  12. Generate refresh token
  13. Store refresh token via `RefreshTokenRepository::create([...])`
  14. Emit audit event via `AuditService::emit()` (actor: key, action: 'keys:exchange', subject: key)
  15. Return tokens array
- **Returns:** `array{access_token: string, refresh_token: string, expires_in: int}`
- **Throws:**
  - `InvalidArgumentException` if credentials are invalid (generic message)
  - `ForbiddenException` if use limit or device limit exceeded

#### `hashPassword(string $password): string` (private)
- **Purpose:** Hash password with Argon2id
- **Parameters:**
  - `$password`: Plaintext password
- **Process:**
  1. Build Argon2id options from environment variables (memory_cost, time_cost, parallelism)
  2. Hash password via `password_hash($password, PASSWORD_ARGON2ID, $options)`
  3. Return hash string
- **Returns:** `string` Argon2id hash

#### `verifyPassword(string $password, string $hash): bool` (private)
- **Purpose:** Verify password against hash
- **Parameters:**
  - `$password`: Plaintext password
  - `$hash`: Argon2id hash
- **Process:**
  1. Verify password via `password_verify($password, $hash)`
  2. Return boolean result
- **Returns:** `bool` True if password matches

#### `generateRefreshToken(): string` (private)
- **Purpose:** Generate a secure refresh token
- **Parameters:** None
- **Process:**
  1. Generate 48 bytes of random data (384 bits)
  2. Format as `rt_` + hex string
  3. Return token string
- **Returns:** `string` Refresh token (format: `rt_<random>`)

---

### 2.6 KeyService

**File:** `src/Services/KeyService.php`

**Purpose:** Handles key minting operations with permission validation

**Dependencies:**
- `KeyRepository` (constructor, private)
- `KeyPublicIdRepository` (constructor, private)
- `AuditService` (constructor, private)
- `PermissionCatalog` (static utility)
- `Ids` (static utility)
- `SensitiveDataSanitizer` (static utility)

**Methods:**

#### `__construct(KeyRepository $keyRepo, KeyPublicIdRepository $keyPublicIdRepo, AuditService $auditService)`
- **Purpose:** Initialize service with repositories
- **Parameters:** Key repository, KeyPublicId repository, and audit service
- **Returns:** void
- **Side Effects:** None

#### `mintPrimaryKey(string $ownerIdHex32, array $permissions, ?string $label = null): array`
- **Purpose:** Mint a Primary Author Key (Owner → Console)
- **CRITICAL SECURITY RULES:**
  - `key_secret` is returned ONLY ONCE in the response (never again)
  - `key_secret` MUST NEVER be logged (use SensitiveDataSanitizer before logging)
  - Client must store key_secret securely; it cannot be retrieved later
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
  - `$permissions`: Requested permissions
  - `$label`: Optional label
- **Process:**
  1. Validate permissions format via `PermissionCatalog::isValidFormat()` for each permission
  2. Generate key_id via `Ids::generateHex32Id()`
  3. Generate key_public_id (apub_...) via `'apub_' . bin2hex(random_bytes(8))`
  4. Generate key_secret via `'sec_' . bin2hex(random_bytes(24))`
  5. Hash key_secret with Argon2id via `password_hash($keySecret, PASSWORD_ARGON2ID)`
  6. Prepare key data array (includes owner_id for primary keys)
  7. **Transaction:**
     - Begin transaction via `$pdo->beginTransaction()`
     - Store key via `KeyRepository::create($keyData)`
     - Store key_public_id mapping via `KeyPublicIdRepository::create($publicIdRecordId, $keyIdHex32, $keyPublicId)`
     - Commit transaction
  8. Emit audit event via `AuditService::emit()` (actor: owner, action: 'keys:mint', subject: key) - sanitize metadata first
  9. Return key data array (includes `key_secret` - returned only once)
- **Returns:** `array{key_id: string, key_public_id: string, key_secret: string}`
- **Throws:**
  - `InvalidArgumentException` if validation fails

#### `mintSecondaryKey(string $parentKeyIdHex32, array $permissions, ?string $label = null): array`
- **Purpose:** Mint a Secondary Author Key (Author Key → Gateway)
- **CRITICAL SECURITY RULES:** Same as mintPrimaryKey
- **Parameters:**
  - `$parentKeyIdHex32`: Parent key ID (hex32)
  - `$permissions`: Requested permissions
  - `$label`: Optional label
- **Process:**
  1. Load parent key via `KeyRepository::findById($parentKeyIdHex32)`
  2. Verify parent key exists (throw NotFoundException if not)
  3. Verify parent key is active
  4. Verify parent key type (must be primary or secondary)
  5. Verify parent has `keys:issue` permission via `validateIssuerCanMint($parentPermissions)`
  6. Validate envelope rule via `validatePermissionEnvelope($permissions, $parentPermissions, 'secondary')`
  7. Generate new key (key_id, key_public_id, key_secret)
  8. Hash key_secret with Argon2id
  9. Propagate `initial_author_key_id` from parent
  10. Prepare key data array (with lineage fields)
  11. **Transaction:**
     - Begin transaction
     - Store key via `KeyRepository::create($keyData)`
     - Store key_public_id mapping via `KeyPublicIdRepository::create(...)`
     - Commit transaction
  12. Emit audit event via `AuditService::emit()` (actor: parent key, action: 'keys:mint', subject: key) - sanitize metadata
  13. Return key data array (includes `key_secret` - returned only once)
- **Returns:** `array{key_id: string, key_public_id: string, key_secret: string}`
- **Throws:**
  - `InvalidArgumentException` if validation fails
  - `NotFoundException` if parent key not found
  - `ForbiddenException` if parent cannot mint or envelope violation

#### `mintUseKey(string $parentKeyIdHex32, array $permissions, ?int $useCountLimit = null, ?int $deviceLimit = null, ?string $label = null): array`
- **Purpose:** Mint a Use Key (Author Key → Gateway)
- **CRITICAL SECURITY RULES:** Same as mintPrimaryKey
- **Parameters:**
  - `$parentKeyIdHex32`: Parent key ID (hex32)
  - `$permissions`: Requested permissions
  - `$useCountLimit`: Use count limit (optional)
  - `$deviceLimit`: Device limit (optional)
  - `$label`: Optional label
- **Process:**
  1. Load parent key via `KeyRepository::findById($parentKeyIdHex32)`
  2. Verify parent key exists (throw NotFoundException if not)
  3. Verify parent key is active
  4. Verify parent key type (must be primary or secondary)
  5. Verify parent has `keys:issue` permission via `validateIssuerCanMint($parentPermissions)`
  6. Validate envelope rule and Use Key restrictions via `validatePermissionEnvelope($permissions, $parentPermissions, 'use')`
  7. Generate new key (key_id, key_public_id, key_secret)
  8. Hash key_secret with Argon2id
  9. Propagate `initial_author_key_id` from parent
  10. Prepare key data array (with lineage fields and limits)
  11. **Transaction:**
     - Begin transaction
     - Store key via `KeyRepository::create($keyData)`
     - Store key_public_id mapping via `KeyPublicIdRepository::create(...)`
     - Commit transaction
  12. Emit audit event via `AuditService::emit()` (actor: parent key, action: 'keys:mint', subject: key) - sanitize metadata
  13. Return key data array (includes `key_secret` - returned only once, and limits)
- **Returns:** `array{key_id: string, key_public_id: string, key_secret: string, use_count: int|null, device_limit: int|null}`
- **Throws:**
  - `InvalidArgumentException` if validation fails
  - `NotFoundException` if parent key not found
  - `ForbiddenException` if parent cannot mint or envelope violation

#### `rotateKey(string $oldKeyIdHex32, ?string $actorTypeHex32 = null, string $actorType = 'owner'): array`
- **Purpose:** Rotate a key (replace while preserving lineage)
- **CRITICAL SECURITY RULES:** Same as mintPrimaryKey (new_key_secret returned only once)
- **Parameters:**
  - `$oldKeyIdHex32`: Old key ID to rotate (hex32)
  - `$actorTypeHex32`: Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
  - `$actorType`: Actor type ('owner' or 'key')
- **Process:**
  1. Load old key via `KeyRepository::findById($oldKeyIdHex32)`
  2. Verify key exists (throw NotFoundException if not)
  3. Verify key is not already retired (check `retired_at` is NULL)
  4. Generate new key with same properties (type, permissions, lineage fields)
  5. Generate new key_public_id and key_secret
  6. Hash new key_secret with Argon2id
  7. Preserve lineage fields from old key
  8. Set `rotated_from_id` to old key ID
  9. Reset use_count_current to 0 for new key
  10. **Transaction:**
     - Begin transaction
     - Store new key via `KeyRepository::create($keyData)`
     - Store new key_public_id mapping via `KeyPublicIdRepository::create(...)`
     - Mark old key as rotated via `KeyRepository::markRotated($oldKeyIdHex32, $newKeyIdHex32)`
     - Commit transaction
  11. Infer actor if not provided (from old key's initial_author_key_id)
  12. Emit audit event via `AuditService::emit()` (actor: owner/key, action: 'keys:rotate', subject: old key) - sanitize metadata
  13. Return rotation result (includes `new_key_secret` - returned only once)
- **Returns:** `array{old_key_id: string, new_key_id: string, new_key_public_id: string, new_key_secret: string}`
- **Throws:**
  - `NotFoundException` if old key not found
  - `InvalidArgumentException` if key is already retired

#### `activateKey(string $keyIdHex32, ?string $actorTypeHex32 = null, string $actorType = 'owner'): void`
- **Purpose:** Activate a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$actorTypeHex32`: Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
  - `$actorType`: Actor type ('owner' or 'key')
- **Process:**
  1. Load key via `KeyRepository::findById($keyIdHex32)`
  2. Verify key exists (throw NotFoundException if not)
  3. Update key active status via `KeyRepository::updateActive($keyIdHex32, true)`
  4. Infer actor if not provided (from key's initial_author_key_id)
  5. Emit audit event via `AuditService::emit()` (actor: owner/key, action: 'keys:activate', subject: key)
- **Returns:** void
- **Throws:**
  - `NotFoundException` if key not found

#### `deactivateKey(string $keyIdHex32, bool $cascade = false, ?string $actorTypeHex32 = null, string $actorType = 'owner'): int`
- **Purpose:** Deactivate a key with optional cascade
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$cascade`: If true, deactivate all descendant keys recursively
  - `$actorTypeHex32`: Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
  - `$actorType`: Actor type ('owner' or 'key')
- **Process:**
  1. Load key via `KeyRepository::findById($keyIdHex32)`
  2. Verify key exists (throw NotFoundException if not)
  3. Deactivate key (with cascade if requested) via `KeyRepository::deactivate($keyIdHex32, $cascade)`
  4. Get count of keys deactivated
  5. Infer actor if not provided (from key's initial_author_key_id)
  6. Emit audit event via `AuditService::emit()` (actor: owner/key, action: 'keys:deactivate', subject: key) with cascade metadata
  7. Return count of keys deactivated
- **Returns:** `int` Number of keys deactivated
- **Throws:**
  - `NotFoundException` if key not found

#### `validatePermissionEnvelope(array $childPermissions, array $parentPermissions, string $keyType): void` (public)
- **Purpose:** Validate permission envelope for child key
- **Enforces:**
  1. Child permissions ⊆ parent permissions (envelope rule)
  2. Use Key restrictions (no posts:create or keys:issue)
- **Parameters:**
  - `$childPermissions`: Child key permissions
  - `$parentPermissions`: Parent key permissions
  - `$keyType`: Key type ('primary', 'secondary', 'use')
- **Process:**
  1. Validate permission format for each child permission via `PermissionCatalog::isValidFormat()`
  2. For primary keys, no parent exists, so no envelope check (return early)
  3. Enforce envelope rule via `PermissionCatalog::validateEnvelope($childPermissions, $parentPermissions)`
  4. If envelope violation, throw InvalidArgumentException with missing permissions list
  5. Enforce Use Key restrictions if keyType is 'use' via `PermissionCatalog::validateUseKeyPermissions($childPermissions)`
  6. If Use Key has forbidden permissions, throw InvalidArgumentException with forbidden permissions list
- **Returns:** void
- **Throws:**
  - `InvalidArgumentException` if validation fails

#### `validateIssuerCanMint(array $issuerPermissions): void` (public)
- **Purpose:** Validate that issuer has keys:issue permission
- **Parameters:**
  - `$issuerPermissions`: Issuer's permissions
- **Process:**
  1. Check if `keys:issue` is in `$issuerPermissions` array
  2. If not present, throw InvalidArgumentException
- **Returns:** void
- **Throws:**
  - `InvalidArgumentException` if issuer lacks keys:issue

#### `listKeys(string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** List keys owned by an owner
- **Requirements:**
  - Owner must have `keys:read` permission
  - Returns all keys where initial_author_key_id matches owner's primary keys
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `keys:read` permission
  2. Find all primary keys owned by this owner via `KeyRepository::findByOwner($ownerIdHex32)`
  3. For each primary key, find all keys with matching initial_author_key_id via `KeyRepository::findByInitialAuthor($primaryKey['key_id'])`
  4. Merge all keys
  5. Remove duplicates (by key_id)
  6. Return unique keys array
- **Returns:** `array<array>` List of keys
- **Throws:**
  - `ForbiddenException` if owner lacks permission

#### `getKey(string $keyIdHex32, string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** Get a key by ID (with ownership verification)
- **Requirements:**
  - Owner must have `keys:read` permission
  - Key must belong to owner (via initial_author_key_id)
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `keys:read` permission
  2. Load key via `KeyRepository::findById($keyIdHex32)`
  3. If key not found, throw NotFoundException
  4. Verify key belongs to owner:
     - Find owner's primary keys via `KeyRepository::findByOwner($ownerIdHex32)`
     - Extract primary key IDs array
     - Check if key's `initial_author_key_id` matches any of owner's primary keys
  5. If not owned, throw NotFoundException (hide key existence)
  6. Return key data array
- **Returns:** `array` Key data
- **Throws:**
  - `NotFoundException` if key not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `getKeyLineage(string $keyIdHex32, string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** Get key lineage tree
- **Requirements:**
  - Owner must have `keys:read` permission
  - Key must belong to owner (via initial_author_key_id)
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `keys:read` permission
  2. Load key via `KeyRepository::findById($keyIdHex32)`
  3. If key not found, throw NotFoundException
  4. Verify key belongs to owner (same as getKey)
  5. Get lineage tree via `KeyRepository::getLineageTree($keyIdHex32)`
  6. Return lineage tree array
- **Returns:** `array<array>` Lineage tree (root to leaf)
- **Throws:**
  - `NotFoundException` if key not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

---

### 2.7 GroupService

**File:** `src/Services/GroupService.php`

**Purpose:** Handles group management operations for Console JSON surface

**Dependencies:**
- `GroupRepository` (constructor, private)
- `GroupMemberRepository` (constructor, private)
- `KeyRepository` (constructor, private)
- `AuditService` (constructor, private) - Note: Missing from constructor in code, but used in methods
- `Ids` (static utility)

**Methods:**

#### `__construct(GroupRepository $groupRepo, GroupMemberRepository $groupMemberRepo, KeyRepository $keyRepo)`
- **Purpose:** Initialize service with repositories
- **Parameters:** Group repository, GroupMember repository, and Key repository
- **Returns:** void
- **Side Effects:** None
- **Note:** AuditService is used but not injected - this may be a bug

#### `createGroup(string $ownerIdHex32, array $ownerPermissions, string $name): array`
- **Purpose:** Create a new group
- **Requirements:**
  - Owner must have `groups:manage` permission
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$name`: Group name
- **Process:**
  1. Validate name length (1-255 chars using `mb_strlen()`)
  2. Verify owner has `groups:manage` permission
  3. Generate group ID via `Ids::generateHex32Id()`
  4. Create group via `GroupRepository::create([...])`
  5. Load created group via `GroupRepository::findById($groupIdHex32)`
  6. Emit audit event via `AuditService::emit()` (actor: owner, action: 'groups:create', subject: group)
  7. Return group data array
- **Returns:** `array{group_id: string, owner_id: string, name: string, created_at: string}`
- **Throws:**
  - `ForbiddenException` if owner lacks permission
  - `InvalidArgumentException` if validation fails

#### `listGroups(string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** List groups owned by an owner
- **Requirements:**
  - Owner must have `groups:manage` permission
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `groups:manage` permission
  2. Find groups by owner via `GroupRepository::findByOwner($ownerIdHex32)`
  3. Return groups array
- **Returns:** `array<array>` List of groups
- **Throws:**
  - `ForbiddenException` if owner lacks permission

#### `getGroup(string $groupIdHex32, string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** Get group details
- **Requirements:**
  - Owner must have `groups:manage` permission
  - Group must be owned by the owner
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `groups:manage` permission
  2. Load group via `GroupRepository::findById($groupIdHex32)`
  3. If group not found, throw NotFoundException
  4. Verify group is owned by owner (check `owner_id` matches)
  5. If not owned, throw NotFoundException (hide existence)
  6. Return group data array
- **Returns:** `array` Group data
- **Throws:**
  - `NotFoundException` if group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `renameGroup(string $groupIdHex32, string $ownerIdHex32, array $ownerPermissions, string $name): array`
- **Purpose:** Rename a group
- **Requirements:**
  - Owner must have `groups:manage` permission
  - Group must be owned by the owner
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$name`: New name
- **Process:**
  1. Validate name length (1-255 chars)
  2. Verify owner has `groups:manage` permission
  3. Load group and verify ownership
  4. Update group name via `GroupRepository::updateName($groupIdHex32, $name)`
  5. Load updated group via `GroupRepository::findById($groupIdHex32)`
  6. Emit audit event via `AuditService::emit()` (actor: owner, action: 'groups:rename', subject: group)
  7. Return updated group data array
- **Returns:** `array{group_id: string, owner_id: string, name: string, updated_at: string}`
- **Throws:**
  - `NotFoundException` if group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission
  - `InvalidArgumentException` if validation fails

#### `deleteGroup(string $groupIdHex32, string $ownerIdHex32, array $ownerPermissions): void`
- **Purpose:** Delete a group
- **Requirements:**
  - Owner must have `groups:manage` permission
  - Group must be owned by the owner
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has `groups:manage` permission
  2. Load group and verify ownership
  3. Delete group via `GroupRepository::delete($groupIdHex32)` (memberships cascade deleted by DB foreign key)
  4. Emit audit event via `AuditService::emit()` (actor: owner, action: 'groups:delete', subject: group)
- **Returns:** void
- **Throws:**
  - `NotFoundException` if group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `addMember(string $groupIdHex32, string $ownerIdHex32, array $ownerPermissions, string $keyIdHex32): array`
- **Purpose:** Add a key to a group
- **Requirements:**
  - Owner must have `groups:manage` permission
  - Group must be owned by the owner
  - Key must be owned by the owner (via initial_author_key_id)
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Verify owner has `groups:manage` permission
  2. Load group and verify ownership
  3. Load key and verify ownership (via initial_author_key_id matching owner's primary keys)
  4. Check if key is already a member via `GroupMemberRepository::isMember($groupIdHex32, $keyIdHex32)`
  5. If already member, return success
  6. Add key to group via `GroupMemberRepository::add($groupIdHex32, $keyIdHex32)`
  7. Emit audit event via `AuditService::emit()` (actor: owner, action: 'groups:member:add', subject: group)
  8. Return membership data array
- **Returns:** `array{group_id: string, key_id: string}`
- **Throws:**
  - `NotFoundException` if group or key not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `removeMember(string $groupIdHex32, string $ownerIdHex32, array $ownerPermissions, string $keyIdHex32): void`
- **Purpose:** Remove a key from a group
- **Requirements:**
  - Owner must have `groups:manage` permission
  - Group must be owned by the owner
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Verify owner has `groups:manage` permission
  2. Load group and verify ownership
  3. Remove key from group via `GroupMemberRepository::remove($groupIdHex32, $keyIdHex32)`
  4. Emit audit event via `AuditService::emit()` (actor: owner, action: 'groups:member:remove', subject: group)
- **Returns:** void
- **Throws:**
  - `NotFoundException` if group not found or not owned by owner
  - `ForbiddenException` if owner lacks permission

#### `listGroupsForKey(string $keyIdHex32, array $keyPermissions): array`
- **Purpose:** List groups (Gateway read-only)
- **Requirements:**
  - Key must have `groups:read` permission
  - Returns groups owned by the key's owner (via initial_author_key_id)
- **Parameters:**
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
- **Process:**
  1. Verify key has `groups:read` permission
  2. Load key via `KeyRepository::findById($keyIdHex32)`
  3. Get owner ID from key's initial_author_key_id (which is the owner's primary key)
  4. Get the primary key to access its owner_id
  5. Find groups by owner via `GroupRepository::findByOwner($ownerIdHex32)`
  6. Return groups array
- **Returns:** `array<array>` List of groups
- **Throws:**
  - `NotFoundException` if key not found
  - `ForbiddenException` if key lacks permission

#### `getGroupForKey(string $groupIdHex32, string $keyIdHex32, array $keyPermissions): array`
- **Purpose:** Get group details (Gateway read-only)
- **Requirements:**
  - Key must have `groups:read` permission
  - Group must be owned by the key's owner (via initial_author_key_id)
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
- **Process:**
  1. Verify key has `groups:read` permission
  2. Load key and get owner ID (via initial_author_key_id → primary key → owner_id)
  3. Load group via `GroupRepository::findById($groupIdHex32)`
  4. Verify group is owned by key's owner
  5. Return group data array
- **Returns:** `array` Group data
- **Throws:**
  - `NotFoundException` if group or key not found or group not owned by key's owner
  - `ForbiddenException` if key lacks permission

#### `listGroupMembersForKey(string $groupIdHex32, string $keyIdHex32, array $keyPermissions): array`
- **Purpose:** List group members (Gateway read-only)
- **Requirements:**
  - Key must have `groups:read` permission
  - Group must be owned by the key's owner (via initial_author_key_id)
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
- **Process:**
  1. Verify key has `groups:read` permission
  2. Load key and get owner ID (via initial_author_key_id → primary key → owner_id)
  3. Load group and verify ownership
  4. Find members of the group via `GroupMemberRepository::findMembers($groupIdHex32)`
  5. Return members array (list of key IDs)
- **Returns:** `array<string>` List of key IDs (hex32)
- **Throws:**
  - `NotFoundException` if group or key not found or group not owned by key's owner
  - `ForbiddenException` if key lacks permission

---

### 2.8 AuditService

**File:** `src/Services/AuditService.php`

**Purpose:** Provides a clean interface for emitting audit events across the system

**Dependencies:**
- `AuditEventRepository` (constructor, private)
- `Ids` (static utility)

**Methods:**

#### `__construct(AuditEventRepository $auditEventRepository)`
- **Purpose:** Initialize service with repository
- **Parameters:**
  - `$auditEventRepository`: Audit event repository
- **Returns:** void
- **Side Effects:** None

#### `emit(string $actorType, string $actorIdHex32, string $action, ?string $subjectType = null, ?string $subjectIdHex32 = null, ?array $metadata = null, ?string $ip = null, ?string $userAgent = null): void`
- **Purpose:** Emit an audit event
- **Parameters:**
  - `$actorType`: Actor type ('owner' or 'key')
  - `$actorIdHex32`: Actor ID (hex32)
  - `$action`: Action name (e.g., 'keys:mint', 'posts:create')
  - `$subjectType`: Subject type (e.g., 'key', 'post', 'group') - optional
  - `$subjectIdHex32`: Subject ID (hex32) - optional
  - `$metadata`: Optional metadata (will be JSON encoded) - optional
  - `$ip`: Optional IP address - optional
  - `$userAgent`: Optional user agent - optional
- **Process:**
  1. Generate event ID via `Ids::generateHex32Id()`
  2. Prepare event data array
  3. Create audit event via `AuditEventRepository::create($eventData)`
- **Returns:** void
- **Side Effects:** Creates audit event record in database

#### `extractRequestMetadata(?ServerRequestInterface $request): array` (static)
- **Purpose:** Extract IP and User-Agent from request
- **Parameters:**
  - `$request`: PSR-7 request (optional)
- **Process:**
  1. If request is null, return null values
  2. Extract IP from `$request->getServerParams()['REMOTE_ADDR']`
  3. Extract User-Agent from `$request->getHeaderLine('User-Agent')`
  4. Return array with ip and user_agent
- **Returns:** `array{ip: string|null, user_agent: string|null}`

---

### 2.9 LoggingService

**File:** `src/Services/LoggingService.php`

**Purpose:** Provides structured JSON logging with Monolog, channel separation, and automatic secret sanitization

**Dependencies:**
- `SensitiveDataSanitizer` (static utility)
- `Monolog\Logger` (class)
- `Monolog\Handler\StreamHandler` (class)
- `Monolog\Formatter\JsonFormatter` (class)

**Methods:**

#### `createLogger(string $channel, string $logPath, string $level = 'INFO'): LoggerInterface` (static)
- **Purpose:** Create a logger for a specific channel
- **Parameters:**
  - `$channel`: Channel name (api, auth, security, db, guzzle.http)
  - `$logPath`: Base log directory path
  - `$level`: Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL) - default: 'INFO'
- **Process:**
  1. Create Monolog Logger instance with channel name
  2. Create log file path (channel-specific: `{logPath}/{channel}.log`)
  3. Create StreamHandler with log file and log level
  4. Create JsonFormatter
  5. Disable stack traces in formatter (never log stack traces in production)
  6. Set formatter on handler
  7. Push handler to logger
  8. Return logger instance
- **Returns:** `LoggerInterface` Configured logger instance
- **Side Effects:** Creates log file if it doesn't exist

#### `sanitizeContext(array $context): array` (static)
- **Purpose:** Sanitize context data before logging
- **Parameters:**
  - `$context`: Log context data
- **Process:**
  1. Sanitize context via `SensitiveDataSanitizer::sanitize($context)`
  2. Return sanitized context
- **Returns:** `array<string, mixed>` Sanitized context

#### `log(LoggerInterface $logger, string $level, string $message, array $context = []): void` (static)
- **Purpose:** Log with automatic secret sanitization
- **Parameters:**
  - `$logger`: Logger instance
  - `$level`: Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
  - `$message`: Log message
  - `$context`: Log context (will be sanitized) - default: []
- **Process:**
  1. Sanitize context via `sanitizeContext($context)`
  2. Call appropriate logger method based on level (debug, info, warning, error, critical)
  3. Default to info if level is unknown
- **Returns:** void
- **Side Effects:** Writes log entry to file

---

### 2.10 KeychainService

**File:** `src/Services/KeychainService.php`

**Purpose:** Handles keychain management operations for Console and Gateway JSON surfaces

**Dependencies:**
- `KeychainRepository` (constructor injection)
- `KeychainMemberRepository` (constructor injection)
- `KeyRepository` (constructor injection)
- `AuditService` (constructor injection)
- `Ids` (static utility)

**Methods:**

#### `__construct(KeychainRepository $keychainRepo, KeychainMemberRepository $keychainMemberRepo, KeyRepository $keyRepo, AuditService $auditService)`
- **Purpose:** Initialize service with repositories
- **Parameters:**
  - `$keychainRepo`: Keychain repository
  - `$keychainMemberRepo`: Keychain member repository
  - `$keyRepo`: Key repository
  - `$auditService`: Audit service
- **Returns:** void
- **Side Effects:** Stores repository references

#### `createKeychain(string $ownerIdHex32, array $ownerPermissions, string $name): array`
- **Purpose:** Create a new keychain
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$name`: Keychain name (1-255 chars)
- **Process:**
  1. Validate name length (1-255 chars)
  2. Verify owner has keychains:manage permission
  3. Generate keychain ID via `Ids::generateHex32Id()`
  4. Create keychain via `KeychainRepository::create()`
  5. Load and return created keychain
  6. Emit audit event via `AuditService::emit()`
- **Returns:** `array{keychain_id: string, owner_id: string, name: string, created_at: string}`
- **Exception Handling:**
  - `ForbiddenException` if owner lacks permission
  - `InvalidArgumentException` if validation fails
- **Side Effects:** Creates keychain record, emits audit event
- **Throws:** `ForbiddenException`, `InvalidArgumentException`

#### `listKeychains(string $ownerIdHex32, array $ownerPermissions): array`
- **Purpose:** List keychains owned by an owner
- **Parameters:**
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
- **Process:**
  1. Verify owner has keychains:manage permission
  2. Find keychains by owner via `KeychainRepository::findByOwner()`
  3. Return list
- **Returns:** `array<array>` List of keychains
- **Exception Handling:** `ForbiddenException` if owner lacks permission
- **Throws:** `ForbiddenException`

#### `addMember(string $keychainIdHex32, string $ownerIdHex32, array $ownerPermissions, string $keyIdHex32): array`
- **Purpose:** Add a key to a keychain
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Verify owner has keychains:manage permission
  2. Load keychain via `KeychainRepository::findById()` and verify ownership
  3. Load key via `KeyRepository::findById()` and verify ownership (via initial_author_key_id)
  4. Add key to keychain via `KeychainMemberRepository::add()` (idempotent)
  5. Emit audit event
- **Returns:** `array{keychain_id: string, key_id: string}`
- **Exception Handling:**
  - `NotFoundException` if keychain/key not found or not owned
  - `ForbiddenException` if owner lacks permission
- **Side Effects:** Creates keychain_members record, emits audit event
- **Throws:** `NotFoundException`, `ForbiddenException`

#### `removeMember(string $keychainIdHex32, string $ownerIdHex32, array $ownerPermissions, string $keyIdHex32): void`
- **Purpose:** Remove a key from a keychain
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$ownerIdHex32`: Owner ID from JWT (hex32)
  - `$ownerPermissions`: Owner permissions from JWT
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Verify owner has keychains:manage permission
  2. Load keychain and verify ownership
  3. Remove key from keychain via `KeychainMemberRepository::remove()`
  4. Emit audit event
- **Returns:** void
- **Exception Handling:**
  - `NotFoundException` if keychain not found or not owned
  - `ForbiddenException` if owner lacks permission
- **Side Effects:** Deletes keychain_members record, emits audit event
- **Throws:** `NotFoundException`, `ForbiddenException`

#### `createExternalKeychain(string $keyIdHex32, array $keyPermissions, string $name): array`
- **Purpose:** Create an external keychain (Gateway)
- **Parameters:**
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
  - `$name`: Keychain name (1-255 chars)
- **Process:**
  1. Validate name length
  2. Verify key has keychains:manage permission
  3. Verify key exists via `KeyRepository::findById()`
  4. Generate keychain ID
  5. Create external keychain (owner_id = NULL) via `KeychainRepository::create()`
  6. Load and return created keychain
  7. Emit audit event
- **Returns:** `array{keychain_id: string, name: string, created_at: string}`
- **Exception Handling:**
  - `NotFoundException` if key not found
  - `ForbiddenException` if key lacks permission
  - `InvalidArgumentException` if validation fails
- **Side Effects:** Creates keychain record with NULL owner_id, emits audit event
- **Throws:** `NotFoundException`, `ForbiddenException`, `InvalidArgumentException`

#### `addMemberToExternalKeychain(string $keychainIdHex32, string $keyIdHex32, array $keyPermissions, string $memberKeyIdHex32): array`
- **Purpose:** Add a key to an external keychain (Gateway)
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
  - `$memberKeyIdHex32`: Member key ID to add (hex32)
- **Process:**
  1. Verify key has keychains:manage permission
  2. Verify key exists
  3. Load keychain and verify it's external (owner_id IS NULL)
  4. Verify member key exists
  5. Add key to keychain (idempotent)
  6. Emit audit event
- **Returns:** `array{keychain_id: string, key_id: string}`
- **Exception Handling:**
  - `NotFoundException` if keychain/key not found or keychain is not external
  - `ForbiddenException` if key lacks permission
- **Side Effects:** Creates keychain_members record, emits audit event
- **Throws:** `NotFoundException`, `ForbiddenException`

#### `removeMemberFromExternalKeychain(string $keychainIdHex32, string $keyIdHex32, array $keyPermissions, string $memberKeyIdHex32): void`
- **Purpose:** Remove a key from an external keychain (Gateway)
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$keyIdHex32`: Key ID from JWT (hex32)
  - `$keyPermissions`: Key permissions from JWT
  - `$memberKeyIdHex32`: Member key ID to remove (hex32)
- **Process:**
  1. Verify key has keychains:manage permission
  2. Verify key exists
  3. Load keychain and verify it's external
  4. Remove key from keychain
  5. Emit audit event
- **Returns:** void
- **Exception Handling:**
  - `NotFoundException` if keychain/key not found or keychain is not external
  - `ForbiddenException` if key lacks permission
- **Side Effects:** Deletes keychain_members record, emits audit event
- **Throws:** `NotFoundException`, `ForbiddenException`

---

### 3.1 BaseRepository

**File:** `src/Repositories/BaseRepository.php`

**Purpose:** Base class providing PDO instance and ID conversion helpers

**Dependencies:**
- `\PDO` (constructor injection)
- `App\Utilities\Ids` (static utility)

**Methods:**

#### `__construct(\PDO $pdo)`
- **Purpose:** Initialize repository with PDO connection
- **Parameters:**
  - `$pdo`: Database connection
- **Returns:** void
- **Side Effects:** None

#### `hex32ToBinary(string $hex32): string` (protected)
- **Purpose:** Convert hex32 string to binary ID (BINARY(16))
- **Parameters:**
  - `$hex32`: 32-character lowercase hex string
- **Returns:** Binary ID (16 bytes)
- **Uses:** `Ids::hex32ToBinary()`
- **Throws:** `InvalidArgumentException` if hex32 is invalid

#### `binaryToHex32(string $binary): string` (protected)
- **Purpose:** Convert binary ID (BINARY(16)) to hex32 string
- **Parameters:**
  - `$binary`: Binary ID (16 bytes)
- **Returns:** hex32 string (32-character lowercase hex)
- **Uses:** `Ids::binaryToHex32()`
- **Throws:** `InvalidArgumentException` if binary is invalid

#### `generateBinaryId(): string` (protected)
- **Purpose:** Generate a new random binary ID (BINARY(16))
- **Parameters:** None
- **Returns:** Binary ID (16 bytes)
- **Uses:** `Ids::generateBinaryId()`

#### `generateHex32Id(): string` (protected)
- **Purpose:** Generate a new random hex32 ID
- **Parameters:** None
- **Returns:** hex32 string (32-character lowercase hex)
- **Uses:** `Ids::generateHex32Id()`

#### `getPdo(): \PDO` (public)
- **Purpose:** Get PDO instance for transaction management
- **Parameters:** None
- **Returns:** `\PDO` Database connection
- **Note:** Services use this to orchestrate transactions across multiple repositories

---

### 3.2 PostRepository

**File:** `src/Repositories/PostRepository.php`

**Purpose:** Data access for posts table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new post
- **Parameters:**
  - `$data`: Post data array with keys: `id` (hex32), `author_key_id` (hex32), `initial_author_key_id` (hex32), `title` (string|null), `content` (string)
- **SQL:** `INSERT INTO posts (id, author_key_id, initial_author_key_id, title, content) VALUES (?, ?, ?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `author_key_id` → hex32ToBinary
  - `initial_author_key_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates post record in database

#### `findById(string $postIdHex32): ?array`
- **Purpose:** Find post by ID
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
- **SQL:** `SELECT * FROM posts WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array|null` Post data with hex32 IDs or null if not found
- **Return Format:**
  ```php
  [
      'post_id' => string (hex32),
      'author_key_id' => string (hex32),
      'initial_author_key_id' => string (hex32),
      'title' => string|null,
      'content' => string,
      'created_at' => string,
      'updated_at' => string,
  ]
  ```

#### `findByAuthor(string $authorKeyIdHex32, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** Find posts by author
- **Parameters:**
  - `$authorKeyIdHex32`: Author key ID (hex32)
  - `$limit`: Limit (default: 20)
  - `$beforeIdHex32`: Cursor (post ID before this) - optional
- **SQL:** `SELECT * FROM posts WHERE author_key_id = ? [AND created_at < (SELECT created_at FROM posts WHERE id = ?)] ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of posts (same format as findById)

#### `findByOwner(array $primaryKeyIdsHex32, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** Find posts by owner (via initial_author_key_id)
- **Parameters:**
  - `$primaryKeyIdsHex32`: Array of owner's primary key IDs (hex32)
  - `$limit`: Limit (default: 20)
  - `$beforeIdHex32`: Cursor (post ID before this) - optional
- **SQL:** `SELECT * FROM posts WHERE initial_author_key_id IN (?, ?, ...) [AND created_at < (SELECT created_at FROM posts WHERE id = ?)] ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: array of hex32 → array of binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of posts (same format as findById)
- **Note:** Returns empty array if `$primaryKeyIdsHex32` is empty

#### `findVisiblePostsForUseKey(string $keyIdHex32, array $groupIdsHex32, int $limit = 20, ?string $beforeIdHex32 = null, ?string $sinceIdHex32 = null): array`
- **Purpose:** Find visible posts for a Use Key (feed query)
- **Parameters:**
  - `$keyIdHex32`: Use Key ID (hex32)
  - `$groupIdsHex32`: List of group IDs the key belongs to (hex32)
  - `$limit`: Limit (default 20, max 100)
  - `$beforeIdHex32`: Cursor for older posts (post ID) - optional
  - `$sinceIdHex32`: Cursor for newer posts (post ID) - optional
- **SQL:** Complex query with JOINs:
  ```sql
  SELECT DISTINCT p.*
  FROM posts p
  INNER JOIN post_access pa ON p.id = pa.post_id
  LEFT JOIN group_members gm ON pa.target_type = 'group' AND pa.target_id = gm.group_id
  WHERE (
      (pa.target_type = 'key' AND pa.target_id = ? AND (pa.permission_mask & 0x01) > 0)
      OR
      (pa.target_type = 'group' AND gm.key_id = ? AND (pa.permission_mask & 0x01) > 0)
  )
  [AND p.created_at < (SELECT created_at FROM posts WHERE id = ?)]
  [OR p.created_at > (SELECT created_at FROM posts WHERE id = ?)]
  ORDER BY p.created_at DESC LIMIT ?
  ```
- **ID Conversions:**
  - Input: hex32 → binary for key_id and group_ids
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of posts (same format as findById)
- **Note:** Only includes posts with VIEW mask (0x01)

#### `findByInitialAuthor(string $initialAuthorKeyIdHex32, int $limit = 20, ?string $beforeIdHex32 = null, ?string $sinceIdHex32 = null): array`
- **Purpose:** Find posts by initial author (for Author feed)
- **Parameters:**
  - `$initialAuthorKeyIdHex32`: Initial author key ID (hex32)
  - `$limit`: Limit (default 20, max 100)
  - `$beforeIdHex32`: Cursor for older posts (post ID) - optional
  - `$sinceIdHex32`: Cursor for newer posts (post ID) - optional
- **SQL:** `SELECT * FROM posts WHERE initial_author_key_id = ? [AND created_at < (SELECT created_at FROM posts WHERE id = ?)] [OR created_at > (SELECT created_at FROM posts WHERE id = ?)] ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of posts (same format as findById)

#### `findVisiblePostsForGroups(array $groupIdsHex32, int $limit = 20, ?string $beforeIdHex32 = null, ?string $sinceIdHex32 = null): array`
- **Purpose:** Find posts visible via group memberships (for Author feed enhancement)
- **Parameters:**
  - `$groupIdsHex32`: List of group IDs (hex32)
  - `$limit`: Limit (default 20, max 100)
  - `$beforeIdHex32`: Cursor for older posts (post ID) - optional
  - `$sinceIdHex32`: Cursor for newer posts (post ID) - optional
- **SQL:**
  ```sql
  SELECT DISTINCT p.*
  FROM posts p
  INNER JOIN post_access pa ON p.id = pa.post_id
  WHERE pa.target_type = 'group'
  AND pa.target_id IN (?, ?, ...)
  AND (pa.permission_mask & 0x01) > 0
  [AND p.created_at < (SELECT created_at FROM posts WHERE id = ?)]
  [OR p.created_at > (SELECT created_at FROM posts WHERE id = ?)]
  ORDER BY p.created_at DESC LIMIT ?
  ```
- **ID Conversions:**
  - Input: array of hex32 → array of binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of posts (same format as findById)
- **Note:** Returns empty array if `$groupIdsHex32` is empty

---

### 3.3 PostAccessRepository

**File:** `src/Repositories/PostAccessRepository.php`

**Purpose:** Data access for post_access table with specialized helpers for access resolution

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `upsert(array $data): void`
- **Purpose:** Create or update access grant
- **Parameters:**
  - `$data`: Access grant data array with keys: `id` (hex32), `post_id` (hex32), `target_type` (string), `target_id` (hex32), `permission_mask` (int)
- **SQL:** `INSERT INTO post_access (id, post_id, target_type, target_id, permission_mask) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE permission_mask = ?`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `post_id` → hex32ToBinary
  - `target_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates or updates access grant record

#### `revoke(string $postIdHex32, string $targetType, string $targetIdHex32): void`
- **Purpose:** Revoke access grant
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$targetType`: Target type ('key' or 'group')
  - `$targetIdHex32`: Target ID (hex32)
- **SQL:** `DELETE FROM post_access WHERE post_id = ? AND target_type = ? AND target_id = ?`
- **ID Conversions:**
  - `post_id` → hex32ToBinary
  - `target_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Deletes access grant record

#### `findByPost(string $postIdHex32): array`
- **Purpose:** Find access grants for a post
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
- **SQL:** `SELECT * FROM post_access WHERE post_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of access grants
- **Return Format:**
  ```php
  [
      [
          'access_id' => string (hex32),
          'post_id' => string (hex32),
          'target_type' => string ('key' or 'group'),
          'target_id' => string (hex32),
          'permission_mask' => int,
          'created_at' => string,
      ],
      ...
  ]
  ```

#### `findDirectAccess(string $postIdHex32, string $keyIdHex32): ?int` (specialized helper)
- **Purpose:** Check if key has access to post (direct grant)
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT permission_mask FROM post_access WHERE post_id = ? AND target_type = 'key' AND target_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
- **Returns:** `int|null` Permission mask or null if no access

#### `resolveAccessMask(string $postIdHex32, string $keyIdHex32, array $groupIdsHex32 = []): int` (specialized helper)
- **Purpose:** Resolve access mask for a key on a post (including group memberships)
- **Checks:**
  1. Direct key grant
  2. Group grants (if key is member of groups)
- **Returns the highest permission mask found (bitwise OR)**
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
  - `$groupIdsHex32`: List of group IDs the key belongs to (hex32) - default: []
- **Process:**
  1. Check direct key grant via `findDirectAccess($postIdHex32, $keyIdHex32)`
  2. If direct access exists, OR mask with result
  3. If group IDs provided, query group grants:
     - SQL: `SELECT permission_mask FROM post_access WHERE post_id = ? AND target_type = 'group' AND target_id IN (?, ?, ...)`
  4. OR mask with each group grant found
  5. Return combined mask (0 if no access)
- **Returns:** `int` Permission mask (0 if no access)

---

### 3.4 CommentRepository

**File:** `src/Repositories/CommentRepository.php`

**Purpose:** Data access for comments table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new comment
- **Parameters:**
  - `$data`: Comment data array with keys: `id` (hex32), `post_id` (hex32), `created_by_key_id` (hex32), `body` (string)
- **SQL:** `INSERT INTO comments (id, post_id, created_by_key_id, body) VALUES (?, ?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `post_id` → hex32ToBinary
  - `created_by_key_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates comment record in database

#### `findById(string $commentIdHex32): ?array`
- **Purpose:** Find comment by ID
- **Parameters:**
  - `$commentIdHex32`: Comment ID (hex32)
- **SQL:** `SELECT * FROM comments WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array|null` Comment data with hex32 IDs or null if not found
- **Return Format:**
  ```php
  [
      'comment_id' => string (hex32),
      'post_id' => string (hex32),
      'created_by_key_id' => string (hex32),
      'body' => string,
      'created_at' => string,
  ]
  ```

#### `findByPost(string $postIdHex32, int $limit = 20, ?string $beforeIdHex32 = null): array`
- **Purpose:** Find comments by post
- **Parameters:**
  - `$postIdHex32`: Post ID (hex32)
  - `$limit`: Limit (default: 20)
  - `$beforeIdHex32`: Cursor (comment ID before this) - optional
- **SQL:** `SELECT * FROM comments WHERE post_id = ? [AND created_at < (SELECT created_at FROM comments WHERE id = ?)] ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of comments (same format as findById)

---

### 3.5 KeyRepository

**File:** `src/Repositories/KeyRepository.php`

**Purpose:** Data access for keys table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new key
- **Parameters:**
  - `$data`: Key data array with all key fields (see KeyService for structure)
- **SQL:** `INSERT INTO keys (id, owner_id, type, key_secret_hash, permissions_json, active, issued_by_key_id, parent_key_id, initial_author_key_id, rotated_from_id, rotated_to_id, retired_at, use_count_limit, use_count_current, device_limit, label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
- **ID Conversions:**
  - All ID fields → hex32ToBinary (id, owner_id, issued_by_key_id, parent_key_id, initial_author_key_id, rotated_from_id, rotated_to_id)
- **JSON Encoding:**
  - `permissions` array → `json_encode($data['permissions'], JSON_THROW_ON_ERROR)`
- **Returns:** void
- **Side Effects:** Creates key record in database

#### `findById(string $keyIdHex32): ?array`
- **Purpose:** Find key by ID
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT * FROM keys WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **JSON Decoding:**
  - `permissions_json` → `json_decode($row['permissions_json'], true)`
- **Returns:** `array|null` Key data with hex32 IDs or null if not found
- **Return Format:**
  ```php
  [
      'key_id' => string (hex32),
      'type' => string ('primary', 'secondary', 'use'),
      'key_secret_hash' => string,
      'permissions' => array<string>,
      'active' => bool,
      'use_count_limit' => int|null,
      'use_count_current' => int,
      'device_limit' => int|null,
      'label' => string|null,
      'created_at' => string,
      'updated_at' => string,
      'owner_id' => string|null (hex32, only for primary keys),
      'issued_by_key_id' => string|null (hex32),
      'parent_key_id' => string|null (hex32),
      'initial_author_key_id' => string (hex32),
      'rotated_from_id' => string|null (hex32),
      'rotated_to_id' => string|null (hex32),
      'retired_at' => string|null,
  ]
  ```

#### `findByOwner(string $ownerIdHex32): array`
- **Purpose:** Find keys by owner (primary keys only)
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
- **SQL:** `SELECT * FROM keys WHERE owner_id = ? AND type = 'primary'`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **Returns:** `array<array>` List of primary keys owned by this owner (same format as findById)

#### `findByInitialAuthor(string $initialAuthorKeyIdHex32): array`
- **Purpose:** Find keys by initial author
- **Parameters:**
  - `$initialAuthorKeyIdHex32`: Initial author key ID (hex32)
- **SQL:** `SELECT * FROM keys WHERE initial_author_key_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **Returns:** `array<array>` List of keys (same format as findById)

#### `updateActive(string $keyIdHex32, bool $active): void`
- **Purpose:** Update key active status
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$active`: Active status (bool)
- **SQL:** `UPDATE keys SET active = ? WHERE id = ?`
- **ID Conversions:**
  - `keyIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Updates key active status

#### `retire(string $keyIdHex32): void`
- **Purpose:** Retire a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `UPDATE keys SET retired_at = NOW() WHERE id = ?`
- **ID Conversions:**
  - `keyIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Sets retired_at timestamp

#### `markRotated(string $keyIdHex32, string $rotatedToIdHex32): void`
- **Purpose:** Update rotated_to_id and retired_at for a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$rotatedToIdHex32`: New key ID that replaced this one (hex32)
- **SQL:** `UPDATE keys SET rotated_to_id = ?, retired_at = NOW(), active = 0 WHERE id = ?`
- **ID Conversions:**
  - Both IDs → hex32ToBinary
- **Returns:** void
- **Side Effects:** Sets rotated_to_id, retired_at, and active=0

#### `deactivate(string $keyIdHex32, bool $cascade = false): int`
- **Purpose:** Deactivate a key and optionally all its descendants (cascade)
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$cascade`: If true, deactivate all descendants recursively (default: false)
- **Process:**
  - If cascade:
    1. Get all descendants via `getDescendants($keyIdHex32)`
    2. Include the key itself in the list
    3. SQL: `UPDATE keys SET active = 0 WHERE id IN (?, ?, ...)`
    4. Return count of keys deactivated
  - If not cascade:
    1. SQL: `UPDATE keys SET active = 0 WHERE id = ?`
    2. Return 1
- **ID Conversions:**
  - Input: hex32 → binary
- **Returns:** `int` Number of keys deactivated

#### `incrementUseCount(string $keyIdHex32): void`
- **Purpose:** Update use count
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `UPDATE keys SET use_count_current = use_count_current + 1 WHERE id = ?`
- **ID Conversions:**
  - `keyIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Increments use_count_current

#### `getLineageTree(string $keyIdHex32): array` (specialized helper)
- **Purpose:** Get lineage tree for a key
- **Returns all keys in the lineage tree (parent chain up to root)**
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Start with current key
  2. Walk up the parent chain:
     - Load key via `findById($currentKeyId)`
     - Add to tree array
     - Move to parent (`parent_key_id`)
     - Repeat until no parent
  3. Reverse array to get root-to-leaf order
- **Returns:** `array<array>` Lineage tree (root to leaf) - same format as findById

#### `getDescendants(string $keyIdHex32): array` (specialized helper)
- **Purpose:** Get all descendant keys (children and their children)
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **Process:**
  1. Call `collectDescendants($keyIdHex32, $descendants)` recursively
  2. Return descendants array
- **Returns:** `array<array>` List of descendant keys (same format as findById)

#### `collectDescendants(string $parentKeyIdHex32, array &$descendants): void` (private, recursive)
- **Purpose:** Recursively collect descendant keys
- **Parameters:**
  - `$parentKeyIdHex32`: Parent key ID (hex32)
  - `&$descendants`: Output array (by reference)
- **SQL:** `SELECT * FROM keys WHERE parent_key_id = ?`
- **Process:**
  1. Query for direct children
  2. For each child, add to descendants array
  3. Recursively call `collectDescendants()` for each child
- **Returns:** void
- **Side Effects:** Populates `$descendants` array

#### `mapRowToArray(array $row): array` (private)
- **Purpose:** Map database row to array with hex32 IDs
- **Parameters:**
  - `$row`: Database row
- **Process:**
  1. Convert all binary IDs to hex32
  2. Decode permissions_json to array
  3. Cast active to bool
  4. Include optional fields if present (owner_id, issued_by_key_id, etc.)
- **Returns:** `array<string, mixed>` Mapped array with hex32 IDs

---

### 3.6 OwnerRepository

**File:** `src/Repositories/OwnerRepository.php`

**Purpose:** Data access for owners table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(string $ownerIdHex32, string $email, string $passwordHash): void`
- **Purpose:** Create a new owner
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
  - `$email`: Owner email
  - `$passwordHash`: Argon2id password hash
- **SQL:** `INSERT INTO owners (id, email, password_hash) VALUES (?, ?, ?)`
- **ID Conversions:**
  - `ownerIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates owner record in database

#### `findById(string $ownerIdHex32): ?array`
- **Purpose:** Find owner by ID
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
- **SQL:** `SELECT * FROM owners WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for id field
- **Returns:** `array|null` Owner data with hex32 ID or null if not found
- **Return Format:**
  ```php
  [
      'owner_id' => string (hex32),
      'email' => string,
      'password_hash' => string,
      'created_at' => string,
      'updated_at' => string,
  ]
  ```

#### `findByEmail(string $email): ?array`
- **Purpose:** Find owner by email
- **Parameters:**
  - `$email`: Owner email
- **SQL:** `SELECT * FROM owners WHERE email = ?`
- **ID Conversions:**
  - Output: binary → hex32 for id field
- **Returns:** `array|null` Owner data with hex32 ID or null if not found (same format as findById)

---

### 3.7 RefreshTokenRepository

**File:** `src/Repositories/RefreshTokenRepository.php`

**Purpose:** Data access for refresh_tokens table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new refresh token
- **Parameters:**
  - `$data`: Token data array with keys: `id` (hex32), `subject_type` (string), `subject_id` (hex32), `token_hash` (string, Argon2id), `lookup_hash` (string, SHA-256), `expires_at` (string), `replaced_by_id` (hex32, optional), `ip` (string, optional), `user_agent` (string, optional)
- **SQL:** `INSERT INTO refresh_tokens (id, subject_type, subject_id, token_hash, lookup_hash, expires_at, replaced_by_id, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `subject_id` → hex32ToBinary
  - `replaced_by_id` → hex32ToBinary (if present)
- **Returns:** void
- **Side Effects:** Creates refresh token record in database

#### `findByLookupHash(string $lookupHash): ?array`
- **Purpose:** Find token by lookup hash (SHA-256)
- **Parameters:**
  - `$lookupHash`: SHA-256 hash of the refresh token
- **SQL:** `SELECT * FROM refresh_tokens WHERE lookup_hash = ?`
- **ID Conversions:**
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **Returns:** `array|null` Token data with hex32 IDs or null if not found
- **Return Format:**
  ```php
  [
      'token_id' => string (hex32),
      'subject_type' => string ('owner' or 'key'),
      'subject_id' => string (hex32),
      'token_hash' => string (Argon2id hash),
      'issued_at' => string,
      'expires_at' => string,
      'revoked_at' => string|null,
      'rotated_at' => string|null,
      'ip' => string|null,
      'user_agent' => string|null,
      'replaced_by_id' => string|null (hex32),
  ]
  ```

#### `findByHash(string $tokenHash): ?array` (deprecated)
- **Purpose:** Find token by hash (deprecated - use findByLookupHash instead)
- **Note:** Deprecated because Argon2id hashes can't be looked up directly (random salts)
- **Returns:** `array|null` Token data or null if not found

#### `markRotated(string $tokenIdHex32, string $replacedByIdHex32): void`
- **Purpose:** Mark token as rotated
- **Parameters:**
  - `$tokenIdHex32`: Token ID (hex32)
  - `$replacedByIdHex32`: Replacement token ID (hex32)
- **SQL:** `UPDATE refresh_tokens SET rotated_at = NOW(), replaced_by_id = ? WHERE id = ?`
- **ID Conversions:**
  - Both IDs → hex32ToBinary
- **Returns:** void
- **Side Effects:** Sets rotated_at timestamp and replaced_by_id

#### `revoke(string $tokenIdHex32): void`
- **Purpose:** Revoke token
- **Parameters:**
  - `$tokenIdHex32`: Token ID (hex32)
- **SQL:** `UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = ?`
- **ID Conversions:**
  - `tokenIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Sets revoked_at timestamp

#### `mapRowToArray(array $row): array` (private)
- **Purpose:** Map database row to array with hex32 IDs
- **Parameters:**
  - `$row`: Database row
- **Process:**
  1. Convert all binary IDs to hex32
  2. Include optional fields if present (replaced_by_id)
- **Returns:** `array<string, mixed>` Mapped array with hex32 IDs

---

### 3.8 KeyPublicIdRepository

**File:** `src/Repositories/KeyPublicIdRepository.php`

**Purpose:** Data access for key_public_ids table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(string $idHex32, string $keyIdHex32, string $keyPublicId): void`
- **Purpose:** Create a new key public ID mapping
- **Parameters:**
  - `$idHex32`: Record ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
  - `$keyPublicId`: Key public ID (apub_...)
- **SQL:** `INSERT INTO key_public_ids (id, key_id, key_public_id) VALUES (?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `key_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates key_public_id mapping record

#### `findKeyIdByPublicId(string $keyPublicId): ?string`
- **Purpose:** Find key ID by public ID
- **Parameters:**
  - `$keyPublicId`: Key public ID (apub_...)
- **SQL:** `SELECT key_id FROM key_public_ids WHERE key_public_id = ?`
- **ID Conversions:**
  - Output: binary → hex32
- **Returns:** `string|null` Key ID (hex32) or null if not found

#### `findPublicIdByKeyId(string $keyIdHex32): ?string`
- **Purpose:** Find public ID by key ID
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT key_public_id FROM key_public_ids WHERE key_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
- **Returns:** `string|null` Key public ID (apub_...) or null if not found

---

### 3.9 GroupMemberRepository

**File:** `src/Repositories/GroupMemberRepository.php`

**Purpose:** Data access for group_members table with specialized helpers for membership lookups

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `add(string $groupIdHex32, string $keyIdHex32): void`
- **Purpose:** Add key to group
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `INSERT INTO group_members (group_id, key_id) VALUES (?, ?)`
- **ID Conversions:**
  - Both IDs → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates group membership record

#### `remove(string $groupIdHex32, string $keyIdHex32): void`
- **Purpose:** Remove key from group
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `DELETE FROM group_members WHERE group_id = ? AND key_id = ?`
- **ID Conversions:**
  - Both IDs → hex32ToBinary
- **Returns:** void
- **Side Effects:** Deletes group membership record

#### `findMembers(string $groupIdHex32): array`
- **Purpose:** Find members of a group
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
- **SQL:** `SELECT key_id FROM group_members WHERE group_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for key_id
- **Returns:** `array<string>` List of key IDs (hex32)

#### `findGroupsForKey(string $keyIdHex32): array` (specialized helper)
- **Purpose:** Find groups for a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT group_id FROM group_members WHERE key_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for group_id
- **Returns:** `array<string>` List of group IDs (hex32)

#### `isMember(string $groupIdHex32, string $keyIdHex32): bool`
- **Purpose:** Check if key is member of group
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT COUNT(*) FROM group_members WHERE group_id = ? AND key_id = ?`
- **ID Conversions:**
  - Both IDs → hex32ToBinary
- **Returns:** `bool` True if key is member of group

---

### 3.10 KeyDeviceRepository

**File:** `src/Repositories/KeyDeviceRepository.php`

**Purpose:** Data access for key_devices table (optional table for device limit tracking)

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `exists(string $keyIdHex32, string $fingerprint): bool`
- **Purpose:** Check if a device fingerprint exists for a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$fingerprint`: Device fingerprint (SHA256 hash)
- **SQL:** `SELECT COUNT(*) FROM key_devices WHERE key_id = ? AND device_fingerprint = ?`
- **ID Conversions:**
  - `keyIdHex32` → hex32ToBinary
- **Returns:** `bool` True if device exists
- **Error Handling:** Returns false if table doesn't exist (PDOException caught)

#### `countDistinct(string $keyIdHex32): int`
- **Purpose:** Count distinct devices for a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `SELECT COUNT(DISTINCT device_fingerprint) FROM key_devices WHERE key_id = ?`
- **ID Conversions:**
  - `keyIdHex32` → hex32ToBinary
- **Returns:** `int` Number of distinct devices
- **Error Handling:** Returns 0 if table doesn't exist (PDOException caught)

#### `register(string $keyIdHex32, string $fingerprint): void`
- **Purpose:** Register a new device fingerprint for a key
- **Parameters:**
  - `$keyIdHex32`: Key ID (hex32)
  - `$fingerprint`: Device fingerprint (SHA256 hash)
- **SQL:** `INSERT INTO key_devices (id, key_id, device_fingerprint) VALUES (?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary (generated)
  - `key_id` → hex32ToBinary
- **Returns:** void
- **Error Handling:** Silently fails if table doesn't exist (PDOException caught)

---

### 3.11 AuditEventRepository

**File:** `src/Repositories/AuditEventRepository.php`

**Purpose:** Data access for audit_events table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new audit event
- **Parameters:**
  - `$data`: Event data array with keys: `id` (hex32), `actor_type` (string), `actor_id` (hex32), `action` (string), `subject_type` (string, optional), `subject_id` (hex32, optional), `metadata` (array, optional), `ip` (string, optional), `user_agent` (string, optional)
- **SQL:** `INSERT INTO audit_events (id, actor_type, actor_id, action, subject_type, subject_id, metadata_json, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `actor_id` → hex32ToBinary
  - `subject_id` → hex32ToBinary (if present)
- **JSON Encoding:**
  - `metadata` array → `json_encode($data['metadata'], JSON_THROW_ON_ERROR)` (if present)
- **Returns:** void
- **Side Effects:** Creates audit event record in database

#### `findByActor(string $actorType, string $actorIdHex32, int $limit = 100): array`
- **Purpose:** Find events by actor
- **Parameters:**
  - `$actorType`: Actor type ('owner' or 'key')
  - `$actorIdHex32`: Actor ID (hex32)
  - `$limit`: Limit (default: 100)
- **SQL:** `SELECT * FROM audit_events WHERE actor_type = ? AND actor_id = ? ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **Returns:** `array<array>` List of audit events

#### `findBySubject(string $subjectType, string $subjectIdHex32, int $limit = 100): array`
- **Purpose:** Find events by subject
- **Parameters:**
  - `$subjectType`: Subject type
  - `$subjectIdHex32`: Subject ID (hex32)
  - `$limit`: Limit (default: 100)
- **SQL:** `SELECT * FROM audit_events WHERE subject_type = ? AND subject_id = ? ORDER BY created_at DESC LIMIT ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (via `mapRowToArray()`)
- **Returns:** `array<array>` List of audit events

#### `mapRowToArray(array $row): array` (private)
- **Purpose:** Map database row to array with hex32 IDs
- **Parameters:**
  - `$row`: Database row
- **Process:**
  1. Convert all binary IDs to hex32
  2. Decode metadata_json to array (if present)
  3. Include optional fields if present (subject_type, subject_id, metadata)
- **Returns:** `array<string, mixed>` Mapped array with hex32 IDs

---

### 3.12 GroupRepository

**File:** `src/Repositories/GroupRepository.php`

**Purpose:** Data access for groups table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new group
- **Parameters:**
  - `$data`: Group data with id, owner_id, name
- **SQL:** `INSERT INTO groups (id, owner_id, name) VALUES (?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `owner_id` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates group record

#### `findById(string $groupIdHex32): ?array`
- **Purpose:** Find group by ID
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
- **SQL:** `SELECT * FROM groups WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array|null` Group data or null if not found

#### `findByOwner(string $ownerIdHex32): array`
- **Purpose:** Find groups by owner
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
- **SQL:** `SELECT * FROM groups WHERE owner_id = ? ORDER BY name`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of groups

#### `updateName(string $groupIdHex32, string $name): void`
- **Purpose:** Update group name
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
  - `$name`: New name
- **SQL:** `UPDATE groups SET name = ? WHERE id = ?`
- **ID Conversions:**
  - `groupIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Updates group record

#### `delete(string $groupIdHex32): void`
- **Purpose:** Delete group
- **Parameters:**
  - `$groupIdHex32`: Group ID (hex32)
- **SQL:** `DELETE FROM groups WHERE id = ?`
- **ID Conversions:**
  - `groupIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Deletes group record

---

### 3.13 KeychainRepository

**File:** `src/Repositories/KeychainRepository.php`

**Purpose:** Data access for keychains table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `create(array $data): void`
- **Purpose:** Create a new keychain
- **Parameters:**
  - `$data`: Keychain data with id, name, optional owner_id
- **SQL:** `INSERT INTO keychains (id, name, owner_id) VALUES (?, ?, ?)`
- **ID Conversions:**
  - `id` → hex32ToBinary
  - `owner_id` → hex32ToBinary (if present, otherwise NULL)
- **Returns:** void
- **Side Effects:** Creates keychain record

#### `findById(string $keychainIdHex32): ?array`
- **Purpose:** Find keychain by ID
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
- **SQL:** `SELECT * FROM keychains WHERE id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields (if owner_id present)
- **Returns:** `array|null` Keychain data or null if not found

#### `findByOwner(string $ownerIdHex32): array`
- **Purpose:** Find keychains by owner
- **Parameters:**
  - `$ownerIdHex32`: Owner ID (hex32)
- **SQL:** `SELECT * FROM keychains WHERE owner_id = ? ORDER BY name`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for all ID fields
- **Returns:** `array<array>` List of keychains

#### `findExternal(): array`
- **Purpose:** Find external keychains (owner_id IS NULL)
- **Parameters:** None
- **SQL:** `SELECT * FROM keychains WHERE owner_id IS NULL ORDER BY name`
- **ID Conversions:**
  - Output: binary → hex32 for id field
- **Returns:** `array<array>` List of external keychains

---

### 3.14 KeychainMemberRepository

**File:** `src/Repositories/KeychainMemberRepository.php`

**Purpose:** Data access for keychain_members table

**Dependencies:**
- `BaseRepository` (extends)
- `Ids` (static utility)

**Methods:**

#### `add(string $keychainIdHex32, string $keyIdHex32): void`
- **Purpose:** Add key to keychain
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `INSERT INTO keychain_members (keychain_id, key_id) VALUES (?, ?)`
- **ID Conversions:**
  - `keychainIdHex32` → hex32ToBinary
  - `keyIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Creates keychain_members record

#### `remove(string $keychainIdHex32, string $keyIdHex32): void`
- **Purpose:** Remove key from keychain
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
  - `$keyIdHex32`: Key ID (hex32)
- **SQL:** `DELETE FROM keychain_members WHERE keychain_id = ? AND key_id = ?`
- **ID Conversions:**
  - `keychainIdHex32` → hex32ToBinary
  - `keyIdHex32` → hex32ToBinary
- **Returns:** void
- **Side Effects:** Deletes keychain_members record

#### `findMembers(string $keychainIdHex32): array`
- **Purpose:** Find members of a keychain
- **Parameters:**
  - `$keychainIdHex32`: Keychain ID (hex32)
- **SQL:** `SELECT key_id FROM keychain_members WHERE keychain_id = ?`
- **ID Conversions:**
  - Input: hex32 → binary
  - Output: binary → hex32 for key_id field
- **Returns:** `array<string>` List of key IDs (hex32)

---

## 3. Repositories

### 3.1 BaseRepository

**File:** `src/Repositories/BaseRepository.php`

**Purpose:** Base class for repository data access and ID conversion helpers

**Responsibilities:**
- Provide shared PDO access
- Convert IDs between hex32 and BINARY(16)
- Centralize repository conventions (prepared statements only)

### 3.2 Entity Repositories

**Directory:** `src/Repositories/`

**Files:**
- **OwnerRepository.php** — Owner data access
- **KeyRepository.php** — Key data access (CRUD, lineage queries, use count)
- **KeyPublicIdRepository.php** — Key public ID lookup (ApiKey exchange)
- **PostRepository.php** — Post data access (CRUD, visibility queries, feed queries)
- **PostAccessRepository.php** — Post access grants (CRUD, mask resolution)
- **CommentRepository.php** — Comment data access
- **GroupRepository.php** — Group data access
- **GroupMemberRepository.php** — Group membership data access
- **KeychainRepository.php** — Keychain data access
- **KeychainMemberRepository.php** — Keychain membership data access
- **RefreshTokenRepository.php** — Refresh token data access (CRUD, rotation tracking)
- **KeyDeviceRepository.php** — Key device tracking (device limits)
- **AuditEventRepository.php** — Audit event data access

**Conventions:**
- Use PDO prepared statements exclusively
- Accept hex32 IDs from services/controllers
- Convert hex32 ↔ BINARY(16) at the repository boundary
- Return arrays or null (no DTOs currently)

---

## 4. Middleware

### 4.1 JwtKeyMiddleware

**File:** `src/Middleware/JwtKeyMiddleware.php`

**Purpose:** Verifies Key JWT tokens and enforces typ=key

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `JwtService` (constructor)
- `LoggerInterface` (constructor, optional)
- `ErrorFactory` (static utility)
- `LoggingService` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, JwtService $jwtService, string $expectedAudience, ?LoggerInterface $logger = null)`
- **Purpose:** Initialize middleware
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$jwtService`: JWT service for verification
  - `$expectedAudience`: Expected audience claim (e.g., '/api')
  - `$logger`: Optional logger for auth channel
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and verify Key JWT
- **Process:**
  1. Extract Bearer token from Authorization header
  2. If missing or invalid format, return 401 Unauthorized
  3. Verify JWT via `JwtService::verify($token, 'key')` - enforces typ=key
  4. Verify audience matches expected audience
  5. Extract `key_id`, `roles`, `permissions` from payload
  6. Attach to request attributes: `key_id`, `roles`, `permissions`
  7. Log authentication success (INFO level)
  8. Call handler
- **Exception Handling:**
  - `ExpiredException` → 401 Unauthorized with "Token expired"
  - `BeforeValidException` → 401 Unauthorized with "Token not yet valid"
  - `InvalidArgumentException` → 401 Unauthorized with "Invalid token"
- **Returns:** `ResponseInterface` (200 OK if successful, 401 if auth fails)
- **Side Effects:** Logs authentication events to auth channel

---

### 4.2 JwtOwnerMiddleware

**File:** `src/Middleware/JwtOwnerMiddleware.php`

**Purpose:** Verifies Owner JWT tokens and enforces typ=owner

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `JwtService` (constructor)
- `LoggerInterface` (constructor, optional)
- `ErrorFactory` (static utility)
- `LoggingService` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, JwtService $jwtService, string $expectedAudience, ?LoggerInterface $logger = null)`
- **Purpose:** Initialize middleware
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$jwtService`: JWT service for verification
  - `$expectedAudience`: Expected audience claim (e.g., '/console')
  - `$logger`: Optional logger for auth channel
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and verify Owner JWT
- **Process:**
  1. Extract Bearer token from Authorization header
  2. If missing or invalid format, return 401 Unauthorized
  3. Verify JWT via `JwtService::verify($token, 'owner')` - enforces typ=owner
  4. Verify audience matches expected audience
  5. Extract `owner_id`, `roles`, `permissions` from payload
  6. Attach to request attributes: `owner_id`, `roles`, `permissions`
  7. Log authentication success (INFO level)
  8. Call handler
- **Exception Handling:** Same as JwtKeyMiddleware
- **Returns:** `ResponseInterface` (200 OK if successful, 401 if auth fails)
- **Side Effects:** Logs authentication events to auth channel

---

### 4.3 ValidationMiddleware

**File:** `src/Middleware/ValidationMiddleware.php`

**Purpose:** Validates request body and query parameters using Respect\Validation

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `array` validation rules (constructor, from config/validation.php)
- `ErrorFactory` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, array $validationRules)`
- **Purpose:** Initialize middleware with validation rules
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$validationRules`: Validation rules array (method => path pattern => rules)
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and validate input
- **Process:**
  1. Get request method and path
  2. Find matching validation rules for method + path pattern
  3. Parse request body (JSON) or query parameters
  4. Validate each field using Respect\Validation rules
  5. If validation fails, return 422 Unprocessable Entity with error details
  6. Call handler
- **Returns:** `ResponseInterface` (200 OK if valid, 422 if validation fails)
- **Side Effects:** None

---

### 4.4 ErrorHandlingMiddleware

**File:** `src/Middleware/ErrorHandlingMiddleware.php`

**Purpose:** Catches exceptions and converts them to JSON error responses

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `LoggerInterface` (constructor, optional)
- `ErrorFactory` (static utility)
- `LoggingService` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, ?LoggerInterface $logger = null)`
- **Purpose:** Initialize middleware
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
  - `$logger`: Optional logger for api channel
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and handle exceptions
- **Process:**
  1. Wrap handler call in try-catch
  2. If exception thrown:
     - Map exception types to HTTP status codes:
       - `NotFoundException` → 404 Not Found
       - `ForbiddenException` → 403 Forbidden
       - `InvalidArgumentException` → 400 Bad Request
       - `PDOException` → 500 Internal Server Error
       - Other exceptions → 500 Internal Server Error
     - Create error response via `ErrorFactory::create()`
     - Log error (ERROR level) with exception details
  3. Return response
- **Returns:** `ResponseInterface` (200 OK if no exception, error response if exception)
- **Side Effects:** Logs exceptions to api channel

---

### 4.5 RateLimitMiddleware

**File:** `src/Middleware/RateLimitMiddleware.php`

**Purpose:** Throttles requests using Symfony rate limiter

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `RateLimiterFactory` (constructor, multiple buckets: GENERAL, AUTH, API, CONSOLE)
- `LoggerInterface` (constructor, optional)
- `ErrorFactory` (static utility)
- `LoggingService` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, RateLimiterFactory $generalLimiter, RateLimiterFactory $authLimiter, RateLimiterFactory $apiLimiter, RateLimiterFactory $consoleLimiter, ?LoggerInterface $logger = null)`
- **Purpose:** Initialize middleware with rate limiters
- **Parameters:** Response factory, four rate limiters (one per bucket), optional logger
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and apply rate limiting
- **Process:**
  1. Determine bucket based on route path:
     - `/api/auth/` or `/console/login` or `/console/owners` → AUTH
     - `/api/` (not auth) → API
     - `/console/` (not login/owners) → CONSOLE
     - Default → GENERAL
  2. Get rate limit key:
     - AUTH/GENERAL → IP address
     - CONSOLE → `owner:{owner_id}` (if available) or IP
     - API → `key:{key_id}` (if available) or IP
  3. Consume rate limit token
  4. If limit exceeded, return 429 Too Many Requests with Retry-After header
  5. Log rate limit violation (WARNING level)
  6. Call handler
- **Returns:** `ResponseInterface` (200 OK if within limit, 429 if exceeded)
- **Side Effects:** Logs rate limit violations to security channel

---

### 4.6 UseKeyLimitMiddleware

**File:** `src/Middleware/UseKeyLimitMiddleware.php`

**Purpose:** Enforces use_count_limit and device_limit for Use Keys on every API request

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `KeyRepository` (constructor)
- `KeyDeviceRepository` (constructor)
- `ErrorFactory` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory, KeyRepository $keyRepository, KeyDeviceRepository $keyDeviceRepository)`
- **Purpose:** Initialize middleware
- **Parameters:** Response factory, key repository, key device repository
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and enforce use key limits
- **Process:**
  1. Get `key_id` from request attributes (set by JwtKeyMiddleware)
  2. If no key_id, pass through (not a key-authenticated request)
  3. Load key via `KeyRepository::findById($keyIdHex32)`
  4. If key not found or not Use Key, pass through
  5. **Enforce use_count_limit:**
     - If limit set and current >= limit, return 403 Forbidden
     - Increment use count via `KeyRepository::incrementUseCount($keyIdHex32)`
  6. **Enforce device_limit:**
     - Generate device fingerprint (SHA-256 of IP + User-Agent)
     - Check if device exists via `KeyDeviceRepository::exists($keyIdHex32, $fingerprint)`
     - If new device:
       - Check device count < device_limit
       - If limit exceeded, return 403 Forbidden
       - Register new device via `KeyDeviceRepository::register($keyIdHex32, $fingerprint)`
  7. Call handler
- **Returns:** `ResponseInterface` (200 OK if within limits, 403 if exceeded)
- **Side Effects:** Increments use_count_current, registers new devices

---

### 4.7 RequestLoggingMiddleware

**File:** `src/Middleware/RequestLoggingMiddleware.php`

**Purpose:** Logs request/response summaries to the 'api' channel with structured JSON

**Dependencies:**
- `LoggerInterface` (constructor, api channel logger)
- `LoggingService` (static utility)
- `Ids` (static utility)

**Methods:**

#### `__construct(LoggerInterface $apiLogger)`
- **Purpose:** Initialize middleware with api logger
- **Parameters:**
  - `$apiLogger`: Logger instance for api channel
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and log summary
- **Process:**
  1. Record start time
  2. Generate or get request_id from request attributes
  3. Call handler
  4. Calculate latency (ms)
  5. Extract authentication context (owner_id or key_id)
  6. Build log context:
     - request_id, method, path, status, latency_ms
     - owner_id or key_id (never both)
     - key_public_id (if available)
     - ip, user_agent
  7. Log request summary (INFO for success, WARNING for 4xx/5xx)
- **Returns:** `ResponseInterface` (unchanged)
- **Side Effects:** Writes log entry to api.log

---

### 4.8 RouteParameterValidatorMiddleware

**File:** `src/Middleware/RouteParameterValidatorMiddleware.php`

**Purpose:** Validates route parameters to ensure correct ID format

**Dependencies:**
- `ResponseFactoryInterface` (constructor)
- `Ids` (static utility)
- `ErrorFactory` (static utility)

**Methods:**

#### `__construct(ResponseFactoryInterface $responseFactory)`
- **Purpose:** Initialize middleware
- **Parameters:**
  - `$responseFactory`: PSR-7 response factory
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and validate route parameters
- **Process:**
  1. Get route parameters from request attributes
  2. For each parameter:
     - If parameter name is `keyPublicId`, validate apub_... format via `Ids::isValidKeyPublicId()`
     - If parameter name ends in `Id`, validate hex32 format via `Ids::isValidHex32()`
  3. If validation fails, return 400 Bad Request with error message
  4. Call handler
- **Returns:** `ResponseInterface` (200 OK if valid, 400 if invalid)
- **Side Effects:** None

---

### 4.9 HttpsMiddleware

**File:** `src/Middleware/HttpsMiddleware.php`

**Purpose:** Enforces HTTPS and applies HSTS headers

**Dependencies:** None (uses environment variables)

**Methods:**

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and enforce HTTPS
- **Process:**
  1. Check if request is HTTPS (scheme or X-Forwarded-Proto header)
  2. If not HTTPS and in production, redirect to HTTPS (301)
  3. Call handler
  4. If HTTPS and in production, add HSTS header (max-age=31536000; includeSubDomains)
- **Returns:** `ResponseInterface` (200 OK or 301 redirect)
- **Side Effects:** Adds HSTS header to response

---

### 4.10 CorsMiddleware

**File:** `src/Middleware/CorsMiddleware.php`

**Purpose:** Applies Cross-Origin Resource Sharing headers based on environment configuration

**Dependencies:** None (uses environment variables)

**Methods:**

#### `__construct()`
- **Purpose:** Initialize middleware with CORS configuration
- **Process:**
  1. Parse `CORS_ALLOWED_ORIGINS` (comma-separated)
  2. Parse `CORS_ALLOWED_METHODS` (default: GET,POST,PUT,PATCH,DELETE,OPTIONS)
  3. Parse `CORS_ALLOWED_HEADERS` (default: Authorization,Content-Type)
  4. Parse `CORS_EXPOSED_HEADERS` (comma-separated)
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and apply CORS headers
- **Process:**
  1. Get Origin header
  2. If OPTIONS request (preflight), return 204 with CORS headers
  3. Call handler
  4. Add CORS headers to response:
     - Access-Control-Allow-Origin (if origin is allowed)
     - Access-Control-Allow-Methods
     - Access-Control-Allow-Headers
     - Access-Control-Expose-Headers
     - Access-Control-Allow-Credentials (if origin is allowed)
- **Returns:** `ResponseInterface` (200 OK or 204 for preflight)
- **Side Effects:** Adds CORS headers to response

---

### 4.11 CspMiddleware

**File:** `src/Middleware/CspMiddleware.php`

**Purpose:** Applies Content Security Policy headers to HTML responses only

**Dependencies:** None (uses environment variables)

**Methods:**

#### `__construct()`
- **Purpose:** Initialize middleware with CSP configuration
- **Process:**
  1. Get `CSP_DEFAULT_SRC` from environment (default: 'self')
- **Returns:** void

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and apply CSP headers
- **Process:**
  1. Call handler
  2. Check Content-Type header
  3. If Content-Type is text/html, add Content-Security-Policy header
  4. CSP value: `default-src {CSP_DEFAULT_SRC}`
- **Returns:** `ResponseInterface` (unchanged)
- **Side Effects:** Adds CSP header to HTML responses only

---

### 4.12 CsrfExposeMiddleware

**File:** `src/Middleware/CsrfExposeMiddleware.php`

**Purpose:** Exposes CSRF tokens as request attributes and response headers

**Dependencies:** None (uses Slim\Csrf\Guard attributes)

**Methods:**

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
- **Purpose:** Process request and expose CSRF tokens
- **Process:**
  1. Call handler
  2. Get CSRF tokens from request attributes (set by Slim\Csrf\Guard):
     - `csrf_name`
     - `csrf_value`
  3. If tokens exist, add to response headers:
     - X-CSRF-Name
     - X-CSRF-Value
- **Returns:** `ResponseInterface` (unchanged)
- **Side Effects:** Adds CSRF headers to response

---

## 5. Security Components

### 5.1 JwtService

**File:** `src/Security/JwtService.php`

**Purpose:** Handles JWT signing and verification using RS256

**Dependencies:**
- `LoggerInterface` (constructor, optional)
- `firebase/php-jwt` library

**Key Methods:**
- `signOwnerToken()` - Signs Owner JWT with typ=owner
- `signKeyToken()` - Signs Key JWT with typ=key
- `verify()` - Verifies JWT and enforces typ claim
- `getJwks()` - Returns JWKS (JSON Web Key Set) for public key distribution

---

### 5.2 PermissionCatalog

**File:** `src/Security/PermissionCatalog.php`

**Purpose:** Defines global permission strings, roles, and validation logic

**Key Constants:**
- Permission strings (e.g., `posts:create`, `keys:issue`)
- Role definitions
- Validation methods:
  - `isValidFormat()` - Validates permission string format
  - `validateEnvelope()` - Validates permission envelope rule
  - `validateUseKeyPermissions()` - Validates Use Key restrictions

---

### 5.3 PostAccessBitmask

**File:** `src/Security/PostAccessBitmask.php`

**Purpose:** Defines constants and helpers for post-specific access control bitmasks

**Key Constants:**
- `VIEW` (0x01) - Can view post
- `COMMENT` (0x02) - Can comment on post
- `MANAGE_ACCESS` (0x08) - Can manage access grants

**Key Methods:**
- `hasView()` - Check if mask includes VIEW
- `hasComment()` - Check if mask includes COMMENT
- `hasManageAccess()` - Check if mask includes MANAGE_ACCESS
- `isValid()` - Validate bitmask value

---

## 6. Utilities

### 6.1 Ids

**File:** `src/Utilities/Ids.php`

**Purpose:** Utility for converting between internal BINARY(16) and external hex32 IDs

**Key Methods:**
- `hex32ToBinary()` - Convert hex32 to binary
- `binaryToHex32()` - Convert binary to hex32
- `generateHex32Id()` - Generate random hex32 ID
- `generateBinaryId()` - Generate random binary ID
- `isValidHex32()` - Validate hex32 format
- `isValidKeyPublicId()` - Validate apub_... format

---

### 6.2 ResponseFactory

**File:** `src/Utilities/ResponseFactory.php`

**Purpose:** Utility for creating standardized JSON success responses

**Key Methods:**
- `single()` - Single object response
- `list()` - List response
- `paginated()` - Paginated list response
- `created()` - 201 Created response
- `noContent()` - 204 No Content response

---

### 6.3 ErrorFactory

**File:** `src/Utilities/ErrorFactory.php`

**Purpose:** Utility for creating standardized JSON error responses

**Key Methods:**
- `create()` - Generic error response
- `badRequest()` - 400 Bad Request
- `unauthorized()` - 401 Unauthorized
- `forbidden()` - 403 Forbidden
- `notFound()` - 404 Not Found
- `unprocessableEntity()` - 422 Unprocessable Entity
- `rateLimited()` - 429 Too Many Requests
- `internalServerError()` - 500 Internal Server Error

---

### 6.4 SensitiveDataSanitizer

**File:** `src/Utilities/SensitiveDataSanitizer.php`

**Purpose:** Utility for redacting sensitive information from arrays before logging

**Key Methods:**
- `sanitize()` - Redacts sensitive keys (password, secret, token, etc.)

---

### 6.5 BootstrapValidator

**File:** `src/Utilities/BootstrapValidator.php`

**Purpose:** Utility for performing fail-fast validation of environment configuration

**Key Methods:**
- `validate()` - Validates all required environment variables

---

## 7. Exceptions

### 7.1 NotFoundException

**File:** `src/Exceptions/NotFoundException.php`

**Purpose:** Custom exception for 404 responses

**Usage:** Used to hide resource existence when principal lacks VIEW permission

---

### 7.2 ForbiddenException

**File:** `src/Exceptions/ForbiddenException.php`

**Purpose:** Custom exception for 403 responses

**Properties:**
- `requiredPermissions` - Array of required permissions
- `requiredMask` - Required post access mask

**Usage:** Used when resource is visible but principal lacks permission for action

---

## 8. Route Definitions

### 8.1 Route Groups

**File:** `config/routes.php`

**Five distinct route groups:**
1. **Public API** (`/api/auth/`) - No auth required
2. **Gateway JSON** (`/api/`) - Key JWT required
3. **Console JSON** (`/console/`) - Owner JWT required
4. **Console HTML** (`/console/`) - Owner JWT + CSRF required
5. **Public** (`/`, `/health`, `/jwks`) - No auth required

**Middleware Pipelines:**
- Each route group has specific middleware applied
- See `CODEBASE_INVENTORY.md` Section 8 for detailed pipeline breakdown

---

## 9. Database Migrations

### 9.1 Migration Structure

**Directory:** `migrations/`

**Format:** `{number}_{description}.php`

**Key Methods:**
- `up()` - Apply migration
- `down()` - Rollback migration

**Migration Files:**
- `001_create_owners.php` - Creates owners table
- `002_create_keys.php` - Creates keys table
- `003_create_posts.php` - Creates posts table
- `004_create_post_access.php` - Creates post_access table
- `005_create_comments.php` - Creates comments table
- `006_create_groups.php` - Creates groups table
- `007_create_group_members.php` - Creates group_members table
- `008_create_refresh_tokens.php` - Creates refresh_tokens table
- `009_create_audit_events.php` - Creates audit_events table
- `010_create_key_public_ids.php` - Creates key_public_ids table
- `011_create_key_devices.php` - Creates key_devices table (optional)

---

## 10. Configuration Files

### 10.1 Bootstrap

**File:** `src/bootstrap.php`

**Purpose:** Application bootstrap - loads environment, validates config, builds DI container

**Key Steps:**
1. Load environment variables via `Dotenv::createImmutable()`
2. Validate configuration via `BootstrapValidator::validate()`
3. Build DI container via `ContainerBuilder::buildDevContainer()` or `buildProdContainer()`
4. Register global middleware
5. Return Slim App instance

---

### 10.2 Container Configuration

**File:** `config/container.php`

**Purpose:** PHP-DI container configuration

**Key Services Configured:**
- PDO database connection
- Rate limiters (GENERAL, AUTH, API, CONSOLE)
- Loggers (api, auth, security, db, guzzle.http channels)
- Response factory
- JWT service
- All repositories
- All services
- All middleware

---

### 10.3 Route Configuration

**File:** `config/routes.php`

**Purpose:** Route registration and middleware pipeline configuration

**Key Functions:**
- `registerPublicApiRoutes()` - Public API routes
- `registerGatewayJsonRoutes()` - Gateway JSON routes
- `registerConsoleJsonRoutes()` - Console JSON routes
- `registerConsoleHtmlRoutes()` - Console HTML routes
- `registerPublicRoutes()` - Public routes

---

### 10.4 Validation Configuration

**File:** `config/validation.php`

**Purpose:** Centralized input validation rules

**Format:** `[HTTP_METHOD => [path_pattern => [field => validation_rule]]]`

**Uses:** Respect\Validation library

---

## Summary

This document provides a comprehensive breakdown of every component in the CRE8.pw codebase, detailing:

- **Controllers:** All HTTP request handlers with their methods, parameters, dependencies, and exception handling
- **Services:** All business logic components with their authorization checks, transactions, and audit events
- **Repositories:** All data access components with their SQL queries, ID conversions, and return formats
- **Middleware:** All request processing middleware with their security checks, logging, and side effects
- **Security Components:** JWT handling, permission validation, and access control
- **Utilities:** ID conversion, response formatting, error handling, and data sanitization
- **Exceptions:** Custom exception types and their usage
- **Route Definitions:** Route groups and middleware pipelines
- **Database Migrations:** Schema changes and rollback operations
- **Configuration Files:** Bootstrap, container, routes, and validation configuration

Each component entry includes:
- File location
- Purpose and responsibilities
- Dependencies (constructor injection and static utilities)
- Methods with detailed breakdowns:
  - Purpose and requirements
  - Parameters with types and descriptions
  - Process steps (numbered)
  - Return types and formats
  - Exception handling
  - Side effects

This breakdown enables methodical scrutiny of each section to answer:
- "How is this supposed to work in general?"
- "How is this supposed to work in this system?"
- "How is this part supposed to interface with all the other parts?"
