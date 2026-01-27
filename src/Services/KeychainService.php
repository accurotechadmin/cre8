<?php
/**
 * CRE8.pw Keychain Service
 * 
 * Handles keychain management operations for Console JSON surface.
 * Owner-scoped keychain CRUD and membership management.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 4
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\KeychainRepository;
use App\Repositories\KeychainMemberRepository;
use App\Repositories\KeyRepository;
use App\Services\AuditService;
use App\Utilities\Ids;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * Keychain Service
 * 
 * Handles keychain management operations.
 */
class KeychainService extends BaseService
{
    public function __construct(
        private KeychainRepository $keychainRepo,
        private KeychainMemberRepository $keychainMemberRepo,
        private KeyRepository $keyRepo,
        private AuditService $auditService
    ) {
    }

    /**
     * Create a new keychain
     * 
     * Requirements:
     * - Owner must have `keychains:manage` permission
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $name Keychain name
     * @return array{keychain_id: string, owner_id: string, name: string, created_at: string}
     * @throws ForbiddenException If owner lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function createKeychain(
        string $ownerIdHex32,
        array $ownerPermissions,
        string $name
    ): array {
        // Validate name
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < 1 || $nameLength > 255) {
            throw new InvalidArgumentException("Keychain name must be between 1 and 255 characters");
        }
        
        // Verify owner has keychains:manage permission
        if (!in_array('keychains:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Generate keychain ID
        $keychainIdHex32 = Ids::generateHex32Id();
        
        // Create keychain
        $this->keychainRepo->create([
            'id' => $keychainIdHex32,
            'owner_id' => $ownerIdHex32,
            'name' => $name,
        ]);
        
        // Load created keychain to return full data
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new \RuntimeException("Failed to create keychain");
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'keychains:create',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['name' => $name]
        );
        
        return $keychain;
    }

    /**
     * List keychains owned by an owner
     * 
     * Requirements:
     * - Owner must have `keychains:manage` permission
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array<array> List of keychains
     * @throws ForbiddenException If owner lacks permission
     */
    public function listKeychains(
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has keychains:manage permission
        if (!in_array('keychains:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Find keychains by owner
        return $this->keychainRepo->findByOwner($ownerIdHex32);
    }

    /**
     * Add a key to a keychain
     * 
     * Requirements:
     * - Owner must have `keychains:manage` permission
     * - Keychain must be owned by the owner
     * - Key must be owned by the owner (via initial_author_key_id)
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array{keychain_id: string, key_id: string}
     * @throws NotFoundException If keychain or key not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function addMember(
        string $keychainIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $keyIdHex32
    ): array {
        // Verify owner has keychains:manage permission
        if (!in_array('keychains:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Load keychain and verify ownership
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Verify keychain is owned by owner
        if (!isset($keychain['owner_id']) || $keychain['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Load key and verify ownership
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Verify key is owned by owner (get owner's primary keys, check if key's initial_author_key_id matches)
        $primaryKeys = $this->keyRepo->findByOwner($ownerIdHex32);
        $ownerPrimaryKeyIds = array_map(fn($k) => $k['key_id'], $primaryKeys);
        if (!in_array($key['initial_author_key_id'], $ownerPrimaryKeyIds, true)) {
            throw new NotFoundException("Key not found");
        }
        
        // Add key to keychain (idempotent - will fail silently if duplicate)
        try {
            $this->keychainMemberRepo->add($keychainIdHex32, $keyIdHex32);
        } catch (\PDOException $e) {
            // If duplicate key error, that's fine - already a member
            if ($e->getCode() !== '23000') { // Not a duplicate key error
                throw $e;
            }
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'keychains:member:add',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['key_id' => $keyIdHex32]
        );
        
        return [
            'keychain_id' => $keychainIdHex32,
            'key_id' => $keyIdHex32,
        ];
    }

    /**
     * Remove a key from a keychain
     * 
     * Requirements:
     * - Owner must have `keychains:manage` permission
     * - Keychain must be owned by the owner
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     * @throws NotFoundException If keychain not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function removeMember(
        string $keychainIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $keyIdHex32
    ): void {
        // Verify owner has keychains:manage permission
        if (!in_array('keychains:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Load keychain and verify ownership
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Verify keychain is owned by owner
        if (!isset($keychain['owner_id']) || $keychain['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Remove key from keychain
        $this->keychainMemberRepo->remove($keychainIdHex32, $keyIdHex32);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'keychains:member:remove',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['key_id' => $keyIdHex32]
        );
    }

    /**
     * Create an external keychain (Gateway)
     * 
     * Gateway external keychain operations.
     * 
     * Requirements:
     * - Key must have `keychains:manage` permission
     * - External keychains have owner_id = NULL
     * 
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @param string $name Keychain name
     * @return array{keychain_id: string, name: string, created_at: string}
     * @throws NotFoundException If key not found
     * @throws ForbiddenException If key lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function createExternalKeychain(
        string $keyIdHex32,
        array $keyPermissions,
        string $name
    ): array {
        // Validate name
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < 1 || $nameLength > 255) {
            throw new InvalidArgumentException("Keychain name must be between 1 and 255 characters");
        }
        
        // Verify key has keychains:manage permission
        if (!in_array('keychains:manage', $keyPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Verify key exists
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Generate keychain ID
        $keychainIdHex32 = Ids::generateHex32Id();
        
        // Create external keychain (owner_id = NULL)
        $this->keychainRepo->create([
            'id' => $keychainIdHex32,
            'name' => $name,
            // owner_id is not set, so it will be NULL (external)
        ]);
        
        // Load created keychain to return full data
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new \RuntimeException("Failed to create keychain");
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $keyIdHex32,
            action: 'keychains:create',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['name' => $name, 'external' => true]
        );
        
        return $keychain;
    }

    /**
     * Add a key to an external keychain (Gateway)
     * 
     * Gateway external keychain operations.
     * 
     * Requirements:
     * - Key must have `keychains:manage` permission
     * - Keychain must be external (owner_id IS NULL)
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @param string $memberKeyIdHex32 Member key ID to add (hex32)
     * @return array{keychain_id: string, key_id: string}
     * @throws NotFoundException If keychain or key not found, or keychain is not external
     * @throws ForbiddenException If key lacks permission
     */
    public function addMemberToExternalKeychain(
        string $keychainIdHex32,
        string $keyIdHex32,
        array $keyPermissions,
        string $memberKeyIdHex32
    ): array {
        // Verify key has keychains:manage permission
        if (!in_array('keychains:manage', $keyPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Verify key exists
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Load keychain and verify it's external (owner_id IS NULL)
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Verify keychain is external (no owner_id)
        if (isset($keychain['owner_id'])) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Verify member key exists
        $memberKey = $this->keyRepo->findById($memberKeyIdHex32);
        if ($memberKey === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Add key to keychain (idempotent - will fail silently if duplicate)
        try {
            $this->keychainMemberRepo->add($keychainIdHex32, $memberKeyIdHex32);
        } catch (\PDOException $e) {
            // If duplicate key error, that's fine - already a member
            if ($e->getCode() !== '23000') { // Not a duplicate key error
                throw $e;
            }
        }
        
        // Emit audit event (external keychain)
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $keyIdHex32,
            action: 'keychains:member:add',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['key_id' => $memberKeyIdHex32, 'external' => true]
        );
        
        return [
            'keychain_id' => $keychainIdHex32,
            'key_id' => $memberKeyIdHex32,
        ];
    }

    /**
     * Remove a key from an external keychain (Gateway)
     * 
     * Gateway external keychain operations.
     * 
     * Requirements:
     * - Key must have `keychains:manage` permission
     * - Keychain must be external (owner_id IS NULL)
     * 
     * @param string $keychainIdHex32 Keychain ID (hex32)
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @param string $memberKeyIdHex32 Member key ID to remove (hex32)
     * @return void
     * @throws NotFoundException If keychain or key not found, or keychain is not external
     * @throws ForbiddenException If key lacks permission
     */
    public function removeMemberFromExternalKeychain(
        string $keychainIdHex32,
        string $keyIdHex32,
        array $keyPermissions,
        string $memberKeyIdHex32
    ): void {
        // Verify key has keychains:manage permission
        if (!in_array('keychains:manage', $keyPermissions, true)) {
            throw new ForbiddenException(['keychains:manage'], null, "Missing required permission: keychains:manage");
        }
        
        // Verify key exists
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Load keychain and verify it's external (owner_id IS NULL)
        $keychain = $this->keychainRepo->findById($keychainIdHex32);
        if ($keychain === null) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Verify keychain is external (no owner_id)
        if (isset($keychain['owner_id'])) {
            throw new NotFoundException("Keychain not found");
        }
        
        // Remove key from keychain
        $this->keychainMemberRepo->remove($keychainIdHex32, $memberKeyIdHex32);
        
        // Emit audit event (external keychain)
        $this->auditService->emit(
            actorType: 'key',
            actorIdHex32: $keyIdHex32,
            action: 'keychains:member:remove',
            subjectType: 'keychain',
            subjectIdHex32: $keychainIdHex32,
            metadata: ['key_id' => $memberKeyIdHex32, 'external' => true]
        );
    }
}
