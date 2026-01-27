<?php
/**
 * CRE8.pw Route Definitions
 * 
 * This file registers route groups for each surface:
 * - Public API (no auth)
 * - Console HTML (Owner-facing HTML pages)
 * - Console JSON (Owner-protected JSON endpoints)
 * - Gateway JSON (Key-protected JSON endpoints)
 * - Gateway HTML (Gateway API client example pages)
 */

declare(strict_types=1);

use Slim\App;

/**
 * Register all route groups
 * 
 * @param App $app Slim application instance
 */
return function (App $app): void {
    // Public API routes (no authentication)
    // Routes: /health, /.well-known/jwks.json, /api/auth/*, /console/owners, /console/login
    // Pipeline: HTTPS → CORS → RateLimit → BodyParsing → RouteParamValidator → Validation
    (require __DIR__ . '/routes/public_api.php')($app);
    
    // Console HTML routes (Owner-facing HTML pages with CSRF)
    // Routes: /, /console/register, /console/login, /console/dashboard
    // Pipeline: HTTPS → CORS → RateLimit → CSRF Guard → CSP → CSRF Expose
    (require __DIR__ . '/routes/console_html.php')($app);
    
    // Console JSON routes (Owner-protected JSON endpoints)
    // Routes: /console/keys/*, /console/groups/*, /console/keychains/*, /console/posts/*
    // Pipeline: HTTPS → CORS → RateLimit → JwtOwnerMiddleware → BodyParsing → RouteParamValidator → Validation
    (require __DIR__ . '/routes/console_json.php')($app);
    
    // Gateway JSON routes (Key-protected JSON endpoints)
    // Routes: /api/posts/*, /api/comments/*, /api/keys/*, /api/feed/*, /api/groups/*, /api/keychains/*
    // Pipeline: HTTPS → CORS → RateLimit → JwtKeyMiddleware → BodyParsing → RouteParamValidator → Validation
    (require __DIR__ . '/routes/gateway_json.php')($app);
    
    // Gateway HTML routes (Gateway API client example pages with CSRF)
    // Routes: /gateway/* (example pages that call /api/* JSON endpoints)
    // Pipeline: HTTPS → CORS → RateLimit → CSRF Guard → CSP → CSRF Expose
    (require __DIR__ . '/routes/gateway_html.php')($app);
};
