<?php
/**
 * CRE8.pw CSRF Expose Middleware
 * 
 * Exposes CSRF tokens as request attributes and response headers.
 * Used for HTML forms and AJAX requests.
 * 
 * CRITICAL: Only applied to HTML routes.
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 3.3
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF Expose Middleware
 * 
 * Exposes CSRF tokens for HTML forms and AJAX requests.
 */
class CsrfExposeMiddleware implements MiddlewareInterface
{
    /**
     * Process request and expose CSRF tokens
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Get CSRF tokens from request attributes (set by Slim\Csrf\Guard)
        $csrfName = $request->getAttribute('csrf_name');
        $csrfValue = $request->getAttribute('csrf_value');

        if ($csrfName && $csrfValue) {
            // Expose as response headers for AJAX
            $response = $response->withHeader('X-CSRF-Name', $csrfName);
            $response = $response->withHeader('X-CSRF-Value', $csrfValue);
        }

        return $response;
    }
}
