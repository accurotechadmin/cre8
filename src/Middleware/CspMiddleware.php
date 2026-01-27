<?php
/**
 * CRE8.pw CSP Middleware
 * 
 * Applies Content Security Policy headers to HTML responses only.
 * CSP is NOT applied to JSON endpoints.
 * 
 * CRITICAL: CSP is only applied to HTML routes (Console HTML).
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 3
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSP Middleware
 * 
 * Applies Content Security Policy headers to HTML responses only.
 */
class CspMiddleware implements MiddlewareInterface
{
    /**
     * CSP directive value
     * 
     * @var string
     */
    private string $defaultSrc;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->defaultSrc = $_ENV['CSP_DEFAULT_SRC'] ?? "'self'";
    }

    /**
     * Process request and apply CSP headers to HTML responses
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only apply CSP to HTML responses (not JSON)
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_starts_with($contentType, 'text/html')) {
            $cspValue = "default-src {$this->defaultSrc}";
            $response = $response->withHeader('Content-Security-Policy', $cspValue);
        }

        return $response;
    }
}
