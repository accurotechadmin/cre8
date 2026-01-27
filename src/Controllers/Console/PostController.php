<?php
/**
 * CRE8.pw Post Controller (Console)
 * 
 * Handles post management endpoints for Console JSON surface.
 * Owner-scoped admin operations for posts.
 * 
 * @see docs/canon/08-Post-Sharing-and-Access-Control.md Section 4.2
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use App\Services\PostService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Post Controller (Console)
 * 
 * Handles post management endpoints for Console JSON surface.
 */
class PostController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private PostService $postService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * List posts owned by the owner
     * 
     * 
     * Endpoint: GET /console/posts
     * Auth: Owner JWT (typ=owner)
     * Required Permission: posts:admin:read
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get owner_id and permissions from JWT (set by JwtOwnerMiddleware)
        $ownerIdHex32 = $request->getAttribute('owner_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($ownerIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Parse query parameters
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        $beforeId = $queryParams['before_id'] ?? null;
        
        // Validate limit
        if ($limit < 1 || $limit > 100) {
            return ErrorFactory::badRequest($this->responseFactory, 'Limit must be between 1 and 100');
        }
        
        try {
            $result = $this->postService->listPostsByOwner($ownerIdHex32, $permissions, $limit, $beforeId);
            return ResponseFactory::paginated(
                $this->responseFactory,
                $result['posts'],
                $result['paging']['limit'],
                $result['paging']['next_cursor']
            );
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Get post details
     * 
     * 
     * Endpoint: GET /console/posts/{postId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: posts:admin:read
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get owner_id and permissions from JWT (set by JwtOwnerMiddleware)
        $ownerIdHex32 = $request->getAttribute('owner_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($ownerIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Get postId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $postIdHex32 = $routeParams['postId'] ?? null;
        
        if ($postIdHex32 === null || !is_string($postIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid postId parameter');
        }
        
        try {
            $post = $this->postService->getPostForOwner($postIdHex32, $ownerIdHex32, $permissions);
            return ResponseFactory::single($this->responseFactory, $post);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Grant group access to a post
     * 
     * 
     * Endpoint: POST /console/posts/{postId}/access/grant-group
     * Auth: Owner JWT (typ=owner)
     * Required Permission: posts:access:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function grantGroupAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get owner_id and permissions from JWT (set by JwtOwnerMiddleware)
        $ownerIdHex32 = $request->getAttribute('owner_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($ownerIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Get postId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $postIdHex32 = $routeParams['postId'] ?? null;
        
        if ($postIdHex32 === null || !is_string($postIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid postId parameter');
        }
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $groupIdHex32 = $body['group_id'] ?? null;
        $permissionMask = $body['permission_mask'] ?? null;
        
        if ($groupIdHex32 === null || !is_string($groupIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid group_id field');
        }
        
        if ($permissionMask === null || !is_int($permissionMask)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid permission_mask field');
        }
        
        try {
            $grant = $this->postService->grantGroupAccess(
                $postIdHex32,
                $ownerIdHex32,
                $permissions,
                $groupIdHex32,
                $permissionMask
            );
            return ResponseFactory::created($this->responseFactory, $grant);
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
     * Revoke group access to a post
     * 
     * 
     * Endpoint: POST /console/posts/{postId}/access/revoke-group
     * Auth: Owner JWT (typ=owner)
     * Required Permission: posts:access:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function revokeGroupAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get owner_id and permissions from JWT (set by JwtOwnerMiddleware)
        $ownerIdHex32 = $request->getAttribute('owner_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($ownerIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Get route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $postIdHex32 = $routeParams['postId'] ?? null;
        
        if ($postIdHex32 === null || !is_string($postIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid postId parameter');
        }
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $groupIdHex32 = $body['group_id'] ?? null;
        
        if ($groupIdHex32 === null || !is_string($groupIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid group_id field');
        }
        
        try {
            $this->postService->revokeGroupAccess(
                $postIdHex32,
                $ownerIdHex32,
                $permissions,
                $groupIdHex32
            );
            return $this->responseFactory->createResponse(204); // No Content
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }
}
