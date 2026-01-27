<?php
/**
 * CRE8.pw Keychain Member Repository
 * 
 * Data access for keychain_members table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Keychain Member Repository
 */
class KeychainMemberRepository extends BaseRepository
{
    /**
     * Add key to keychain
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function add(string $keychainIdHex32, string $keyIdHex32): void
    {
        $sql = "INSERT INTO keychain_members (keychain_id, key_id) VALUES (?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($keychainIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);
    }

    /**
     * Remove key from keychain
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     */
    public function remove(string $keychainIdHex32, string $keyIdHex32): void
    {
        $sql = "DELETE FROM keychain_members WHERE keychain_id = ? AND key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($keychainIdHex32),
            Ids::hex32ToBinary($keyIdHex32),
        ]);
    }

    /**
     * Find members of a keychain
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @return array<string> List of key IDs (hex32)
     */
    public function findMembers(string $keychainIdHex32): array
    {
        $sql = "SELECT key_id FROM keychain_members WHERE keychain_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keychainIdHex32)]);

        $members = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $members[] = Ids::binaryToHex32($row['key_id']);
        }

        return $members;
    }
}
