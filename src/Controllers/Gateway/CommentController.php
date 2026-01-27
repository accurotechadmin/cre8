<?php
/**
 * CRE8.pw Comment Controller (Gateway)
 * 
 * Handles comment creation endpoints for Gateway JSON surface.
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 6.2
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\CommentService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Comment Controller (Gateway)
 * 
 * Handles comment-related endpoints for Gateway JSON surface.
 */
class CommentController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private CommentService $commentService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Create a new comment
     * 
     * 
     * Endpoint: POST /api/posts/{postId}/comments
     * Auth: Key JWT (typ=key)
     * Required Permission: comments:write
     * Required Mask: COMMENT (0x02) on the post
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        $commentBody = $body['body'] ?? null;
        
        if ($commentBody === null || !is_string($commentBody)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid body field');
        }
        
        try {
            $comment = $this->commentService->createComment(
                $postIdHex32,
                $keyIdHex32,
                $permissions,
                $commentBody
            );
            return ResponseFactory::created($this->responseFactory, $comment);
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
     * List comments for a post
     * 
     * 
     * Endpoint: GET /api/posts/{postId}/comments
     * Auth: Key JWT (typ=key)
     * Required Permission: posts:read
     * Required Mask: VIEW (0x01) on the post
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
        
        // Parse query parameters
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 20;
        $beforeId = $queryParams['before_id'] ?? null;
        
        try {
            $result = $this->commentService->listComments(
                $postIdHex32,
                $keyIdHex32,
                $permissions,
                $limit,
                $beforeId
            );
            return ResponseFactory::paginated(
                $this->responseFactory,
                $result['comments'],
                $result['paging']['limit'],
                $result['paging']['next_cursor']
            );
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
