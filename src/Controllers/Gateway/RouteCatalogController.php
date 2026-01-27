<?php
/**
 * CRE8.pw Route Catalog Controller (Gateway)
 * 
 * Provides route catalog endpoint for Gateway JSON surface.
 * Returns a list of all available Gateway JSON routes with their methods,
 * paths, required permissions, and descriptions.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md
 * @see docs/APPENDIX/I-helper_route_inventory.md
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use App\Utilities\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Route Catalog Controller (Gateway)
 * 
 * Handles route catalog endpoint for Gateway JSON surface.
 */
class RouteCatalogController extends BaseController
{
    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        parent::__construct($responseFactory);
    }

    /**
     * Get Gateway route catalog
     * 
     * Endpoint: GET /api/routes
     * Auth: Key JWT (typ=key)
     * Required Permission: None (public catalog endpoint)
     * 
     * Returns a catalog of all available Gateway JSON routes with their
     * methods, paths, required permissions, and descriptions.
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function catalog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $catalog = [
            'surface' => 'gateway_json',
            'auth' => 'Key JWT (typ=key)',
            'rate_limiting' => 'Keyed by key_id (hex32)',
            'routes' => [
                // Key Issuance
                [
                    'method' => 'POST',
                    'path' => '/api/keys/{authorKeyId}/secondary',
                    'purpose' => 'Mint Secondary Author Key',
                    'required_permission' => 'keys:issue',
                    'description' => 'Create a secondary author key with delegated permissions',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/keys/{authorKeyId}/use',
                    'purpose' => 'Mint Use Key',
                    'required_permission' => 'keys:issue',
                    'description' => 'Create a use key with optional use_count and device_limit restrictions',
                ],
                
                // Posts
                [
                    'method' => 'POST',
                    'path' => '/api/posts',
                    'purpose' => 'Create post',
                    'required_permission' => 'posts:create',
                    'description' => 'Create a new post (Author keys only)',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/posts/{postId}/access',
                    'purpose' => 'Grant/update post access',
                    'required_permission' => 'posts:access:manage',
                    'required_mask' => 'MANAGE_ACCESS (0x08)',
                    'description' => 'Grant or update access to a post for a key or group',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/api/posts/{postId}/access/{targetType}/{targetId}',
                    'purpose' => 'Revoke post access',
                    'required_permission' => 'posts:access:manage',
                    'required_mask' => 'MANAGE_ACCESS (0x08)',
                    'description' => 'Revoke access to a post for a key or group',
                ],
                
                // Comments
                [
                    'method' => 'POST',
                    'path' => '/api/posts/{postId}/comments',
                    'purpose' => 'Create comment',
                    'required_permission' => 'comments:write',
                    'required_mask' => 'COMMENT (0x02)',
                    'description' => 'Create a comment on a post',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/posts/{postId}/comments',
                    'purpose' => 'List comments',
                    'required_permission' => 'posts:read',
                    'required_mask' => 'VIEW (0x01)',
                    'description' => 'List comments on a post with pagination',
                ],
                
                // Feeds
                [
                    'method' => 'GET',
                    'path' => '/api/feed/use/{useKeyId}',
                    'purpose' => 'Get Use Key feed',
                    'required_permission' => 'posts:read',
                    'description' => 'Get feed of posts visible to the Use Key (path useKeyId must match JWT key_id)',
                    'query_params' => ['limit', 'before_id', 'since_id'],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/feed/author',
                    'purpose' => 'Get Author feed',
                    'required_permission' => 'posts:read',
                    'description' => 'Get feed of posts authored by the Author Key or its descendants',
                    'query_params' => ['limit', 'before_id', 'since_id'],
                ],
                
                // Groups (Read-Only)
                [
                    'method' => 'GET',
                    'path' => '/api/groups',
                    'purpose' => 'List groups',
                    'required_permission' => 'groups:read',
                    'description' => 'List groups owned by the key\'s owner',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/groups/{groupId}',
                    'purpose' => 'Get group',
                    'required_permission' => 'groups:read',
                    'description' => 'Get group details',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/groups/{groupId}/members',
                    'purpose' => 'List group members',
                    'required_permission' => 'groups:read',
                    'description' => 'List members of a group',
                ],
                
                // Keychains (External)
                [
                    'method' => 'POST',
                    'path' => '/api/keychains',
                    'purpose' => 'Create external keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Create an external keychain (owner_id = NULL)',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/keychains/{id}/members',
                    'purpose' => 'Add member to keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Add a key to an external keychain',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/api/keychains/{id}/members/{keyId}',
                    'purpose' => 'Remove member from keychain',
                    'required_permission' => 'keychains:manage',
                    'description' => 'Remove a key from an external keychain',
                ],
            ],
        ];
        
        return ResponseFactory::single($this->responseFactory, $catalog);
    }
}
