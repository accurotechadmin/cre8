<?php
/**
 * CRE8.pw Feed Controller (Gateway)
 * 
 * Handles feed endpoints for Gateway JSON surface.
 * Use Key feed with visibility filtering and cursor pagination.
 * Author feed scaffolding.
 * 
 * @see docs/canon/05-Feed-System.md
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\FeedService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Feed Controller (Gateway)
 * 
 * Handles feed endpoints for Gateway JSON surface.
 */
class FeedController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private FeedService $feedService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Get Use Key feed
     * 
     * Use Key feed endpoint with visibility filtering and cursor pagination.
     * 
     * Feed path guard: path useKeyId must match JWT key_id.
     * 
     * Endpoint: GET /api/feed/use/{useKeyId}
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:read
     * 
     * Query Parameters:
     * - limit (default 20, max 100)
     * - before_id (cursor for older posts)
     * - since_id (cursor for newer posts)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function getUseFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $jwtKeyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($jwtKeyIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Get useKeyId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $useKeyIdHex32 = $routeParams['useKeyId'] ?? null;
        
        if ($useKeyIdHex32 === null || !is_string($useKeyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid useKeyId parameter');
        }
        
        // Get query parameters
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        $beforeIdHex32 = isset($queryParams['before_id']) && is_string($queryParams['before_id']) ? $queryParams['before_id'] : null;
        $sinceIdHex32 = isset($queryParams['since_id']) && is_string($queryParams['since_id']) ? $queryParams['since_id'] : null;
        
        // Validate that before_id and since_id are not both provided
        if ($beforeIdHex32 !== null && $sinceIdHex32 !== null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Cannot specify both before_id and since_id');
        }
        
        try {
            $feed = $this->feedService->getUseKeyFeed(
                $useKeyIdHex32,
                $jwtKeyIdHex32,
                $permissions,
                $limit,
                $beforeIdHex32,
                $sinceIdHex32
            );
            return ResponseFactory::single($this->responseFactory, $feed);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\InvalidArgumentException $e) {
            return ErrorFactory::badRequest($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Get Author Key feed
     * 
     * Author feed endpoint (scaffolding).
     * 
     * Endpoint: GET /api/feed/author
     * Auth: Author Key JWT (typ=key, Primary or Secondary)
     * Required Permission: posts:read
     * 
     * Query Parameters:
     * - limit (default 20, max 100)
     * - before_id (cursor for older posts)
     * - since_id (cursor for newer posts)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function getAuthorFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $authorKeyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($authorKeyIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Get query parameters
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        $beforeIdHex32 = isset($queryParams['before_id']) && is_string($queryParams['before_id']) ? $queryParams['before_id'] : null;
        $sinceIdHex32 = isset($queryParams['since_id']) && is_string($queryParams['since_id']) ? $queryParams['since_id'] : null;
        
        // Validate that before_id and since_id are not both provided
        if ($beforeIdHex32 !== null && $sinceIdHex32 !== null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Cannot specify both before_id and since_id');
        }
        
        try {
            $feed = $this->feedService->getAuthorFeed(
                $authorKeyIdHex32,
                $permissions,
                $limit,
                $beforeIdHex32,
                $sinceIdHex32
            );
            return ResponseFactory::single($this->responseFactory, $feed);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\InvalidArgumentException $e) {
            return ErrorFactory::badRequest($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }
}
