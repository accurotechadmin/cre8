<?php
/**
 * CRE8.pw Owner Controller
 * 
 * Handles Owner registration and login endpoints.
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 4
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Owner Controller
 * 
 * Handles Owner registration and login.
 */
class OwnerController extends BaseController
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param AuthService $authService Authentication service
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private AuthService $authService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Register a new owner
     * 
     * Endpoint: POST /console/owners
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }

        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            return ErrorFactory::validationFailed($this->responseFactory, [
                'email' => empty($email) ? ['Email is required'] : [],
                'password' => empty($password) ? ['Password is required'] : [],
            ]);
        }

        try {
            $ownerId = $this->authService->registerOwner($email, $password);
            
            return $this->created([
                'owner_id' => $ownerId,
            ]);
        } catch (\InvalidArgumentException $e) {
            // Security: Don't reveal if email exists
            if (str_contains($e->getMessage(), 'Registration failed')) {
                return ErrorFactory::validationFailed($this->responseFactory, [
                    'email' => ['An account with this email already exists'],
                ]);
            }
            
            return ErrorFactory::validationFailed($this->responseFactory, [
                'email' => str_contains($e->getMessage(), 'email') ? [$e->getMessage()] : [],
                'password' => str_contains($e->getMessage(), 'Password') ? [$e->getMessage()] : [],
            ]);
        }
    }

    /**
     * Login owner
     * 
     * Endpoint: POST /console/login
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }

        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            return ErrorFactory::validationFailed($this->responseFactory, [
                'email' => empty($email) ? ['Email is required'] : [],
                'password' => empty($password) ? ['Password is required'] : [],
            ]);
        }

        try {
            // Get client IP and user agent
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? null;
            
            // Check X-Forwarded-For header (for proxies)
            $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
            if (!empty($forwardedFor)) {
                $ips = explode(',', $forwardedFor);
                $ip = trim($ips[0]);
            }
            
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;

            $tokens = $this->authService->loginOwner($email, $password, $ip, $userAgent);
            
            return $this->single($tokens);
        } catch (\InvalidArgumentException $e) {
            // Security: Always return generic error
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid email or password');
        }
    }

    /**
     * Refresh access token
     * 
     * Endpoint: POST /api/auth/refresh
     * 
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        
        if (!is_array($body)) {
            return ErrorFactory::badRequest($this->responseFactory, 'Invalid request body');
        }

        $refreshToken = $body['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return ErrorFactory::validationFailed($this->responseFactory, [
                'refresh_token' => ['Refresh token is required'],
            ]);
        }

        try {
            // Get client IP and user agent
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? null;
            
            // Check X-Forwarded-For header (for proxies)
            $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
            if (!empty($forwardedFor)) {
                $ips = explode(',', $forwardedFor);
                $ip = trim($ips[0]);
            }
            
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;

            $tokens = $this->authService->refreshToken($refreshToken, $ip, $userAgent);
            
            return $this->single($tokens);
        } catch (\InvalidArgumentException $e) {
            // Security: Always return generic error
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid refresh token');
        }
    }

    /**
     * Exchange ApiKey for JWT tokens
     * 
     * Endpoint: POST /api/auth/exchange
     * 
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function exchange(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Parse Authorization header: "ApiKey <key_public_id>:<key_secret>"
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'ApiKey ')) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        $credentials = substr($authHeader, 7); // Remove "ApiKey " prefix
        
        // Split key_public_id and key_secret
        $parts = explode(':', $credentials, 2);
        if (count($parts) !== 2) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
        
        [$keyPublicId, $keySecret] = $parts;
        
        if (empty($keyPublicId) || empty($keySecret)) {
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }

        try {
            // Get client IP and user agent
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? null;
            
            // Check X-Forwarded-For header (for proxies)
            $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
            if (!empty($forwardedFor)) {
                $ips = explode(',', $forwardedFor);
                $ip = trim($ips[0]);
            }
            
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;

            $tokens = $this->authService->exchangeApiKey($keyPublicId, $keySecret, $ip, $userAgent);
            
            return $this->single($tokens);
        } catch (\InvalidArgumentException $e) {
            // Security: Always return generic error
            return ErrorFactory::unauthorized($this->responseFactory, 'Invalid credentials');
        }
    }
}
