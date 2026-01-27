<?php
/**
 * CRE8.pw Post Service
 * 
 * Handles post creation and access management with authorization checks.
 * Author keys only; default private unless explicitly granted.
 * 
 * @see docs/canon/08-Post-Sharing-and-Access-Control.md
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\PostRepository;
use App\Repositories\PostAccessRepository;
use App\Repositories\KeyRepository;
use App\Repositories\GroupRepository;
use App\Repositories\GroupMemberRepository;
use App\Services\AuditService;
use App\Security\PermissionCatalog;
use App\Security\PostAccessBitmask;
use App\Utilities\Ids;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * Post Service
 * 
 * Handles post creation and access management.
 */
class PostService extends BaseService
{
    public function __construct(
        private PostRepository $postRepo,
        private PostAccessRepository $postAccessRepo,
        private KeyRepository $keyRepo,
        private GroupRepository $groupRepo,
        private GroupMemberRepository $groupMemberRepo,
        private AuditService $auditService
    ) {
    }

    /**
     * Create a new post
     * 
     * Requirements:
     * - Key must have `posts:create` permission
     * - Key type must be `primary` or `secondary` (NOT `use`)
     * - Post is created with NO access grants by default (private)
     * 
     * @param string $authorKeyIdHex32 Author key ID from JWT (hex32)
     * @param string $content Post content (1-10000 chars)
     * @param string|null $title Post title (optional, 1-255 chars)
     * @return array{post_id: string, author_key_id: string, initial_author_key_id: string, content: string, title: string|null, created_at: string}
     * @throws ForbiddenException If key lacks permission or is Use Key
     * @throws InvalidArgumentException If validation fails
     */
    public function createPost(
        string $authorKeyIdHex32,
        string $content,
        ?string $title = null
    ): array {
        // Validate content length (1-10000 chars)
        $contentLength = mb_strlen($content, 'UTF-8');
        if ($contentLength < 1 || $contentLength > 10000) {
            throw new InvalidArgumentException("Content must be between 1 and 10000 characters");
        }
        
        // Validate title length if provided (1-255 chars)
        if ($title !== null) {
            $titleLength = mb_strlen($title, 'UTF-8');
            if ($titleLength < 1 || $titleLength > 255) {
                throw new InvalidArgumentException("Title must be between 1 and 255 characters");
            }
        }
        
        // Load author key to verify permissions and type
        $authorKey = $this->keyRepo->findById($authorKeyIdHex32);
        if ($authorKey === null) {
            throw new ForbiddenException(['posts:create'], null, "Author key not found");
        }
        
        // Verify key is active
        if (!$authorKey['active']) {
            throw new ForbiddenException(['posts:create'], null, "Author key is inactive");
        }
        
        // Verify key has posts:create permission
        $permissions = $authorKey['permissions'] ?? [];
        if (!in_array('posts:create', $permissions, true)) {
            throw new ForbiddenException(['posts:create'], null, "Missing required permission: posts:create");
        }
        
        // Verify key type is primary or secondary (NOT use)
        if ($authorKey['type'] === 'use') {
            throw new ForbiddenException(['posts:create'], null, "Use Keys cannot create posts");
        }
        
        if (!in_array($authorKey['type'], ['primary', 'secondary'], true)) {
            throw new ForbiddenException(['posts:create'], null, "Invalid key type for post creation");
        }
        
        // Get initial_author_key_id from key (for provenance)
        $initialAuthorKeyId = $authorKey['initial_author_key_id'] ?? $authorKeyIdHex32;
        
        // Generate post ID
        $postIdHex32 = Ids::generateHex32Id();
        
        // Create post
        $this->postRepo->create([
            'id' => $postIdHex32,
            'author_key_id' => $authorKeyIdHex32,
            'initial_author_key_id' => $initialAuthorKeyId,
            'content' => $content,
            'title' => $title,
        ]);
        
        // Load created post to return full data
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new \RuntimeException("Failed to create post");
        }
        
        // Emit audit event (actor is the author key)
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $authorKeyIdHex32,
            action: 'posts:create',
            subjectType: 'post',
            subjectIdHex32: $postIdHex32,
            metadata: ['title' => $title] // Safe to log title
        );
        
        return $post;
    }

    /**
     * Grant access to a post (key or group)
     * 
     * Requirements:
     * - Requester must have `posts:access:manage` permission
     * - Requester must have MANAGE_ACCESS mask (0x08) on the post
     * - Upsert behavior (insert or update existing grant)
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $requesterKeyIdHex32 Requester key ID from JWT (hex32)
     * @param string $requesterPermissions Requester permissions from JWT
     * @param string $targetType Target type ('key' or 'group')
     * @param string $targetIdHex32 Target ID (hex32)
     * @param int $permissionMask Permission mask (must be valid)
     * @return array{access_id: string, post_id: string, target_type: string, target_id: string, permission_mask: int}
     * @throws NotFoundException If post or target not found
     * @throws ForbiddenException If requester lacks permission or MANAGE_ACCESS mask
     * @throws InvalidArgumentException If validation fails
     */
    public function grantAccess(
        string $postIdHex32,
        string $requesterKeyIdHex32,
        array $requesterPermissions,
        string $targetType,
        string $targetIdHex32,
        int $permissionMask
    ): array {
        // Validate target_type
        if (!in_array($targetType, ['key', 'group'], true)) {
            throw new InvalidArgumentException("Invalid target_type: {$targetType}. Must be 'key' or 'group'");
        }
        
        // Validate permission mask
        if (!PostAccessBitmask::isValid($permissionMask)) {
            throw new InvalidArgumentException("Invalid permission mask: {$permissionMask}");
        }
        
        // Reject zero mask (must grant at least one permission)
        if ($permissionMask === 0) {
            throw new InvalidArgumentException("Permission mask must grant at least one permission. Mask cannot be zero.");
        }
        
        // Verify post exists
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify requester has posts:access:manage permission
        if (!in_array('posts:access:manage', $requesterPermissions, true)) {
            throw new ForbiddenException(['posts:access:manage'], null, "Missing required permission: posts:access:manage");
        }
        
        // Verify requester has MANAGE_ACCESS mask on the post
        // Get requester's groups for mask resolution
        $requesterGroups = $this->groupMemberRepo->findGroupsForKey($requesterKeyIdHex32);
        $requesterMask = $this->postAccessRepo->resolveAccessMask($postIdHex32, $requesterKeyIdHex32, $requesterGroups);
        
        if (!PostAccessBitmask::hasManageAccess($requesterMask)) {
            throw new ForbiddenException(
                ['posts:access:manage'],
                'MANAGE_ACCESS',
                "Insufficient post access: MANAGE_ACCESS mask required"
            );
        }
        
        // Verify target exists
        if ($targetType === 'key') {
            $targetKey = $this->keyRepo->findById($targetIdHex32);
            if ($targetKey === null) {
                throw new NotFoundException("Target key not found");
            }
        } elseif ($targetType === 'group') {
            $targetGroup = $this->groupRepo->findById($targetIdHex32);
            if ($targetGroup === null) {
                throw new NotFoundException("Target group not found");
            }
        }
        
        // Upsert access grant
        $accessIdHex32 = Ids::generateHex32Id();
        $this->postAccessRepo->upsert([
            'id' => $accessIdHex32,
            'post_id' => $postIdHex32,
            'target_type' => $targetType,
            'target_id' => $targetIdHex32,
            'permission_mask' => $permissionMask,
        ]);
        
        // Emit audit event (actor is the requester key)
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $requesterKeyIdHex32,
            action: 'posts:access:grant',
            subjectType: 'post',
            subjectIdHex32: $postIdHex32,
            metadata: [
                'target_type' => $targetType,
                'target_id' => $targetIdHex32,
                'permission_mask' => $permissionMask
            ]
        );
        
        return [
            'access_id' => $accessIdHex32,
            'post_id' => $postIdHex32,
            'target_type' => $targetType,
            'target_id' => $targetIdHex32,
            'permission_mask' => $permissionMask,
        ];
    }

    /**
     * Revoke access to a post
     * 
     * Requirements:
     * - Requester must have `posts:access:manage` permission
     * - Requester must have MANAGE_ACCESS mask (0x08) on the post
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $requesterKeyIdHex32 Requester key ID from JWT (hex32)
     * @param array $requesterPermissions Requester permissions from JWT
     * @param string $targetType Target type ('key' or 'group')
     * @param string $targetIdHex32 Target ID (hex32)
     * @return void
     * @throws NotFoundException If post not found
     * @throws ForbiddenException If requester lacks permission or MANAGE_ACCESS mask
     * @throws InvalidArgumentException If validation fails
     */
    public function revokeAccess(
        string $postIdHex32,
        string $requesterKeyIdHex32,
        array $requesterPermissions,
        string $targetType,
        string $targetIdHex32
    ): void {
        // Validate target_type
        if (!in_array($targetType, ['key', 'group'], true)) {
            throw new InvalidArgumentException("Invalid target_type: {$targetType}. Must be 'key' or 'group'");
        }
        
        // Verify post exists
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify requester has posts:access:manage permission
        if (!in_array('posts:access:manage', $requesterPermissions, true)) {
            throw new ForbiddenException(['posts:access:manage'], null, "Missing required permission: posts:access:manage");
        }
        
        // Verify requester has MANAGE_ACCESS mask on the post
        $requesterGroups = $this->groupMemberRepo->findGroupsForKey($requesterKeyIdHex32);
        $requesterMask = $this->postAccessRepo->resolveAccessMask($postIdHex32, $requesterKeyIdHex32, $requesterGroups);
        
        if (!PostAccessBitmask::hasManageAccess($requesterMask)) {
            throw new ForbiddenException(
                ['posts:access:manage'],
                'MANAGE_ACCESS',
                "Insufficient post access: MANAGE_ACCESS mask required"
            );
        }
        
        // Revoke access grant
        $this->postAccessRepo->revoke($postIdHex32, $targetType, $targetIdHex32);
        
        // Emit audit event (actor is the requester key)
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $requesterKeyIdHex32,
            action: 'posts:access:revoke',
            subjectType: 'post',
            subjectIdHex32: $postIdHex32,
            metadata: [
                'target_type' => $targetType,
                'target_id' => $targetIdHex32
            ]
        );
    }

    /**
     * List posts accessible to a key (Gateway)
     * 
     * Requirements:
     * - Key must have `posts:read` permission
     * - Returns posts visible to the key via direct grants or group memberships
     * - Only includes posts with READ mask (0x01)
     * 
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @param int $limit Limit (default: 20, max: 100)
     * @param string|null $beforeIdHex32 Cursor for pagination (post ID before this)
     * @return array<array> List of posts
     * @throws ForbiddenException If key lacks permission
     */
    public function listPosts(
        string $keyIdHex32,
        array $keyPermissions,
        int $limit = 20,
        ?string $beforeIdHex32 = null
    ): array {
        // Verify key has posts:read permission
        if (!in_array('posts:read', $keyPermissions, true)) {
            throw new ForbiddenException(['posts:read'], null, "Missing required permission: posts:read");
        }
        
        // Get groups the key belongs to
        $groupIdsHex32 = $this->groupMemberRepo->findGroupsForKey($keyIdHex32);
        
        // Find visible posts
        $posts = $this->postRepo->findVisiblePostsForUseKey($keyIdHex32, $groupIdsHex32, $limit, $beforeIdHex32);
        
        return $posts;
    }

    /**
     * Get post details (Gateway)
     * 
     * Requirements:
     * - Key must have `posts:read` permission
     * - Key must have READ mask (0x01) on the post
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @return array Post data
     * @throws NotFoundException If post not found or key lacks access
     * @throws ForbiddenException If key lacks permission
     */
    public function getPost(
        string $postIdHex32,
        string $keyIdHex32,
        array $keyPermissions
    ): array {
        // Verify key has posts:read permission
        if (!in_array('posts:read', $keyPermissions, true)) {
            throw new ForbiddenException(['posts:read'], null, "Missing required permission: posts:read");
        }
        
        // Load post
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify key has access to post
        $groupIdsHex32 = $this->groupMemberRepo->findGroupsForKey($keyIdHex32);
        
        $accessMask = $this->postAccessRepo->resolveAccessMask($postIdHex32, $keyIdHex32, $groupIdsHex32);
        
        if (!PostAccessBitmask::hasView($accessMask)) {
            // Return 404 to hide existence
            throw new NotFoundException("Post not found");
        }
        
        return $post;
    }

    /**
     * List posts owned by an owner
     * 
     * Owner admin post management.
     * 
     * Requirements:
     * - Owner must have `posts:admin:read` permission
     * - Returns posts created by any key owned by the owner (via initial_author_key_id)
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param int $limit Limit (default: 20)
     * @param string|null $beforeIdHex32 Cursor for pagination (post ID before this)
     * @return array{posts: array, paging: array{limit: int, before_id: string|null}}
     * @throws ForbiddenException If owner lacks permission
     */
    public function listPostsByOwner(
        string $ownerIdHex32,
        array $ownerPermissions,
        int $limit = 20,
        ?string $beforeIdHex32 = null
    ): array {
        // Verify owner has posts:admin:read permission
        if (!in_array('posts:admin:read', $ownerPermissions, true)) {
            throw new ForbiddenException(['posts:admin:read'], null, "Missing required permission: posts:admin:read");
        }
        
        // Find posts by owner (get owner's primary keys first, then query posts by those key IDs)
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $primaryKeyIds = array_map(fn($k) => $k['key_id'], $primaryKeys);
        $posts = $this->postRepo->findByOwner($primaryKeyIds, $limit, $beforeIdHex32);
        
        // Determine next cursor (last post ID if there are results)
        $nextCursor = null;
        if (!empty($posts) && count($posts) === $limit) {
            $nextCursor = end($posts)['post_id'];
        }
        
        return [
            'posts' => $posts,
            'paging' => [
                'limit' => $limit,
                'before_id' => $beforeIdHex32,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /**
     * Get post details for owner admin view
     * 
     * Owner admin post management.
     * 
     * Requirements:
     * - Owner must have `posts:admin:read` permission
     * - Post must be owned by the owner (via initial_author_key_id)
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array Post data
     * @throws NotFoundException If post not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function getPostForOwner(
        string $postIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has posts:admin:read permission
        if (!in_array('posts:admin:read', $ownerPermissions, true)) {
            throw new ForbiddenException(['posts:admin:read'], null, "Missing required permission: posts:admin:read");
        }
        
        // Load post
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify post is owned by owner (get owner's primary keys, check if post's initial_author_key_id matches)
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($k) => $k['key_id'], $primaryKeys);
        if (!in_array($post['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            // Return 404 to hide existence
            throw new NotFoundException("Post not found");
        }
        
        return $post;
    }

    /**
     * Grant group access to a post (Console variant)
     * 
     * Owner admin post management.
     * 
     * Requirements:
     * - Owner must have `posts:access:manage` permission
     * - Post must be owned by the owner (via initial_author_key_id)
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $groupIdHex32 Group ID (hex32)
     * @param int $permissionMask Permission mask (must be valid)
     * @return array{access_id: string, post_id: string, group_id: string, permission_mask: int}
     * @throws NotFoundException If post or group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function grantGroupAccess(
        string $postIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $groupIdHex32,
        int $permissionMask
    ): array {
        // Validate permission mask
        if (!PostAccessBitmask::isValid($permissionMask)) {
            throw new InvalidArgumentException("Invalid permission mask: {$permissionMask}");
        }
        
        // Reject zero mask (must grant at least one permission)
        if ($permissionMask === 0) {
            throw new InvalidArgumentException("Permission mask must grant at least one permission. Mask cannot be zero.");
        }
        
        // Verify owner has posts:access:manage permission
        if (!in_array('posts:access:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['posts:access:manage'], null, "Missing required permission: posts:access:manage");
        }
        
        // Load post and verify ownership
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify post is owned by owner (get owner's primary keys, check if post's initial_author_key_id matches)
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($k) => $k['key_id'], $primaryKeys);
        if (!in_array($post['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify group exists and is owned by owner
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Upsert access grant
        $accessIdHex32 = Ids::generateHex32Id();
        $this->postAccessRepo->upsert([
            'id' => $accessIdHex32,
            'post_id' => $postIdHex32,
            'target_type' => 'group',
            'target_id' => $groupIdHex32,
            'permission_mask' => $permissionMask,
        ]);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'posts:access:grant-group',
            subjectType: 'post',
            subjectIdHex32: $postIdHex32,
            metadata: ['group_id' => $groupIdHex32, 'permission_mask' => $permissionMask]
        );
        
        return [
            'access_id' => $accessIdHex32,
            'post_id' => $postIdHex32,
            'group_id' => $groupIdHex32,
            'permission_mask' => $permissionMask,
        ];
    }

    /**
     * Revoke group access to a post (Console variant)
     * 
     * Owner admin post management.
     * 
     * Requirements:
     * - Owner must have `posts:access:manage` permission
     * - Post must be owned by the owner (via initial_author_key_id)
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $groupIdHex32 Group ID (hex32)
     * @return void
     * @throws NotFoundException If post or group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function revokeGroupAccess(
        string $postIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $groupIdHex32
    ): void {
        // Verify owner has posts:access:manage permission
        if (!in_array('posts:access:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['posts:access:manage'], null, "Missing required permission: posts:access:manage");
        }
        
        // Load post and verify ownership
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify post is owned by owner (get owner's primary keys, check if post's initial_author_key_id matches)
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($k) => $k['key_id'], $primaryKeys);
        if (!in_array($post['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify group exists and is owned by owner
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Revoke access grant
        $this->postAccessRepo->revoke($postIdHex32, 'group', $groupIdHex32);
        
        // Emit audit event (actor is owner for Console variant)
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'posts:access:revoke',
            subjectType: 'post',
            subjectIdHex32: $postIdHex32,
            metadata: [
                'target_type' => 'group',
                'target_id' => $groupIdHex32
            ]
        );
    }
}
