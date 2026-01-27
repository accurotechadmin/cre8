<?php
/**
 * CRE8.pw Template Permission Helpers
 * 
 * TICKET T14.3: Permissions-aware UI
 * 
 * Helper functions for checking permissions in templates.
 * Used to conditionally show/hide or enable/disable UI elements.
 */

/**
 * Check if user has a specific permission
 * 
 * TICKET T14.3: Permissions-aware UI
 * 
 * @param array $permissions Array of permission strings
 * @param string $permission Permission to check (e.g., 'keys:issue', 'groups:manage')
 * @return bool True if permission is present
 */
function hasPermission(array $permissions, string $permission): bool
{
    return in_array($permission, $permissions, true);
}

/**
 * Check if user has any of the specified permissions
 * 
 * TICKET T14.3: Permissions-aware UI
 * 
 * @param array $permissions Array of permission strings
 * @param array $requiredPermissions Array of permission strings to check (OR logic)
 * @return bool True if any permission is present
 */
function hasAnyPermission(array $permissions, array $requiredPermissions): bool
{
    foreach ($requiredPermissions as $permission) {
        if (hasPermission($permissions, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the specified permissions
 * 
 * TICKET T14.3: Permissions-aware UI
 * 
 * @param array $permissions Array of permission strings
 * @param array $requiredPermissions Array of permission strings to check (AND logic)
 * @return bool True if all permissions are present
 */
function hasAllPermissions(array $permissions, array $requiredPermissions): bool
{
    foreach ($requiredPermissions as $permission) {
        if (!hasPermission($permissions, $permission)) {
            return false;
        }
    }
    return true;
}
