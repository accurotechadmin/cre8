<?php
/**
 * CRE8.pw JWKS Controller
 * 
 * Publishes RS256 public keys for JWT verification.
 * Supports key rotation with overlapping validity periods.
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 6
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Security\JwtService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * JWKS Controller
 * 
 * Handles JSON Web Key Set (JWKS) endpoint for public key publishing.
 */
class JwksController extends BaseController
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param JwtService $jwtService JWT service (for key ID)
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private JwtService $jwtService
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Get JWKS (JSON Web Key Set)
     * 
     * Endpoint: GET /.well-known/jwks.json
     * 
     * Returns the public key in JWKS format for JWT verification.
     * Supports key rotation by returning multiple keys during overlap periods.
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function jwks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH'] ?? '';
            
            if (empty($publicKeyPath) || !file_exists($publicKeyPath)) {
                throw new RuntimeException("Public key file not found");
            }
            
            $pem = file_get_contents($publicKeyPath);
            if ($pem === false) {
                throw new RuntimeException("Failed to read public key file");
            }
            
            $publicKey = openssl_pkey_get_public($pem);
            if ($publicKey === false) {
                throw new RuntimeException("Invalid public key format");
            }
            
            $details = openssl_pkey_get_details($publicKey);
            if ($details === false || !isset($details['rsa'])) {
                throw new RuntimeException("Invalid RSA public key");
            }
            
            // Extract modulus and exponent
            $modulus = $details['rsa']['n'];
            $exponent = $details['rsa']['e'];
            
            // Base64url encode (RFC 4648 Section 5)
            $n = rtrim(strtr(base64_encode($modulus), '+/', '-_'), '=');
            $e = rtrim(strtr(base64_encode($exponent), '+/', '-_'), '=');
            
            // Get key ID from JwtService (matches the kid in JWT headers)
            $kid = $this->jwtService->getKeyId();
            
            // Build JWKS response with current key
            $keys = [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $kid,
                    'n' => $n,
                    'e' => $e,
                ]
            ];
            
            // Support key rotation by returning multiple keys during overlap period
            // Check for old key (during rotation overlap period)
            $oldPublicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH_OLD'] ?? null;
            if (!empty($oldPublicKeyPath) && file_exists($oldPublicKeyPath)) {
                $oldPem = file_get_contents($oldPublicKeyPath);
                if ($oldPem !== false) {
                    $oldPublicKey = openssl_pkey_get_public($oldPem);
                    if ($oldPublicKey !== false) {
                        $oldDetails = openssl_pkey_get_details($oldPublicKey);
                        if ($oldDetails !== false && isset($oldDetails['rsa'])) {
                            $oldModulus = $oldDetails['rsa']['n'];
                            $oldExponent = $oldDetails['rsa']['e'];
                            $oldN = rtrim(strtr(base64_encode($oldModulus), '+/', '-_'), '=');
                            $oldE = rtrim(strtr(base64_encode($oldExponent), '+/', '-_'), '=');
                            $oldKid = $_ENV['JWT_KEY_ID_OLD'] ?? ($kid . '-old');
                            
                            // Add old key to the set (for overlap period)
                            $keys[] = [
                                'kty' => 'RSA',
                                'use' => 'sig',
                                'alg' => 'RS256',
                                'kid' => $oldKid,
                                'n' => $oldN,
                                'e' => $oldE,
                            ];
                        }
                    }
                }
            }
            
            $jwks = ['keys' => $keys];
            
            $response->getBody()->write(json_encode($jwks, JSON_THROW_ON_ERROR));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'public, max-age=600, must-revalidate');
        } catch (\Exception $e) {
            // Log error (when logging is available in M16)
            // Return empty keys array on error (don't expose internal errors)
            $jwks = ['keys' => []];
            $response->getBody()->write(json_encode($jwks, JSON_THROW_ON_ERROR));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'public, max-age=600, must-revalidate')
                ->withStatus(500);
        }
    }
}
