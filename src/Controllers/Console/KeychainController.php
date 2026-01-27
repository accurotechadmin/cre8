<?php
/**
 * CRE8.pw Keychain Controller (Console)
 * 
 * Handles keychain management endpoints for Console JSON surface.
 * Owner-scoped keychain CRUD and membership management.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 4
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use App\Services\KeychainService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Keychain Controller (Console)
 * 
 * Handles keychain management endpoints for Console JSON surface.
 */
class KeychainController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private KeychainService $keychainService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * List keychains owned by the owner
     * 
     * 
     * Endpoint: GET /console/keychains
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keychains:manage
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
            $keychains = $this->keychainService->listKeychains($ownerIdHex32, $permissions);
            return ResponseFactory::list($this->responseFactory, $keychains);
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
     * Create a new keychain
     * 
     * 
     * Endpoint: POST /console/keychains
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keychains:manage
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
            $keychain = $this->keychainService->createKeychain($ownerIdHex32, $permissions, $name);
            return ResponseFactory::created($this->responseFactory, $keychain);
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
     * Add a key to a keychain
     * 
     * 
     * Endpoint: POST /console/keychains/{id}/members
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keychains:manage
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
        
        // Get keychainId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keychainIdHex32 = $routeParams['id'] ?? null;
        
        if ($keychainIdHex32 === null || !is_string($keychainIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid id parameter');
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
            $result = $this->keychainService->addMember($keychainIdHex32, $ownerIdHex32, $permissions, $keyIdHex32);
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
     * Remove a key from a keychain
     * 
     * 
     * Endpoint: DELETE /console/keychains/{id}/members/{keyId}
     * Auth: Owner JWT (typ=owner)
     * Required Permission: keychains:manage
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
        $keychainIdHex32 = $routeParams['id'] ?? null;
        $keyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keychainIdHex32 === null || !is_string($keychainIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid id parameter');
        }
        
        if ($keyIdHex32 === null || !is_string($keyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $this->keychainService->removeMember($keychainIdHex32, $ownerIdHex32, $permissions, $keyIdHex32);
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
