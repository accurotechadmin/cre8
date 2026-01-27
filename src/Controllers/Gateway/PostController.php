<?php
/**
 * CRE8.pw Post Controller (Gateway)
 * 
 * Handles post creation and access management endpoints for Gateway JSON surface.
 * 
 * @see docs/canon/08-Post-Sharing-and-Access-Control.md
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\PostService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Post Controller (Gateway)
 * 
 * Handles post-related endpoints for Gateway JSON surface.
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
     * Create a new post
     * 
     * 
     * Endpoint: POST /api/posts
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:create
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        if ($keyIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $content = $body['content'] ?? null;
        $title = $body['title'] ?? null;
        
        if ($content === null || !is_string($content)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid content field');
        }
        
        try {
            $post = $this->postService->createPost($keyIdHex32, $content, $title);
            return ResponseFactory::created($this->responseFactory, $post);
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
     * Grant access to a post
     * 
     * 
     * Endpoint: POST /api/posts/{postId}/access
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:access:manage
     * Required Mask: MANAGE_ACCESS (0x08) on the post
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function grantAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($keyIdHex32 === null) {
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
        
        $targetType = $body['target_type'] ?? null;
        $targetIdHex32 = $body['target_id'] ?? null;
        $permissionMask = $body['permission_mask'] ?? null;
        
        if ($targetType === null || !is_string($targetType)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid target_type field');
        }
        
        if ($targetIdHex32 === null || !is_string($targetIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid target_id field');
        }
        
        if ($permissionMask === null || !is_int($permissionMask)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid permission_mask field');
        }
        
        try {
            $grant = $this->postService->grantAccess(
                $postIdHex32,
                $keyIdHex32,
                $permissions,
                $targetType,
                $targetIdHex32,
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
     * List posts (Gateway)
     * 
     * 
     * Endpoint: GET /api/posts
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:read
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($keyIdHex32 === null) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Verify key has posts:read permission
        if (!in_array('posts:read', $permissions, true)) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['posts:read'],
                null
            );
        }
        
        // Parse query parameters
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        $beforeId = $queryParams['before_id'] ?? null;
        
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        
        try {
            $posts = $this->postService->listPosts($keyIdHex32, $permissions, $limit, $beforeId);
            return ResponseFactory::list($this->responseFactory, $posts);
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Get post details (Gateway)
     * 
     * 
     * Endpoint: GET /api/posts/{postId}
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:read
     * Required Mask: READ (0x01) on the post
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($keyIdHex32 === null) {
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
            $post = $this->postService->getPost($postIdHex32, $keyIdHex32, $permissions);
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
     * Revoke access to a post
     * 
     * 
     * Endpoint: DELETE /api/posts/{postId}/access/{targetType}/{targetId}
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:access:manage
     * Required Mask: MANAGE_ACCESS (0x08) on the post
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function revokeAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get key_id and permissions from JWT (set by JwtKeyMiddleware)
        $keyIdHex32 = $request->getAttribute('key_id');
        $permissions = $request->getAttribute('permissions', []);
        
        if ($keyIdHex32 === null) {
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
        $targetType = $routeParams['targetType'] ?? null;
        $targetIdHex32 = $routeParams['targetId'] ?? null;
        
        if ($postIdHex32 === null || !is_string($postIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid postId parameter');
        }
        
        if ($targetType === null || !is_string($targetType)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid targetType parameter');
        }
        
        if ($targetIdHex32 === null || !is_string($targetIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid targetId parameter');
        }
        
        try {
            $this->postService->revokeAccess(
                $postIdHex32,
                $keyIdHex32,
                $permissions,
                $targetType,
                $targetIdHex32
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
        } catch (\InvalidArgumentException $e) {
            return ErrorFactory::badRequest($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }
}
