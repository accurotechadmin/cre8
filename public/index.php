<?php
/**
 * CRE8.pw Application Entry Point
 * 
 * This is the public entry point for all HTTP requests.
 * All requests flow through this file to the Slim application.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap the application
$app = require __DIR__ . '/../src/bootstrap.php';

// Run the application
$app->run();
