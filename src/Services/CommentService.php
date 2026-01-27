<?php
/**
 * CRE8.pw Comment Service
 * 
 * Handles comment creation with authorization checks.
 * Requires both global permission (comments:write) and post-scoped mask (COMMENT).
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 6.2
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\CommentRepository;
use App\Repositories\PostRepository;
use App\Repositories\PostAccessRepository;
use App\Repositories\GroupMemberRepository;
use App\Security\PostAccessBitmask;
use App\Utilities\Ids;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * Comment Service
 * 
 * Handles comment creation and listing.
 */
class CommentService extends BaseService
{
    public function __construct(
        private CommentRepository $commentRepo,
        private PostRepository $postRepo,
        private PostAccessRepository $postAccessRepo,
        private GroupMemberRepository $groupMemberRepo,
        private AuditService $auditService
    ) {
    }

    /**
     * Create a new comment
     * 
     * Requirements:
     * - Key must have `comments:write` permission
     * - Key must have COMMENT mask (0x02) on the post
     * - Post must exist and be visible to the key
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $authorKeyIdHex32 Author key ID from JWT (hex32)
     * @param array $authorPermissions Author permissions from JWT
     * @param string $body Comment body (1-5000 chars)
     * @return array{comment_id: string, post_id: string, created_by_key_id: string, body: string, created_at: string}
     * @throws NotFoundException If post not found or not visible
     * @throws ForbiddenException If key lacks permission or COMMENT mask
     * @throws InvalidArgumentException If validation fails
     */
    public function createComment(
        string $postIdHex32,
        string $authorKeyIdHex32,
        array $authorPermissions,
        string $body
    ): array {
        // Validate body length (1-5000 chars)
        $bodyLength = mb_strlen($body, 'UTF-8');
        if ($bodyLength < 1 || $bodyLength > 5000) {
            throw new InvalidArgumentException("Comment body must be between 1 and 5000 characters");
        }
        
        // Verify post exists
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify key has comments:write permission
        if (!in_array('comments:write', $authorPermissions, true)) {
            throw new ForbiddenException(['comments:write'], null, "Missing required permission: comments:write");
        }
        
        // Get key's groups for mask resolution
        $keyGroups = $this->groupMemberRepo->findGroupsForKey($authorKeyIdHex32);
        
        // Resolve access mask for the key on this post
        $accessMask = $this->postAccessRepo->resolveAccessMask($postIdHex32, $authorKeyIdHex32, $keyGroups);
        
        // Check if key has VIEW access (to verify post is visible)
        if (!PostAccessBitmask::hasView($accessMask)) {
            // Return 404 to hide post existence
            throw new NotFoundException("Post not found");
        }
        
        // Check if key has COMMENT mask
        if (!PostAccessBitmask::hasComment($accessMask)) {
            throw new ForbiddenException(
                ['comments:write'],
                'COMMENT',
                "Insufficient post access: COMMENT mask required"
            );
        }
        
        // Generate comment ID
        $commentIdHex32 = Ids::generateHex32Id();
        
        // Create comment
        $this->commentRepo->create([
            'id' => $commentIdHex32,
            'post_id' => $postIdHex32,
            'created_by_key_id' => $authorKeyIdHex32,
            'body' => $body,
        ]);
        
        // Load created comment to return full data
        $comment = $this->commentRepo->findById($commentIdHex32);
        if ($comment === null) {
            throw new \RuntimeException("Failed to create comment");
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $authorKeyIdHex32,
            action: 'comments:create',
            subjectType: 'comment',
            subjectIdHex32: $commentIdHex32,
            metadata: ['post_id' => $postIdHex32]
        );
        
        return $comment;
    }

    /**
     * List comments for a post
     * 
     * Requirements:
     * - Key must have `posts:read` permission
     * - Key must have VIEW mask (0x01) on the post
     * - Post must exist and be visible to the key
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $viewerKeyIdHex32 Viewer key ID from JWT (hex32)
     * @param array $viewerPermissions Viewer permissions from JWT
     * @param int $limit Limit (default: 20, max: 100)
     * @param string|null $beforeIdHex32 Cursor for pagination (comment ID before this)
     * @return array{comments: array, paging: array{limit: int, before_id: string|null, next_cursor: string|null}}
     * @throws NotFoundException If post not found or not visible
     * @throws ForbiddenException If key lacks permission or VIEW mask
     */
    public function listComments(
        string $postIdHex32,
        string $viewerKeyIdHex32,
        array $viewerPermissions,
        int $limit = 20,
        ?string $beforeIdHex32 = null
    ): array {
        // Validate limit
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException("Limit must be between 1 and 100");
        }
        
        // Verify post exists
        $post = $this->postRepo->findById($postIdHex32);
        if ($post === null) {
            throw new NotFoundException("Post not found");
        }
        
        // Verify key has posts:read permission
        if (!in_array('posts:read', $viewerPermissions, true)) {
            throw new ForbiddenException(['posts:read'], null, "Missing required permission: posts:read");
        }
        
        // Get key's groups for mask resolution
        $keyGroups = $this->groupMemberRepo->findGroupsForKey($viewerKeyIdHex32);
        
        // Resolve access mask for the key on this post
        $accessMask = $this->postAccessRepo->resolveAccessMask($postIdHex32, $viewerKeyIdHex32, $keyGroups);
        
        // Check if key has VIEW access
        if (!PostAccessBitmask::hasView($accessMask)) {
            // Return 404 to hide post existence
            throw new NotFoundException("Post not found");
        }
        
        // Find comments for the post
        $comments = $this->commentRepo->findByPost($postIdHex32, $limit, $beforeIdHex32);
        
        // Determine next cursor (last comment ID if there are results)
        $nextCursor = null;
        if (!empty($comments) && count($comments) === $limit) {
            $nextCursor = end($comments)['comment_id'];
        }
        
        return [
            'comments' => $comments,
            'paging' => [
                'limit' => $limit,
                'before_id' => $beforeIdHex32,
                'next_cursor' => $nextCursor,
            ],
        ];
    }
}
