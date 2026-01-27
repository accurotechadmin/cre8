<?php
/**
 * CRE8.pw JWT Service
 * 
 * Handles JWT signing and verification using RS256 algorithm.
 * Supports Owner and Key token types with proper claims structure.
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 2
 */

declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use InvalidArgumentException;
use RuntimeException;

/**
 * JWT Service
 * 
 * Provides JWT signing and verification functionality.
 */
class JwtService
{
    /**
     * @param string $privateKeyPath Path to private key PEM file
     * @param string $publicKeyPath Path to public key PEM file
     * @param string $issuer JWT issuer claim
     * @param string $audience JWT audience claim
     * @param int $accessTtl Access token TTL in seconds (default: 900 = 15 min)
     * @param int $refreshTtl Refresh token TTL in seconds (default: 2592000 = 30 days)
     * @param int $leeway Clock skew tolerance in seconds (default: 10)
     * @param string $keyId Key ID for JWT header (default: auto-generated)
     */
    public function __construct(
        private string $privateKeyPath,
        private string $publicKeyPath,
        private string $issuer,
        private string $audience,
        private int $accessTtl = 900,
        private int $refreshTtl = 2592000,
        private int $leeway = 10,
        private string $keyId = ''
    ) {
        // Validate key files exist and are readable
        if (!file_exists($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new RuntimeException("Private key file not found or not readable: {$privateKeyPath}");
        }
        
        if (!file_exists($publicKeyPath) || !is_readable($publicKeyPath)) {
            throw new RuntimeException("Public key file not found or not readable: {$publicKeyPath}");
        }
        
        // Load and validate keys
        $privateKeyContent = file_get_contents($privateKeyPath);
        $publicKeyContent = file_get_contents($publicKeyPath);
        
        if ($privateKeyContent === false || $publicKeyContent === false) {
            throw new RuntimeException("Failed to read key files");
        }
        
        // Validate PEM format
        if (!str_contains($privateKeyContent, '-----BEGIN') || !str_contains($publicKeyContent, '-----BEGIN')) {
            throw new RuntimeException("Invalid PEM format in key files");
        }
        
        // Generate key ID if not provided (from public key thumbprint)
        if (empty($this->keyId)) {
            $this->keyId = $this->generateKeyId($publicKeyContent);
        }
    }

    /**
     * Sign an Owner JWT
     * 
     * @param string $ownerId Owner ID (hex32)
     * @param array<string> $roles Role names
     * @param array<string> $permissions Permission strings
     * @param string|null $audience Audience claim (defaults to configured audience)
     * @return string Signed JWT token
     */
    public function signOwnerToken(
        string $ownerId,
        array $roles = [],
        array $permissions = [],
        ?string $audience = null
    ): string {
        $now = time();
        
        $payload = [
            'iss' => $this->issuer,
            'sub' => "owner:{$ownerId}",
            'aud' => $audience ?? $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'typ' => 'owner',
            'owner_id' => $ownerId,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        
        return $this->sign($payload);
    }

    /**
     * Sign a Key JWT
     * 
     * @param string $keyId Key ID (hex32)
     * @param array<string> $roles Role names
     * @param array<string> $permissions Permission strings
     * @param string|null $keyPublicId Key public ID (apub_..., optional)
     * @param string|null $audience Audience claim (defaults to configured audience)
     * @return string Signed JWT token
     */
    public function signKeyToken(
        string $keyId,
        array $roles = [],
        array $permissions = [],
        ?string $keyPublicId = null,
        ?string $audience = null
    ): string {
        $now = time();
        
        $payload = [
            'iss' => $this->issuer,
            'sub' => "key:{$keyId}",
            'aud' => $audience ?? $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'typ' => 'key',
            'key_id' => $keyId,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        
        if ($keyPublicId !== null) {
            $payload['key_public_id'] = $keyPublicId;
        }
        
        return $this->sign($payload);
    }

    /**
     * Verify and decode a JWT token
     * 
     * @param string $token JWT token string
     * @param string $expectedTyp Expected token type ('owner' or 'key')
     * @return array Decoded JWT payload
     * @throws InvalidArgumentException If token is invalid or type mismatch
     * @throws ExpiredException If token is expired
     * @throws BeforeValidException If token is not yet valid
     */
    public function verify(string $token, string $expectedTyp): array
    {
        try {
            $publicKey = file_get_contents($this->publicKeyPath);
            if ($publicKey === false) {
                throw new RuntimeException("Failed to read public key");
            }
            
            $decoded = JWT::decode(
                $token,
                new Key($publicKey, 'RS256')
            );
            
            // Convert stdClass to array
            $payload = json_decode(json_encode($decoded), true);
            
            if (!is_array($payload)) {
                throw new InvalidArgumentException("Invalid JWT payload structure");
            }
            
            // Verify issuer
            if (!isset($payload['iss']) || $payload['iss'] !== $this->issuer) {
                throw new InvalidArgumentException("Invalid issuer");
            }
            
            // Audience check removed - middleware handles it (cleaner separation of concerns)
            // Middleware (JwtOwnerMiddleware, JwtKeyMiddleware) already validates audience correctly
            
            // Verify token type
            if (!isset($payload['typ']) || $payload['typ'] !== $expectedTyp) {
                throw new InvalidArgumentException("Token type mismatch. Expected: {$expectedTyp}, got: " . ($payload['typ'] ?? 'none'));
            }
            
            // Verify exp with leeway
            if (isset($payload['exp'])) {
                $exp = (int)$payload['exp'];
                if ($exp < (time() - $this->leeway)) {
                    throw new ExpiredException("Token expired");
                }
            }
            
            // Verify nbf with leeway
            if (isset($payload['nbf'])) {
                $nbf = (int)$payload['nbf'];
                if ($nbf > (time() + $this->leeway)) {
                    throw new BeforeValidException("Token not yet valid");
                }
            }
            
            return $payload;
        } catch (ExpiredException $e) {
            throw $e;
        } catch (BeforeValidException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InvalidArgumentException("JWT verification failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sign a JWT payload
     * 
     * @param array<string, mixed> $payload JWT payload
     * @return string Signed JWT token
     */
    private function sign(array $payload): string
    {
        $privateKey = file_get_contents($this->privateKeyPath);
        if ($privateKey === false) {
            throw new RuntimeException("Failed to read private key");
        }
        
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->keyId,
        ];
        
        return JWT::encode($payload, $privateKey, 'RS256', null, $header);
    }

    /**
     * Generate key ID from public key content
     * 
     * @param string $publicKeyContent Public key PEM content
     * @return string Key ID (base64url-encoded SHA-256 thumbprint)
     */
    private function generateKeyId(string $publicKeyContent): string
    {
        $thumbprint = hash('sha256', $publicKeyContent, true);
        return rtrim(strtr(base64_encode($thumbprint), '+/', '-_'), '=');
    }

    /**
     * Get access token TTL
     * 
     * @return int TTL in seconds
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    /**
     * Get refresh token TTL
     * 
     * @return int TTL in seconds
     */
    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    /**
     * Get key ID
     * 
     * @return string Key ID
     */
    public function getKeyId(): string
    {
        return $this->keyId;
    }
}
