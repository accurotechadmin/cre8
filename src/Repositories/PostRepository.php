<?php
/**
 * CRE8.pw Post Repository
 * 
 * Data access for posts table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Post Repository
 */
class PostRepository extends BaseRepository
{
    /**
     * Create a new post
     * 
     * @param array<string, mixed> $data Post data
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO posts (id, author_key_id, initial_author_key_id, title, content) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['author_key_id']),
            Ids::hex32ToBinary($data['initial_author_key_id']),
            $data['title'] ?? null,
            $data['content'],
        ]);
    }

    /**
     * Find post by ID
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @return array|null Post data or null if not found
     */
    public function findById(string $postIdHex32): ?array
    {
        $sql = "SELECT * FROM posts WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($postIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'post_id' => Ids::binaryToHex32($row['id']),
            'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
            'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
            'title' => $row['title'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Find posts by author
     * 
     * @param string $authorKeyIdHex32 Author key ID (hex32)
     * @param int $limit Limit
     * @param string|null $beforeIdHex32 Cursor (post ID before this)
     * @return array<array> List of posts
     */
    public function findByAuthor(string $authorKeyIdHex32, int $limit = 20, ?string $beforeIdHex32 = null): array
    {
        $sql = "SELECT * FROM posts WHERE author_key_id = ?";
        $params = [Ids::hex32ToBinary($authorKeyIdHex32)];

        if ($beforeIdHex32 !== null) {
            $sql .= " AND created_at < (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $posts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $posts[] = [
                'post_id' => Ids::binaryToHex32($row['id']),
                'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
                'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $posts;
    }

    /**
     * Find posts by owner (via initial_author_key_id)
     * 
     * Finds all posts created by any key owned by the owner (via initial_author_key_id).
     * 
     * @param array<string> $primaryKeyIdsHex32 Array of owner's primary key IDs (hex32)
     * @param int $limit Limit
     * @param string|null $beforeIdHex32 Cursor (post ID before this)
     * @return array<array> List of posts
     */
    public function findByOwner(array $primaryKeyIdsHex32, int $limit = 20, ?string $beforeIdHex32 = null): array
    {
        if (empty($primaryKeyIdsHex32)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($primaryKeyIdsHex32), '?'));
        $sql = "SELECT * FROM posts WHERE initial_author_key_id IN ({$placeholders})";
        $params = [];
        foreach ($primaryKeyIdsHex32 as $keyId) {
            $params[] = Ids::hex32ToBinary($keyId);
        }

        if ($beforeIdHex32 !== null) {
            $sql .= " AND created_at < (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $posts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $posts[] = [
                'post_id' => Ids::binaryToHex32($row['id']),
                'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
                'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $posts;
    }

    /**
     * Find visible posts for a Use Key (feed query)
     * 
     * Returns posts visible to the Use Key via direct grants or group memberships.
     * Only includes posts with VIEW mask (0x01).
     * 
     * @param string $keyIdHex32 Use Key ID (hex32)
     * @param array<string> $groupIdsHex32 List of group IDs the key belongs to (hex32)
     * @param int $limit Limit (default 20, max 100)
     * @param string|null $beforeIdHex32 Cursor for older posts (post ID)
     * @param string|null $sinceIdHex32 Cursor for newer posts (post ID)
     * @return array<array> List of posts
     */
    public function findVisiblePostsForUseKey(
        string $keyIdHex32,
        array $groupIdsHex32,
        int $limit = 20,
        ?string $beforeIdHex32 = null,
        ?string $sinceIdHex32 = null
    ): array {
        $keyIdBinary = Ids::hex32ToBinary($keyIdHex32);
        
        // Build query to find posts with VIEW access
        // Posts are visible if:
        // 1. Direct grant: post_access.target_type='key' AND post_access.target_id=key_id AND permission_mask & 0x01 > 0
        // 2. Group grant: post_access.target_type='group' AND post_access.target_id IN (groups) AND permission_mask & 0x01 > 0
        $sql = "SELECT DISTINCT p.*
                FROM posts p
                INNER JOIN post_access pa ON p.id = pa.post_id
                LEFT JOIN group_members gm ON pa.target_type = 'group' AND pa.target_id = gm.group_id
                WHERE (
                    -- Direct grant
                    (pa.target_type = 'key' AND pa.target_id = ? AND (pa.permission_mask & 0x01) > 0)
                    OR
                    -- Group grant
                    (pa.target_type = 'group' AND gm.key_id = ? AND (pa.permission_mask & 0x01) > 0)
                )";
        
        $params = [$keyIdBinary, $keyIdBinary];
        
        // Handle pagination cursors
        if ($beforeIdHex32 !== null) {
            // Get older posts (created_at < cursor post's created_at)
            $sql .= " AND p.created_at < (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        } elseif ($sinceIdHex32 !== null) {
            // Get newer posts (created_at > cursor post's created_at)
            $sql .= " AND p.created_at > (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($sinceIdHex32);
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $posts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $posts[] = [
                'post_id' => Ids::binaryToHex32($row['id']),
                'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
                'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }
        
        return $posts;
    }

    /**
     * Find posts by initial author (for Author feed)
     * 
     * Finds posts authored by keys with the same initial_author_key_id.
     * This includes the Author Key itself and all its descendant keys.
     * 
     * @param string $initialAuthorKeyIdHex32 Initial author key ID (hex32)
     * @param int $limit Limit (default 20, max 100)
     * @param string|null $beforeIdHex32 Cursor for older posts (post ID)
     * @param string|null $sinceIdHex32 Cursor for newer posts (post ID)
     * @return array<array> List of posts
     */
    public function findByInitialAuthor(
        string $initialAuthorKeyIdHex32,
        int $limit = 20,
        ?string $beforeIdHex32 = null,
        ?string $sinceIdHex32 = null
    ): array {
        $sql = "SELECT * FROM posts WHERE initial_author_key_id = ?";
        $params = [Ids::hex32ToBinary($initialAuthorKeyIdHex32)];

        // Handle pagination cursors
        if ($beforeIdHex32 !== null) {
            // Get older posts (created_at < cursor post's created_at)
            $sql .= " AND created_at < (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        } elseif ($sinceIdHex32 !== null) {
            // Get newer posts (created_at > cursor post's created_at)
            $sql .= " AND created_at > (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($sinceIdHex32);
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $posts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $posts[] = [
                'post_id' => Ids::binaryToHex32($row['id']),
                'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
                'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $posts;
    }

    /**
     * Find posts visible via group memberships (for Author feed enhancement)
     * 
     * TICKET M13+: Enhance Author feed to include group membership visibility
     * 
     * Returns posts that have been granted to any of the specified groups with VIEW mask.
     * 
     * @param array<string> $groupIdsHex32 List of group IDs (hex32)
     * @param int $limit Limit (default 20, max 100)
     * @param string|null $beforeIdHex32 Cursor for older posts (post ID)
     * @param string|null $sinceIdHex32 Cursor for newer posts (post ID)
     * @return array<array> List of posts
     */
    public function findVisiblePostsForGroups(
        array $groupIdsHex32,
        int $limit = 20,
        ?string $beforeIdHex32 = null,
        ?string $sinceIdHex32 = null
    ): array {
        if (empty($groupIdsHex32)) {
            return [];
        }
        
        // Build query to find posts with VIEW access via group grants
        $placeholders = implode(',', array_fill(0, count($groupIdsHex32), '?'));
        $sql = "SELECT DISTINCT p.*
                FROM posts p
                INNER JOIN post_access pa ON p.id = pa.post_id
                WHERE pa.target_type = 'group'
                AND pa.target_id IN ({$placeholders})
                AND (pa.permission_mask & 0x01) > 0";
        
        $params = [];
        foreach ($groupIdsHex32 as $groupId) {
            $params[] = Ids::hex32ToBinary($groupId);
        }
        
        // Handle pagination cursors
        if ($beforeIdHex32 !== null) {
            $sql .= " AND p.created_at < (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($beforeIdHex32);
        } elseif ($sinceIdHex32 !== null) {
            $sql .= " AND p.created_at > (SELECT created_at FROM posts WHERE id = ?)";
            $params[] = Ids::hex32ToBinary($sinceIdHex32);
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $posts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $posts[] = [
                'post_id' => Ids::binaryToHex32($row['id']),
                'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
                'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }
        
        return $posts;
    }
}
