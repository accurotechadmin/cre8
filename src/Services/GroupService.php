<?php
/**
 * CRE8.pw Group Service
 * 
 * Handles group management operations for Console JSON surface.
 * Owner-scoped group CRUD and membership management.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 4
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\BaseService;
use App\Repositories\GroupRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\KeyRepository;
use App\Services\AuditService;
use App\Utilities\Ids;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use InvalidArgumentException;

/**
 * Group Service
 * 
 * Handles group management operations.
 */
class GroupService extends BaseService
{
    public function __construct(
        private GroupRepository $groupRepo,
        private GroupMemberRepository $groupMemberRepo,
        private KeyRepository $keyRepo,
        private AuditService $auditService
    ) {
    }

    /**
     * Create a new group
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $name Group name
     * @return array{group_id: string, owner_id: string, name: string, created_at: string}
     * @throws ForbiddenException If owner lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function createGroup(
        string $ownerIdHex32,
        array $ownerPermissions,
        string $name
    ): array {
        // Validate name
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < 1 || $nameLength > 255) {
            throw new InvalidArgumentException("Group name must be between 1 and 255 characters");
        }
        
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Generate group ID
        $groupIdHex32 = Ids::generateHex32Id();
        
        // Create group
        $this->groupRepo->create([
            'id' => $groupIdHex32,
            'owner_id' => $ownerIdHex32,
            'name' => $name,
        ]);
        
        // Load created group to return full data
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new \RuntimeException("Failed to create group");
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'groups:create',
            subjectType: 'group',
            subjectIdHex32: $groupIdHex32,
            metadata: ['name' => $name]
        );
        
        return $group;
    }

    /**
     * List groups owned by an owner
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * 
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array<array> List of groups
     * @throws ForbiddenException If owner lacks permission
     */
    public function listGroups(
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Find groups by owner
        return $this->groupRepo->findByOwner($ownerIdHex32);
    }

    /**
     * Get group details
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * - Group must be owned by the owner
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return array Group data
     * @throws NotFoundException If group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function getGroup(
        string $groupIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions
    ): array {
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Load group
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        // Verify group is owned by owner
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        return $group;
    }

    /**
     * Rename a group
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * - Group must be owned by the owner
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $name New name
     * @return array{group_id: string, owner_id: string, name: string, updated_at: string}
     * @throws NotFoundException If group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     * @throws InvalidArgumentException If validation fails
     */
    public function renameGroup(
        string $groupIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $name
    ): array {
        // Validate name
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < 1 || $nameLength > 255) {
            throw new InvalidArgumentException("Group name must be between 1 and 255 characters");
        }
        
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Load group and verify ownership
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Update group name
        $this->groupRepo->updateName($groupIdHex32, $name);
        
        // Load updated group to return full data
        $updatedGroup = $this->groupRepo->findById($groupIdHex32);
        if ($updatedGroup === null) {
            throw new \RuntimeException("Failed to update group");
        }
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'groups:rename',
            subjectType: 'group',
            subjectIdHex32: $groupIdHex32,
            metadata: ['name' => $name]
        );
        
        return $updatedGroup;
    }

    /**
     * Delete a group
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * - Group must be owned by the owner
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @return void
     * @throws NotFoundException If group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function deleteGroup(
        string $groupIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions
    ): void {
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Load group and verify ownership
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Delete group (memberships will be cascade deleted by DB foreign key)
        $this->groupRepo->delete($groupIdHex32);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'groups:delete',
            subjectType: 'group',
            subjectIdHex32: $groupIdHex32
        );
    }

    /**
     * Add a key to a group
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * - Group must be owned by the owner
     * - Key must be owned by the owner (via initial_author_key_id)
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $keyIdHex32 Key ID (hex32)
     * @return array{group_id: string, key_id: string}
     * @throws NotFoundException If group or key not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function addMember(
        string $groupIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $keyIdHex32
    ): array {
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Load group and verify ownership
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
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
        
        // Check if key is already a member
        if ($this->groupMemberRepo->isMember($groupIdHex32, $keyIdHex32)) {
            // Already a member, return success
            return [
                'group_id' => $groupIdHex32,
                'key_id' => $keyIdHex32,
            ];
        }
        
        // Add key to group
        $this->groupMemberRepo->add($groupIdHex32, $keyIdHex32);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'groups:member:add',
            subjectType: 'group',
            subjectIdHex32: $groupIdHex32,
            metadata: ['key_id' => $keyIdHex32]
        );
        
        return [
            'group_id' => $groupIdHex32,
            'key_id' => $keyIdHex32,
        ];
    }

    /**
     * Remove a key from a group
     * 
     * Requirements:
     * - Owner must have `groups:manage` permission
     * - Group must be owned by the owner
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $ownerIdHex32 Owner ID from JWT (hex32)
     * @param array $ownerPermissions Owner permissions from JWT
     * @param string $keyIdHex32 Key ID (hex32)
     * @return void
     * @throws NotFoundException If group not found or not owned by owner
     * @throws ForbiddenException If owner lacks permission
     */
    public function removeMember(
        string $groupIdHex32,
        string $ownerIdHex32,
        array $ownerPermissions,
        string $keyIdHex32
    ): void {
        // Verify owner has groups:manage permission
        if (!in_array('groups:manage', $ownerPermissions, true)) {
            throw new ForbiddenException(['groups:manage'], null, "Missing required permission: groups:manage");
        }
        
        // Load group and verify ownership
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Remove key from group (no need to verify key ownership for removal)
        $this->groupMemberRepo->remove($groupIdHex32, $keyIdHex32);
        
        // Emit audit event
        $this->auditService->emit(
            actorType: 'owner',
            actorIdHex32: $ownerIdHex32,
            action: 'groups:member:remove',
            subjectType: 'group',
            subjectIdHex32: $groupIdHex32,
            metadata: ['key_id' => $keyIdHex32]
        );
    }

    /**
     * List groups (Gateway read-only)
     * 
     * Gateway read-only group operations.
     * 
     * Requirements:
     * - Key must have `groups:read` permission
     * - Returns groups owned by the key's owner (via initial_author_key_id)
     * 
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @return array<array> List of groups
     * @throws NotFoundException If key not found
     * @throws ForbiddenException If key lacks permission
     */
    public function listGroupsForKey(
        string $keyIdHex32,
        array $keyPermissions
    ): array {
        // Verify key has groups:read permission
        if (!in_array('groups:read', $keyPermissions, true)) {
            throw new ForbiddenException(['groups:read'], null, "Missing required permission: groups:read");
        }
        
        // Load key to get owner ID (via initial_author_key_id)
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Get owner ID from key's initial_author_key_id (which is the owner's primary key)
        // Get the primary key to access its owner_id
        $primaryKey = $this->keyRepo->findById($key['initial_author_key_id']);
        if ($primaryKey === null || !isset($primaryKey['owner_id'])) {
            throw new NotFoundException("Key not found");
        }
        $ownerIdHex32 = $primaryKey['owner_id'];
        
        // Find groups by owner
        return $this->groupRepo->findByOwner($ownerIdHex32);
    }

    /**
     * Get group details (Gateway read-only)
     * 
     * Gateway read-only group operations.
     * 
     * Requirements:
     * - Key must have `groups:read` permission
     * - Group must be owned by the key's owner (via initial_author_key_id)
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @return array Group data
     * @throws NotFoundException If group or key not found or group not owned by key's owner
     * @throws ForbiddenException If key lacks permission
     */
    public function getGroupForKey(
        string $groupIdHex32,
        string $keyIdHex32,
        array $keyPermissions
    ): array {
        // Verify key has groups:read permission
        if (!in_array('groups:read', $keyPermissions, true)) {
            throw new ForbiddenException(['groups:read'], null, "Missing required permission: groups:read");
        }
        
        // Load key to get owner ID (via initial_author_key_id)
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Get owner ID from key's initial_author_key_id (which is the owner's primary key)
        // Get the primary key to access its owner_id
        $primaryKey = $this->keyRepo->findById($key['initial_author_key_id']);
        if ($primaryKey === null || !isset($primaryKey['owner_id'])) {
            throw new NotFoundException("Key not found");
        }
        $ownerIdHex32 = $primaryKey['owner_id'];
        
        // Load group
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        // Verify group is owned by key's owner
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        return $group;
    }

    /**
     * List group members (Gateway read-only)
     * 
     * Gateway read-only group operations.
     * 
     * Requirements:
     * - Key must have `groups:read` permission
     * - Group must be owned by the key's owner (via initial_author_key_id)
     * 
     * @param string $groupIdHex32 Group ID (hex32)
     * @param string $keyIdHex32 Key ID from JWT (hex32)
     * @param array $keyPermissions Key permissions from JWT
     * @return array<string> List of key IDs (hex32)
     * @throws NotFoundException If group or key not found or group not owned by key's owner
     * @throws ForbiddenException If key lacks permission
     */
    public function listGroupMembersForKey(
        string $groupIdHex32,
        string $keyIdHex32,
        array $keyPermissions
    ): array {
        // Verify key has groups:read permission
        if (!in_array('groups:read', $keyPermissions, true)) {
            throw new ForbiddenException(['groups:read'], null, "Missing required permission: groups:read");
        }
        
        // Load key to get owner ID (via initial_author_key_id)
        $key = $this->keyRepo->findById($keyIdHex32);
        if ($key === null) {
            throw new NotFoundException("Key not found");
        }
        
        // Get owner ID from key's initial_author_key_id (which is the owner's primary key)
        // Get the primary key to access its owner_id
        $primaryKey = $this->keyRepo->findById($key['initial_author_key_id']);
        if ($primaryKey === null || !isset($primaryKey['owner_id'])) {
            throw new NotFoundException("Key not found");
        }
        $ownerIdHex32 = $primaryKey['owner_id'];
        
        // Load group and verify ownership
        $group = $this->groupRepo->findById($groupIdHex32);
        if ($group === null) {
            throw new NotFoundException("Group not found");
        }
        
        if ($group['owner_id'] !== $ownerIdHex32) {
            throw new NotFoundException("Group not found");
        }
        
        // Find members of the group
        return $this->groupMemberRepo->findMembers($groupIdHex32);
    }
}
