<?php
/**
 * CRE8.pw Use Key Limit Middleware
 * 
 * Enforces use_count_limit and device_limit for Use Keys on every API request.
 * This middleware runs after JwtKeyMiddleware to ensure key_id is available.
 * 
 * CRITICAL: Use key limits must be enforced on every request, not just during exchange.
 * 
 * @see docs/canon/07-Key-Lifecycle-and-Provenance.md Section 5
 * @see docs/canon/08-Post-Sharing-and-Access-Control.md Section 6
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\KeyRepository;
use App\Repositories\KeyDeviceRepository;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Use Key Limit Middleware
 * 
 * Enforces use_count_limit and device_limit for Use Keys on every API request.
 */
class UseKeyLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param KeyRepository $keyRepository Key repository
     * @param KeyDeviceRepository $keyDeviceRepository Key device repository
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private KeyRepository $keyRepository,
        private KeyDeviceRepository $keyDeviceRepository
    ) {}

    /**
     * Process request and enforce use key limits
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get key_id from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        
        // If no key_id, pass through (not a key-authenticated request)
        if ($keyIdHex32 === null) {
            return $handler->handle($request);
        }
        
        // Load key to check type and limits
        $key = $this->keyRepository->findById($keyIdHex32);
        
        // If key not found, pass through (will be caught by other middleware)
        if ($key === null) {
            return $handler->handle($request);
        }
        
        // Only enforce limits for Use Keys
        if ($key['type'] !== 'use') {
            return $handler->handle($request);
        }
        
            // Enforce use_count_limit
        if ($key['use_count_limit'] !== null) {
            if ($key['use_count_current'] >= $key['use_count_limit']) {
                return ErrorFactory::create(
                    $this->responseFactory,
                    ErrorFactory::CODE_FORBIDDEN,
                    'Use limit exceeded',
                    ['error_code' => 'use_limit_exceeded'],
                    403
                );
            }
            
            // Increment use count on each request (per-request enforcement)
            $this->keyRepository->incrementUseCount($keyIdHex32);
        }
        
        // Enforce device_limit
        if ($key['device_limit'] !== null) {
            // Generate device fingerprint from IP + User-Agent
            $ip = $this->getClientIp($request);
            $userAgent = $request->getHeaderLine('User-Agent') ?: '';
            $fingerprint = hash('sha256', $ip . $userAgent);
            
            // Check if this device is already registered
            $deviceExists = $this->keyDeviceRepository->exists($keyIdHex32, $fingerprint);
            
            if (!$deviceExists) {
                // New device - check if limit exceeded
                $deviceCount = $this->keyDeviceRepository->countDistinct($keyIdHex32);
                if ($deviceCount >= $key['device_limit']) {
                    return ErrorFactory::create(
                        $this->responseFactory,
                        ErrorFactory::CODE_FORBIDDEN,
                        'Device limit exceeded',
                        ['error_code' => 'device_limit_exceeded'],
                        403
                    );
                }
                
                // Register new device
                $this->keyDeviceRepository->register($keyIdHex32, $fingerprint);
            }
        }
        
        return $handler->handle($request);
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
