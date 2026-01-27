<?php
/**
 * CRE8.pw Group Repository
 * 
 * Data access for groups table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Group Repository
 */
class GroupRepository extends BaseRepository
{
    /**
     * Create a new group
     * 
     * @param array<string, mixed> $data Group data
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO groups (id, owner_id, name) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['owner_id']),
            $data['name'],
        ]);
    }

    /**
     * Find group by ID
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @return array|null Group data or null if not found
     */
    public function findById(string $groupIdHex32): ?array
    {
        $sql = "SELECT * FROM groups WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($groupIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'group_id' => Ids::binaryToHex32($row['id']),
            'owner_id' => Ids::binaryToHex32($row['owner_id']),
            'name' => $row['name'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Find groups by owner
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @return array<array> List of groups
     */
    public function findByOwner(string $ownerIdHex32): array
    {
        $sql = "SELECT * FROM groups WHERE owner_id = ? ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($ownerIdHex32)]);

        $groups = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $groups[] = [
                'group_id' => Ids::binaryToHex32($row['id']),
                'owner_id' => Ids::binaryToHex32($row['owner_id']),
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $groups;
    }

    /**
     * Update group name
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $name New name
     * @return void
     */
    public function updateName(string $groupIdHex32, string $name): void
    {
        $sql = "UPDATE groups SET name = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, Ids::hex32ToBinary($groupIdHex32)]);
    }

    /**
     * Delete group
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @return void
     */
    public function delete(string $groupIdHex32): void
    {
        $sql = "DELETE FROM groups WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($groupIdHex32)]);
    }
}
