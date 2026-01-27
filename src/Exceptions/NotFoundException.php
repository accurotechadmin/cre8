<?php
/**
 * CRE8.pw Not Found Exception
 * 
 * Thrown when a resource does not exist OR when the principal lacks
 * VIEW permission (to hide existence).
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 8
 */

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Not Found Exception
 * 
 * Thrown when a resource does not exist OR when the principal lacks
 * VIEW permission (to hide existence).
 * 
 * Should return 404 Not Found.
 */
class NotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message, 404);
    }
}
