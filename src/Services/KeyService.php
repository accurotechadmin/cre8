<?php
/**
 * CRE8.pw Key Service
 * 
 * Handles key minting with permission envelope validation.
 * Enforces child ⊆ parent rule and Use Key restrictions.
 * 
 * @see docs/canon/03-Authorization-and-Permissions.md Section 7
 * @see docs/canon/07-Key-Lifecycle-and-Provenance.md
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Services\LoggingService;
use App\Repositories\KeyRepository;
use App\Repositories\KeyPublicIdRepository;
use App\Security\PermissionCatalog;
use App\Security\HashingService;
use App\Utilities\Ids;
use App\Utilities\SensitiveDataSanitizer;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Key Service
 * 
 * Handles key minting operations with permission validation.
 */
class KeyService extends BaseService
{
    public function __construct(
        private KeyRepository $keyRepo,
        private KeyPublicIdRepository $keyPublicIdRepo,
        private HashingService $hashingService,
        private AuditService $auditService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Mint a Primary Author Key (Owner → Console)
     * 
     * CRITICAL SECURITY RULES:
     * - key_secret is returned ONLY ONCE in the response (never again)
     * - key_secret MUST NEVER be logged (use SensitiveDataSanitizer before logging)
     * - Client must store key_secret securely; it cannot be retrieved later
     * 
     * @param string $ownerIdHex32 Owner ID (hex32)
     * @param array<string> $permissions Requested permissions
     * @param string|null $label Optional label
     * @return array{key_id: string, key_public_id: string, key_secret: string}
     * @throws InvalidArgumentException If validation fails
     */
    public function mintPrimaryKey(
        string $ownerIdHex32,
        array $permissions,
        ?string $label = null
    ): array {
        // Validate permissions format
        foreach ($permissions as $permission) {
            if (!PermissionCatalog::isValidFormat($permission)) {
                throw new InvalidArgumentException("Invalid permission format: {$permission}");
            }
        }

        // Generate key_id
        $keyIdHex32 = Ids::generateHex32Id();
        
        // Generate key_public_id (apub_...)
        $keyPublicId = 'apub_' . bin2hex(random_bytes(8));
        
        // Generate key_secret (long random string)
        $keySecret = 'sec_' . bin2hex(random_bytes(24)); // 48 hex chars = 24 bytes
        
        // Hash key_secret with Argon2id
        $keySecretHash = $this->hashingService->hash($keySecret);
        
        // Prepare key data
        $keyData = [
            'id' => $keyIdHex32,
            'owner_id' => $ownerIdHex32,  // owner_id only for primary keys
            'type' => 'primary',
            'key_secret_hash' => $keySecretHash,
            'permissions' => $permissions,
            'active' => true,
            'issued_by_key_id' => null, // Primary keys have no issuer
            'parent_key_id' => null, // Primary keys have no parent
            'initial_author_key_id' => $keyIdHex32, // Self-reference for primary keys
            'use_count_limit' => null,
            'use_count_current' => 0,
            'device_limit' => null,
            'label' => $label,
        ];
        
        // Wrap in transaction for atomicity (key + key_public_id must be created together)
        $pdo = $this->keyRepo->getPdo();
        $pdo->beginTransaction();
        try {
            // Store key
            $this->keyRepo->create($keyData);
            
            // Store key_public_id mapping
            $publicIdRecordId = Ids::generateHex32Id();
            $this->keyPublicIdRepo->create($publicIdRecordId, $keyIdHex32, $keyPublicId);
            
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // Log transaction failure for debugging
            LoggingService::log(
                $this->logger,
                'ERROR',
                'Transaction rollback in mintPrimaryKey: ' . $e->getMessage(),
                LoggingService::sanitizeContext([
                    'method' => __METHOD__,
                    'exception' => get_class($e),
                    'key_id' => $keyIdHex32 ?? null,
                    'owner_id' => $ownerIdHex32 ?? null,
                ])
            );
            throw $e;
        }
        
        // Emit audit event (actor is owner minting primary key)
        $metadata = ['type' => 'primary', 'permissions' => $permissions];
        if ($label !== null) {
            $metadata['label'] = $label;
        }
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'keys:mint',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32,
            metadata: SensitiveDataSanitizer::sanitize($metadata) // Ensure no secrets in metadata
        );
        
        return [
            'key_id' => $keyIdHex32,
            'key_public_id' => $keyPublicId,
            'key_secret' => $keySecret, // Returned ONCE, never again (T7.2)
            // NOTE: key_secret is only returned here. Never return it again.
            // Never log key_secret. Use SensitiveDataSanitizer before logging.
        ];
    }

    /**
     * Mint a Secondary Author Key (Author Key → Gateway)
     * 
     * CRITICAL SECURITY RULES:
     * - key_secret is returned ONLY ONCE in the response (never again)
     * - key_secret MUST NEVER be logged (use SensitiveDataSanitizer before logging)
     * - Client must store key_secret securely; it cannot be retrieved later
     * 
     * @param string $parentKeyIdHex32 Parent key ID (hex32)
     * @param array<string> $permissions Requested permissions
     * @param string|null $label Optional label
     * @return array{key_id: string, key_public_id: string, key_secret: string}
     * @throws InvalidArgumentException If validation fails
     * @throws NotFoundException If parent key not found
     * @throws ForbiddenException If parent cannot mint or envelope violation
     */
    public function mintSecondaryKey(
        string $parentKeyIdHex32,
        array $permissions,
        ?string $label = null
    ): array {
        // Load parent key
        $parentKey = $this->keyRepo->findById($parentKeyIdHex32);
        if ($parentKey === null) {
            throw new NotFoundException("Parent key not found");
        }
        
        // Verify parent key is active
        if (!$parentKey['active']) {
            throw new ForbiddenException(['keys:issue'], null, "Parent key is inactive");
        }
        
        // Verify parent key type (must be primary or secondary)
        if (!in_array($parentKey['type'], ['primary', 'secondary'], true)) {
            throw new ForbiddenException(['keys:issue'], null, "Only primary or secondary keys can mint secondary keys");
        }
        
        // Verify parent has keys:issue permission
        $parentPermissions = $parentKey['permissions'] ?? [];
        $this->validateIssuerCanMint($parentPermissions);
        
        // Validate envelope rule
        $this->validatePermissionEnvelope($permissions, $parentPermissions, 'secondary');
        
        // Generate new key
        $keyIdHex32 = Ids::generateHex32Id();
        $keyPublicId = 'apub_' . bin2hex(random_bytes(8));
        $keySecret = 'sec_' . bin2hex(random_bytes(24));
        $keySecretHash = $this->hashingService->hash($keySecret);
        
        // Propagate initial_author_key_id from parent
        $initialAuthorKeyId = $parentKey['initial_author_key_id'] ?? $parentKeyIdHex32;
        
        // Prepare key data
        $keyData = [
            'id' => $keyIdHex32,
            'type' => 'secondary',
            'key_secret_hash' => $keySecretHash,
            'permissions' => $permissions,
            'active' => true,
            'issued_by_key_id' => $parentKeyIdHex32,
            'parent_key_id' => $parentKeyIdHex32,
            'initial_author_key_id' => $initialAuthorKeyId, // Propagate root
            'use_count_limit' => null,
            'use_count_current' => 0,
            'device_limit' => null,
            'label' => $label,
        ];
        
        // Wrap in transaction for atomicity (key + key_public_id must be created together)
        $pdo = $this->keyRepo->getPdo();
        $pdo->beginTransaction();
        try {
            // Store key
            $this->keyRepo->create($keyData);
            
            // Store key_public_id mapping
            $publicIdRecordId = Ids::generateHex32Id();
            $this->keyPublicIdRepo->create($publicIdRecordId, $keyIdHex32, $keyPublicId);
            
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // Log transaction failure for debugging
            LoggingService::log(
                $this->logger,
                'ERROR',
                'Transaction rollback in mintSecondaryKey: ' . $e->getMessage(),
                LoggingService::sanitizeContext([
                    'method' => __METHOD__,
                    'exception' => get_class($e),
                    'key_id' => $keyIdHex32 ?? null,
                    'parent_key_id' => $parentKeyIdHex32 ?? null,
                ])
            );
            throw $e;
        }
        
        // Emit audit event (actor is parent key minting secondary key)
        $metadata = ['type' => 'secondary', 'permissions' => $permissions];
        if ($label !== null) {
            $metadata['label'] = $label;
        }
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $parentKeyIdHex32,
            action: 'keys:mint',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32,
            metadata: SensitiveDataSanitizer::sanitize($metadata) // Ensure no secrets in metadata
        );
        
        return [
            'key_id' => $keyIdHex32,
            'key_public_id' => $keyPublicId,
            'key_secret' => $keySecret, // Returned ONCE, never again (T7.2)
            // NOTE: key_secret is only returned here. Never return it again.
            // Never log key_secret. Use SensitiveDataSanitizer before logging.
        ];
    }

    /**
     * Mint a Use Key (Author Key → Gateway)
     * 
     * CRITICAL SECURITY RULES:
     * - key_secret is returned ONLY ONCE in the response (never again)
     * - key_secret MUST NEVER be logged (use SensitiveDataSanitizer before logging)
     * - Client must store key_secret securely; it cannot be retrieved later
     * 
     * @param string $parentKeyIdHex32 Parent key ID (hex32)
     * @param array<string> $permissions Requested permissions
     * @param int|null $useCountLimit Use count limit (optional)
     * @param int|null $deviceLimit Device limit (optional)
     * @param string|null $label Optional label
     * @return array{key_id: string, key_public_id: string, key_secret: string, use_count: int|null, device_limit: int|null}
     * @throws InvalidArgumentException If validation fails
     * @throws NotFoundException If parent key not found
     * @throws ForbiddenException If parent cannot mint or envelope violation
     */
    public function mintUseKey(
        string $parentKeyIdHex32,
        array $permissions,
        ?int $useCountLimit = null,
        ?int $deviceLimit = null,
        ?string $label = null
    ): array {
        // Load parent key
        $parentKey = $this->keyRepo->findById($parentKeyIdHex32);
        if ($parentKey === null) {
            throw new NotFoundException("Parent key not found");
        }
        
        // Verify parent key is active
        if (!$parentKey['active']) {
            throw new ForbiddenException(['keys:issue'], null, "Parent key is inactive");
        }
        
        // Verify parent key type (must be primary or secondary)
        if (!in_array($parentKey['type'], ['primary', 'secondary'], true)) {
            throw new ForbiddenException(['keys:issue'], null, "Only primary or secondary keys can mint use keys");
        }
        
        // Verify parent has keys:issue permission
        $parentPermissions = $parentKey['permissions'] ?? [];
        $this->validateIssuerCanMint($parentPermissions);
        
        // Validate envelope rule and Use Key restrictions
        $this->validatePermissionEnvelope($permissions, $parentPermissions, 'use');
        
        // Generate new key
        $keyIdHex32 = Ids::generateHex32Id();
        $keyPublicId = 'apub_' . bin2hex(random_bytes(8));
        $keySecret = 'sec_' . bin2hex(random_bytes(24));
        $keySecretHash = $this->hashingService->hash($keySecret);
        
        // Propagate initial_author_key_id from parent
        $initialAuthorKeyId = $parentKey['initial_author_key_id'] ?? $parentKeyIdHex32;
        
        // Prepare key data
        $keyData = [
            'id' => $keyIdHex32,
            'type' => 'use',
            'key_secret_hash' => $keySecretHash,
            'permissions' => $permissions,
            'active' => true,
            'issued_by_key_id' => $parentKeyIdHex32,
            'parent_key_id' => $parentKeyIdHex32,
            'initial_author_key_id' => $initialAuthorKeyId, // Propagate root
            'use_count_limit' => $useCountLimit,
            'use_count_current' => 0,
            'device_limit' => $deviceLimit,
            'label' => $label,
        ];
        
        // Wrap in transaction for atomicity (key + key_public_id must be created together)
        $pdo = $this->keyRepo->getPdo();
        $pdo->beginTransaction();
        try {
            // Store key
            $this->keyRepo->create($keyData);
            
            // Store key_public_id mapping
            $publicIdRecordId = Ids::generateHex32Id();
            $this->keyPublicIdRepo->create($publicIdRecordId, $keyIdHex32, $keyPublicId);
            
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // Log transaction failure for debugging
            LoggingService::log(
                $this->logger,
                'ERROR',
                'Transaction rollback in mintUseKey: ' . $e->getMessage(),
                LoggingService::sanitizeContext([
                    'method' => __METHOD__,
                    'exception' => get_class($e),
                    'key_id' => $keyIdHex32 ?? null,
                    'parent_key_id' => $parentKeyIdHex32 ?? null,
                ])
            );
            throw $e;
        }
        
        // Emit audit event (actor is parent key minting use key)
        $metadata = ['type' => 'use', 'permissions' => $permissions];
        if ($label !== null) {
            $metadata['label'] = $label;
        }
        if ($useCountLimit !== null) {
            $metadata['use_count_limit'] = $useCountLimit;
        }
        if ($deviceLimit !== null) {
            $metadata['device_limit'] = $deviceLimit;
        }
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $parentKeyIdHex32,
            action: 'keys:mint',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32,
            metadata: SensitiveDataSanitizer::sanitize($metadata) // Ensure no secrets in metadata
        );
        
        return [
            'key_id' => $keyIdHex32,
            'key_public_id' => $keyPublicId,
            'key_secret' => $keySecret, // Returned ONCE, never again (T7.2)
            // NOTE: key_secret is only returned here. Never return it again.
            // Never log key_secret. Use SensitiveDataSanitizer before logging.
            'use_count' => $useCountLimit,
            'device_limit' => $deviceLimit,
        ];
    }

    /**
     * Rotate a key (replace while preserving lineage)
     * 
     * Generates a new key with same type, permissions, and lineage fields.
     * Old key is marked as retired and inactive.
     * 
     * CRITICAL SECURITY RULES:
     * - new_key_secret is returned ONLY ONCE in the response (never again)
     * - new_key_secret MUST NEVER be logged (use SensitiveDataSanitizer before logging)
     * 
     * @param string $oldKeyIdHex32 Old key ID to rotate (hex32)
     * @param string|null $actorTypeHex32 Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
     * @param string $actorType Actor type ('owner' or 'key')
     * @return array{old_key_id: string, new_key_id: string, new_key_public_id: string, new_key_secret: string}
     * @throws NotFoundException If old key not found
     * @throws InvalidArgumentException If key is already retired
     */
    public function rotateKey(string $oldKeyIdHex32, ?string $actorTypeHex32 = null, string $actorType = 'owner'): array
    {
        // Load old key
        $oldKey = $this->keyRepo->findById($oldKeyIdHex32);
        if ($oldKey === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Verify key is not already retired
        if ($oldKey['retired_at'] !== null) {
            throw new InvalidArgumentException("Key is already retired");
        }
        
        // Generate new key with same properties
        $newKeyIdHex32 = Ids::generateHex32Id();
        $newKeyPublicId = 'apub_' . bin2hex(random_bytes(8));
        $newKeySecret = 'sec_' . bin2hex(random_bytes(24));
        $newKeySecretHash = $this->hashingService->hash($newKeySecret);
        
        // Preserve lineage fields from old key
        $keyData = [
            'id' => $newKeyIdHex32,
            'type' => $oldKey['type'],
            'key_secret_hash' => $newKeySecretHash,
            'permissions' => $oldKey['permissions'],
            'active' => true,
            'issued_by_key_id' => $oldKey['issued_by_key_id'] ?? null,
            'parent_key_id' => $oldKey['parent_key_id'] ?? null,
            'initial_author_key_id' => $oldKey['initial_author_key_id'],
            'rotated_from_id' => $oldKeyIdHex32, // Link to old key
            'use_count_limit' => $oldKey['use_count_limit'],
            'use_count_current' => 0, // Reset use count for new key
            'device_limit' => $oldKey['device_limit'],
            'label' => $oldKey['label'] ?? null, // Preserve label
        ];
        
        // Wrap in transaction for atomicity (new key + key_public_id + mark old key as rotated)
        $pdo = $this->keyRepo->getPdo();
        $pdo->beginTransaction();
        try {
            // Store new key
            $this->keyRepo->create($keyData);
            
            // Store new key_public_id mapping
            $publicIdRecordId = Ids::generateHex32Id();
            $this->keyPublicIdRepo->create($publicIdRecordId, $newKeyIdHex32, $newKeyPublicId);
            
            // Mark old key as rotated and retired
            $this->keyRepo->markRotated($oldKeyIdHex32, $newKeyIdHex32);
            
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            // Log transaction failure for debugging
            LoggingService::log(
                $this->logger,
                'ERROR',
                'Transaction rollback in rotateKey: ' . $e->getMessage(),
                LoggingService::sanitizeContext([
                    'method' => __METHOD__,
                    'exception' => get_class($e),
                    'old_key_id' => $oldKeyIdHex32 ?? null,
                    'new_key_id' => $newKeyIdHex32 ?? null,
                ])
            );
            throw $e;
        }
        
        // Emit audit event (use provided actor or infer from old key's owner)
        if ($actorTypeHex32 === null) {
            // Infer actor from old key's initial_author_key_id (owner's primary key)
            $actorTypeHex32 = $oldKey['initial_author_key_id'] ?? $oldKeyIdHex32;
            $actorType = 'owner'; // Default to owner if not specified
        }
        $metadata = ['old_key_id' => $oldKeyIdHex32, 'new_key_id' => $newKeyIdHex32];
        $this->auditService->emit(
            actorType: $actorType,
            actorIdHex32: $actorTypeHex32,
            action: 'keys:rotate',
            subjectType: 'key',
            subjectIdHex32: $oldKeyIdHex32,
            metadata: SensitiveDataSanitizer::sanitize($metadata) // Ensure no secrets
        );
        
        return [
            'old_key_id' => $oldKeyIdHex32,
            'new_key_id' => $newKeyIdHex32,
            'new_key_public_id' => $newKeyPublicId,
            'new_key_secret' => $newKeySecret, // Returned ONCE, never again (T7.2)
            // NOTE: new_key_secret is only returned here. Never return it again.
            // Never log new_key_secret. Use SensitiveDataSanitizer before logging.
        ];
    }

    /**
     * Activate a key
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string|null $actorTypeHex32 Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
     * @param string $actorType Actor type ('owner' or 'key')
     * @return void
     * @throws NotFoundException If key not found
     */
    public function activateKey(string $keyIdHex32, ?string $actorTypeHex32 = null, string $actorType = 'owner'): void
    {
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        $this->keyRepo->updateActive($keyIdHex32, true);
        
        // Emit audit event (use provided actor or infer from key's owner)
        if ($actorTypeHex32 === null) {
            $actorTypeHex32 = $key['initial_author_key_id'] ?? $keyIdHex32;
            $actorType = 'owner';
        }
        $this->auditService->emit(
            actorType: $actorType,
            actorIdHex32: $actorTypeHex32,
            action: 'keys:activate',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32
        );
    }

    /**
     * Deactivate a key with optional cascade
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param bool $cascade If true, deactivate all descendant keys recursively
     * @param string|null $actorTypeHex32 Actor ID (hex32) - owner_id if actor_type='owner', key_id if actor_type='key'
     * @param string $actorType Actor type ('owner' or 'key')
     * @return int Number of keys deactivated
     * @throws NotFoundException If key not found
     */
    public function deactivateKey(string $keyIdHex32, bool $cascade = false, ?string $actorTypeHex32 = null, string $actorType = 'owner'): int
    {
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        $count = $this->keyRepo->deactivate($keyIdHex32, $cascade);
        
        // Emit audit event (use provided actor or infer from key's owner)
        if ($actorTypeHex32 === null) {
            $actorTypeHex32 = $key['initial_author_key_id'] ?? $keyIdHex32;
            $actorType = 'owner';
        }
        $metadata = ['cascade' => $cascade, 'keys_deactivated' => $count];
        $this->auditService->emit(
            actorType: $actorType,
            actorIdHex32: $actorTypeHex32,
            action: 'keys:deactivate',
            subjectType: 'key',
            subjectIdHex32: $keyIdHex32,
            metadata: $metadata
        );
        
        return $count;
    }

    /**
     * Validate permission envelope for child key
     * 
     * Enforces:
     * 1. Child permissions ⊆ parent permissions (envelope rule)
     * 2. Use Key restrictions (no posts:create or keys:issue)
     * 
     * @param array<string> $childPermissions Child key permissions
     * @param array<string> $parentPermissions Parent key permissions
     * @param string $keyType Key type ('primary', 'secondary', 'use')
     * @throws InvalidArgumentException If validation fails
     */
    public function validatePermissionEnvelope(
        array $childPermissions,
        array $parentPermissions,
        string $keyType
    ): void {
        // Validate permission format
        foreach ($childPermissions as $permission) {
            if (!PermissionCatalog::isValidFormat($permission)) {
                throw new InvalidArgumentException(
                    "Invalid permission format: {$permission}"
                );
            }
        }

        // For primary keys, no parent exists, so no envelope check
        if ($keyType === 'primary') {
            // Primary keys can have any permissions (no restrictions)
            return;
        }

        // Enforce envelope rule: child ⊆ parent
        if (!PermissionCatalog::validateEnvelope($childPermissions, $parentPermissions)) {
            $missing = array_diff($childPermissions, $parentPermissions);
            throw new InvalidArgumentException(
                "Permission envelope violation: child permissions must be subset of parent. " .
                "Missing from parent: " . implode(', ', $missing)
            );
        }

        // Enforce Use Key restrictions
        if ($keyType === 'use') {
            if (!PermissionCatalog::validateUseKeyPermissions($childPermissions)) {
                $forbidden = array_intersect(
                    $childPermissions,
                    PermissionCatalog::USE_KEY_FORBIDDEN_PERMISSIONS
                );
                throw new InvalidArgumentException(
                    "Use Keys cannot have permissions: " . implode(', ', $forbidden)
                );
            }
        }
    }

    /**
     * Validate that issuer has keys:issue permission
     * 
     * @param array<string> $issuerPermissions Issuer's permissions
     * @throws InvalidArgumentException If issuer lacks keys:issue
     */
    public function validateIssuerCanMint(array $issuerPermissions): void
    {
        if (!in_array('keys:issue', $issuerPermissions, true)) {
            throw new InvalidArgumentException(
                "Issuer lacks required permission: keys:issue"
            );
        }
    }

    /**
     * List keys owned by an owner
     * 
     * Requirements:
     * - Owner must have `keys:read` permission
     * - Returns all keys where initial_author_key_id matches owner's primary keys
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array<array> List of keys
     * @throws ForbiddenException If owner lacks permission
     */
    public function listKeys(
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has keys:read permission
        if (!in_array('keys:read', $ownerPermissions, true)) {
            throw new ForbiddenException(['keys:read'], null, "Missing required permission: keys:read");
        }
        
        // Find all primary keys owned by this owner
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        
        // Find all keys with initial_author_key_id matching any of the owner's primary keys
        $allKeys = [];
        foreach ($primaryKeys as $primaryKey) {
            $keys = $this->keyRepo->findByInitialAuthor($primaryKey['key_id']);
            $allKeys = array_merge($allKeys, $keys);
        }
        
        // Remove duplicates (in case a key appears multiple times)
        $uniqueKeys = [];
        $seenIds = [];
        foreach ($allKeys as $key) {
            if (!in_array($key['key_id'], $seenIds, true)) {
                $uniqueKeys[] = $key;
                $seenIds[] = $key['key_id'];
            }
        }
        
        return $uniqueKeys;
    }

    /**
     * Get a key by ID (with ownership verification)
     * 
     * Requirements:
     * - Owner must have `keys:read` permission
     * - Key must belong to owner (via initial_author_key_id)
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array Key data
     * @throws NotFoundException If key not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function getKey(
        string $keyIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has keys:read permission
        if (!in_array('keys:read', $ownerPermissions, true)) {
            throw new ForbiddenException(['keys:read'], null, "Missing required permission: keys:read");
        }
        
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Verify key belongs to owner
        // A key belongs to an owner if its initial_author_key_id matches one of the owner's primary keys
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($pk) => $pk['key_id'], $primaryKeys);
        
        // Check if the key's initial_author_key_id matches any of the owner's primary keys
        if (!in_array($key['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            // Return 404 to hide key existence (security best practice)
            throw new NotFoundException("Key not found");
        }
        
        return $key;
    }

    /**
     * Get key lineage tree
     * 
     * Requirements:
     * - Owner must have `keys:read` permission
     * - Key must belong to owner (via initial_author_key_id)
     * 
     * @param string $keyIdHex32 Key ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array<array> Lineage tree (root to leaf)
     * @throws NotFoundException If key not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function getKeyLineage(
        string $keyIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has keys:read permission
        if (!in_array('keys:read', $ownerPermissions, true)) {
            throw new ForbiddenException(['keys:read'], null, "Missing required permission: keys:read");
        }
        
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Verify key belongs to owner
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($pk) => $pk['key_id'], $primaryKeys);
        
        if (!in_array($key['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            throw new NotFoundException("Key not found");
        }
        
        // Get lineage tree
        return $this->keyRepo->getLineageTree($keyIdHex32);
    }
}
