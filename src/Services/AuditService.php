<?php
/**
 * CRE8.pw Audit Service
 * 
 * Provides a clean interface for emitting audit events across the system.
 * Handles actor/subject identification and metadata serialization.
 * 
 * @see docs/canon/11-Logging-Audit-and-Observability.md Section 3
 */

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEventRepository;
use App\Utilities\Ids;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Audit Service
 * 
 * Handles audit event emission for lifecycle actions.
 */
class AuditService
{
    public function __construct(
        private AuditEventRepository $auditEventRepository
    ) {}

    /**
     * Emit an audit event
     * 
     * @param string $actorType Actor type ('owner' or 'key')
     * @param string $actorIdHex32 Actor ID (hex32)
     * @param string $action Action name (e.g., 'keys:mint', 'posts:create')
     * @param string|null $subjectType Subject type (e.g., 'key', 'post', 'group')
     * @param string|null $subjectIdHex32 Subject ID (hex32)
     * @param array<string, mixed>|null $metadata Optional metadata (will be JSON encoded)
     * @param string|null $ip Optional IP address
     * @param string|null $userAgent Optional user agent
     * @return void
     */
    public function emit(
        string $actorType,
        string $actorIdHex32,
        string $action,
        ?string $subjectType = null,
        ?string $subjectIdHex32 = null,
        ?array $metadata = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        // Generate event ID
        $eventIdHex32 = Ids::generateHex32Id();
        
        // Prepare event data
        $eventData = [
            'id' => $eventIdHex32,
            'actor_type' => $actorType,
            'actor_id' => $actorIdHex32,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectIdHex32,
            'metadata' => $metadata,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
        
        // Create audit event
        $this->auditEventRepository->create($eventData);
    }

    /**
     * Extract IP and User-Agent from request
     * 
     * @param ServerRequestInterface|null $request PSR-7 request
     * @return array{ip: string|null, user_agent: string|null}
     */
    public static function extractRequestMetadata(?ServerRequestInterface $request): array
    {
        if ($request === null) {
            return ['ip' => null, 'user_agent' => null];
        }
        
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? null;
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        
        return ['ip' => $ip, 'user_agent' => $userAgent];
    }
}
