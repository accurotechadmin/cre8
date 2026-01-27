<?php
/**
 * CRE8.pw Key Device Repository
 * 
 * Data access for key_devices table (optional table for device limit tracking).
 * 
 * NOTE: This table is optional and not in core schema. Device limit enforcement
 * will be skipped if the table doesn't exist.
 * 
 * @see docs/canon/07-Key-Lifecycle-and-Provenance.md Section 5.2
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Utilities\Ids;

/**
 * Key Device Repository
 * 
 * Handles device fingerprint tracking for Use Keys with device limits.
 */
class KeyDeviceRepository extends BaseRepository
{
    /**
     * Check if a device fingerprint exists for a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $fingerprint Device fingerprint (SHA256 hash)
     * @return bool True if device exists
     */
    public function exists(string $keyIdHex32, string $fingerprint): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM key_devices WHERE key_id = ? AND device_fingerprint = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                Ids::hex32ToBinary($keyIdHex32),
                $fingerprint
            ]);
            $count = (int)$stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            // Table doesn't exist - device limit enforcement disabled
            return false;
        }
    }

    /**
     * Count distinct devices for a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @return int Number of distinct devices
     */
    public function countDistinct(string $keyIdHex32): int
    {
        try {
            $sql = "SELECT COUNT(DISTINCT device_fingerprint) FROM key_devices WHERE key_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Table doesn't exist - device limit enforcement disabled
            return 0;
        }
    }

    /**
     * Register a new device fingerprint for a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $fingerprint Device fingerprint (SHA256 hash)
     * @return void
     */
    public function register(string $keyIdHex32, string $fingerprint): void
    {
        try {
            $idHex32 = Ids::generateHex32Id();
            $sql = "INSERT INTO key_devices (id, key_id, device_fingerprint) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                Ids::hex32ToBinary($idHex32),
                Ids::hex32ToBinary($keyIdHex32),
                $fingerprint
            ]);
        } catch (\PDOException $e) {
            // Table doesn't exist - device limit enforcement disabled
            // Silently fail (device limit check will be skipped)
        }
    }
}
