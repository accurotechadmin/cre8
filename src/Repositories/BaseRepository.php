<?php
/**
 * CRE8.pw Base Repository Abstract Class
 * 
 * Base class for all repositories. Enforces responsibility boundaries:
 * - Repositories handle data access using PDO prepared statements
 * - Repositories handle binary ID conversion (BINARY(16) ↔ hex32)
 * - Repositories MUST NOT contain business logic or authorization checks
 * 
 * @see docs/canon/09-Implementation-Guide.md Section 3.3
 * @see ARCHITECTURE.md Layer Responsibilities
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Utilities\Ids;

/**
 * Base Repository
 * 
 * All repositories extend this class to ensure consistent data access patterns
 * and enforce architectural boundaries.
 * 
 * Repositories are responsible for:
 * - Prepared SQL queries (PDO prepared statements only)
 * - Binary ID handling (BINARY(16) internal, hex32 external)
 * - Data access isolation
 * - Specialized helpers (access resolution, membership joins, lineage lookups)
 * 
 * Repositories MUST NOT:
 * - Access HTTP concerns
 * - Perform authorization checks
 * - Contain business rules
 * - Leak binary IDs beyond repository boundaries
 */
abstract class BaseRepository
{
    /**
     * @param \PDO $pdo Database connection
     */
    public function __construct(
        protected \PDO $pdo
    ) {}

    /**
     * Convert hex32 string to binary ID (BINARY(16))
     * 
     * @param string $hex32 32-character lowercase hex string
     * @return string Binary ID (16 bytes)
     * @throws \InvalidArgumentException If hex32 is invalid
     */
    protected function hex32ToBinary(string $hex32): string
    {
        return Ids::hex32ToBinary($hex32);
    }

    /**
     * Convert binary ID (BINARY(16)) to hex32 string
     * 
     * @param string $binary Binary ID (16 bytes)
     * @return string hex32 string (32-character lowercase hex)
     * @throws \InvalidArgumentException If binary is invalid
     */
    protected function binaryToHex32(string $binary): string
    {
        return Ids::binaryToHex32($binary);
    }

    /**
     * Generate a new random binary ID (BINARY(16))
     * 
     * @return string Binary ID (16 bytes)
     */
    protected function generateBinaryId(): string
    {
        return Ids::generateBinaryId();
    }

    /**
     * Generate a new random hex32 ID
     * 
     * @return string hex32 string (32-character lowercase hex)
     */
    protected function generateHex32Id(): string
    {
        return Ids::generateHex32Id();
    }

    /**
     * Get PDO instance for transaction management
     * 
     * Services use this to orchestrate transactions across multiple repositories.
     * 
     * @return \PDO Database connection
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
