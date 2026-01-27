<?php
/**
 * CRE8.pw Hashing Service
 * 
 * Provides Argon2id password/secret hashing using ext-sodium.
 * Centralizes hashing configuration and provides consistent interface.
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 4
 * @see docs-organized/08-implementation/dependency-wiring.md
 */

declare(strict_types=1);

namespace App\Security;

/**
 * Hashing Service
 * 
 * Provides Argon2id hashing functionality with configurable cost parameters.
 */
class HashingService
{
    private int $memoryCost;
    private int $timeCost;
    private int $parallelism;

    /**
     * @param int $memoryCost Memory cost in KB (default: 65536 = 64 MB)
     * @param int $timeCost Time cost (default: 4)
     * @param int $parallelism Parallelism factor (default: 1)
     */
    public function __construct(
        int $memoryCost = 65536,  // 64 MB
        int $timeCost = 4,
        int $parallelism = 1
    ) {
        $this->memoryCost = $memoryCost;
        $this->timeCost = $timeCost;
        $this->parallelism = $parallelism;
    }

    /**
     * Hash a password or secret using Argon2id
     * 
     * @param string $value Plaintext value to hash
     * @return string Argon2id hash
     */
    public function hash(string $value): string
    {
        return password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->parallelism,
        ]);
    }

    /**
     * Verify a value against its hash
     * 
     * @param string $value Plaintext value to verify
     * @param string $hash Argon2id hash to verify against
     * @return bool True if value matches hash
     */
    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    /**
     * Check if a hash needs rehashing (e.g., after cost parameter changes)
     * 
     * @param string $hash Hash to check
     * @return bool True if hash should be rehashed
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->parallelism,
        ]);
    }

    /**
     * Generate a cryptographically secure random string for key secrets
     * 
     * @param int $length Length in bytes (default: 24 bytes = 48 hex chars)
     * @return string Secret string with 'sec_' prefix
     */
    public function generateSecret(int $length = 24): string
    {
        return 'sec_' . bin2hex(random_bytes($length));
    }
}
