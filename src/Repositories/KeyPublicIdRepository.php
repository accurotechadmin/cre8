<?php
/**
 * CRE8.pw Key Public ID Repository
 * 
 * Data access for key_public_ids table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Key Public ID Repository
 */
class KeyPublicIdRepository extends BaseRepository
{
    /**
     * Create a new key public ID mapping
     * 
     * @param string $idHex32 Record ID (hex32)
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $keyPublicId Key public ID (apub_...)
     * @return void
     */
    public function create(string $idHex32, string $keyIdHex32, string $keyPublicId): void
    {
        $sql = "INSERT INTO key_public_ids (id, key_id, key_public_id) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($idHex32),
            Ids::hex32ToBinary($keyIdHex32),
            $keyPublicId,
        ]);
    }

    /**
     * Find key ID by public ID
     * 
     * @param string $keyPublicId Key public ID (apub_...)
     * @return string|null Key ID (hex32) or null if not found
     */
    public function findKeyIdByPublicId(string $keyPublicId): ?string
    {
        $sql = "SELECT key_id FROM key_public_ids WHERE key_public_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyPublicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return Ids::binaryToHex32($row['key_id']);
    }

    /**
     * Find public ID by key ID
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return string|null Key public ID (apub_...) or null if not found
     */
    public function findPublicIdByKeyId(string $keyIdHex32): ?string
    {
        $sql = "SELECT key_public_id FROM key_public_ids WHERE key_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row['key_public_id'];
    }
}
