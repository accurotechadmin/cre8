<?php
/**
 * CRE8.pw Console HTML Route Group
 * 
 * Routes for Owner-facing HTML pages:
 * - GET / (landing page)
 * - GET /console/register (registration form)
 * - GET /console/login (login form)
 * - GET /console/dashboard (Owner dashboard)
 * - GET /console/keys (keys list)
 * - GET /console/groups (groups list)
 * - GET /console/keychains (keychains list)
 * - GET /console/posts (posts list)
 * 
 * Pipeline: Console HTML (CSRF Guard required)
 * CSRF: Required (HTML form submissions)
 * Auth: Session-based or Owner JWT for dashboard
 */

declare(strict_types=1);

use Slim\App;

/**
 * Register Console HTML routes
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    $container = $app->getContainer();
    
    // Split routes into public and protected groups
    $consoleController = $container->get(\App\Controllers\Console\ConsoleController::class);
    
    // Common middleware for all HTML routes
    $commonMiddleware = [
        $container->get(\App\Middleware\HttpsMiddleware::class),
        $container->get(\App\Middleware\CorsMiddleware::class),
        $container->get(\App\Middleware\RateLimitMiddleware::class),
        new \Slim\Csrf\Guard($container->get(\Psr\Http\Message\ResponseFactoryInterface::class)),
        $container->get(\App\Middleware\CspMiddleware::class),
        $container->get(\App\Middleware\CsrfExposeMiddleware::class),
    ];
    
    // Public routes (no auth required)
    $app->group('', function ($group) use ($consoleController) {
        // Landing page
        $group->get('/', [$consoleController, 'landing']);
        
        // Owner registration form
        $group->get('/console/register', [$consoleController, 'register']);
        
        // Owner login form
        $group->get('/console/login', [$consoleController, 'login']);
    })
    // Add RequestLoggingMiddleware as outer layer (first middleware) to capture full request lifecycle
    ->add($container->get(\App\Middleware\RequestLoggingMiddleware::class))
    ->add(...$commonMiddleware);
    
    // Protected routes (require JWT authentication)
    $app->group('', function ($group) use ($consoleController) {
        // Owner dashboard
        $group->get('/console/dashboard', [$consoleController, 'dashboard']);
        
        // Keys management pages
        $group->get('/console/keys', [$consoleController, 'keysList']);
        
        // Groups management pages
        $group->get('/console/groups', [$consoleController, 'groupsList']);
        
        // Keychains management pages
        $group->get('/console/keychains', [$consoleController, 'keychainsList']);
        
        // Posts administration pages
        $group->get('/console/posts', [$consoleController, 'postsList']);
    })
    // Add RequestLoggingMiddleware as outer layer (first middleware) to capture full request lifecycle
    ->add($container->get(\App\Middleware\RequestLoggingMiddleware::class))
        ->add($container->get(\App\Middleware\JwtOwnerMiddleware::class))  // JWT authentication required
    ->add(...$commonMiddleware);
};
