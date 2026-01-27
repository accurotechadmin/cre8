<?php
/**
 * CRE8.pw Group Controller (Console)
 * 
 * Handles group management endpoints for Console JSON surface.
 * Owner-scoped group CRUD and membership management.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 4
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use App\Services\GroupService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Group Controller (Console)
 * 
 * Handles group management endpoints for Console JSON surface.
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
     * Create a new group
     * 
     * 
     * Endpoint: POST /console/groups
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $name = $body['name'] ?? null;
        
        if ($name === null || !is_string($name)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid name field');
        }
        
        try {
            $group = $this->groupService->createGroup($ownerIdHex32, $permissions, $name);
            return ResponseFactory::created($this->responseFactory, $group);
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
     * List groups owned by the owner
     * 
     * 
     * Endpoint: GET /console/groups
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
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
        
        try {
            $groups = $this->groupService->listGroups($ownerIdHex32, $permissions);
            return ResponseFactory::list($this->responseFactory, $groups);
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
     * Endpoint: GET /console/groups/{groupId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
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
            $group = $this->groupService->getGroup($groupIdHex32, $ownerIdHex32, $permissions);
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
     * Rename a group
     * 
     * 
     * Endpoint: POST /console/groups/{groupId}/rename
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function rename(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $name = $body['name'] ?? null;
        
        if ($name === null || !is_string($name)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid name field');
        }
        
        try {
            $group = $this->groupService->renameGroup($groupIdHex32, $ownerIdHex32, $permissions, $name);
            return ResponseFactory::single($this->responseFactory, $group);
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
     * Delete a group
     * 
     * 
     * Endpoint: DELETE /console/groups/{groupId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
            $this->groupService->deleteGroup($groupIdHex32, $ownerIdHex32, $permissions);
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

    /**
     * Add a key to a group
     * 
     * 
     * Endpoint: POST /console/groups/{groupId}/members
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function addMember(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $keyIdHex32 = $body['key_id'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid key_id field');
        }
        
        try {
            $result = $this->groupService->addMember($groupIdHex32, $ownerIdHex32, $permissions, $keyIdHex32);
            return ResponseFactory::created($this->responseFactory, $result);
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
     * Remove a key from a group
     * 
     * 
     * Endpoint: DELETE /console/groups/{groupId}/members/{keyId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: groups:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function removeMember(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        $groupIdHex32 = $routeParams['groupId'] ?? null;
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($groupIdHex32 === null || !is_string($groupIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid groupId parameter');
        }
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $this->groupService->removeMember($groupIdHex32, $ownerIdHex32, $permissions, $keyIdHex32);
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
