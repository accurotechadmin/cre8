<?php
/**
 * CRE8.pw Key Controller (Gateway)
 * 
 * Handles key minting endpoints for Gateway JSON surface.
 * Key-scoped key issuance (secondary and use keys).
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 5
 * @see docs/canon/07-Key-Lifecycle-and-Provenance.md
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Services\KeyService;
use App\Utilities\ResponseFactory;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Key Controller (Gateway)
 * 
 * Handles key minting endpoints for Gateway JSON surface.
 */
class KeyController extends BaseController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private KeyService $keyService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Mint a Secondary Author Key
     * 
     * 
     * Endpoint: POST /api/keys/{authorKeyId}/secondary
     * Auth: Key JWT (typ=key)
     * Required Permission: keys:issue
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function mintSecondary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Get authorKeyId from route
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $authorKeyIdHex32 = $routeParams['authorKeyId'] ?? '';
        
        if (empty($authorKeyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing authorKeyId parameter');
        }
        
        // Verify that the JWT key_id matches the authorKeyId (author must mint their own keys)
        if ($keyIdHex32 !== $authorKeyIdHex32) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:issue'],
                null,
                'Cannot mint keys for other keys'
            );
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
            $key = $this->keyService->mintSecondaryKey($authorKeyIdHex32, $keyPermissions, $label);
            return ResponseFactory::created($this->responseFactory, $key);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
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
     * Mint a Use Key
     * 
     * 
     * Endpoint: POST /api/keys/{authorKeyId}/use
     * Auth: Key JWT (typ=key)
     * Required Permission: keys:issue
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function mintUse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
        
        // Get authorKeyId from route
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $authorKeyIdHex32 = $routeParams['authorKeyId'] ?? '';
        
        if (empty($authorKeyIdHex32)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Missing authorKeyId parameter');
        }
        
        // Verify that the JWT key_id matches the authorKeyId (author must mint their own keys)
        if ($keyIdHex32 !== $authorKeyIdHex32) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                ['keys:issue'],
                null,
                'Cannot mint keys for other keys'
            );
        }
        
        // Parse request body
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }
        
        $keyPermissions = $body['permissions'] ?? [];
        $label = $body['label'] ?? null;
        $useCountLimit = isset($body['use_count']) ? (int)$body['use_count'] : null;
        $deviceLimit = isset($body['device_limit']) ? (int)$body['device_limit'] : null;
        
        if (!is_array($keyPermissions)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid permissions field');
        }
        
        if ($label !== null && !is_string($label)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid label field');
        }
        
        if ($useCountLimit !== null && $useCountLimit < 1) {
            return ErrorFactory::badRequest($this->responseFactory, 'use_count must be at least 1');
        }
        
        if ($deviceLimit !== null && $deviceLimit < 1) {
            return ErrorFactory::badRequest($this->responseFactory, 'device_limit must be at least 1');
        }
        
        try {
            $key = $this->keyService->mintUseKey(
                $authorKeyIdHex32,
                $keyPermissions,
                $useCountLimit,
                $deviceLimit,
                $label
            );
            return ResponseFactory::created($this->responseFactory, $key);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $e->getRequiredPermissions(),
                $e->getRequiredMask()
            );
        } catch (\App\Exceptions\NotFoundException $e) {
            return ErrorFactory::notFound($this->responseFactory, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ErrorFactory::badRequest($this->responseFactory, $e->getMessage());
        } catch (\Throwable $e) {
            // Let ErrorHandlingMiddleware handle other exceptions
            throw $e;
        }
    }
}
