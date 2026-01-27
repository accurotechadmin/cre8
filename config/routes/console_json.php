<?php
/**
 * CRE8.pw Console JSON Route Group
 * 
 * Routes for Owner-protected JSON endpoints:
 * - /console/routes (route catalog)
 * - /console/keys/* (key management)
 * - /console/groups/* (group management)
 * - /console/keychains/* (keychain management)
 * - /console/posts/* (post management)
 * 
 * Pipeline: Console JSON (JwtOwnerMiddleware, typ=owner)
 * CSRF: NOT required (JSON endpoints use Bearer token auth)
 * Auth: Owner JWT (typ=owner) - enforced by JwtOwnerMiddleware
 * Rate Limiting: Keyed by owner_id (hex32)
 * 
 * Security Enforcement (T12.2):
 * - JwtOwnerMiddleware enforces typ=owner (rejects key tokens)
 * - All routes require valid Owner JWT
 * - Permissions enforced in Service layer (per-route requirements)
 * 
 * CSRF Enforcement (T12.3):
 * - CSRF middleware is NOT applied to this route group
 * - JSON endpoints use Bearer token authentication (stateless)
 * - CSRF protection is unnecessary and inappropriate for token-based auth
 * - If X-CSRF-* headers are present, they are ignored (not validated)
 * - CSRF scope = HTML routes only (see console_html.php)
 */

declare(strict_types=1);

use Slim\App;

/**
 * Register Console JSON routes
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    $container = $app->getContainer();
    
    // Console JSON routes with middleware pipeline
    $app->group('/console', function ($group) use ($container) {
        // Route catalog (T12.1: Console route catalog)
        $routeCatalogController = $container->get(\App\Controllers\Console\RouteCatalogController::class);
        $group->get('/routes', [$routeCatalogController, 'catalog']);
        
        // Key management routes (T7.1: Key minting flows, T7.3: Rotation + activate/deactivate)
        $group->group('/keys', function ($keyGroup) use ($container) {
            $keyController = $container->get(\App\Controllers\Console\KeyController::class);
            $keyGroup->post('/primary', [$keyController, 'mintPrimary']);
            $keyGroup->get('', [$keyController, 'list']);
            $keyGroup->get('/{keyId}', [$keyController, 'get']);
            $keyGroup->get('/{keyId}/lineage', [$keyController, 'getLineage']);
            $keyGroup->post('/{keyId}/rotate', [$keyController, 'rotate']);
            $keyGroup->post('/{keyId}/activate', [$keyController, 'activate']);
            $keyGroup->post('/{keyId}/deactivate', [$keyController, 'deactivate']);
        });

        // Group management routes (T10.1: Console group CRUD + membership)
        $group->group('/groups', function ($groupGroup) use ($container) {
            $groupController = $container->get(\App\Controllers\Console\GroupController::class);
            $groupGroup->post('', [$groupController, 'create']);
            $groupGroup->get('', [$groupController, 'list']);
            $groupGroup->get('/{groupId}', [$groupController, 'get']);
            $groupGroup->post('/{groupId}/rename', [$groupController, 'rename']);
            $groupGroup->delete('/{groupId}', [$groupController, 'delete']);
            $groupGroup->post('/{groupId}/members', [$groupController, 'addMember']);
            $groupGroup->delete('/{groupId}/members/{keyId}', [$groupController, 'removeMember']);
        });

        // Keychain management routes (T10.2: Console keychain CRUD + membership)
        $group->group('/keychains', function ($keychainGroup) use ($container) {
            $keychainController = $container->get(\App\Controllers\Console\KeychainController::class);
            $keychainGroup->get('', [$keychainController, 'list']);
            $keychainGroup->post('', [$keychainController, 'create']);
            $keychainGroup->post('/{id}/members', [$keychainController, 'addMember']);
            $keychainGroup->delete('/{id}/members/{keyId}', [$keychainController, 'removeMember']);
        });

        // Post management routes (T8.3: Owner admin post management)
        $group->group('/posts', function ($postGroup) use ($container) {
            $postController = $container->get(\App\Controllers\Console\PostController::class);
            $postGroup->get('', [$postController, 'list']);
            $postGroup->get('/{postId}', [$postController, 'get']);
            $postGroup->post('/{postId}/access/grant-group', [$postController, 'grantGroupAccess']);
            $postGroup->post('/{postId}/access/revoke-group', [$postController, 'revokeGroupAccess']);
        });
    })
    // Add RequestLoggingMiddleware as outer layer (first middleware) to capture full request lifecycle
    ->add($container->get(\App\Middleware\RequestLoggingMiddleware::class))
    ->add($container->get(\App\Middleware\HttpsMiddleware::class))
    ->add($container->get(\App\Middleware\CorsMiddleware::class))
    ->add($container->get(\App\Middleware\RateLimitMiddleware::class))
    ->add($container->get(\App\Middleware\JwtOwnerMiddleware::class))
    ->add(new \Slim\Middleware\BodyParsingMiddleware())
    ->add($container->get(\App\Middleware\RouteParameterValidatorMiddleware::class))
    ->add($container->get(\App\Middleware\ValidationMiddleware::class));
};
