<?php
/**
 * CRE8.pw Owner Repository
 * 
 * Data access for owners table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Owner Repository
 */
class OwnerRepository extends BaseRepository
{
    /**
     * Create a new owner
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @param string $email Owner email
     * @param string $passwordHash Argon2id password hash
     * @return void
     */
    public function create(string $ownerIdHex32, string $email, string $passwordHash): void
    {
        $sql = "INSERT INTO owners (id, email, password_hash) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($ownerIdHex32),
            $email,
            $passwordHash,
        ]);
    }

    /**
     * Find owner by ID
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @return array|null Owner data or null if not found
     */
    public function findById(string $ownerIdHex32): ?array
    {
        $sql = "SELECT * FROM owners WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($ownerIdHex32)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'owner_id' => Ids::binaryToHex32($row['id']),
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Find owner by email
     * 
     * @param string $email Owner email
     * @return array|null Owner data or null if not found
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM owners WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'owner_id' => Ids::binaryToHex32($row['id']),
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
