<?php
/**
 * CRE8.pw CORS Middleware
 * 
 * Applies Cross-Origin Resource Sharing headers based on environment configuration.
 * Handles preflight OPTIONS requests efficiently.
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 5.2
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS Middleware
 * 
 * Applies CORS headers based on environment configuration.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Allowed origins (from CORS_ALLOWED_ORIGINS)
     * 
     * @var array<string>
     */
    private array $allowedOrigins;

    /**
     * Allowed methods (from CORS_ALLOWED_METHODS)
     * 
     * @var array<string>
     */
    private array $allowedMethods;

    /**
     * Allowed headers (from CORS_ALLOWED_HEADERS)
     * 
     * @var array<string>
     */
    private array $allowedHeaders;

    /**
     * Exposed headers (from CORS_EXPOSED_HEADERS)
     * 
     * @var array<string>
     */
    private array $exposedHeaders;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->allowedOrigins = $this->parseCommaSeparated($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $this->allowedMethods = $this->parseCommaSeparated($_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $this->allowedHeaders = $this->parseCommaSeparated($_ENV['CORS_ALLOWED_HEADERS'] ?? 'Authorization,Content-Type');
        $this->exposedHeaders = $this->parseCommaSeparated($_ENV['CORS_EXPOSED_HEADERS'] ?? '');
    }

    /**
     * Process request and apply CORS headers
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            $response = $this->addCorsHeaders($response, $origin);
            return $response->withStatus(204);
        }

        // Process request
        $response = $handler->handle($request);

        // Add CORS headers to response
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Add CORS headers to response
     * 
     * @param ResponseInterface $response PSR-7 response
     * @param string $origin Origin header value
     * @return ResponseInterface
     */
    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // Check if origin is allowed
        if (!empty($origin) && $this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } elseif (empty($this->allowedOrigins)) {
            // If no origins configured, allow all (development only)
            if (($env = $_ENV['APP_ENV'] ?? 'production') === 'development') {
                $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            }
        }

        // Add other CORS headers
        if (!empty($this->allowedMethods)) {
            $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        }

        if (!empty($this->allowedHeaders)) {
            $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        }

        if (!empty($this->exposedHeaders)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }

        // Add credentials header if origin is allowed
        if (!empty($origin) && $this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     * 
     * @param string $origin Origin to check
     * @return bool
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($this->allowedOrigins)) {
            return false;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Parse comma-separated string into array
     * 
     * @param string $value Comma-separated string
     * @return array<string> Trimmed values
     */
    private function parseCommaSeparated(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        $values = explode(',', $value);
        return array_map('trim', array_filter($values));
    }
}
