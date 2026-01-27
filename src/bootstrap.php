<?php
/**
 * CRE8.pw Application Bootstrap
 * 
 * This file initializes the Slim application, configures dependency injection,
 * registers middleware pipelines, and sets up route groups.
 */

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Validate bootstrap requirements (fail fast if misconfigured)
// Bootstrap validation & fail-fast checks
try {
    App\Utilities\BootstrapValidator::validate();
} catch (\RuntimeException $e) {
    // Log error (when logging is available in M16)
    // For now, output to stderr and exit
    error_log("Bootstrap validation failed: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => [
            'code' => 'configuration_error',
            'message' => 'Application configuration is invalid',
            'details' => [],
        ]
    ], JSON_THROW_ON_ERROR);
    exit(1);
}

// Build DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Create Slim app with container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register route groups
$routes = require __DIR__ . '/../config/routes.php';
$routes($app);

// Register error handling middleware (last, catches all exceptions)
$app->add($container->get(\App\Middleware\ErrorHandlingMiddleware::class));

return $app;
