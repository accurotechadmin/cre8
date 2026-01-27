<?php
/**
 * CRE8.pw Forbidden Exception
 * 
 * Thrown when a resource exists and is visible, but the principal
 * lacks required permissions for the action.
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 8
 */

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Forbidden Exception
 * 
 * Thrown when:
 * - The resource exists and principal has VIEW permission, BUT
 * - The principal lacks the required permission/mask for the action
 * 
 * Should return 403 Forbidden with details about required permissions/masks.
 */
class ForbiddenException extends \RuntimeException
{
    /**
     * @param array<string>|null $requiredPermissions Required permissions
     * @param string|null $requiredMask Required mask name (e.g., "COMMENT")
     * @param string $message Error message
     */
    public function __construct(
        private ?array $requiredPermissions = null,
        private ?string $requiredMask = null,
        string $message = 'Access denied'
    ) {
        parent::__construct($message, 403);
    }

    /**
     * Get required permissions
     * 
     * @return array<string>|null
     */
    public function getRequiredPermissions(): ?array
    {
        return $this->requiredPermissions;
    }

    /**
     * Get required mask name
     * 
     * @return string|null
     */
    public function getRequiredMask(): ?string
    {
        return $this->requiredMask;
    }
}
