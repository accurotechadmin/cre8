<?php
/**
 * CRE8.pw Refresh Token Repository
 * 
 * Data access for refresh_tokens table.
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Refresh Token Repository
 */
class RefreshTokenRepository extends BaseRepository
{
    /**
     * Create a new refresh token
     * 
     * Uses lookup_hash (SHA-256) for efficient token lookup.
     * 
     * @param array<string, mixed> $data Token data (must include 'lookup_hash' and 'token_hash')
     * @return void
     */
    public function create(array $data): void
    {
        $sql = "INSERT INTO refresh_tokens (
            id, subject_type, subject_id, token_hash, lookup_hash, expires_at,
            replaced_by_id, ip, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            $data['subject_type'],
            Ids::hex32ToBinary($data['subject_id']),
            $data['token_hash'],
            $data['lookup_hash'],  // SHA-256 hash for efficient lookup
            $data['expires_at'],
            isset($data['replaced_by_id']) ? Ids::hex32ToBinary($data['replaced_by_id']) : null,
            $data['ip'] ?? null,
            $data['user_agent'] ?? null,
        ]);
    }

    /**
     * Find token by hash (deprecated - use findByLookupHash instead)
     * 
     * @param string $tokenHash Token hash
     * @return array|null Token data or null if not found
     * @deprecated Use findByLookupHash() instead - Argon2id hashes can't be looked up directly
     */
    public function findByHash(string $tokenHash): ?array
    {
        $sql = "SELECT * FROM refresh_tokens WHERE token_hash = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToArray($row);
    }

    /**
     * Find token by lookup hash (SHA-256)
     * 
     * Efficient token lookup using SHA-256 hash.
     * 
     * @param string $lookupHash SHA-256 hash of the refresh token
     * @return array|null Token data or null if not found
     */
    public function findByLookupHash(string $lookupHash): ?array
    {
        $sql = "SELECT * FROM refresh_tokens WHERE lookup_hash = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lookupHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToArray($row);
    }

    /**
     * Mark token as rotated
     * 
     * @param string $tokenIdHex32 Token ID (hex32)
     * @param string $replacedByIdHex32 Replacement token ID (hex32)
     * @return void
     */
    public function markRotated(string $tokenIdHex32, string $replacedByIdHex32): void
    {
        $sql = "UPDATE refresh_tokens SET rotated_at = NOW(), replaced_by_id = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($replacedByIdHex32),
            Ids::hex32ToBinary($tokenIdHex32),
        ]);
    }

    /**
     * Revoke token
     * 
     * @param string $tokenIdHex32 Token ID (hex32)
     * @return void
     */
    public function revoke(string $tokenIdHex32): void
    {
        $sql = "UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($tokenIdHex32)]);
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
            'token_id' => Ids::binaryToHex32($row['id']),
            'subject_type' => $row['subject_type'],
            'subject_id' => Ids::binaryToHex32($row['subject_id']),
            'token_hash' => $row['token_hash'],
            'issued_at' => $row['issued_at'],
            'expires_at' => $row['expires_at'],
            'revoked_at' => $row['revoked_at'],
            'rotated_at' => $row['rotated_at'],
            'ip' => $row['ip'],
            'user_agent' => $row['user_agent'],
        ];

        if ($row['replaced_by_id']) {
            $result['replaced_by_id'] = Ids::binaryToHex32($row['replaced_by_id']);
        }

        return $result;
    }
}
