Based on the documentation, I'll provide a detailed explanation of how each dependency in the `composer.json` file is wired throughout the CRE8.pw application. The documentation specifies the exact packages and versions, and describes how they integrate across the layered architecture.

---

# CRE8.pw Dependency Wiring Guide

## Complete Composer Dependencies

Based on the documentation (specifically **[implementation-guide.md](implementation-guide.md)**), here are all the dependencies:

```json
{
  "require": {
    "php": "^8.3",
    "slim/slim": "^4.15",
    "slim/psr7": "^1.7",
    "php-di/php-di": "^7.1",
    "firebase/php-jwt": "^6.11",
    "ext-pdo": "*",
    "ext-sodium": "*",
    "respect/validation": "^2.4",
    "vlucas/phpdotenv": "^5.6",
    "guzzlehttp/guzzle": "^7.10",
    "neomerx/cors-psr7": "^3.0",
    "slim/csrf": "^1.5",
    "monolog/monolog": "^3.9",
    "symfony/rate-limiter": "^7.3",
    "symfony/cache": "^7.3"
  }
}
```

---

## 1. Core Framework: `slim/slim` ^4.15 + `slim/psr7` ^1.7

### Purpose
Slim is the micro-framework that handles HTTP routing, middleware pipelines, and request/response handling.

### Wiring Location: `public/index.php` (Entry Point)

```php
<?php
// public/index.php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables (vlucas/phpdotenv)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Build DI container (php-di/php-di)
$container = require __DIR__ . '/../config/container.php';

// Create Slim app with container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware (order matters - last added runs first)
$app->addBodyParsingMiddleware();  // Parse JSON/form bodies
$app->addRoutingMiddleware();       // Route resolution

// Add custom middleware from container
$app->add($container->get(ErrorHandlingMiddleware::class));
$app->add($container->get(ValidationMiddleware::class));
// JWT middleware added per-route-group (see below)
$app->add($container->get(RateLimitMiddleware::class));
$app->add($container->get(CorsMiddleware::class));
$app->add($container->get(HttpsMiddleware::class));

// Load routes
require __DIR__ . '/../config/routes.php';

// Run application
$app->run();
```

### Wiring Location: `config/routes.php`

```php
<?php
// config/routes.php

use Slim\Routing\RouteCollectorProxy;
use App\Controllers\Gateway\PostController;
use App\Controllers\Gateway\KeyController;
use App\Controllers\Console\OwnerController;
use App\Middleware\JwtKeyMiddleware;
use App\Middleware\JwtOwnerMiddleware;

// ─────────────────────────────────────────────────────────────
// PUBLIC ROUTES (No Authentication)
// ─────────────────────────────────────────────────────────────
$app->get('/health', [HealthController::class, 'check']);
$app->get('/.well-known/jwks.json', [JwksController::class, 'keys']);

$app->group('/api/auth', function (RouteCollectorProxy $group) {
    $group->post('/exchange', [ApiKeyController::class, 'exchange']);
    $group->post('/refresh', [AuthController::class, 'refresh']);
});

$app->post('/console/owners', [OwnerController::class, 'register']);
$app->post('/console/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────────────────────
// GATEWAY ROUTES (Key JWT Required - typ=key)
// ─────────────────────────────────────────────────────────────
$app->group('/api', function (RouteCollectorProxy $group) {
    // Posts
    $group->post('/posts', [PostController::class, 'create']);
    $group->get('/posts', [PostController::class, 'list']);
    $group->get('/posts/{postId}', [PostController::class, 'show']);
    $group->post('/posts/{postId}/access', [PostController::class, 'grantAccess']);
    $group->delete('/posts/{postId}/access/{accessId}', [PostController::class, 'revokeAccess']);
    
    // Comments
    $group->get('/posts/{postId}/comments', [CommentController::class, 'list']);
    $group->post('/posts/{postId}/comments', [CommentController::class, 'create']);
    
    // Key Issuance
    $group->post('/keys/{authorKeyId}/secondary', [KeyController::class, 'mintSecondary']);
    $group->post('/keys/{authorKeyId}/use', [KeyController::class, 'mintUse']);
    
    // Feeds
    $group->get('/feed/use/{useKeyId}', [FeedController::class, 'useKeyFeed']);
    $group->get('/feed/author', [FeedController::class, 'authorFeed']);
    
    // Groups (read-only)
    $group->get('/groups', [GroupController::class, 'list']);
    $group->get('/groups/{groupId}', [GroupController::class, 'show']);
    $group->get('/groups/{groupId}/members', [GroupController::class, 'members']);
    
    // External Keychains
    $group->post('/keychains', [KeychainController::class, 'create']);
    $group->post('/keychains/{id}/members', [KeychainController::class, 'addMember']);
    $group->delete('/keychains/{id}/members/{keyId}', [KeychainController::class, 'removeMember']);
    
})->add(JwtKeyMiddleware::class);  // ← Middleware applied to entire group

// ─────────────────────────────────────────────────────────────
// CONSOLE JSON ROUTES (Owner JWT Required - typ=owner)
// ─────────────────────────────────────────────────────────────
$app->group('/console', function (RouteCollectorProxy $group) {
    // Key Management
    $group->post('/keys/primary', [ConsoleKeyController::class, 'mintPrimary']);
    $group->get('/keys', [ConsoleKeyController::class, 'list']);
    $group->get('/keys/{keyId}', [ConsoleKeyController::class, 'show']);
    $group->get('/keys/{keyId}/lineage', [ConsoleKeyController::class, 'lineage']);
    $group->post('/keys/{keyId}/rotate', [ConsoleKeyController::class, 'rotate']);
    $group->post('/keys/{keyId}/activate', [ConsoleKeyController::class, 'activate']);
    $group->post('/keys/{keyId}/deactivate', [ConsoleKeyController::class, 'deactivate']);
    
    // Groups
    $group->post('/groups', [ConsoleGroupController::class, 'create']);
    $group->get('/groups', [ConsoleGroupController::class, 'list']);
    $group->get('/groups/{groupId}', [ConsoleGroupController::class, 'show']);
    $group->post('/groups/{groupId}/rename', [ConsoleGroupController::class, 'rename']);
    $group->delete('/groups/{groupId}', [ConsoleGroupController::class, 'delete']);
    $group->post('/groups/{groupId}/members', [ConsoleGroupController::class, 'addMember']);
    $group->delete('/groups/{groupId}/members/{keyId}', [ConsoleGroupController::class, 'removeMember']);
    
    // Keychains
    $group->get('/keychains', [ConsoleKeychainController::class, 'list']);
    $group->post('/keychains', [ConsoleKeychainController::class, 'create']);
    $group->post('/keychains/{id}/members', [ConsoleKeychainController::class, 'addMember']);
    $group->delete('/keychains/{id}/members/{keyId}', [ConsoleKeychainController::class, 'removeMember']);
    
    // Posts (Admin)
    $group->get('/posts', [ConsolePostController::class, 'list']);
    $group->get('/posts/{postId}', [ConsolePostController::class, 'show']);
    $group->post('/posts/{postId}/access/grant-group', [ConsolePostController::class, 'grantGroupAccess']);
    $group->post('/posts/{postId}/access/revoke-group', [ConsolePostController::class, 'revokeGroupAccess']);
    
})->add(JwtOwnerMiddleware::class);  // ← Middleware applied to entire group

// ─────────────────────────────────────────────────────────────
// CONSOLE HTML ROUTES (CSRF Required)
// ─────────────────────────────────────────────────────────────
$app->get('/', [ConsoleController::class, 'landing']);
$app->get('/console/register', [ConsoleController::class, 'registerPage'])
    ->add($container->get(Slim\Csrf\Guard::class));
$app->get('/console/login', [ConsoleController::class, 'loginPage'])
    ->add($container->get(Slim\Csrf\Guard::class));
$app->get('/console/dashboard', [ConsoleController::class, 'dashboard'])
    ->add($container->get(Slim\Csrf\Guard::class))
    ->add(JwtOwnerMiddleware::class);
```

### How Slim PSR-7 Is Used

```php
<?php
// src/Controllers/Gateway/PostController.php

namespace App\Controllers\Gateway;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\PostService;

class PostController
{
    public function __construct(
        private PostService $postService,
        private ResponseFactory $responseFactory
    ) {}

    public function create(Request $request, Response $response): Response
    {
        // Extract key_id from JWT (attached by JwtKeyMiddleware)
        $keyId = $request->getAttribute('key_id');  // hex32
        
        // Get parsed body (from BodyParsingMiddleware)
        $body = $request->getParsedBody();
        
        // Call service
        $post = $this->postService->createPost(
            $keyId,
            $body['content'],
            $body['title'] ?? null
        );
        
        // Return standardized response using Slim PSR-7
        return $this->responseFactory->json($response, ['data' => $post], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $postId = $args['postId'];  // From route parameter {postId}
        $keyId = $request->getAttribute('key_id');
        
        $post = $this->postService->getPost($postId, $keyId);
        
        return $this->responseFactory->json($response, ['data' => $post]);
    }
}
```

---

## 2. Dependency Injection: `php-di/php-di` ^7.1

### Purpose
PHP-DI provides autowiring and explicit dependency injection for all services, repositories, controllers, and middleware.

### Wiring Location: `config/container.php`

```php
<?php
// config/container.php

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Cache\Adapter\PdoAdapter;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    
    // ─────────────────────────────────────────────────────────────
    // DATABASE (PDO)
    // ─────────────────────────────────────────────────────────────
    PDO::class => function (ContainerInterface $c): PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'] ?? 3306,
            $_ENV['DB_NAME']
        );
        
        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Ensure proper collation
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_bin");
        
        return $pdo;
    },

    // ─────────────────────────────────────────────────────────────
    // LOGGING (Monolog)
    // ─────────────────────────────────────────────────────────────
    LoggerInterface::class => function (): LoggerInterface {
        $logger = new Logger('cre8pw');
        
        $handler = new StreamHandler(
            $_ENV['LOG_PATH'] . '/app.log',
            Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'info')
        );
        $handler->setFormatter(new JsonFormatter());
        
        $logger->pushHandler($handler);
        
        return $logger;
    },
    
    // Named loggers for specific channels
    'logger.api' => function (ContainerInterface $c): LoggerInterface {
        $logger = new Logger('api');
        $handler = new StreamHandler($_ENV['LOG_PATH'] . '/api.log');
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);
        return $logger;
    },
    
    'logger.auth' => function (ContainerInterface $c): LoggerInterface {
        $logger = new Logger('auth');
        $handler = new StreamHandler($_ENV['LOG_PATH'] . '/auth.log');
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);
        return $logger;
    },
    
    'logger.security' => function (ContainerInterface $c): LoggerInterface {
        $logger = new Logger('security');
        $handler = new StreamHandler($_ENV['LOG_PATH'] . '/security.log');
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);
        return $logger;
    },
    
    'logger.db' => function (ContainerInterface $c): LoggerInterface {
        $logger = new Logger('db');
        $handler = new StreamHandler($_ENV['LOG_PATH'] . '/db.log');
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);
        return $logger;
    },

    // ─────────────────────────────────────────────────────────────
    // JWT HELPER (firebase/php-jwt)
    // ─────────────────────────────────────────────────────────────
    JwtHelper::class => DI\factory(function (): JwtHelper {
        $privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH'];
        $publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH'];
        
        // Bootstrap validation - fail fast
        if (!file_exists($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new RuntimeException("JWT private key not found or unreadable: $privateKeyPath");
        }
        if (!file_exists($publicKeyPath) || !is_readable($publicKeyPath)) {
            throw new RuntimeException("JWT public key not found or unreadable: $publicKeyPath");
        }
        
        return new JwtHelper(
            privateKey: file_get_contents($privateKeyPath),
            publicKey: file_get_contents($publicKeyPath),
            algorithm: $_ENV['JWT_ALGO'] ?? 'RS256',
            issuer: $_ENV['JWT_ISSUER'],
            audience: $_ENV['JWT_AUDIENCE'],
            accessTtl: (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900),
            refreshTtl: (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000),
            leeway: (int) ($_ENV['JWT_LEEWAY'] ?? 10)
        );
    })->singleton(),  // ← Singleton: expensive setup, stateless

    // ─────────────────────────────────────────────────────────────
    // RATE LIMITING (Symfony)
    // ─────────────────────────────────────────────────────────────
    'rate_limiter.storage' => function (ContainerInterface $c) {
        $backing = $_ENV['RATE_LIMIT_BACKING'] ?? 'memory';
        
        if ($backing === 'database') {
            return new PdoAdapter($c->get(PDO::class), 'rate_limits');
        }
        
        return new InMemoryStorage();
    },
    
    'rate_limiter.general' => function (ContainerInterface $c): RateLimiterFactory {
        // Parse "100 per minute" format
        [$limit, , $interval] = explode(' ', $_ENV['RATE_LIMIT_GENERAL'] ?? '100 per minute');
        
        return new RateLimiterFactory([
            'id' => 'general',
            'policy' => 'sliding_window',
            'limit' => (int) $limit,
            'interval' => "1 $interval",
        ], $c->get('rate_limiter.storage'));
    },
    
    'rate_limiter.auth' => function (ContainerInterface $c): RateLimiterFactory {
        [$limit, , $interval] = explode(' ', $_ENV['RATE_LIMIT_AUTH'] ?? '10 per minute');
        
        return new RateLimiterFactory([
            'id' => 'auth',
            'policy' => 'sliding_window',
            'limit' => (int) $limit,
            'interval' => "1 $interval",
        ], $c->get('rate_limiter.storage'));
    },
    
    'rate_limiter.api' => function (ContainerInterface $c): RateLimiterFactory {
        [$limit, , $interval] = explode(' ', $_ENV['RATE_LIMIT_API'] ?? '60 per minute');
        
        return new RateLimiterFactory([
            'id' => 'api',
            'policy' => 'sliding_window',
            'limit' => (int) $limit,
            'interval' => "1 $interval",
        ], $c->get('rate_limiter.storage'));
    },

    // ─────────────────────────────────────────────────────────────
    // CORS (neomerx/cors-psr7)
    // ─────────────────────────────────────────────────────────────
    CorsSettings::class => function (): CorsSettings {
        return new CorsSettings([
            'allowedOrigins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? ''),
            'allowedMethods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
            'allowedHeaders' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Authorization,Content-Type'),
            'exposedHeaders' => explode(',', $_ENV['CORS_EXPOSED_HEADERS'] ?? ''),
            'maxAge' => 86400,
            'credentialsSupported' => true,
        ]);
    },

    // ─────────────────────────────────────────────────────────────
    // CSRF (slim/csrf) - HTML routes only
    // ─────────────────────────────────────────────────────────────
    Slim\Csrf\Guard::class => function (ContainerInterface $c): Slim\Csrf\Guard {
        $responseFactory = $c->get(Slim\Psr7\Factory\ResponseFactory::class);
        
        $guard = new Slim\Csrf\Guard(
            $responseFactory,
            'csrf',           // Prefix for token names
            200,              // Strength (bytes of entropy)
            true              // Persistent tokens (survive page refresh)
        );
        
        // Custom failure handler
        $guard->setFailureHandler(function ($request, $handler) use ($c) {
            $logger = $c->get('logger.security');
            $logger->warning('CSRF validation failed', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'path' => $request->getUri()->getPath(),
            ]);
            
            throw new CsrfValidationException('CSRF token validation failed');
        });
        
        return $guard;
    },

    // ─────────────────────────────────────────────────────────────
    // HTTP CLIENT (Guzzle)
    // ─────────────────────────────────────────────────────────────
    GuzzleHttp\Client::class => function (ContainerInterface $c): GuzzleHttp\Client {
        return new GuzzleHttp\Client([
            'timeout' => (float) ($_ENV['HTTP_TIMEOUT'] ?? 30),
            'connect_timeout' => 10,
            'http_errors' => false,  // Don't throw on 4xx/5xx
            'headers' => [
                'User-Agent' => 'CRE8.pw/1.0',
            ],
        ]);
    },

    // ─────────────────────────────────────────────────────────────
    // HASHING UTILITY
    // ─────────────────────────────────────────────────────────────
    HashingService::class => function (): HashingService {
        return new HashingService(
            memoryCost: (int) ($_ENV['PASSWORD_MEMORY_COST'] ?? 65536),
            timeCost: (int) ($_ENV['PASSWORD_TIME_COST'] ?? 4),
            parallelism: (int) ($_ENV['PASSWORD_PARALLELISM'] ?? 1)
        );
    },

    // ─────────────────────────────────────────────────────────────
    // REPOSITORIES (autowired with PDO injection)
    // ─────────────────────────────────────────────────────────────
    OwnerRepository::class => DI\autowire(),
    KeyRepository::class => DI\autowire(),
    PostRepository::class => DI\autowire(),
    CommentRepository::class => DI\autowire(),
    GroupRepository::class => DI\autowire(),
    KeychainRepository::class => DI\autowire(),
    TokenRepository::class => DI\autowire(),
    AuditRepository::class => DI\autowire(),

    // ─────────────────────────────────────────────────────────────
    // SERVICES (autowired with repository/helper injection)
    // ─────────────────────────────────────────────────────────────
    AuthService::class => DI\autowire(),
    OwnerService::class => DI\autowire(),
    KeyService::class => DI\autowire(),
    PostService::class => DI\autowire(),
    CommentService::class => DI\autowire(),
    FeedService::class => DI\autowire(),
    GroupService::class => DI\autowire(),
    KeychainService::class => DI\autowire(),

    // ─────────────────────────────────────────────────────────────
    // CONTROLLERS (autowired with service injection)
    // ─────────────────────────────────────────────────────────────
    // Gateway Controllers
    PostController::class => DI\autowire(),
    CommentController::class => DI\autowire(),
    KeyController::class => DI\autowire(),
    FeedController::class => DI\autowire(),
    GroupController::class => DI\autowire(),
    KeychainController::class => DI\autowire(),
    ApiKeyController::class => DI\autowire(),
    AuthController::class => DI\autowire(),
    
    // Console Controllers
    ConsoleKeyController::class => DI\autowire(),
    ConsoleGroupController::class => DI\autowire(),
    ConsoleKeychainController::class => DI\autowire(),
    ConsolePostController::class => DI\autowire(),
    ConsoleController::class => DI\autowire(),
    OwnerController::class => DI\autowire(),
    
    // Infrastructure Controllers
    HealthController::class => DI\autowire(),
    JwksController::class => DI\autowire(),

    // ─────────────────────────────────────────────────────────────
    // MIDDLEWARE (autowired with dependencies)
    // ─────────────────────────────────────────────────────────────
    HttpsMiddleware::class => DI\autowire(),
    CorsMiddleware::class => DI\autowire(),
    RateLimitMiddleware::class => DI\autowire(),
    JwtOwnerMiddleware::class => DI\autowire(),
    JwtKeyMiddleware::class => DI\autowire(),
    ValidationMiddleware::class => DI\autowire(),
    ErrorHandlingMiddleware::class => DI\autowire(),

    // ─────────────────────────────────────────────────────────────
    // VALIDATION RULES
    // ─────────────────────────────────────────────────────────────
    'validation.rules' => function (): array {
        return require __DIR__ . '/validation.php';
    },

    // ─────────────────────────────────────────────────────────────
    // RESPONSE FACTORY
    // ─────────────────────────────────────────────────────────────
    ResponseFactory::class => DI\autowire(),
    Slim\Psr7\Factory\ResponseFactory::class => DI\create(),

]);

return $builder->build();
```

---

## 3. JWT Handling: `firebase/php-jwt` ^6.11

### Purpose
Handles RS256 JWT signing, verification, and JWKS generation.

### Wiring Location: `src/Security/JwtHelper.php`

```php
<?php
// src/Security/JwtHelper.php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\JWK;
use UnexpectedValueException;
use DomainException;

class JwtHelper
{
    private string $privateKey;
    private string $publicKey;
    private string $algorithm;
    private string $issuer;
    private string $audience;
    private int $accessTtl;
    private int $refreshTtl;
    private int $leeway;
    private string $kid;

    public function __construct(
        string $privateKey,
        string $publicKey,
        string $algorithm,
        string $issuer,
        string $audience,
        int $accessTtl,
        int $refreshTtl,
        int $leeway
    ) {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->algorithm = $algorithm;
        $this->issuer = $issuer;
        $this->audience = $audience;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
        $this->leeway = $leeway;
        
        // Generate kid from public key thumbprint
        $pubKeyResource = openssl_pkey_get_public($publicKey);
        $details = openssl_pkey_get_details($pubKeyResource);
        $this->kid = $this->base64UrlEncode(
            hash('sha256', $details['key'], true)
        );
        
        // Set leeway for clock skew
        JWT::$leeway = $leeway;
    }

    /**
     * Generate Access Token for Owner
     */
    public function generateOwnerAccessToken(
        string $ownerId,
        array $roles,
        array $permissions
    ): string {
        $now = time();
        
        $payload = [
            'iss' => $this->issuer,
            'sub' => "owner:$ownerId",
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'typ' => 'owner',
            'owner_id' => $ownerId,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        
        return JWT::encode($payload, $this->privateKey, $this->algorithm, $this->kid);
    }

    /**
     * Generate Access Token for Key
     */
    public function generateKeyAccessToken(
        string $keyId,
        string $keyPublicId,
        array $roles,
        array $permissions
    ): string {
        $now = time();
        
        $payload = [
            'iss' => $this->issuer,
            'sub' => "key:$keyId",
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'typ' => 'key',
            'key_id' => $keyId,
            'key_public_id' => $keyPublicId,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
        
        return JWT::encode($payload, $this->privateKey, $this->algorithm, $this->kid);
    }

    /**
     * Verify and decode a JWT
     * @throws UnexpectedValueException on invalid token
     */
    public function verifyToken(string $token): object
    {
        return JWT::decode(
            $token,
            new Key($this->publicKey, $this->algorithm)
        );
    }

    /**
     * Generate JWKS response for /.well-known/jwks.json
     */
    public function getJwks(): array
    {
        $pubKeyResource = openssl_pkey_get_public($this->publicKey);
        $details = openssl_pkey_get_details($pubKeyResource);
        
        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => $this->algorithm,
                'kid' => $this->kid,
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ]]
        ];
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
```

### Wiring in JWT Middleware: `src/Middleware/JwtKeyMiddleware.php`

```php
<?php
// src/Middleware/JwtKeyMiddleware.php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Security\JwtHelper;
use App\Exceptions\UnauthorizedException;
use Psr\Log\LoggerInterface;

class JwtKeyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JwtHelper $jwtHelper,
        private LoggerInterface $logger  // Injected as 'logger.auth'
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $this->logger->warning('Missing or malformed Authorization header', [
                'path' => $request->getUri()->getPath(),
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);
            throw new UnauthorizedException('Missing or invalid Authorization header');
        }
        
        $token = $matches[1];
        
        try {
            $decoded = $this->jwtHelper->verifyToken($token);
        } catch (\Exception $e) {
            $this->logger->warning('JWT verification failed', [
                'error' => $e->getMessage(),
                'path' => $request->getUri()->getPath(),
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);
            throw new UnauthorizedException('Invalid or expired token');
        }
        
        // Enforce token typing: must be typ=key
        if (!isset($decoded->typ) || $decoded->typ !== 'key') {
            $this->logger->warning('Token type mismatch', [
                'expected' => 'key',
                'actual' => $decoded->typ ?? 'missing',
                'path' => $request->getUri()->getPath(),
            ]);
            throw new UnauthorizedException('Invalid token type for this endpoint');
        }
        
        // Attach trusted attributes to request
        $request = $request
            ->withAttribute('key_id', $decoded->key_id)
            ->withAttribute('key_public_id', $decoded->key_public_id ?? null)
            ->withAttribute('roles', $decoded->roles ?? [])
            ->withAttribute('permissions', $decoded->permissions ?? []);
        
        $this->logger->debug('Key JWT verified', [
            'key_id' => $decoded->key_id,
            'roles' => $decoded->roles,
        ]);
        
        return $handler->handle($request);
    }
}
```

### Wiring in JWKS Controller: `src/Controllers/JwksController.php`

```php
<?php
// src/Controllers/JwksController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Security\JwtHelper;

class JwksController
{
    public function __construct(private JwtHelper $jwtHelper) {}

    public function keys(Request $request, Response $response): Response
    {
        $jwks = $this->jwtHelper->getJwks();
        
        $response->getBody()->write(json_encode($jwks));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=600, must-revalidate');
    }
}
```

---

## 4. Validation: `respect/validation` ^2.4

### Purpose
Provides declarative, chainable validation rules for request bodies, query parameters, and headers.

### Wiring Location: `config/validation.php`

```php
<?php
// config/validation.php

use Respect\Validation\Validator as v;

return [
    // ─────────────────────────────────────────────────────────────
    // AUTHENTICATION
    // ─────────────────────────────────────────────────────────────
    "POST /console/owners" => [
        'body' => v::key('email', v::email()->notEmpty())
                    ->key('password', v::stringType()->notEmpty()->length(8, 255)),
        'rejectUnknown' => true,
    ],

    "POST /console/login" => [
        'body' => v::key('email', v::email()->notEmpty())
                    ->key('password', v::stringType()->notEmpty()),
        'rejectUnknown' => true,
    ],

    "POST /api/auth/refresh" => [
        'body' => v::key('refresh_token', v::stringType()->notEmpty()),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // CONSOLE KEY MANAGEMENT
    // ─────────────────────────────────────────────────────────────
    "POST /console/keys/primary" => [
        'body' => v::key('permissions', v::arrayType()->notEmpty()->each(v::stringType()))
                    ->key('label', v::optional(v::stringType()->length(1, 100))),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // GATEWAY KEY ISSUANCE
    // ─────────────────────────────────────────────────────────────
    "POST /api/keys/{authorKeyId}/secondary" => [
        'body' => v::key('permissions', v::arrayType()->notEmpty()->each(v::stringType()))
                    ->key('label', v::optional(v::stringType()->length(1, 100))),
        'rejectUnknown' => true,
    ],

    "POST /api/keys/{authorKeyId}/use" => [
        'body' => v::key('permissions', v::arrayType()->notEmpty()->each(v::stringType()))
                    ->key('label', v::optional(v::stringType()->length(1, 100)))
                    ->key('use_count', v::optional(v::intType()->positive()))
                    ->key('device_limit', v::optional(v::intType()->positive())),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // POSTS
    // ─────────────────────────────────────────────────────────────
    "POST /api/posts" => [
        'body' => v::key('content', v::stringType()->notEmpty()->length(1, 10000))
                    ->key('title', v::optional(v::stringType()->length(1, 255))),
        'rejectUnknown' => true,
    ],

    "POST /api/posts/{postId}/access" => [
        'body' => v::key('target_type', v::in(['key', 'group']))
                    ->key('target_id', v::stringType()->regex('/^[a-f0-9]{32}$/'))
                    ->key('permission_mask', v::intType()->between(0, 255)),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // COMMENTS
    // ─────────────────────────────────────────────────────────────
    "POST /api/posts/{postId}/comments" => [
        'body' => v::key('body', v::stringType()->notEmpty()->length(1, 5000)),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // GROUPS
    // ─────────────────────────────────────────────────────────────
    "POST /console/groups" => [
        'body' => v::key('name', v::stringType()->notEmpty()->length(1, 255)),
        'rejectUnknown' => true,
    ],

    "POST /console/groups/{groupId}/rename" => [
        'body' => v::key('name', v::stringType()->notEmpty()->length(1, 255)),
        'rejectUnknown' => true,
    ],

    "POST /console/groups/{groupId}/members" => [
        'body' => v::key('key_id', v::stringType()->regex('/^[a-f0-9]{32}$/')),
        'rejectUnknown' => true,
    ],

    "POST /console/posts/{postId}/access/grant-group" => [
        'body' => v::key('group_id', v::stringType()->regex('/^[a-f0-9]{32}$/'))
                    ->key('permission_mask', v::intType()->between(0, 255)),
        'rejectUnknown' => true,
    ],

    // ─────────────────────────────────────────────────────────────
    // KEYCHAINS
    // ─────────────────────────────────────────────────────────────
    "POST /console/keychains" => [
        'body' => v::key('name', v::stringType()->notEmpty()->length(1, 255)),
        'rejectUnknown' => true,
    ],

    "POST /api/keychains" => [
        'body' => v::key('name', v::stringType()->notEmpty()->length(1, 255)),
        'rejectUnknown' => true,
    ],

    "POST /api/keychains/{id}/members" => [
        'body' => v::key('key_id', v::stringType()->regex('/^[a-f0-9]{32}$/')),
        'rejectUnknown' => true,
    ],

    "POST /console/keychains/{id}/members" => [
        'body' => v::key('key_id', v::stringType()->regex('/^[a-f0-9]{32}$/')),
        'rejectUnknown' => true,
    ],
];
```

### Wiring in Validation Middleware: `src/Middleware/ValidationMiddleware.php`

```php
<?php
// src/Middleware/ValidationMiddleware.php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Respect\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use App\Exceptions\ValidationException;

class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;

    public function __construct(array $validationRules)
    {
        $this->rules = $validationRules;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Build route key for lookup
        $routeKey = "$method $path";
        
        // Try exact match first
        $rule = $this->rules[$routeKey] ?? null;
        
        // If no exact match, try pattern matching (for routes with params)
        if ($rule === null) {
            $rule = $this->findMatchingRule($method, $path);
        }
        
        // No validation rule for this route - pass through
        if ($rule === null) {
            return $handler->handle($request);
        }
        
        $body = $request->getParsedBody() ?? [];
        $errors = [];
        
        // Validate body
        if (isset($rule['body'])) {
            try {
                $rule['body']->assert($body);
            } catch (NestedValidationException $e) {
                foreach ($e->getMessages() as $field => $messages) {
                    $errors[$field] = (array) $messages;
                }
            }
        }
        
        // Reject unknown fields
        if ($rule['rejectUnknown'] ?? false) {
            $allowedKeys = $this->extractAllowedKeys($rule['body']);
            $unknownKeys = array_diff(array_keys($body), $allowedKeys);
            
            foreach ($unknownKeys as $unknownKey) {
                $errors[$unknownKey] = ['Unknown field'];
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
        
        return $handler->handle($request);
    }

    private function findMatchingRule(string $method, string $path): ?array
    {
        foreach ($this->rules as $pattern => $rule) {
            // Convert {param} to regex
            $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
            $regex = str_replace('/', '\/', $regex);
            $regex = "/^$regex$/";
            
            if (preg_match($regex, "$method $path")) {
                return $rule;
            }
        }
        
        return null;
    }

    private function extractAllowedKeys(Validator $validator): array
    {
        // Extract key names from the validator
        // This is implementation-dependent on Respect\Validation internals
        $keys = [];
        
        foreach ($validator->getRules() as $rule) {
            if ($rule instanceof \Respect\Validation\Rules\Key) {
                $keys[] = $rule->getName();
            }
        }
        
        return $keys;
    }
}
```

### Container Wiring

```php
// In config/container.php
ValidationMiddleware::class => function (ContainerInterface $c): ValidationMiddleware {
    return new ValidationMiddleware($c->get('validation.rules'));
},
```

---

## 5. Environment Variables: `vlucas/phpdotenv` ^5.6

### Purpose
Loads `.env` file into `$_ENV` superglobal for configuration.

### Wiring Location: `public/index.php` (Bootstrap)

```php
<?php
// public/index.php (top of file)

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables FIRST - before anything else
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Validate required variables (fail fast)
$dotenv->required([
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'JWT_PRIVATE_KEY_PATH',
    'JWT_PUBLIC_KEY_PATH',
    'JWT_ISSUER',
    'JWT_AUDIENCE',
])->notEmpty();

// Optional with defaults handled in container.php
$dotenv->ifPresent([
    'APP_ENV',
    'APP_DEBUG',
    'DB_PORT',
    'JWT_ACCESS_TTL',
    'JWT_REFRESH_TTL',
    'JWT_LEEWAY',
    'RATE_LIMIT_GENERAL',
    'RATE_LIMIT_AUTH',
    'RATE_LIMIT_API',
    'RATE_LIMIT_BACKING',
    'LOG_LEVEL',
    'LOG_PATH',
]);

// ... rest of bootstrap
```

### `.env.example` Template

```bash
# .env.example

# ─────────────────────────────────────────────────────────────
# APPLICATION
# ─────────────────────────────────────────────────────────────
APP_NAME=CRE8.pw
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# ─────────────────────────────────────────────────────────────
# DATABASE
# ─────────────────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cre8pw
DB_USER=cre8_user
DB_PASS=secret_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_bin

# ─────────────────────────────────────────────────────────────
# JWT
# ─────────────────────────────────────────────────────────────
JWT_ALGO=RS256
JWT_PRIVATE_KEY_PATH=/app/keys/private.pem
JWT_PUBLIC_KEY_PATH=/app/keys/public.pem
JWT_ISSUER=https://cre8.pw
JWT_AUDIENCE=https://cre8.pw/console
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=2592000
JWT_LEEWAY=10

# ─────────────────────────────────────────────────────────────
# CORS
# ─────────────────────────────────────────────────────────────
CORS_ALLOWED_ORIGINS=http://localhost:8000,http://localhost:3000
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_EXPOSED_HEADERS=

# ─────────────────────────────────────────────────────────────
# CSRF
# ─────────────────────────────────────────────────────────────
CSRF_SECRET=random_32_character_secret_here

# ─────────────────────────────────────────────────────────────
# RATE LIMITING
# ─────────────────────────────────────────────────────────────
RATE_LIMIT_GENERAL=100 per minute
RATE_LIMIT_AUTH=10 per minute
RATE_LIMIT_API=60 per minute
RATE_LIMIT_BACKING=memory

# ─────────────────────────────────────────────────────────────
# HTTP CLIENT
# ─────────────────────────────────────────────────────────────
HTTP_TIMEOUT=30
HTTP_RETRY_MAX=3

# ─────────────────────────────────────────────────────────────
# LOGGING
# ─────────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_PATH=/app/logs

# ─────────────────────────────────────────────────────────────
# HASHING
# ─────────────────────────────────────────────────────────────
PASSWORD_MEMORY_COST=65536
PASSWORD_TIME_COST=4
PASSWORD_PARALLELISM=1
```

---

## 6. CORS: `neomerx/cors-psr7` ^3.0

### Purpose
Handles Cross-Origin Resource Sharing headers for browser-based API access.

### Wiring in Middleware: `src/Middleware/CorsMiddleware.php`

```php
<?php
// src/Middleware/CorsMiddleware.php

namespace App\Middleware;

use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\AnalysisResultInterface;
use Neomerx\Cors\Strategies\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class CorsMiddleware implements MiddlewareInterface
{
    private Analyzer $analyzer;
    private ResponseFactory $responseFactory;

    public function __construct(CorsSettings $settings, ResponseFactory $responseFactory)
    {
        $this->analyzer = Analyzer::instance($settings->toNeomerxSettings());
        $this->responseFactory = $responseFactory;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cors = $this->analyzer->analyze($request);
        
        switch ($cors->getRequestType()) {
            case AnalysisResultInterface::ERR_NO_HOST_HEADER:
            case AnalysisResultInterface::ERR_ORIGIN_NOT_ALLOWED:
            case AnalysisResultInterface::ERR_METHOD_NOT_SUPPORTED:
            case AnalysisResultInterface::ERR_HEADERS_NOT_SUPPORTED:
                // CORS error - return 403
                return $this->responseFactory->createResponse(403);
            
            case AnalysisResultInterface::TYPE_PRE_FLIGHT_REQUEST:
                // Handle preflight OPTIONS request
                $response = $this->responseFactory->createResponse(204);
                return $this->addCorsHeaders($response, $cors);
            
            case AnalysisResultInterface::TYPE_REQUEST_OUT_OF_CORS_SCOPE:
                // Not a CORS request - proceed normally
                return $handler->handle($request);
            
            default:
                // Regular CORS request - add headers to response
                $response = $handler->handle($request);
                return $this->addCorsHeaders($response, $cors);
        }
    }

    private function addCorsHeaders(
        ResponseInterface $response,
        AnalysisResultInterface $cors
    ): ResponseInterface {
        foreach ($cors->getResponseHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        
        return $response;
    }
}
```

### Wiring in Settings Class: `src/Security/CorsSettings.php`

```php
<?php
// src/Security/CorsSettings.php

namespace App\Security;

use Neomerx\Cors\Strategies\Settings;

class CorsSettings
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function toNeomerxSettings(): Settings
    {
        $settings = new Settings();
        
        // Set allowed origins
        if (!empty($this->config['allowedOrigins'])) {
            foreach ($this->config['allowedOrigins'] as $origin) {
                if ($origin === '*') {
                    $settings->enableAllOriginsAllowed();
                } else {
                    $settings->setAllowedOrigins([$origin => true]);
                }
            }
        }
        
        // Set allowed methods
        $settings->setAllowedMethods($this->config['allowedMethods'] ?? [
            'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'
        ]);
        
        // Set allowed headers
        $settings->setAllowedHeaders($this->config['allowedHeaders'] ?? [
            'Authorization', 'Content-Type'
        ]);
        
        // Set exposed headers
        if (!empty($this->config['exposedHeaders'])) {
            $settings->setExposedHeaders($this->config['exposedHeaders']);
        }
        
        // Credentials support
        if ($this->config['credentialsSupported'] ?? false) {
            $settings->setCredentialsSupported();
        }
        
        // Max age for preflight caching
        $settings->setPreFlightCacheMaxAge($this->config['maxAge'] ?? 86400);
        
        return $settings;
    }
}
```

---

## 7. CSRF: `slim/csrf` ^1.5

### Purpose
Provides CSRF token generation and validation for **HTML form routes only**.

### Wiring Location: Container and Route-Specific Middleware

```php
// In config/container.php (shown above)
Slim\Csrf\Guard::class => function (ContainerInterface $c): Slim\Csrf\Guard {
    // ...
};
```

### Applied Only to HTML Routes

```php
// In config/routes.php

// CSRF applied ONLY to HTML routes
$app->get('/console/register', [ConsoleController::class, 'registerPage'])
    ->add($container->get(Slim\Csrf\Guard::class));

$app->get('/console/login', [ConsoleController::class, 'loginPage'])
    ->add($container->get(Slim\Csrf\Guard::class));

$app->get('/console/dashboard', [ConsoleController::class, 'dashboard'])
    ->add($container->get(Slim\Csrf\Guard::class))
    ->add(JwtOwnerMiddleware::class);

// JSON routes NEVER have CSRF middleware attached
```

### CSRF Token Exposure Utility: `src/Utilities/Csrf.php`

```php
<?php
// src/Utilities/Csrf.php

namespace App\Utilities;

use Psr\Http\Message\ServerRequestInterface;

class Csrf
{
    /**
     * Generate hidden form fields for CSRF tokens
     */
    public static function hiddenFields(ServerRequestInterface $request): string
    {
        $nameKey = $request->getAttribute('csrf_name_key') ?? 'csrf_name';
        $valueKey = $request->getAttribute('csrf_value_key') ?? 'csrf_value';
        $name = $request->getAttribute($nameKey);
        $value = $request->getAttribute($valueKey);
        
        if (!$name || !$value) {
            return '';
        }
        
        return sprintf(
            '<input type="hidden" name="%s" value="%s">' .
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($nameKey),
            htmlspecialchars($name),
            htmlspecialchars($valueKey),
            htmlspecialchars($value)
        );
    }

    /**
     * Get CSRF headers for AJAX requests
     */
    public static function headerArray(ServerRequestInterface $request): array
    {
        return [
            'X-CSRF-Name' => $request->getAttribute('csrf_name'),
            'X-CSRF-Value' => $request->getAttribute('csrf_value'),
        ];
    }
}
```

---

## 8. HTTP Client: `guzzlehttp/guzzle` ^7.10

### Purpose
Handles outbound HTTP requests (e.g., external API calls, webhooks).

### Wiring in Container

```php
// In config/container.php
GuzzleHttp\Client::class => function (ContainerInterface $c): GuzzleHttp\Client {
    return new GuzzleHttp\Client([
        'timeout' => (float) ($_ENV['HTTP_TIMEOUT'] ?? 30),
        'connect_timeout' => 10,
        'http_errors' => false,
        'headers' => [
            'User-Agent' => 'CRE8.pw/1.0',
        ],
    ]);
},
```

### Example Service Usage: `src/Services/WebhookService.php`

```php
<?php
// src/Services/WebhookService.php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class WebhookService
{
    public function __construct(
        private Client $httpClient,
        private LoggerInterface $logger  // 'logger.api' channel
    ) {}

    public function notify(string $url, array $payload): bool
    {
        try {
            $response = $this->httpClient->post($url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
            
            $this->logger->info('Webhook sent', [
                'url' => $url,
                'status' => $response->getStatusCode(),
            ]);
            
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            
        } catch (GuzzleException $e) {
            $this->logger->error('Webhook failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}
```

---

## 9. Logging: `monolog/monolog` ^3.9

### Purpose
Structured JSON logging across multiple channels (api, auth, security, db).

### Wiring in Container (shown above in full container.php)

Multiple named loggers are created for different channels:
- `LoggerInterface::class` → Default application logger
- `'logger.api'` → API request/response logging
- `'logger.auth'` → Authentication events
- `'logger.security'` → Security events (rate limits, CSRF failures, replay attempts)
- `'logger.db'` → Database errors

### Usage in Services: `src/Services/AuthService.php`

```php
<?php
// src/Services/AuthService.php

namespace App\Services;

use Psr\Log\LoggerInterface;

class AuthService
{
    private LoggerInterface $authLogger;
    private LoggerInterface $securityLogger;

    public function __construct(
        private JwtHelper $jwtHelper,
        private TokenRepository $tokenRepository,
        private KeyRepository $keyRepository,
        private HashingService $hashingService,
        // Named parameter injection via PHP-DI
        #[Inject('logger.auth')]
        LoggerInterface $authLogger,
        #[Inject('logger.security')]
        LoggerInterface $securityLogger
    ) {
        $this->authLogger = $authLogger;
        $this->securityLogger = $securityLogger;
    }

    public function exchangeApiKey(
        string $keyPublicId,
        string $keySecret,
        string $ip,
        string $userAgent
    ): array {
        // Lookup key
        $key = $this->keyRepository->findByPublicId($keyPublicId);
        
        if (!$key) {
            $this->authLogger->warning('ApiKey exchange: unknown public_id', [
                'key_public_id' => $keyPublicId,
                'ip' => $ip,
            ]);
            throw new UnauthorizedException('Invalid credentials');
        }
        
        // Verify secret
        if (!$this->hashingService->verify($keySecret, $key['key_secret_hash'])) {
            $this->authLogger->warning('ApiKey exchange: invalid secret', [
                'key_public_id' => $keyPublicId,
                'key_id' => $key['id'],
                'ip' => $ip,
            ]);
            throw new UnauthorizedException('Invalid credentials');
        }
        
        // Check active
        if (!$key['active']) {
            $this->authLogger->warning('ApiKey exchange: inactive key', [
                'key_id' => $key['id'],
                'ip' => $ip,
            ]);
            throw new UnauthorizedException('Invalid credentials');
        }
        
        // Generate tokens
        $accessToken = $this->jwtHelper->generateKeyAccessToken(
            $key['id'],
            $keyPublicId,
            $key['roles'],
            $key['permissions']
        );
        
        $refreshToken = $this->generateRefreshToken();
        
        // Store refresh token
        $this->tokenRepository->createRefreshToken(
            subjectType: 'key',
            subjectId: $key['id'],
            tokenHash: $this->hashingService->hash($refreshToken),
            expiresAt: time() + $this->jwtHelper->getRefreshTtl(),
            ip: $ip,
            userAgent: $userAgent
        );
        
        $this->authLogger->info('ApiKey exchange successful', [
            'key_id' => $key['id'],
            'key_public_id' => $keyPublicId,
            'ip' => $ip,
        ]);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->jwtHelper->getAccessTtl(),
        ];
    }

    public function refreshToken(
        string $refreshToken,
        string $ip,
        string $userAgent
    ): array {
        $tokenHash = $this->hashingService->hash($refreshToken);
        $storedToken = $this->tokenRepository->findByHash($tokenHash);
        
        if (!$storedToken) {
            $this->authLogger->warning('Refresh: unknown token', ['ip' => $ip]);
            throw new UnauthorizedException('Invalid refresh token');
        }
        
        // Check if already rotated (replay attack)
        if ($storedToken['rotated_at'] !== null) {
            $this->securityLogger->warning('Refresh token replay detected', [
                'subject_type' => $storedToken['subject_type'],
                'subject_id' => $storedToken['subject_id'],
                'original_ip' => $storedToken['ip'],
                'replay_ip' => $ip,
                'original_ua' => $storedToken['user_agent'],
                'replay_ua' => $userAgent,
            ]);
            throw new UnauthorizedException('Invalid refresh token');
        }
        
        // Check expiry
        if (strtotime($storedToken['expires_at']) < time()) {
            $this->authLogger->warning('Refresh: expired token', [
                'subject_id' => $storedToken['subject_id'],
                'ip' => $ip,
            ]);
            throw new UnauthorizedException('Refresh token expired');
        }
        
        // Mark as rotated
        $this->tokenRepository->markRotated($storedToken['id']);
        
        // Load subject
        if ($storedToken['subject_type'] === 'key') {
            $key = $this->keyRepository->findById($storedToken['subject_id']);
            $accessToken = $this->jwtHelper->generateKeyAccessToken(
                $key['id'],
                $key['key_public_id'],
                $key['roles'],
                $key['permissions']
            );
        } else {
            $owner = $this->ownerRepository->findById($storedToken['subject_id']);
            $accessToken = $this->jwtHelper->generateOwnerAccessToken(
                $owner['id'],
                ['owner'],
                $this->getOwnerPermissions()
            );
        }
        
        // Generate new refresh token
        $newRefreshToken = $this->generateRefreshToken();
        $newTokenId = $this->tokenRepository->createRefreshToken(
            subjectType: $storedToken['subject_type'],
            subjectId: $storedToken['subject_id'],
            tokenHash: $this->hashingService->hash($newRefreshToken),
            expiresAt: time() + $this->jwtHelper->getRefreshTtl(),
            ip: $ip,
            userAgent: $userAgent,
            replacedById: $storedToken['id']
        );
        
        $this->authLogger->info('Token refresh successful', [
            'subject_type' => $storedToken['subject_type'],
            'subject_id' => $storedToken['subject_id'],
            'ip' => $ip,
        ]);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => $this->jwtHelper->getAccessTtl(),
        ];
    }

    private function generateRefreshToken(): string
    {
        return 'rt_' . bin2hex(random_bytes(32));
    }
}
```

---

## 10. Rate Limiting: `symfony/rate-limiter` ^7.3 + `symfony/cache` ^7.3

### Purpose
Throttles requests to prevent abuse, with different limits per bucket and keying strategies.

### Wiring in Container (shown above)

Three rate limiter factories are created:
- `'rate_limiter.general'` → 100/minute (default)
- `'rate_limiter.auth'` → 10/minute (authentication endpoints)
- `'rate_limiter.api'` → 60/minute (Gateway endpoints)

### Wiring in Middleware: `src/Middleware/RateLimitMiddleware.php`

```php
<?php
// src/Middleware/RateLimitMiddleware.php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use App\Exceptions\RateLimitException;
use Psr\Log\LoggerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterFactory $generalLimiter,
        private RateLimiterFactory $authLimiter,
        private RateLimiterFactory $apiLimiter,
        private LoggerInterface $securityLogger
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        
        // Determine bucket and key
        [$limiter, $key] = $this->selectLimiterAndKey($request, $path);
        
        // Consume a token
        $limit = $limiter->create($key)->consume();
        
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            
            $this->securityLogger->warning('Rate limit exceeded', [
                'bucket' => $this->getBucketName($path),
                'key' => $this->sanitizeKey($key),
                'retry_after' => $retryAfter,
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'path' => $path,
            ]);
            
            throw new RateLimitException('Too many requests', $retryAfter);
        }
        
        return $handler->handle($request);
    }

    private function selectLimiterAndKey(
        ServerRequestInterface $request,
        string $path
    ): array {
        // Auth endpoints: stricter limits, keyed by IP
        if (str_starts_with($path, '/api/auth') || 
            str_starts_with($path, '/console/login') ||
            str_starts_with($path, '/console/owners')) {
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            return [$this->authLimiter, "ip:$ip"];
        }
        
        // Gateway API: keyed by key_id (if authenticated)
        if (str_starts_with($path, '/api/')) {
            $keyId = $request->getAttribute('key_id');
            if ($keyId) {
                return [$this->apiLimiter, "key:$keyId"];
            }
            // Fallback to IP for unauthenticated
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            return [$this->apiLimiter, "ip:$ip"];
        }
        
        // Console JSON: keyed by owner_id (if authenticated)
        if (str_starts_with($path, '/console/')) {
            $ownerId = $request->getAttribute('owner_id');
            if ($ownerId) {
                return [$this->generalLimiter, "owner:$ownerId"];
            }
        }
        
        // Default: general limiter, IP-keyed
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        return [$this->generalLimiter, "ip:$ip"];
    }

    private function getBucketName(string $path): string
    {
        if (str_starts_with($path, '/api/auth') || 
            str_starts_with($path, '/console/login')) {
            return 'auth';
        }
        if (str_starts_with($path, '/api/')) {
            return 'api';
        }
        return 'general';
    }

    private function sanitizeKey(string $key): string
    {
        // Don't log full IPs in production - just the prefix
        if (str_starts_with($key, 'ip:')) {
            return 'ip:***';
        }
        return $key;
    }
}
```

### Container Wiring for Middleware

```php
// In config/container.php
RateLimitMiddleware::class => function (ContainerInterface $c): RateLimitMiddleware {
    return new RateLimitMiddleware(
        $c->get('rate_limiter.general'),
        $c->get('rate_limiter.auth'),
        $c->get('rate_limiter.api'),
        $c->get('logger.security')
    );
},
```

---

## 11. Database: `ext-pdo`

### Purpose
Core database access using prepared statements exclusively.

### Wiring in Container (shown above)

```php
PDO::class => function (ContainerInterface $c): PDO {
    // ... connection setup with utf8mb4_bin collation
},
```

### Example Repository: `src/Repositories/KeyRepository.php`

```php
<?php
// src/Repositories/KeyRepository.php

namespace App\Repositories;

use PDO;
use App\Utilities\Ids;

class KeyRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(string $keyIdHex32): ?array
    {
        $sql = "SELECT 
                    k.id,
                    k.type,
                    k.permissions_json,
                    k.active,
                    k.issued_by_key_id,
                    k.parent_key_id,
                    k.initial_author_key_id,
                    k.use_count_limit,
                    k.use_count_current,
                    k.device_limit,
                    k.created_at,
                    kp.key_public_id
                FROM keys k
                LEFT JOIN key_public_ids kp ON k.id = kp.key_id
                WHERE k.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateKey($row);
    }

    public function findByPublicId(string $keyPublicId): ?array
    {
        $sql = "SELECT 
                    k.id,
                    k.type,
                    k.key_secret_hash,
                    k.permissions_json,
                    k.active,
                    k.issued_by_key_id,
                    k.parent_key_id,
                    k.initial_author_key_id,
                    k.use_count_limit,
                    k.use_count_current,
                    k.device_limit,
                    k.created_at,
                    kp.key_public_id
                FROM keys k
                INNER JOIN key_public_ids kp ON k.id = kp.key_id
                WHERE kp.key_public_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyPublicId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrateKey($row);
    }

    public function create(array $data): string
    {
        $keyId = Ids::generate();  // Generate BINARY(16)
        $keyIdHex32 = Ids::binaryToHex32($keyId);
        
        $sql = "INSERT INTO keys (
                    id, type, key_secret_hash, permissions_json, active,
                    issued_by_key_id, parent_key_id, initial_author_key_id,
                    use_count_limit, device_limit, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $keyId,
            $data['type'],
            $data['key_secret_hash'],
            json_encode($data['permissions']),
            $data['active'] ?? 1,
            $data['issued_by_key_id'] ? Ids::hex32ToBinary($data['issued_by_key_id']) : null,
            $data['parent_key_id'] ? Ids::hex32ToBinary($data['parent_key_id']) : null,
            $data['initial_author_key_id'] ? Ids::hex32ToBinary($data['initial_author_key_id']) : $keyId,
            $data['use_count_limit'] ?? null,
            $data['device_limit'] ?? null,
        ]);
        
        // Create public ID
        $keyPublicId = 'apub_' . bin2hex(random_bytes(8));
        $this->createPublicId($keyIdHex32, $keyPublicId);
        
        return $keyIdHex32;
    }

    public function createPublicId(string $keyIdHex32, string $keyPublicId): void
    {
        $sql = "INSERT INTO key_public_ids (id, key_id, key_public_id, created_at)
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::generate(),
            Ids::hex32ToBinary($keyIdHex32),
            $keyPublicId,
        ]);
    }

    public function incrementUseCount(string $keyIdHex32): void
    {
        $sql = "UPDATE keys SET use_count_current = use_count_current + 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
    }

    public function deactivate(string $keyIdHex32, bool $cascade = false): void
    {
        if ($cascade) {
            // Recursive CTE to deactivate entire lineage
            $sql = "WITH RECURSIVE descendants AS (
                        SELECT id FROM keys WHERE id = ?
                        UNION ALL
                        SELECT k.id FROM keys k
                        INNER JOIN descendants d ON k.parent_key_id = d.id
                    )
                    UPDATE keys SET active = 0 WHERE id IN (SELECT id FROM descendants)";
        } else {
            $sql = "UPDATE keys SET active = 0 WHERE id = ?";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($keyIdHex32)]);
    }

    private function hydrateKey(array $row): array
    {
        return [
            'id' => Ids::binaryToHex32($row['id']),
            'type' => $row['type'],
            'key_secret_hash' => $row['key_secret_hash'] ?? null,
            'key_public_id' => $row['key_public_id'],
            'permissions' => json_decode($row['permissions_json'], true),
            'roles' => $this->getRolesForType($row['type']),
            'active' => (bool) $row['active'],
            'issued_by_key_id' => $row['issued_by_key_id'] 
                ? Ids::binaryToHex32($row['issued_by_key_id']) 
                : null,
            'parent_key_id' => $row['parent_key_id'] 
                ? Ids::binaryToHex32($row['parent_key_id']) 
                : null,
            'initial_author_key_id' => Ids::binaryToHex32($row['initial_author_key_id']),
            'use_count_limit' => $row['use_count_limit'],
            'use_count_current' => (int) $row['use_count_current'],
            'device_limit' => $row['device_limit'],
            'created_at' => $row['created_at'],
        ];
    }

    private function getRolesForType(string $type): array
    {
        return match ($type) {
            'primary', 'secondary' => ['author'],
            'use' => ['use'],
            default => [],
        };
    }
}
```

---

## 12. Cryptography: `ext-sodium`

### Purpose
Provides Argon2id hashing for passwords and API key secrets.

### Wiring in Service: `src/Security/HashingService.php`

```php
<?php
// src/Security/HashingService.php

namespace App\Security;

class HashingService
{
    private int $memoryCost;
    private int $timeCost;
    private int $parallelism;

    public function __construct(
        int $memoryCost = 65536,  // 64 MB
        int $timeCost = 4,
        int $parallelism = 1
    ) {
        $this->memoryCost = $memoryCost;
        $this->timeCost = $timeCost;
        $this->parallelism = $parallelism;
    }

    /**
     * Hash a password or secret using Argon2id
     */
    public function hash(string $value): string
    {
        return password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->parallelism,
        ]);
    }

    /**
     * Verify a value against its hash
     */
    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    /**
     * Check if a hash needs rehashing (e.g., after cost parameter changes)
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->parallelism,
        ]);
    }

    /**
     * Generate a cryptographically secure random string
     */
    public function generateSecret(int $length = 32): string
    {
        return 'sec_' . bin2hex(random_bytes($length));
    }
}
```

---

## 13. ID Utilities: `src/Utilities/Ids.php`

### Purpose
Centralized conversion between BINARY(16) and hex32 formats.

```php
<?php
// src/Utilities/Ids.php

namespace App\Utilities;

class Ids
{
    /**
     * Generate a new BINARY(16) ID
     */
    public static function generate(): string
    {
        return random_bytes(16);
    }

    /**
     * Convert BINARY(16) to hex32 for external use
     */
    public static function binaryToHex32(string $binary): string
    {
        return bin2hex($binary);
    }

    /**
     * Convert hex32 to BINARY(16) for database storage
     */
    public static function hex32ToBinary(string $hex32): string
    {
        if (strlen($hex32) !== 32 || !ctype_xdigit($hex32)) {
            throw new \InvalidArgumentException("Invalid hex32 format: $hex32");
        }
        
        return hex2bin($hex32);
    }

    /**
     * Validate hex32 format
     */
    public static function isValidHex32(string $value): bool
    {
        return strlen($value) === 32 && ctype_xdigit($value);
    }

    /**
     * Validate apub_ format
     */
    public static function isValidApub(string $value): bool
    {
        return str_starts_with($value, 'apub_') && strlen($value) > 5;
    }
}
```

---

## Dependency Flow Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ENTRY POINT                                        │
│                         public/index.php                                     │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        vlucas/phpdotenv                                      │
│                     Load .env → $_ENV                                        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          php-di/php-di                                       │
│                     Build DI Container                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ PDO (ext-pdo)                                                       │    │
│  │ JwtHelper (firebase/php-jwt)                                        │    │
│  │ HashingService (ext-sodium)                                         │    │
│  │ Loggers (monolog/monolog)                                           │    │
│  │ RateLimiters (symfony/rate-limiter + symfony/cache)                 │    │
│  │ CorsSettings (neomerx/cors-psr7)                                    │    │
│  │ CsrfGuard (slim/csrf)                                               │    │
│  │ HttpClient (guzzlehttp/guzzle)                                      │    │
│  │ ValidationRules (respect/validation)                                │    │
│  │ Repositories → Services → Controllers → Middleware                 │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           slim/slim                                          │
│                    Create Slim App with Container                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       MIDDLEWARE PIPELINE                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ HttpsMiddleware                                                     │    │
│  │ CorsMiddleware         ← neomerx/cors-psr7                          │    │
│  │ RateLimitMiddleware    ← symfony/rate-limiter                       │    │
│  │ JwtMiddleware          ← firebase/php-jwt                           │    │
│  │ ValidationMiddleware   ← respect/validation                         │    │
│  │ CsrfGuard (HTML only)  ← slim/csrf                                  │    │
│  │ ErrorHandlingMiddleware                                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          slim/psr7                                           │
│                   Request → Controller → Response                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SERVICE LAYER                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ AuthService (JwtHelper, HashingService, TokenRepository)            │    │
│  │ KeyService (KeyRepository, AuditRepository, HashingService)         │    │
│  │ PostService (PostRepository, KeyRepository)                         │    │
│  │ ... etc                                                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       REPOSITORY LAYER                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ PDO Prepared Statements                                             │    │
│  │ hex32 ↔ BINARY(16) conversion (Utilities/Ids)                       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MariaDB 11.4.x                                     │
│                         utf8mb4_bin collation                                │
└─────────────────────────────────────────────────────────────────────────────┘
```

This comprehensive wiring guide shows how each Composer dependency flows through the application architecture, from bootstrap to database, with concrete code examples demonstrating actual integration patterns.