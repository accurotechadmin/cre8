<?php
/**
 * CRE8.pw Group Member Repository
 * 
 * Data access for group_members table.
 * Includes specialized helpers for membership lookups.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Group Member Repository
 */
class GroupMemberRepository extends BaseRepository
{
    /**
     * Add key to group
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function add(string $groupIdHex32, string $keyIdHex32): void
    {
        $sql = "INSERT INTO group_members (group_id, key_id) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($groupIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);
    }

    /**
     * Remove key from group
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function remove(string $groupIdHex32, string $keyIdHex32): void
    {
        $sql = "DELETE FROM group_members WHERE group_id = ? AND key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($groupIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);
    }

    /**
     * Find members of a group
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @return array<string> List of key IDs (hex32)
     */
    public function findMembers(string $groupIdHex32): array
    {
        $sql = "SELECT key_id FROM group_members WHERE group_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($groupIdHex32)]);

        $members = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $members[] = Ids::binaryToHex32($row['key_id']);
        }

        return $members;
    }

    /**
     * Find groups for a key
     * 
     * Find all groups a key belongs to.
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array<string> List of group IDs (hex32)
     */
    public function findGroupsForKey(string $keyIdHex32): array
    {
        $sql = "SELECT group_id FROM group_members WHERE key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);

        $groups = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $groups[] = Ids::binaryToHex32($row['group_id']);
        }

        return $groups;
    }

    /**
     * Check if key is member of group
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return bool
     */
    public function isMember(string $groupIdHex32, string $keyIdHex32): bool
    {
        $sql = "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($groupIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
