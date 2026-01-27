<?php
/**
 * CRE8.pw Gateway JSON Route Group
 * 
 * Routes for Key-protected JSON API endpoints:
 * - /api/posts/* (post creation, access management)
 * - /api/comments/* (comment creation, listing)
 * - /api/keys/* (key issuance, rotation)
 * - /api/feed/* (feed endpoints)
 * - /api/groups/* (read-only group access)
 * - /api/keychains/* (external keychain management)
 * - /api/routes (route catalog)
 * 
 * Pipeline: Gateway JSON (JwtKeyMiddleware, typ=key)
 * CSRF: NOT required (JSON endpoints use Bearer token auth)
 * Auth: Key JWT (typ=key) - enforced by JwtKeyMiddleware
 * Rate Limiting: Keyed by key_id (hex32)
 * 
 * Security Enforcement (T11.2):
 * - JwtKeyMiddleware enforces typ=key (rejects owner tokens)
 * - All routes require valid Key JWT
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
 * Register Gateway JSON routes
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    $container = $app->getContainer();
    
    // Gateway JSON routes with middleware pipeline
    $app->group('/api', function ($group) use ($container) {
        // Route catalog (T11.1: Gateway route catalog)
        $routeCatalogController = $container->get(\App\Controllers\Gateway\RouteCatalogController::class);
        $group->get('/routes', [$routeCatalogController, 'catalog']);
        
        // Post routes (T8.1: Post creation, T8.2: Access grant/revoke)
        $group->group('/posts', function ($postGroup) use ($container) {
            $postController = $container->get(\App\Controllers\Gateway\PostController::class);
            $postGroup->post('', [$postController, 'create']);
            $postGroup->get('', [$postController, 'list']);
            $postGroup->get('/{postId}', [$postController, 'get']);
            $postGroup->post('/{postId}/access', [$postController, 'grantAccess']);
            $postGroup->delete('/{postId}/access/{targetType}/{targetId}', [$postController, 'revokeAccess']);
            
            // Comment routes (T9.1: Comment creation, T9.2: Comment listing)
            $commentController = $container->get(\App\Controllers\Gateway\CommentController::class);
            $postGroup->post('/{postId}/comments', [$commentController, 'create']);
            $postGroup->get('/{postId}/comments', [$commentController, 'list']);
        });

        // Key issuance routes (T7.1: Key minting flows)
        $group->group('/keys', function ($keyGroup) use ($container) {
            $keyController = $container->get(\App\Controllers\Gateway\KeyController::class);
            $keyGroup->post('/{authorKeyId}/secondary', [$keyController, 'mintSecondary']);
            $keyGroup->post('/{authorKeyId}/use', [$keyController, 'mintUse']);
        });

        // Feed routes (T13.1: Use Key feed, T13.2: Author feed scaffolding)
        $group->group('/feed', function ($feedGroup) use ($container) {
            $feedController = $container->get(\App\Controllers\Gateway\FeedController::class);
            $feedGroup->get('/use/{useKeyId}', [$feedController, 'getUseFeed']);
            $feedGroup->get('/author', [$feedController, 'getAuthorFeed']);
        });

        // Group routes (T10.3: Gateway groups read-only)
        $group->group('/groups', function ($groupGroup) use ($container) {
            $groupController = $container->get(\App\Controllers\Gateway\GroupController::class);
            $groupGroup->get('', [$groupController, 'list']);
            $groupGroup->get('/{groupId}', [$groupController, 'get']);
            $groupGroup->get('/{groupId}/members', [$groupController, 'listMembers']);
        });

        // Keychain routes (T10.4: Gateway external keychains)
        $group->group('/keychains', function ($keychainGroup) use ($container) {
            $keychainController = $container->get(\App\Controllers\Gateway\KeychainController::class);
            $keychainGroup->post('', [$keychainController, 'create']);
            $keychainGroup->post('/{id}/members', [$keychainController, 'addMember']);
            $keychainGroup->delete('/{id}/members/{keyId}', [$keychainController, 'removeMember']);
        });
    })
    // Add RequestLoggingMiddleware as outer layer (first middleware) to capture full request lifecycle
    ->add($container->get(\App\Middleware\RequestLoggingMiddleware::class))
    ->add($container->get(\App\Middleware\HttpsMiddleware::class))
    ->add($container->get(\App\Middleware\CorsMiddleware::class))
    ->add($container->get(\App\Middleware\RateLimitMiddleware::class))
    ->add($container->get(\App\Middleware\JwtKeyMiddleware::class))
    ->add($container->get(\App\Middleware\UseKeyLimitMiddleware::class))
    ->add(new \Slim\Middleware\BodyParsingMiddleware())
    ->add($container->get(\App\Middleware\RouteParameterValidatorMiddleware::class))
    ->add($container->get(\App\Middleware\ValidationMiddleware::class));
};
