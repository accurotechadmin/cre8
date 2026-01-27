<?php
/**
 * CRE8.pw Gateway HTML Route Group
 * 
 * Routes for Gateway API client example HTML pages.
 * These are example/reference pages that demonstrate Gateway API usage.
 * They call the /api/* JSON endpoints.
 * 
 * Routes for Gateway API client example pages:
 * - /gateway/auth/exchange (API key exchange form)
 * - /gateway/auth/refresh (token refresh page)
 * - /gateway/keys/{authorKeyId}/secondary (mint secondary key)
 * - /gateway/keys/{authorKeyId}/use (mint use key)
 * - /gateway/posts (list + create posts)
 * - /gateway/posts/{postId} (post detail)
 * - /gateway/posts/{postId}/access (grant access)
 * - /gateway/posts/{postId}/comments (list + create comments)
 * - /gateway/feed/use/{useKeyId} (use key feed)
 * - /gateway/feed/author (author feed)
 * - /gateway/groups (list groups)
 * - /gateway/groups/{groupId} (group detail)
 * - /gateway/keychains (list + create keychains)
 * 
 * Pipeline: Gateway HTML (CSRF Guard required for forms)
 * CSRF: Required (HTML form submissions)
 * Auth: Key JWT (via Bearer token in forms/AJAX)
 */

declare(strict_types=1);

use Slim\App;

/**
 * Register Gateway HTML routes
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    $container = $app->getContainer();
    
    // Gateway HTML routes with middleware pipeline (CSRF + CSP)
    $app->group('/gateway', function ($group) use ($container) {
        // Gateway UI page set
        $gatewayController = $container->get(\App\Controllers\Gateway\GatewayController::class);
        
        // Auth & Token Pages
        $group->get('/auth/exchange', [$gatewayController, 'authExchange']);
        $group->get('/auth/refresh', [$gatewayController, 'authRefresh']);
        
        // Key Issuance Pages
        $group->get('/keys/{authorKeyId}/secondary', [$gatewayController, 'mintSecondaryKey']);
        $group->get('/keys/{authorKeyId}/use', [$gatewayController, 'mintUseKey']);
        
        // Posts + Access Pages
        $group->get('/posts', [$gatewayController, 'postsList']);
        $group->get('/posts/{postId}', [$gatewayController, 'postDetail']);
        $group->get('/posts/{postId}/access', [$gatewayController, 'grantAccess']);
        
        // Comments Pages
        $group->get('/posts/{postId}/comments', [$gatewayController, 'commentsList']);
        
        // Feed Pages
        $group->get('/feed/use/{useKeyId}', [$gatewayController, 'useKeyFeed']);
        $group->get('/feed/author', [$gatewayController, 'authorFeed']);
        
        // Groups (Read-Only) Pages
        $group->get('/groups', [$gatewayController, 'groupsList']);
        $group->get('/groups/{groupId}', [$gatewayController, 'groupDetail']);
        
        // External Keychains Pages
        $group->get('/keychains', [$gatewayController, 'keychainsList']);
        
        // Sharing workflow UI
        // Sharing workflow wizard (post → mint use key → grant → recipient view/comment)
        $group->get('/share', [$gatewayController, 'shareWorkflow']);
    })
    ->add($container->get(\App\Middleware\HttpsMiddleware::class))
    ->add($container->get(\App\Middleware\CorsMiddleware::class))
    ->add($container->get(\App\Middleware\RateLimitMiddleware::class))
    ->add(new \Slim\Csrf\Guard($container->get(\Psr\Http\Message\ResponseFactoryInterface::class)))
    ->add($container->get(\App\Middleware\CspMiddleware::class))
    ->add($container->get(\App\Middleware\CsrfExposeMiddleware::class));
};
