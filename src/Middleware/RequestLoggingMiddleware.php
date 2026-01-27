<?php
/**
 * CRE8.pw Request Logging Middleware
 * 
 * Logs request/response summaries to the 'api' channel with structured JSON.
 * Includes request metadata, response status, latency, and authentication context.
 * 
 * @see docs/canon/11-Logging-Audit-and-Observability.md Section 1.1
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LoggingService;
use App\Utilities\Ids;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Request Logging Middleware
 * 
 * Logs API request summaries with structured JSON format.
 */
class RequestLoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $apiLogger;

    public function __construct(LoggerInterface $apiLogger)
    {
        $this->apiLogger = $apiLogger;
    }

    /**
     * Process request and log summary
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        
        // Generate request ID if not present
        $requestId = $request->getAttribute('request_id');
        if (!$requestId) {
            $requestId = 'req_' . bin2hex(random_bytes(8));
            $request = $request->withAttribute('request_id', $requestId);
        }
        
        // Process request
        $response = $handler->handle($request);
        
        // Calculate latency
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);
        
        // Extract authentication context
        $ownerId = $request->getAttribute('owner_id');
        $keyId = $request->getAttribute('key_id');
        $keyPublicId = $request->getAttribute('key_public_id');
        
        // Convert binary IDs to hex32 if present
        if ($ownerId && is_string($ownerId) && strlen($ownerId) === 16) {
            $ownerId = Ids::binaryToHex32($ownerId);
        }
        if ($keyId && is_string($keyId) && strlen($keyId) === 16) {
            $keyId = Ids::binaryToHex32($keyId);
        }
        
        // Build log context
        $logContext = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'latency_ms' => $latencyMs,
        ];
        
        // Add authentication context (never both owner_id and key_id)
        if ($ownerId) {
            $logContext['owner_id'] = $ownerId;
        } elseif ($keyId) {
            $logContext['key_id'] = $keyId;
        }
        
        if ($keyPublicId) {
            $logContext['key_public_id'] = $keyPublicId;
        }
        
        // Add IP and User-Agent
        $serverParams = $request->getServerParams();
        if (isset($serverParams['REMOTE_ADDR'])) {
            $logContext['ip'] = $serverParams['REMOTE_ADDR'];
        }
        
        $userAgent = $request->getHeaderLine('User-Agent');
        if ($userAgent) {
            $logContext['user_agent'] = $userAgent;
        }
        
        // Log request summary
        $message = 'Request completed';
        $level = $response->getStatusCode() >= 400 ? 'WARNING' : 'INFO';
        
        LoggingService::log($this->apiLogger, $level, $message, $logContext);
        
        return $response;
    }
}
