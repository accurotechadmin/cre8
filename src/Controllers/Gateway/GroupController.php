<?php
/**
 * CRE8.pw Group Controller (Gateway)
 * 
 * Handles read-only group endpoints for Gateway JSON surface.
 * Key-scoped read-only group access.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 5
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\GroupService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Group Controller (Gateway)
 * 
 * Handles read-only group endpoints for Gateway JSON surface.
 */
class GroupController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private GroupService $groupService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * List groups
     * 
     * 
     * Endpoint: GET /api/groups
     * Auth: Key JWT (typ=key)
     * Required Permission: groups:read
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
        
        try {
            $groups = $this->groupService->listGroupsForKey($keyIdHex32, $permissions);
            return ResponseFactory::list($this->responseFactory, $groups);
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
     * Get group details
     * 
     * 
     * Endpoint: GET /api/groups/{groupId}
     * Auth: Key JWT (typ=key)
     * Required Permission: groups:read
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
        
        // Get groupId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $groupIdHex32 = $routeParams['groupId'] ?? null;
        
        if ($groupIdHex32 === null || !is_string($groupIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid groupId parameter');
        }
        
        try {
            $group = $this->groupService->getGroupForKey($groupIdHex32, $keyIdHex32, $permissions);
            return ResponseFactory::single($this->responseFactory, $group);
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
     * List group members
     * 
     * 
     * Endpoint: GET /api/groups/{groupId}/members
     * Auth: Key JWT (typ=key)
     * Required Permission: groups:read
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function listMembers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Get groupId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $groupIdHex32 = $routeParams['groupId'] ?? null;
        
        if ($groupIdHex32 === null || !is_string($groupIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid groupId parameter');
        }
        
        try {
            $members = $this->groupService->listGroupMembersForKey($groupIdHex32, $keyIdHex32, $permissions);
            // Return as array of key IDs
            $memberData = array_map(fn($keyId) => ['key_id' => $keyId], $members);
            return ResponseFactory::list($this->responseFactory, $memberData);
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
