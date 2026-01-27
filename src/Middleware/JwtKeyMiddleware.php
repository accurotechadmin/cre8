<?php
/**
 * CRE8.pw JWT Key Middleware
 * 
 * Verifies Key JWT tokens and enforces typ=key.
 * 
 * Security Enforcement:
 * - Enforces typ=key claim (rejects owner tokens)
 * - Extracts key_id and permissions from JWT
 * - Attaches key_id and permissions to request attributes
 * - All Gateway routes must use this middleware
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 5.4
 * @see docs/canon/02-Authentication-and-Identity.md Section 2
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Security\JwtService;
use App\Services\LoggingService;
use App\Utilities\ErrorFactory;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * JWT Key Middleware
 * 
 * Verifies Key JWT tokens and enforces typ=key.
 */
class JwtKeyMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param JwtService $jwtService JWT service for verification
     * @param string $expectedAudience Expected audience claim
     * @param LoggerInterface|null $logger Optional logger for auth channel
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private JwtService $jwtService,
        private string $expectedAudience,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Process request and verify Key JWT
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Extract Bearer token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Missing or invalid authorization header');
        }
        
        $token = substr($authHeader, 7); // Remove "Bearer " prefix
        
        try {
            // Verify JWT and enforce typ=key (rejects tokens with typ=owner or missing typ)
            $payload = $this->jwtService->verify($token, 'key');
            
            // Verify audience matches expected audience
            if (!isset($payload['aud']) || $payload['aud'] !== $this->expectedAudience) {
                return ErrorFactory::unauthorized($this->responseFactory, 'Invalid audience');
            }
            
            // Extract key_id, roles, permissions
            $keyId = $payload['key_id'] ?? null;
            if (!$keyId || !is_string($keyId)) {
                return ErrorFactory::unauthorized($this->responseFactory, 'Missing key_id claim');
            }
            
            $roles = $payload['roles'] ?? [];
            $permissions = $payload['permissions'] ?? [];
            
            if (!is_array($roles)) {
                $roles = [];
            }
            if (!is_array($permissions)) {
                $permissions = [];
            }
            
            // Attach to request attributes
            $request = $request->withAttribute('key_id', $keyId);
            $request = $request->withAttribute('roles', $roles);
            $request = $request->withAttribute('permissions', $permissions);
            
            // Optionally attach key_public_id if present (for debug/correlation)
            if (isset($payload['key_public_id']) && is_string($payload['key_public_id'])) {
                $request = $request->withAttribute('key_public_id', $payload['key_public_id']);
            }
            
            // Log authentication success
            if ($this->logger !== null) {
                LoggingService::log(
                    $this->logger,
                    'INFO',
                    'Key authentication successful',
                    LoggingService::sanitizeContext([
                        'key_id' => $keyId,
                        'path' => $request->getUri()->getPath(),
                    ])
                );
            }
            
            return $handler->handle($request);
        } catch (ExpiredException $e) {
            if ($this->logger !== null) {
                LoggingService::log(
                    $this->logger,
                    'WARNING',
                    'Key authentication failed: token expired',
                    LoggingService::sanitizeContext([
                        'path' => $request->getUri()->getPath(),
                    ])
                );
            }
            return ErrorFactory::unauthorized($this->responseFactory, 'Token expired');
        } catch (BeforeValidException $e) {
            if ($this->logger !== null) {
                LoggingService::log(
                    $this->logger,
                    'WARNING',
                    'Key authentication failed: token not yet valid',
                    LoggingService::sanitizeContext([
                        'path' => $request->getUri()->getPath(),
                    ])
                );
            }
            return ErrorFactory::unauthorized($this->responseFactory, 'Token not yet valid');
        } catch (\InvalidArgumentException $e) {
            // Log authentication success
            if ($this->logger !== null) {
                LoggingService::log(
                    $this->logger,
                    'WARNING',
                    'Key authentication failed: invalid token',
                    LoggingService::sanitizeContext([
                        'path' => $request->getUri()->getPath(),
                        'error' => $e->getMessage(),
                    ])
                );
            }
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid token');
        }
    }
}
