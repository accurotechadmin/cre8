<?php
/**
 * CRE8.pw Feed Service
 * 
 * Handles feed operations for Gateway JSON surface.
 * Visibility resolution and pagination for Use Key feeds and Author feeds.
 * 
 * @see docs/canon/05-Feed-System.md
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\PostRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\KeyRepository;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * Feed Service
 * 
 * Handles feed operations.
 */
class FeedService extends BaseService
{
    public function __construct(
        private PostRepository $postRepo,
        private GroupMemberRepository $groupMemberRepo,
        private KeyRepository $keyRepo
    ) {
    }

    /**
     * Get Use Key feed
     * 
     * Get Use Key feed with visibility filtering and cursor pagination.
     * 
     * Feed path guard: path useKeyId must match JWT key_id (returns 404 on mismatch).
     * 
     * Requirements:
     * - Key must have `posts:read` permission
     * - Path `useKeyId` must match JWT `key_id` (enforced here - returns 404 on mismatch)
     * - Returns posts visible to the Use Key via direct grants or group memberships
     * - Only includes posts with VIEW mask (0x01)
     * 
     * Security: Path guard prevents cross-key snooping by returning 404 (not 403) when
     * path useKeyId doesn't match JWT key_id. This hides the existence of other feeds.
     * 
     * @param string $useKeyIdHex32 Use Key ID from path (hex32)
     * @param string $jwtKeyIdHex32 Key ID from JWT (hex32) - must match useKeyIdHex32
     * @param array $keyPermissions Key permissions from JWT
     * @param int $limit Limit (default 20, max 100)
     * @param string|null $beforeIdHex32 Cursor for older posts (post ID)
     * @param string|null $sinceIdHex32 Cursor for newer posts (post ID)
     * @return array{data: array<array>, paging: array{limit: int, cursor: string|null}}
     * @throws NotFoundException If key not found or path/JWT mismatch (T11.3: path guard)
     * @throws ForbiddenException If key lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function getUseKeyFeed(
        string $useKeyIdHex32,
        string $jwtKeyIdHex32,
        array $keyPermissions,
        int $limit = 20,
        ?string $beforeIdHex32 = null,
        ?string $sinceIdHex32 = null
    ): array {
        // Validate limit
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException("Limit must be between 1 and 100");
        }
        
        // Feed path guard: Validate that path key ID matches JWT key ID
        // Security: prevent cross-key snooping by returning 404 (not 403) when mismatch
        // This hides the existence of other feeds from unauthorized principals
        if ($useKeyIdHex32 !== $jwtKeyIdHex32) {
            throw new NotFoundException("Feed not found");
        }
        
        // Verify key has posts:read permission
        if (!in_array('posts:read', $keyPermissions, true)) {
            throw new ForbiddenException(['posts:read'], null, "Missing required permission: posts:read");
        }
        
        // Verify key exists
        $key = $this->keyRepo->findById($useKeyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Get groups the key belongs to
        $groupIdsHex32 = $this->groupMemberRepo->findGroupsForKey($useKeyIdHex32);
        
        // Find visible posts
        $posts = $this->postRepo->findVisiblePostsForUseKey(
            $useKeyIdHex32,
            $groupIdsHex32,
            $limit,
            $beforeIdHex32,
            $sinceIdHex32
        );
        
        // Determine cursor (last post ID in result set, or null if empty)
        $cursor = null;
        if (!empty($posts)) {
            $cursor = $posts[count($posts) - 1]['post_id'];
        }
        
        return [
            'data' => $posts,
            'paging' => [
                'limit' => $limit,
                'cursor' => $cursor,
            ],
        ];
    }

    /**
     * Get Author Key feed
     * 
     * Get Author Key feed
     * 
     * Requirements:
     * - Key must be an Author Key (Primary or Secondary)
     * - Key must have `posts:read` permission
     * - Returns posts authored by the Author Key or its descendants, OR
     *   posts visible via group memberships
     * 
     * Note: This is scaffolding - full implementation may be enhanced in future tickets.
     * 
     * @param string $authorKeyIdHex32 Author Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @param int $limit Limit (default 20, max 100)
     * @param string|null $beforeIdHex32 Cursor for older posts (post ID)
     * @param string|null $sinceIdHex32 Cursor for newer posts (post ID)
     * @return array{data: array<array>, paging: array{limit: int, cursor: string|null}}
     * @throws NotFoundException If key not found or not an Author Key
     * @throws ForbiddenException If key lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function getAuthorFeed(
        string $authorKeyIdHex32,
        array $keyPermissions,
        int $limit = 20,
        ?string $beforeIdHex32 = null,
        ?string $sinceIdHex32 = null
    ): array {
        // Validate limit
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException("Limit must be between 1 and 100");
        }
        
        // Verify key has posts:read permission
        if (!in_array('posts:read', $keyPermissions, true)) {
            throw new ForbiddenException(['posts:read'], null, "Missing required permission: posts:read");
        }
        
        // Verify key exists and is an Author Key (Primary or Secondary)
        $key = $this->keyRepo->findById($authorKeyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Verify key is an Author Key (not a Use Key)
        if ($key['type'] === 'use') {
            throw new NotFoundException("Author feed not available for Use Keys");
        }
        
        // Get groups the key belongs to
        $groupIdsHex32 = $this->groupMemberRepo->findGroupsForKey($authorKeyIdHex32);
        
        // Find posts authored by this key or its descendants (via initial_author_key_id)
        // For scaffolding, we'll use the initial_author_key_id to find posts
        // Full implementation would need to find all descendant keys
        $initialAuthorKeyIdHex32 = $key['initial_author_key_id'];
        
        // Find posts authored by keys with the same initial_author_key_id
        // This includes the Author Key itself and all its descendants
        $authoredPosts = $this->postRepo->findByInitialAuthor($initialAuthorKeyIdHex32, $limit * 2, $beforeIdHex32, $sinceIdHex32);
        
        // TICKET M13+: Enhance to also include posts visible via group memberships
        // Find posts visible via group memberships (if key belongs to groups)
        $groupVisiblePosts = [];
        if (!empty($groupIdsHex32)) {
            $groupVisiblePosts = $this->postRepo->findVisiblePostsForGroups(
                $groupIdsHex32,
                $limit * 2,
                $beforeIdHex32,
                $sinceIdHex32
            );
        }
        
        // Merge and deduplicate posts (by post_id)
        $allPosts = [];
        $seenPostIds = [];
        
        // Add authored posts
        foreach ($authoredPosts as $post) {
            $postId = $post['post_id'];
            if (!in_array($postId, $seenPostIds, true)) {
                $allPosts[] = $post;
                $seenPostIds[] = $postId;
            }
        }
        
        // Add group-visible posts
        foreach ($groupVisiblePosts as $post) {
            $postId = $post['post_id'];
            if (!in_array($postId, $seenPostIds, true)) {
                $allPosts[] = $post;
                $seenPostIds[] = $postId;
            }
        }
        
        // Sort by created_at DESC and limit
        usort($allPosts, function($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
        
        $posts = array_slice($allPosts, 0, $limit);
        
        // Determine cursor (last post ID in result set, or null if empty)
        $cursor = null;
        if (!empty($posts)) {
            $cursor = $posts[count($posts) - 1]['post_id'];
        }
        
        return [
            'data' => $posts,
            'paging' => [
                'limit' => $limit,
                'cursor' => $cursor,
            ],
        ];
    }
}
