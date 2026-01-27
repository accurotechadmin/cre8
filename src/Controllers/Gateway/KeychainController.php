<?php
/**
 * CRE8.pw Keychain Controller (Gateway)
 * 
 * Handles external keychain endpoints for Gateway JSON surface.
 * Key-scoped external keychain management (owner_id = NULL).
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 5
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\KeychainService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Keychain Controller (Gateway)
 * 
 * Handles external keychain endpoints for Gateway JSON surface.
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
     * Create external keychain
     * 
     * 
     * Endpoint: POST /api/keychains
     * Auth: Key JWT (typ=key)
     * Required Permission: keychains:manage
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
        
        // Get request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $name = $body['name'] ?? null;
        if ($name === null || !is_string($name)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid name parameter');
        }
        
        try {
            $keychain = $this->keychainService->createExternalKeychain($keyIdHex32, $permissions, $name);
            return ResponseFactory::created($this->responseFactory, $keychain);
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
     * Add member to external keychain
     * 
     * 
     * Endpoint: POST /api/keychains/{id}/members
     * Auth: Key JWT (typ=key)
     * Required Permission: keychains:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function addMember(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Get request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $memberKeyIdHex32 = $body['key_id'] ?? null;
        if ($memberKeyIdHex32 === null || !is_string($memberKeyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid key_id parameter');
        }
        
        try {
            $result = $this->keychainService->addMemberToExternalKeychain(
                $keychainIdHex32,
                $keyIdHex32,
                $permissions,
                $memberKeyIdHex32
            );
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
     * Remove member from external keychain
     * 
     * 
     * Endpoint: DELETE /api/keychains/{id}/members/{keyId}
     * Auth: Key JWT (typ=key)
     * Required Permission: keychains:manage
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function removeMember(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Get keychainId and memberKeyId from route parameters
        $route = $request->getAttribute('route');
        if ($route === null) {
            return ErrorFactory::badRequest($this->responseFactory, 'Route not found');
        }
        $routeParams = $route->getArguments();
        $keychainIdHex32 = $routeParams['id'] ?? null;
        $memberKeyIdHex32 = $routeParams['keyId'] ?? null;
        
        if ($keychainIdHex32 === null || !is_string($keychainIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid id parameter');
        }
        
        if ($memberKeyIdHex32 === null || !is_string($memberKeyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing or invalid keyId parameter');
        }
        
        try {
            $this->keychainService->removeMemberFromExternalKeychain(
                $keychainIdHex32,
                $keyIdHex32,
                $permissions,
                $memberKeyIdHex32
            );
            return ResponseFactory::deleted($this->responseFactory);
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
