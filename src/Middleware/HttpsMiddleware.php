<?php
/**
 * CRE8.pw HTTPS Middleware
 * 
 * Enforces HTTPS and applies HSTS headers.
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 5.1
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HTTPS Middleware
 * 
 * Enforces HTTPS and applies HTTP Strict Transport Security (HSTS) headers.
 */
class HttpsMiddleware implements MiddlewareInterface
{
    /**
     * Process request and enforce HTTPS
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if request is HTTPS
        $scheme = $request->getUri()->getScheme();
        $isHttps = $scheme === 'https';

        // Check X-Forwarded-Proto header (for proxies)
        $forwardedProto = $request->getHeaderLine('X-Forwarded-Proto');
        if (!empty($forwardedProto)) {
            $isHttps = strtolower($forwardedProto) === 'https';
        }

        // Redirect HTTP to HTTPS if not in development
        if (!$isHttps && ($_ENV['APP_ENV'] ?? 'production') === 'production') {
            $uri = $request->getUri()->withScheme('https')->withPort(443);
            $response = new \Slim\Psr7\Response();
            return $response
                ->withStatus(301)
                ->withHeader('Location', (string)$uri);
        }

        // Process request
        $response = $handler->handle($request);

        // Apply HSTS headers in production
        if (($env = $_ENV['APP_ENV'] ?? 'production') === 'production' && $isHttps) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
