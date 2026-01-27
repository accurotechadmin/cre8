<?php
/**
 * CRE8.pw Keychain Repository
 * 
 * Data access for keychains table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Keychain Repository
 */
class KeychainRepository extends BaseRepository
{
    /**
     * Create a new keychain
     * 
     * @param array<string, mixed> $data Keychain data
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO keychains (id, name, owner_id) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            $data['name'],
            isset($data['owner_id']) ? Ids::hex32ToBinary($data['owner_id']) : null,
        ]);
    }

    /**
     * Find keychain by ID
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @return array|null Keychain data or null if not found
     */
    public function findById(string $keychainIdHex32): ?array
    {
        $sql = "SELECT * FROM keychains WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keychainIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $result = [
            'keychain_id' => Ids::binaryToHex32($row['id']),
            'name' => $row['name'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];

        if ($row['owner_id']) {
            $result['owner_id'] = Ids::binaryToHex32($row['owner_id']);
        }

        return $result;
    }

    /**
     * Find keychains by owner
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @return array<array> List of keychains
     */
    public function findByOwner(string $ownerIdHex32): array
    {
        $sql = "SELECT * FROM keychains WHERE owner_id = ? ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($ownerIdHex32)]);

        $keychains = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $keychains[] = [
                'keychain_id' => Ids::binaryToHex32($row['id']),
                'owner_id' => Ids::binaryToHex32($row['owner_id']),
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $keychains;
    }

    /**
     * Find external keychains (owner_id IS NULL)
     * 
     * @return array<array> List of external keychains
     */
    public function findExternal(): array
    {
        $sql = "SELECT * FROM keychains WHERE owner_id IS NULL ORDER BY name";
        $stmt = $this->pdo->query($sql);

        $keychains = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $keychains[] = [
                'keychain_id' => Ids::binaryToHex32($row['id']),
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $keychains;
    }
}
