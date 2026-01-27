<?php
/**
 * CRE8.pw Route Catalog Controller (Console)
 * 
 * Provides route catalog endpoint for Console JSON surface.
 * Returns a list of all available Console JSON routes with their methods,
 * paths, required permissions, and descriptions.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md
 * @see docs/APPENDIX/I-helper_route_inventory.md
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use App\Utilities\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Route Catalog Controller (Console)
 * 
 * Handles route catalog endpoint for Console JSON surface.
 */
class RouteCatalogController extends BaseController
{
    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        parent::__construct($responseFactory);
    }

    /**
     * Get Console route catalog
     * 
     * Endpoint: GET /console/routes
     * Auth: Owner JWT (typ=owner)
     * Required Permission: None (public catalog endpoint)
     * 
     * Returns a catalog of all available Console JSON routes with their
     * methods, paths, required permissions, and descriptions.
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function catalog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $catalog = [
            'surface' => 'console_json',
            'auth' => 'Owner JWT (typ=owner)',
            'rate_limiting' => 'Keyed by owner_id (hex32)',
            'routes' => [
                // Key Management
                [
                    'method' => 'POST',
                    'path' => '/console/keys/primary',
                    'purpose' => 'Mint Primary Author Key',
                    'required_permission' => 'keys:issue',
                    'description' => 'Create a primary author key (root machine principal)',
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/keys',
                    'purpose' => 'List Owner\'s keys',
                    'required_permission' => 'keys:read',
                    'description' => 'List all keys owned by the owner',
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/keys/{keyId}',
                    'purpose' => 'Get key details',
                    'required_permission' => 'keys:read',
                    'description' => 'Get detailed information about a specific key',
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/keys/{keyId}/lineage',
                    'purpose' => 'View key lineage tree',
                    'required_permission' => 'keys:read',
                    'description' => 'View the lineage/provenance tree for a key',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/keys/{keyId}/rotate',
                    'purpose' => 'Rotate key',
                    'required_permission' => 'keys:rotate',
                    'description' => 'Rotate a key (create replacement and retire old key)',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/keys/{keyId}/activate',
                    'purpose' => 'Activate key',
                    'required_permission' => 'keys:state:update',
                    'description' => 'Activate a deactivated key',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/keys/{keyId}/deactivate',
                    'purpose' => 'Deactivate key',
                    'required_permission' => 'keys:state:update',
                    'description' => 'Deactivate a key (prevent further use)',
                ],
                
                // Group Management
                [
                    'method' => 'POST',
                    'path' => '/console/groups',
                    'purpose' => 'Create group',
                    'required_permission' => 'groups:manage',
                    'description' => 'Create a new group for bulk access management',
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/groups',
                    'purpose' => 'List groups',
                    'required_permission' => 'groups:manage',
                    'description' => 'List all groups owned by the owner',
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/groups/{groupId}',
                    'purpose' => 'Get group details',
                    'required_permission' => 'groups:manage',
                    'description' => 'Get detailed information about a specific group',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/groups/{groupId}/rename',
                    'purpose' => 'Rename group',
                    'required_permission' => 'groups:manage',
                    'description' => 'Rename an existing group',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/console/groups/{groupId}',
                    'purpose' => 'Delete group',
                    'required_permission' => 'groups:manage',
                    'description' => 'Delete a group (memberships cascade deleted)',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/groups/{groupId}/members',
                    'purpose' => 'Add member to group',
                    'required_permission' => 'groups:manage',
                    'description' => 'Add a key to a group',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/console/groups/{groupId}/members/{keyId}',
                    'purpose' => 'Remove member from group',
                    'required_permission' => 'groups:manage',
                    'description' => 'Remove a key from a group',
                ],
                
                // Keychain Management
                [
                    'method' => 'GET',
                    'path' => '/console/keychains',
                    'purpose' => 'List owner keychains',
                    'required_permission' => 'keychains:manage',
                    'description' => 'List all keychains owned by the owner',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/keychains',
                    'purpose' => 'Create keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Create a new owner-managed keychain',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/keychains/{id}/members',
                    'purpose' => 'Add member to keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Add a key to a keychain',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/console/keychains/{id}/members/{keyId}',
                    'purpose' => 'Remove member from keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Remove a key from a keychain',
                ],
                
                // Post Management (Owner Admin)
                [
                    'method' => 'GET',
                    'path' => '/console/posts',
                    'purpose' => 'List posts from Owner\'s keys',
                    'required_permission' => 'posts:admin:read',
                    'description' => 'List all posts created by keys owned by the owner',
                    'query_params' => ['limit', 'before_id'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/console/posts/{postId}',
                    'purpose' => 'Get post details',
                    'required_permission' => 'posts:admin:read',
                    'description' => 'Get detailed information about a specific post',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/posts/{postId}/access/grant-group',
                    'purpose' => 'Grant group access to post',
                    'required_permission' => 'posts:access:manage',
                    'description' => 'Grant access to a post for a group',
                ],
                [
                    'method' => 'POST',
                    'path' => '/console/posts/{postId}/access/revoke-group',
                    'purpose' => 'Revoke group access from post',
                    'required_permission' => 'posts:access:manage',
                    'description' => 'Revoke access to a post for a group',
                ],
            ],
        ];
        
        return ResponseFactory::single($this->responseFactory, $catalog);
    }
}
