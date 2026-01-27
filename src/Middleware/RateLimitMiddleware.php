<?php
/**
 * CRE8.pw Rate Limit Middleware
 * 
 * Throttles requests using Symfony rate limiter.
 * Supports multiple buckets (GENERAL, AUTH, API) with different keying strategies.
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 5.3
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LoggingService;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Rate Limit Middleware
 * 
 * Throttles requests based on configured buckets and keying strategy.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param RateLimiterFactory $generalLimiter General bucket limiter
     * @param RateLimiterFactory $authLimiter Auth bucket limiter
     * @param RateLimiterFactory $apiLimiter API bucket limiter
     * @param RateLimiterFactory $consoleLimiter Console bucket limiter
     * @param LoggerInterface|null $logger Optional logger for security channel
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private RateLimiterFactory $generalLimiter,
        private RateLimiterFactory $authLimiter,
        private RateLimiterFactory $apiLimiter,
        private RateLimiterFactory $consoleLimiter,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Process request and apply rate limiting
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Determine bucket based on route
        $bucket = $this->determineBucket($request);
        
        // Get limiter for bucket
        $limiter = match ($bucket) {
            'AUTH' => $this->authLimiter,
            'API' => $this->apiLimiter,
            'CONSOLE' => $this->consoleLimiter,
            default => $this->generalLimiter,
        };

        // Get key for rate limiting
        $key = $this->getRateLimitKey($request, $bucket);
        
        // Check rate limit
        $rateLimit = $limiter->create($key);
        $limit = $rateLimit->consume();
        
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()?->getTimestamp() - time();
            
            // Log rate limit exceeded
            if ($this->logger !== null) {
                LoggingService::log(
                    $this->logger,
                    'WARNING',
                    'Rate limit exceeded',
                    LoggingService::sanitizeContext([
                        'bucket' => $bucket,
                        'key' => $key,
                        'path' => $request->getUri()->getPath(),
                        'method' => $request->getMethod(),
                        'retry_after' => $retryAfter,
                    ])
                );
            }
            
            return ErrorFactory::rateLimited(
                $this->responseFactory,
                $retryAfter > 0 ? $retryAfter : null
            );
        }

        return $handler->handle($request);
    }

    /**
     * Determine rate limit bucket based on route
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @return string Bucket name ('GENERAL', 'AUTH', 'API', or 'CONSOLE')
     */
    private function determineBucket(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        
        // Auth endpoints use AUTH bucket
        if (str_starts_with($path, '/api/auth/') || $path === '/console/login' || $path === '/console/owners') {
            return 'AUTH';
        }
        
        // Gateway API endpoints use API bucket
        if (str_starts_with($path, '/api/') && !str_starts_with($path, '/api/auth/')) {
            return 'API';
        }
        
        // Console JSON endpoints use CONSOLE bucket
        if (str_starts_with($path, '/console/') && $path !== '/console/login' && $path !== '/console/owners') {
            return 'CONSOLE';
        }
        
        // Default to GENERAL
        return 'GENERAL';
    }

    /**
     * Get rate limit key based on bucket and request
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param string $bucket Bucket name
     * @return string Rate limit key
     */
    private function getRateLimitKey(ServerRequestInterface $request, string $bucket): string
    {
        // For AUTH and GENERAL buckets, use IP address
        if ($bucket === 'AUTH' || $bucket === 'GENERAL') {
            return $this->getClientIp($request);
        }
        
        // For CONSOLE bucket, use owner_id if available (set by JwtOwnerMiddleware)
        if ($bucket === 'CONSOLE') {
            $ownerId = $request->getAttribute('owner_id');
            if ($ownerId) {
                return "owner:{$ownerId}";
            }
            
            // Fallback to IP if no owner_id (shouldn't happen after JwtOwnerMiddleware)
            return $this->getClientIp($request);
        }
        
        // For API bucket, use principal ID if available
        if ($bucket === 'API') {
            $keyId = $request->getAttribute('key_id');
            if ($keyId) {
                return "key:{$keyId}";
            }
            
            // Fallback to IP if no key_id
            return $this->getClientIp($request);
        }
        
        return $this->getClientIp($request);
    }

    /**
     * Get client IP address
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @return string IP address
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        // Check X-Forwarded-For header (for proxies)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwardedFor)) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }
        
        // Check X-Real-IP header
        $realIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($realIp)) {
            return trim($realIp);
        }
        
        // Fallback to server parameter
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
