<?php
/**
 * CRE8.pw Permission Catalog
 * 
 * Defines all permission strings and role → permission resolution.
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 3
 */

declare(strict_types=1);

namespace App\Security;

/**
 * Permission Catalog
 * 
 * Central catalog of all permission strings and role definitions.
 */
class PermissionCatalog
{
    /**
     * Owner permissions (Console-Scoped)
     * 
     * All owners have these permissions by default.
     */
    public const OWNER_PERMISSIONS = [
        'owners:manage',
        'keys:issue',
        'keys:read',
        'keys:rotate',
        'keys:state:update',
        'groups:manage',
        'keychains:manage',
        'posts:admin:read',
        'posts:access:manage',
    ];

    /**
     * Key permissions (Gateway-Scoped)
     * 
     * These are the available permissions for Keys.
     * Actual permissions vary by mint-time specification and parent envelope.
     */
    public const KEY_PERMISSIONS = [
        'keys:issue',
        'posts:create',
        'posts:read',
        'comments:write',
        'groups:read',
        'keychains:manage',
        'posts:access:manage',
    ];

    /**
     * Use Key forbidden permissions
     * 
     * Use Keys MUST NEVER be granted these permissions.
     */
    public const USE_KEY_FORBIDDEN_PERMISSIONS = [
        'posts:create',
        'keys:issue',
    ];

    /**
     * Resolve role to permissions for Owner
     * 
     * @param string $role Role name (e.g., 'owner')
     * @return array<string> Permission strings
     */
    public static function resolveOwnerRole(string $role): array
    {
        return match ($role) {
            'owner' => self::OWNER_PERMISSIONS,
            default => [],
        };
    }

    /**
     * Resolve role to permissions for Key
     * 
     * Note: Actual permissions come from keys.permissions_json.
     * Roles are informational only.
     * 
     * @param string $role Role name (e.g., 'author', 'use')
     * @return array<string> Typical permission strings for this role (informational)
     */
    public static function resolveKeyRole(string $role): array
    {
        return match ($role) {
            'author' => [
                'keys:issue',
                'posts:create',
                'posts:read',
                'comments:write',
                'groups:read',
                'keychains:manage',
                'posts:access:manage',
            ],
            'use' => [
                'posts:read',
                'comments:write',
                'groups:read',
            ],
            default => [],
        };
    }

    /**
     * Validate permissions for Use Key
     * 
     * Use Keys cannot have posts:create or keys:issue.
     * 
     * @param array<string> $permissions Permission strings to validate
     * @return bool True if valid, false if forbidden permissions present
     */
    public static function validateUseKeyPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (in_array($permission, self::USE_KEY_FORBIDDEN_PERMISSIONS, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if child permissions are subset of parent permissions (envelope rule)
     * 
     * Child permissions must be a subset of parent permissions (child ⊆ parent).
     * 
     * @param array<string> $childPermissions Child key permissions
     * @param array<string> $parentPermissions Parent key permissions
     * @return bool True if child ⊆ parent
     */
    public static function validateEnvelope(array $childPermissions, array $parentPermissions): bool
    {
        foreach ($childPermissions as $permission) {
            if (!in_array($permission, $parentPermissions, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all available permission strings
     * 
     * @return array<string> All permission strings
     */
    public static function getAllPermissions(): array
    {
        return array_unique(array_merge(
            self::OWNER_PERMISSIONS,
            self::KEY_PERMISSIONS
        ));
    }

    /**
     * Validate permission string format
     * 
     * Permissions follow pattern: <resource>:<action>
     * 
     * @param string $permission Permission string
     * @return bool True if valid format
     */
    public static function isValidFormat(string $permission): bool
    {
        // Pattern: <resource>:<action> or <resource>:<subresource>:<action>
        return preg_match('/^[a-z]+(:[a-z]+)*(:[a-z]+)$/', $permission) === 1;
    }
}
