<?php
/**
 * CRE8.pw Authentication Service
 * 
 * Handles password hashing/verification and token generation.
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 4
 */

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OwnerRepository;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\KeyRepository;
use App\Repositories\KeyPublicIdRepository;
use App\Repositories\KeyDeviceRepository;
use App\Security\JwtService;
use App\Security\HashingService;
use App\Services\AuditService;
use App\Utilities\Ids;
use App\Exceptions\ForbiddenException;
use InvalidArgumentException;

/**
 * Authentication Service
 * 
 * Handles Owner authentication, password hashing, and token generation.
 */
class AuthService extends BaseService
{
    /**
     * Owner permissions (static list for all owners)
     */
    private const OWNER_PERMISSIONS = [
        'owners:manage',
        'keys:issue',
        'keys:read',
        'keys:rotate',
        'keys:state:update',
        'groups:manage',
        'keychains:manage',
        'posts:admin:read',
        'posts:access:manage',
    ];

    /**
     * @param OwnerRepository $ownerRepository Owner repository
     * @param RefreshTokenRepository $refreshTokenRepository Refresh token repository
     * @param KeyRepository $keyRepository Key repository
     * @param KeyPublicIdRepository $keyPublicIdRepository Key public ID repository
     * @param KeyDeviceRepository $keyDeviceRepository Key device repository (optional table)
     * @param JwtService $jwtService JWT service
     * @param HashingService $hashingService Hashing service
     * @param AuditService $auditService Audit service
     */
    public function __construct(
        private OwnerRepository $ownerRepository,
        private RefreshTokenRepository $refreshTokenRepository,
        private KeyRepository $keyRepository,
        private KeyPublicIdRepository $keyPublicIdRepository,
        private KeyDeviceRepository $keyDeviceRepository,
        private JwtService $jwtService,
        private HashingService $hashingService,
        private AuditService $auditService
    ) {}

    /**
     * Register a new owner
     * 
     * @param string $email Owner email
     * @param string $password Plaintext password
     * @return string Owner ID (hex32)
     * @throws InvalidArgumentException If email already exists or validation fails
     */
    public function registerOwner(string $email, string $password): string
    {
        // Validate email format (RFC 5322)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }

        // Validate password strength (min 8 characters)
        if (strlen($password) < 8) {
            throw new InvalidArgumentException("Password must be at least 8 characters");
        }

        // Check uniqueness
        $existing = $this->ownerRepository->findByEmail($email);
        if ($existing !== null) {
            // Security: Don't reveal if email exists
            throw new InvalidArgumentException("Registration failed");
        }

        // Hash password with Argon2id
        $passwordHash = $this->hashingService->hash($password);

        // Generate owner ID
        $ownerIdHex32 = Ids::generateHex32Id();

        // Insert owner
        $this->ownerRepository->create($ownerIdHex32, $email, $passwordHash);

        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'owners:register',
            subjectType: 'owner',
            subjectIdHex32: $ownerIdHex32,
            metadata: ['email' => $email] // Safe to log email (not a secret)
        );

        return $ownerIdHex32;
    }

    /**
     * Login owner and generate tokens
     * 
     * @param string $email Owner email
     * @param string $password Plaintext password
     * @param string|null $ip Client IP address (optional)
     * @param string|null $userAgent User agent string (optional)
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws InvalidArgumentException If credentials are invalid
     */
    public function loginOwner(
        string $email,
        string $password,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // Lookup owner by email
        $owner = $this->ownerRepository->findByEmail($email);
        
        // Security: Always return generic error (don't reveal if email exists)
        if ($owner === null) {
            throw new InvalidArgumentException("Invalid email or password");
        }

        // Verify password
        if (!$this->hashingService->verify($password, $owner['password_hash'])) {
            throw new InvalidArgumentException("Invalid email or password");
        }

        // Generate Owner JWT
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $audience = $_ENV['JWT_AUDIENCE'] ?? ($baseUrl . '/console');
        
        $accessToken = $this->jwtService->signOwnerToken(
            ownerId: $owner['owner_id'],
            roles: ['owner'],
            permissions: self::OWNER_PERMISSIONS,
            audience: $audience
        );

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken();
        $lookupHash = hash('sha256', $refreshToken);  // SHA-256 hash for efficient lookup
        $refreshTokenHash = $this->hashingService->hash($refreshToken);  // Argon2id hash for verification

        // Store refresh token
        $refreshTokenIdHex32 = Ids::generateHex32Id();
        $expiresAt = date('Y-m-d H:i:s', time() + $this->jwtService->getRefreshTtl());
        
        $this->refreshTokenRepository->create([
            'id' => $refreshTokenIdHex32,
            'subject_type' => 'owner',
            'subject_id' => $owner['owner_id'],
            'token_hash' => $refreshTokenHash,
            'lookup_hash' => $lookupHash,  // SHA-256 hash for efficient lookup
            'expires_at' => $expiresAt,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $owner['owner_id'],
            action: 'owners:login',
            subjectType: 'owner',
            subjectIdHex32: $owner['owner_id'],
            ip: $ip,
            userAgent: $userAgent
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->jwtService->getAccessTtl(),
        ];
    }


    /**
     * Refresh access token using refresh token
     * 
     * Exchange refresh token for new access token.
     * 
     * Implements refresh token rotation and replay detection.
     * 
     * @param string $refreshToken Plaintext refresh token
     * @param string|null $ip Client IP address (optional)
     * @param string|null $userAgent User agent string (optional)
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws InvalidArgumentException If refresh token is invalid, expired, revoked, or already rotated
     */
    public function refreshToken(
        string $refreshToken,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // Lookup token by SHA-256 hash (not Argon2id - Argon2id uses random salts)
        $lookupHash = hash('sha256', $refreshToken);
        $tokenData = $this->refreshTokenRepository->findByLookupHash($lookupHash);
        
        // Security: Always return generic error (don't reveal if token exists)
        if ($tokenData === null) {
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Verify token using HashingService (checks Argon2id hash)
        if (!$this->hashingService->verify($refreshToken, $tokenData['token_hash'])) {
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Verify expires_at is in the future
        $expiresAt = strtotime($tokenData['expires_at']);
        if ($expiresAt === false || $expiresAt < time()) {
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Verify revoked_at is NULL
        if ($tokenData['revoked_at'] !== null) {
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Verify rotated_at is NULL (single-use enforcement)
        if ($tokenData['rotated_at'] !== null) {
            // Replay attack detected
            // Emit audit event (security event)
            $this->auditService->emit(
                actorType: $tokenData['subject_type'],
                actorIdHex32: $tokenData['subject_id'],
                action: 'refresh:replay_attempt',
                subjectType: 'refresh_token',
                subjectIdHex32: $tokenData['token_id'],
                ip: $ip,
                userAgent: $userAgent
            );
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Generate new refresh token ID first (needed for markRotated)
        $newRefreshTokenIdHex32 = Ids::generateHex32Id();
        
        // Generate new access JWT based on subject_type
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        
        if ($tokenData['subject_type'] === 'owner') {
            // Owner refresh: use Owner permissions and /console audience
            $audience = $_ENV['JWT_AUDIENCE'] ?? ($baseUrl . '/console');
            $accessToken = $this->jwtService->signOwnerToken(
                ownerId: $tokenData['subject_id'],
                roles: ['owner'],
                permissions: self::OWNER_PERMISSIONS,
                audience: $audience
            );
        } elseif ($tokenData['subject_type'] === 'key') {
            // Key refresh: load permissions from keys table
            $key = $this->keyRepository->findById($tokenData['subject_id']);
            if ($key === null) {
                throw new InvalidArgumentException("Invalid refresh token");
            }
            
            $permissions = $key['permissions'] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }
            
            $roles = match ($key['type']) {
                'primary' => ['author'],
                'secondary' => ['author'],
                'use' => ['use'],
                default => [],
            };
            
            $keyPublicId = $this->keyPublicIdRepository->findPublicIdByKeyId($tokenData['subject_id']);
            
            $audience = $_ENV['JWT_AUDIENCE_API'] ?? ($baseUrl . '/api');
            $accessToken = $this->jwtService->signKeyToken(
                keyId: $tokenData['subject_id'],
                roles: $roles,
                permissions: $permissions,
                keyPublicId: $keyPublicId,
                audience: $audience
            );
        } else {
            throw new InvalidArgumentException("Invalid refresh token");
        }
        
        // Generate new refresh token
        $newRefreshToken = $this->generateRefreshToken();
        $newLookupHash = hash('sha256', $newRefreshToken);  // SHA-256 hash for efficient lookup
        $newRefreshTokenHash = $this->hashingService->hash($newRefreshToken);  // Argon2id hash for verification
        
        // Store new refresh token (without replaced_by_id - that's set on the old token)
        $expiresAt = date('Y-m-d H:i:s', time() + $this->jwtService->getRefreshTtl());
        
        $this->refreshTokenRepository->create([
            'id' => $newRefreshTokenIdHex32,
            'subject_type' => $tokenData['subject_type'],
            'subject_id' => $tokenData['subject_id'],
            'token_hash' => $newRefreshTokenHash,
            'lookup_hash' => $newLookupHash,  // SHA-256 hash for efficient lookup
            'expires_at' => $expiresAt,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
        
        // Mark old token as rotated (this sets rotated_at and replaced_by_id on the old token)
        $this->refreshTokenRepository->markRotated(
            $tokenData['token_id'],
            $newRefreshTokenIdHex32
        );
        
        // Emit audit event
        $this->auditService->emit(
            actorType: $tokenData['subject_type'],
            actorIdHex32: $tokenData['subject_id'],
            action: 'refresh_token:rotate',
            subjectType: 'refresh_token',
            subjectIdHex32: $newRefreshTokenIdHex32,
            metadata: ['replaced_token_id' => $tokenData['token_id']],
            ip: $ip,
            userAgent: $userAgent
        );
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => $this->jwtService->getAccessTtl(),
        ];
    }

    /**
     * Exchange ApiKey for JWT tokens
     * 
     * Exchange ApiKey (key_public_id + key_secret) for JWT access token.
     * 
     * @param string $keyPublicId Key public ID (apub_...)
     * @param string $keySecret Plaintext key secret
     * @param string|null $ip Client IP address (optional)
     * @param string|null $userAgent User agent string (optional)
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws InvalidArgumentException If credentials are invalid
     */
    public function exchangeApiKey(
        string $keyPublicId,
        string $keySecret,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // Lookup key_id via key_public_ids table
        $keyIdHex32 = $this->keyPublicIdRepository->findKeyIdByPublicId($keyPublicId);
        
        // Security: Always return generic error (don't reveal if key_public_id exists)
        if ($keyIdHex32 === null) {
            throw new InvalidArgumentException("Invalid credentials");
        }
        
        // Load key record from keys table
        $key = $this->keyRepository->findById($keyIdHex32);
        
        // Security: Always return generic error
        if ($key === null) {
            throw new InvalidArgumentException("Invalid credentials");
        }
        
        // Verify key_secret against key_secret_hash using Argon2id
        if (!$this->hashingService->verify($keySecret, $key['key_secret_hash'])) {
            // Emit audit event (optional throttle)
            $this->auditService->emit(
                actorType: 'key',
                actorIdHex32: $keyIdHex32,
                action: 'apikey:exchange:failed',
                subjectType: 'key',
                subjectIdHex32: $keyIdHex32,
                metadata: ['reason' => 'invalid_secret'],
                ip: $ip,
                userAgent: $userAgent
            );
            throw new InvalidArgumentException("Invalid credentials");
        }
        
        // Verify key is active=1
        if (!$key['active']) {
            throw new InvalidArgumentException("Invalid credentials");
        }
        
        // Enforce use_count and device_limit for Use Keys
        if ($key['type'] === 'use') {
            // Enforce use_count_limit
            if ($key['use_count_limit'] !== null) {
                if ($key['use_count_current'] >= $key['use_count_limit']) {
                    throw new ForbiddenException(
                        ['use_limit'],
                        null,
                        "Use limit exceeded"
                    );
                }
                // Increment use count on successful exchange (per-exchange, not per-request)
                $this->keyRepository->incrementUseCount($keyIdHex32);
            }
            
            // Enforce device_limit (if key_devices table exists)
            if ($key['device_limit'] !== null) {
                // Generate device fingerprint from IP + User-Agent
                $fingerprint = hash('sha256', ($ip ?? '') . ($userAgent ?? ''));
                
                // Check if this device is already registered
                $deviceExists = $this->keyDeviceRepository->exists($keyIdHex32, $fingerprint);
                
                if (!$deviceExists) {
                    // New device - check if limit exceeded
                    $deviceCount = $this->keyDeviceRepository->countDistinct($keyIdHex32);
                    if ($deviceCount >= $key['device_limit']) {
                        throw new ForbiddenException(
                            ['device_limit'],
                            null,
                            "Device limit exceeded"
                        );
                    }
                    // Register new device
                    $this->keyDeviceRepository->register($keyIdHex32, $fingerprint);
                }
            }
        }
        
        // Load permissions from keys.permissions_json
        $permissions = $key['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Determine roles based on key type
        $roles = match ($key['type']) {
            'primary' => ['author'],
            'secondary' => ['author'],
            'use' => ['use'],
            default => [],
        };
        
        // Generate Key JWT
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $audience = $_ENV['JWT_AUDIENCE_API'] ?? ($baseUrl . '/api');
        
        $accessToken = $this->jwtService->signKeyToken(
            keyId: $keyIdHex32,
            roles: $roles,
            permissions: $permissions,
            keyPublicId: $keyPublicId,
            audience: $audience
        );
        
        // Generate refresh token
        $refreshToken = $this->generateRefreshToken();
        $lookupHash = hash('sha256', $refreshToken);  // SHA-256 hash for efficient lookup
        $refreshTokenHash = $this->hashingService->hash($refreshToken);  // Argon2id hash for verification

        // Store refresh token
        $refreshTokenIdHex32 = Ids::generateHex32Id();
        $expiresAt = date('Y-m-d H:i:s', time() + $this->jwtService->getRefreshTtl());
        
        $this->refreshTokenRepository->create([
            'id' => $refreshTokenIdHex32,
            'subject_type' => 'key',
            'subject_id' => $keyIdHex32,
            'token_hash' => $refreshTokenHash,
            'lookup_hash' => $lookupHash,  // SHA-256 hash for efficient lookup
            'expires_at' => $expiresAt,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $keyIdHex32,
            action: 'keys:exchange',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32,
            metadata: ['key_public_id' => $keyPublicId],
            ip: $ip,
            userAgent: $userAgent
        );
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->jwtService->getAccessTtl(),
        ];
    }

    /**
     * Generate a secure refresh token
     * 
     * @return string Refresh token (format: rt_<random>)
     */
    private function generateRefreshToken(): string
    {
        // Generate 48 bytes of random data (384 bits)
        $randomBytes = random_bytes(48);
        $token = 'rt_' . bin2hex($randomBytes);
        
        return $token;
    }
}
