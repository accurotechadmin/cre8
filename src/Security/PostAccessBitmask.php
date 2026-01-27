<?php
/**
 * CRE8.pw Post Access Bitmask Utilities
 * 
 * Defines bitmask constants and helper methods for post access permissions.
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 5
 */

declare(strict_types=1);

namespace App\Security;

/**
 * Post Access Bitmask Utilities
 * 
 * Provides constants and helper methods for working with post access bitmasks.
 */
class PostAccessBitmask
{
    /**
     * Bitmask bit positions and hex values
     */
    public const VIEW = 0x01;              // Bit 0: View/read the post
    public const COMMENT = 0x02;           // Bit 1: Create comments on the post
    public const MANAGE_ACCESS = 0x08;     // Bit 3: Manage post access grants/revocations

    /**
     * Bitmask presets (common combinations)
     */
    public const READ_ONLY = 0x01;         // VIEW only
    public const INTERACT = 0x03;          // VIEW + COMMENT (0x01 | 0x02)
    public const ADMIN = 0x0B;            // VIEW + COMMENT + MANAGE_ACCESS (0x01 | 0x02 | 0x08)

    /**
     * Check if a mask grants VIEW permission
     * 
     * @param int $mask Permission mask
     * @return bool True if VIEW is granted
     */
    public static function hasView(int $mask): bool
    {
        return ($mask & self::VIEW) !== 0;
    }

    /**
     * Check if a mask grants COMMENT permission
     * 
     * @param int $mask Permission mask
     * @return bool True if COMMENT is granted
     */
    public static function hasComment(int $mask): bool
    {
        return ($mask & self::COMMENT) !== 0;
    }

    /**
     * Check if a mask grants MANAGE_ACCESS permission
     * 
     * @param int $mask Permission mask
     * @return bool True if MANAGE_ACCESS is granted
     */
    public static function hasManageAccess(int $mask): bool
    {
        return ($mask & self::MANAGE_ACCESS) !== 0;
    }

    /**
     * Combine multiple permissions into a single mask
     * 
     * @param int ...$permissions Permission flags to combine
     * @return int Combined mask
     */
    public static function combine(int ...$permissions): int
    {
        $mask = 0;
        foreach ($permissions as $permission) {
            $mask |= $permission;
        }
        return $mask;
    }

    /**
     * Get preset mask by name
     * 
     * @param string $presetName Preset name ('read_only', 'interact', 'admin')
     * @return int Mask value
     * @throws \InvalidArgumentException If preset name is invalid
     */
    public static function getPreset(string $presetName): int
    {
        return match (strtolower($presetName)) {
            'read_only' => self::READ_ONLY,
            'interact' => self::INTERACT,
            'admin' => self::ADMIN,
            default => throw new \InvalidArgumentException("Invalid preset name: {$presetName}"),
        };
    }

    /**
     * Validate mask value
     * 
     * Ensures mask only uses defined bits (0x01, 0x02, 0x08).
     * 
     * @param int $mask Permission mask
     * @return bool True if valid
     */
    public static function isValid(int $mask): bool
    {
        // Only allow defined bits: 0x01, 0x02, 0x08
        $allowedBits = self::VIEW | self::COMMENT | self::MANAGE_ACCESS;
        return ($mask & ~$allowedBits) === 0;
    }

    /**
     * Get human-readable description of mask
     * 
     * @param int $mask Permission mask
     * @return array<string> List of permission names
     */
    public static function describe(int $mask): array
    {
        $permissions = [];
        
        if (self::hasView($mask)) {
            $permissions[] = 'VIEW';
        }
        if (self::hasComment($mask)) {
            $permissions[] = 'COMMENT';
        }
        if (self::hasManageAccess($mask)) {
            $permissions[] = 'MANAGE_ACCESS';
        }
        
        return $permissions;
    }
}
