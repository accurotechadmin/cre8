<?php
/**
 * CRE8.pw Post Access Repository
 * 
 * Data access for post_access table.
 * Includes specialized helpers for access resolution.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Post Access Repository
 */
class PostAccessRepository extends BaseRepository
{
    /**
     * Create or update access grant
     * 
     * @param array<string, mixed> $data Access grant data
     * @return void
     */
    public function upsert(array $data): void
    {
        $sql = "INSERT INTO post_access (id, post_id, target_type, target_id, permission_mask)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE permission_mask = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['post_id']),
            $data['target_type'],
            Ids::hex32ToBinary($data['target_id']),
            $data['permission_mask'],
            $data['permission_mask'], // For ON DUPLICATE KEY UPDATE
        ]);
    }

    /**
     * Revoke access grant
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $targetType Target type ('key' or 'group')
     * @param string $targetIdHex32 Target ID (hex32)
     * @return void
     */
    public function revoke(string $postIdHex32, string $targetType, string $targetIdHex32): void
    {
        $sql = "DELETE FROM post_access WHERE post_id = ? AND target_type = ? AND target_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($postIdHex32),
            $targetType,
            Ids::hex32ToBinary($targetIdHex32),
        ]);
    }

    /**
     * Find access grants for a post
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @return array<array> List of access grants
     */
    public function findByPost(string $postIdHex32): array
    {
        $sql = "SELECT * FROM post_access WHERE post_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($postIdHex32)]);

        $grants = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $grants[] = [
                'access_id' => Ids::binaryToHex32($row['id']),
                'post_id' => Ids::binaryToHex32($row['post_id']),
                'target_type' => $row['target_type'],
                'target_id' => Ids::binaryToHex32($row['target_id']),
                'permission_mask' => (int)$row['permission_mask'],
                'created_at' => $row['created_at'],
            ];
        }

        return $grants;
    }

    /**
     * Check if key has access to post (direct grant)
     * 
     * Specialized helper for access resolution.
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return int|null Permission mask or null if no access
     */
    public function findDirectAccess(string $postIdHex32, string $keyIdHex32): ?int
    {
        $sql = "SELECT permission_mask FROM post_access 
                WHERE post_id = ? AND target_type = 'key' AND target_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($postIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return (int)$row['permission_mask'];
    }

    /**
     * Resolve access mask for a key on a post (including group memberships)
     * 
     * Checks:
     * 1. Direct key grant
     * 2. Group grants (if key is member of groups)
     * 
     * Returns the highest permission mask found (bitwise OR).
     * 
     * @param string $postIdHex32 Post ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @param array<string> $groupIdsHex32 List of group IDs the key belongs to (hex32)
     * @return int Permission mask (0 if no access)
     */
    public function resolveAccessMask(string $postIdHex32, string $keyIdHex32, array $groupIdsHex32 = []): int
    {
        $mask = 0;

        // Check direct key grant
        $directAccess = $this->findDirectAccess($postIdHex32, $keyIdHex32);
        if ($directAccess !== null) {
            $mask |= $directAccess;
        }

        // Check group grants
        if (!empty($groupIdsHex32)) {
            $placeholders = implode(',', array_fill(0, count($groupIdsHex32), '?'));
            $sql = "SELECT permission_mask FROM post_access 
                    WHERE post_id = ? AND target_type = 'group' AND target_id IN ({$placeholders})";
            $params = [Ids::hex32ToBinary($postIdHex32)];
            foreach ($groupIdsHex32 as $groupId) {
                $params[] = Ids::hex32ToBinary($groupId);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $mask |= (int)$row['permission_mask'];
            }
        }

        return $mask;
    }
}
