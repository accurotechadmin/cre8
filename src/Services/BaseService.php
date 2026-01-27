<?php
/**
 * CRE8.pw Base Service Abstract Class
 * 
 * Base class for all services. Enforces responsibility boundaries:
 * - Services contain business logic, authorization checks, and orchestration
 * - Services MUST NOT access HTTP concerns or write direct SQL queries
 * 
 * @see docs/canon/09-Implementation-Guide.md Section 3.2
 * @see ARCHITECTURE.md Layer Responsibilities
 */

declare(strict_types=1);

namespace App\Services;

/**
 * Base Service
 * 
 * All services extend this class to ensure consistent patterns
 * and enforce architectural boundaries.
 * 
 * Services are responsible for:
 * - Enforcing global permissions (check JWT permissions)
 * - Enforcing post bitmasks (for post-scoped actions)
 * - Enforcing invariants (key type, lineage, immutability)
 * - Orchestrating multiple repositories (transactions)
 * - Emitting audit events
 * - Throwing deterministic exceptions
 * 
 * Services MUST NOT:
 * - Access HTTP concerns (request/response objects)
 * - Write direct SQL queries (use repositories)
 */
abstract class BaseService
{
    // Base service class - no common implementation yet
    // Subclasses will implement business logic specific to their domain
}
