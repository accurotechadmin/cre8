<?php
/**
 * CRE8.pw Public API Route Group
 * 
 * Routes that require no authentication:
 * - /health
 * - /.well-known/jwks.json
 * - /api/auth/* (ApiKey exchange, refresh)
 * - /console/owners (Owner registration)
 * - /console/login (Owner login)
 * 
 * Pipeline: Public API (no JWT middleware)
 * CSRF: Not applicable (JSON endpoints)
 */

declare(strict_types=1);

use Slim\App;

/**
 * Register Public API routes
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    $container = $app->getContainer();
    
    // Public API routes with middleware pipeline
    $app->group('', function ($group) use ($container) {
        // Health check endpoint (T18.1)
        $group->get('/health', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\HealthController::class);
            return $controller->health($request, $response);
        });

        // JWKS endpoint (T5.5, T18.2)
        $group->get('/.well-known/jwks.json', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\JwksController::class);
            return $controller->jwks($request, $response);
        });

        // ApiKey exchange (T5.3)
        $group->post('/api/auth/exchange', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\OwnerController::class);
            return $controller->exchange($request, $response);
        });

        // Refresh token rotation (T5.4)
        $group->post('/api/auth/refresh', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\OwnerController::class);
            return $controller->refresh($request, $response);
        });

        // Owner registration (T5.2)
        $group->post('/console/owners', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\OwnerController::class);
            return $controller->register($request, $response);
        });

        // Owner login (T5.2)
        $group->post('/console/login', function ($request, $response) use ($container) {
            $controller = $container->get(\App\Controllers\OwnerController::class);
            return $controller->login($request, $response);
        });
    })
    // Add RequestLoggingMiddleware as outer layer (first middleware) to capture full request lifecycle
    ->add($container->get(\App\Middleware\RequestLoggingMiddleware::class))
    ->add($container->get(\App\Middleware\HttpsMiddleware::class))
    ->add($container->get(\App\Middleware\CorsMiddleware::class))
    ->add($container->get(\App\Middleware\RateLimitMiddleware::class))
    ->add(new \Slim\Middleware\BodyParsingMiddleware())
    ->add($container->get(\App\Middleware\RouteParameterValidatorMiddleware::class))
    ->add($container->get(\App\Middleware\ValidationMiddleware::class));
};
