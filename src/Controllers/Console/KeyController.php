<?php
/**
 * CRE8.pw Key Controller (Console)
 * 
 * Handles key management endpoints for Console JSON surface.
 * Owner-scoped key CRUD and lifecycle management.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 4
 * @see docs/canon/07-Key-Lifecycle-and-Provenance.md
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use App\Services\KeyService;
use App\Repositories\KeyRepository;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Key Controller (Console)
 * 
 * Handles key management endpoints for Console JSON surface.
 */
class KeyController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private KeyService $keyService,
        private KeyRepository $keyRepository
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Mint a Primary Author Key
     * 
     * 
     * Endpoint: POST /console/keys/primary
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:issue
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function mintPrimary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        $keyPermissions = $body['permissions'] ?? [];
        $label = $body['label'] ?? null;
        
        if (!is_array($keyPermissions)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid permissions field');
        }
        
        if ($label !== null && !is_string($label)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid label field');
        }
        
        try {
            $key = $this->keyService->mintPrimaryKey($ownerIdHex32, $keyPermissions, $label);
            return ResponseFactory::created($this->responseFactory, $key);
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
     * List Owner's keys
     * 
     * 
     * Endpoint: GET /console/keys
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:read
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
            // Service enforces keys:read permission
            $keys = $this->keyService->listKeys($ownerIdHex32, $permissions);
            return ResponseFactory::list($this->responseFactory, $keys);
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
     * Get key details
     * 
     * 
     * Endpoint: GET /console/keys/{keyId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:read
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
        
        // Get keyId from route
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            // Service enforces keys:read permission and ownership verification
            $key = $this->keyService->getKey($keyIdHex32, $ownerIdHex32, $permissions);
            return ResponseFactory::single($this->responseFactory, $key);
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
     * View key lineage tree
     * 
     * 
     * Endpoint: GET /console/keys/{keyId}/lineage
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:read
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function getLineage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Verify owner has keys:read permission
        if (!in_array('keys:read', $permissions, true)) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:read'],
                null
            );
        }
        
        // Get keyId from route
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $key = $this->keyRepository->findById($keyIdHex32);
            
            if ($key === null) {
                return ErrorFactory::notFound($this->responseFactory, 'Key not found');
            }
            
            // Get lineage tree
            $lineage = $this->keyRepository->getLineageTree($keyIdHex32);
            
            return ResponseFactory::single($this->responseFactory, ['lineage' => $lineage]);
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Rotate a key
     * 
     * 
     * Endpoint: POST /console/keys/{keyId}/rotate
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:rotate
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function rotate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Verify owner has keys:rotate permission
        if (!in_array('keys:rotate', $permissions, true)) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:rotate'],
                null
            );
        }
        
        // Get keyId from route
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $result = $this->keyService->rotateKey($keyIdHex32, $ownerIdHex32, 'owner');
            return ResponseFactory::single($this->responseFactory, $result);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ErrorFactory::badRequest($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Activate a key
     * 
     * 
     * Endpoint: POST /console/keys/{keyId}/activate
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:state:update
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function activate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Verify owner has keys:state:update permission
        if (!in_array('keys:state:update', $permissions, true)) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:state:update'],
                null
            );
        }
        
        // Get keyId from route
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $this->keyService->activateKey($keyIdHex32, $ownerIdHex32, 'owner');
            
            $key = $this->keyRepository->findById($keyIdHex32);
            if ($key === null) {
                return ErrorFactory::notFound($this->responseFactory, 'Key not found');
            }
            
            return ResponseFactory::single($this->responseFactory, [
                'key_id' => $keyIdHex32,
                'active' => true,
            ]);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }

    /**
     * Deactivate a key
     * 
     * 
     * Endpoint: POST /console/keys/{keyId}/deactivate
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keys:state:update
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function deactivate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Verify owner has keys:state:update permission
        if (!in_array('keys:state:update', $permissions, true)) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:state:update'],
                null
            );
        }
        
        // Get keyId from route
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        // Parse request body for cascade option
        $body = $request->getParsedBody();
        $cascade = false;
        if (is_array($body) && isset($body['cascade'])) {
            $cascade = (bool)$body['cascade'];
        }
        
        try {
            $count = $this->keyService->deactivateKey($keyIdHex32, $cascade, $ownerIdHex32, 'owner');
            
            return ResponseFactory::single($this->responseFactory, [
                'key_id' => $keyIdHex32,
                'active' => false,
                'keys_deactivated' => $count,
            ]);
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }
}
