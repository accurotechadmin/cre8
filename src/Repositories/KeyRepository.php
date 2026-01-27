<?php
/**
 * CRE8.pw Key Repository
 * 
 * Data access for keys table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Key Repository
 */
class KeyRepository extends BaseRepository
{
    /**
     * Create a new key
     * 
     * Note: owner_id is only set for primary keys.
     * 
     * @param array<string, mixed> $data Key data (must include 'owner_id' for primary keys)
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO keys (
            id, owner_id, type, key_secret_hash, permissions_json, active,
            issued_by_key_id, parent_key_id, initial_author_key_id,
            rotated_from_id, rotated_to_id, retired_at,
            use_count_limit, use_count_current, device_limit, label
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            isset($data['owner_id']) ? Ids::hex32ToBinary($data['owner_id']) : null,  // owner_id only for primary keys
            $data['type'],
            $data['key_secret_hash'],
            json_encode($data['permissions'], JSON_THROW_ON_ERROR),
            $data['active'] ?? true,
            isset($data['issued_by_key_id']) ? Ids::hex32ToBinary($data['issued_by_key_id']) : null,
            isset($data['parent_key_id']) ? Ids::hex32ToBinary($data['parent_key_id']) : null,
            Ids::hex32ToBinary($data['initial_author_key_id']),
            isset($data['rotated_from_id']) ? Ids::hex32ToBinary($data['rotated_from_id']) : null,
            isset($data['rotated_to_id']) ? Ids::hex32ToBinary($data['rotated_to_id']) : null,
            $data['retired_at'] ?? null,
            $data['use_count_limit'] ?? null,
            $data['use_count_current'] ?? 0,
            $data['device_limit'] ?? null,
            $data['label'] ?? null,
        ]);
    }

    /**
     * Find key by ID
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array|null Key data or null if not found
     */
    public function findById(string $keyIdHex32): ?array
    {
        $sql = "SELECT * FROM keys WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToArray($row);
    }

    /**
     * Find keys by owner (primary keys only)
     * 
     * Queries by owner_id (only primary keys have owner_id).
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @return array<array> List of primary keys owned by this owner
     */
    public function findByOwner(string $ownerIdHex32): array
    {
        // Query by owner_id (only primary keys have owner_id)
        $sql = "SELECT * FROM keys WHERE owner_id = ? AND type = 'primary'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($ownerIdHex32)]);
        
        $keys = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $keys[] = $this->mapRowToArray($row);
        }
        
        return $keys;
    }

    /**
     * Find keys by initial author
     * 
     * @param string $initialAuthorKeyIdHex32 Initial author key ID (hex32)
     * @return array<array> List of keys
     */
    public function findByInitialAuthor(string $initialAuthorKeyIdHex32): array
    {
        $sql = "SELECT * FROM keys WHERE initial_author_key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($initialAuthorKeyIdHex32)]);
        
        $keys = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $keys[] = $this->mapRowToArray($row);
        }
        
        return $keys;
    }

    /**
     * Update key active status
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param bool $active Active status
     * @return void
     */
    public function updateActive(string $keyIdHex32, bool $active): void
    {
        $sql = "UPDATE keys SET active = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$active, Ids::hex32ToBinary($keyIdHex32)]);
    }

    /**
     * Retire a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function retire(string $keyIdHex32): void
    {
        $sql = "UPDATE keys SET retired_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
    }

    /**
     * Update rotated_to_id and retired_at for a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $rotatedToIdHex32 New key ID that replaced this one (hex32)
     * @return void
     */
    public function markRotated(string $keyIdHex32, string $rotatedToIdHex32): void
    {
        $sql = "UPDATE keys SET rotated_to_id = ?, retired_at = NOW(), active = 0 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($rotatedToIdHex32),
            Ids::hex32ToBinary($keyIdHex32)
        ]);
    }

    /**
     * Deactivate a key and optionally all its descendants (cascade)
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param bool $cascade If true, deactivate all descendants recursively
     * @return int Number of keys deactivated
     */
    public function deactivate(string $keyIdHex32, bool $cascade = false): int
    {
        if ($cascade) {
            // Get all descendants (does not include the key itself)
            $descendants = $this->getDescendants($keyIdHex32);
            $keyIds = [Ids::hex32ToBinary($keyIdHex32)]; // Include the key itself
            foreach ($descendants as $descendant) {
                $keyIds[] = Ids::hex32ToBinary($descendant['key_id']);
            }
            
            // Deactivate all at once
            $placeholders = str_repeat('?,', count($keyIds) - 1) . '?';
            $sql = "UPDATE keys SET active = 0 WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($keyIds);
            
            return count($keyIds);
        } else {
            // Simple deactivation
            $sql = "UPDATE keys SET active = 0 WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
            return 1;
        }
    }

    /**
     * Update use count
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function incrementUseCount(string $keyIdHex32): void
    {
        $sql = "UPDATE keys SET use_count_current = use_count_current + 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
    }

    /**
     * Map database row to array with hex32 IDs
     * 
     * @param array<string, mixed> $row Database row
     * @return array<string, mixed> Mapped array
     */
    private function mapRowToArray(array $row): array
    {
        $result = [
            'key_id' => Ids::binaryToHex32($row['id']),
            'type' => $row['type'],
            'key_secret_hash' => $row['key_secret_hash'],
            'permissions' => json_decode($row['permissions_json'], true),
            'active' => (bool)$row['active'],
            'use_count_limit' => $row['use_count_limit'],
            'use_count_current' => (int)$row['use_count_current'],
            'device_limit' => $row['device_limit'],
            'label' => $row['label'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];

            // Add owner_id if present (only primary keys have owner_id)
        if (isset($row['owner_id']) && $row['owner_id'] !== null) {
            $result['owner_id'] = Ids::binaryToHex32($row['owner_id']);
        }

        if ($row['issued_by_key_id']) {
            $result['issued_by_key_id'] = Ids::binaryToHex32($row['issued_by_key_id']);
        }
        if ($row['parent_key_id']) {
            $result['parent_key_id'] = Ids::binaryToHex32($row['parent_key_id']);
        }
        if ($row['initial_author_key_id']) {
            $result['initial_author_key_id'] = Ids::binaryToHex32($row['initial_author_key_id']);
        }
        if ($row['rotated_from_id']) {
            $result['rotated_from_id'] = Ids::binaryToHex32($row['rotated_from_id']);
        }
        if ($row['rotated_to_id']) {
            $result['rotated_to_id'] = Ids::binaryToHex32($row['rotated_to_id']);
        }
        if ($row['retired_at']) {
            $result['retired_at'] = $row['retired_at'];
        }

        return $result;
    }

    /**
     * Get lineage tree for a key
     * 
     * Specialized helper for lineage tree resolution.
     * 
     * Returns all keys in the lineage tree (parent chain up to root).
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array<array> Lineage tree (root to leaf)
     */
    public function getLineageTree(string $keyIdHex32): array
    {
        $tree = [];
        $currentKeyId = $keyIdHex32;

        // Walk up the parent chain
        while ($currentKeyId !== null) {
            $key = $this->findById($currentKeyId);
            if (!$key) {
                break;
            }

            $tree[] = $key;

            // Move to parent
            $currentKeyId = $key['parent_key_id'] ?? null;
        }

        // Reverse to get root-to-leaf order
        return array_reverse($tree);
    }

    /**
     * Get all descendant keys (children and their children)
     * 
     * Specialized helper for descendant key resolution.
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array<array> List of descendant keys
     */
    public function getDescendants(string $keyIdHex32): array
    {
        $descendants = [];
        $this->collectDescendants($keyIdHex32, $descendants);
        return $descendants;
    }

    /**
     * Recursively collect descendant keys
     * 
     * @param string $parentKeyIdHex32 Parent key ID (hex32)
     * @param array<array> &$descendants Output array
     * @return void
     */
    private function collectDescendants(string $parentKeyIdHex32, array &$descendants): void
    {
        $sql = "SELECT * FROM keys WHERE parent_key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($parentKeyIdHex32)]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $key = $this->mapRowToArray($row);
            $descendants[] = $key;
            // Recursively get children
            $this->collectDescendants($key['key_id'], $descendants);
        }
    }
}
