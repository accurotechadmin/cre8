<?php
/**
 * CRE8.pw Dependency Injection Container Configuration
 * 
 * This file configures PHP-DI container bindings for all application dependencies.
 * See docs/APPENDIX/K-wiring_dependencies.md for complete wiring guide.
 */

declare(strict_types=1);

use DI\Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    // PSR-7 Factories
    Psr\Http\Message\ResponseFactoryInterface::class => function (Container $c) {
        return new Slim\Psr7\Factory\ResponseFactory();
    },
    
    // Database connection (T2.1)
    PDO::class => function (Container $c): PDO {
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
    
    // Rate limiter storage (T4.4)
    // Supports 'memory' (development) and 'database' (production) backing
    Symfony\Component\RateLimiter\Storage\StorageInterface::class => function (Container $c) {
        $backing = $_ENV['RATE_LIMIT_BACKING'] ?? 'memory';
        
        // Database-backed storage for production
        if ($backing === 'database') {
            try {
                $pdo = $c->get(PDO::class);
                // Create PDO adapter for Symfony Cache, then wrap in CacheStorage
                // Symfony RateLimiter uses CacheStorage with a PDO adapter for database backing
                $adapter = new \Symfony\Component\Cache\Adapter\PdoAdapter($pdo);
                return new \Symfony\Component\RateLimiter\Storage\CacheStorage($adapter);
            } catch (\Exception $e) {
                // Check environment - production should fail fast
                $env = $_ENV['APP_ENV'] ?? 'production';
                
                if ($env === 'production') {
                    // Production: fail fast - Database is required for rate limiting
                    error_log("CRITICAL: Database connection failed in production: " . $e->getMessage());
                    throw new \RuntimeException(
                        "Database connection failed. Rate limiting requires database in production environment.",
                        0,
                        $e
                    );
                } else {
                    // Development: graceful fallback
                    error_log("Database connection failed: " . $e->getMessage() . ". Falling back to in-memory storage.");
                    return new Symfony\Component\RateLimiter\Storage\InMemoryStorage();
                }
            }
        }
        
        // Development fallback: in-memory storage
        return new Symfony\Component\RateLimiter\Storage\InMemoryStorage();
    },
    
    // Rate limiter factories (T4.4)
    'rate_limiter.general' => function (Container $c) {
        $config = parseRateLimitConfig($_ENV['RATE_LIMIT_GENERAL'] ?? '100 per minute');
        $storage = $c->get(Symfony\Component\RateLimiter\Storage\StorageInterface::class);
        // RateLimiterFactory constructor: (array $config, StorageInterface $storage)
        return new Symfony\Component\RateLimiter\RateLimiterFactory($config, $storage);
    },
    
    'rate_limiter.auth' => function (Container $c) {
        $config = parseRateLimitConfig($_ENV['RATE_LIMIT_AUTH'] ?? '10 per minute');
        $storage = $c->get(Symfony\Component\RateLimiter\Storage\StorageInterface::class);
        return new Symfony\Component\RateLimiter\RateLimiterFactory($config, $storage);
    },
    
    'rate_limiter.api' => function (Container $c) {
        $config = parseRateLimitConfig($_ENV['RATE_LIMIT_API'] ?? '60 per minute');
        $storage = $c->get(Symfony\Component\RateLimiter\Storage\StorageInterface::class);
        return new Symfony\Component\RateLimiter\RateLimiterFactory($config, $storage);
    },
    
    'rate_limiter.console' => function (Container $c) {
        $config = parseRateLimitConfig($_ENV['RATE_LIMIT_CONSOLE'] ?? '100 per minute');
        $storage = $c->get(Symfony\Component\RateLimiter\Storage\StorageInterface::class);
        return new Symfony\Component\RateLimiter\RateLimiterFactory($config, $storage);
    },
    
    // Middleware (T4.1, T17.1)
    App\Middleware\ErrorHandlingMiddleware::class => DI\autowire()
        ->constructorParameter('logger', DI\get('logger.api')),
    App\Middleware\ValidationMiddleware::class => DI\autowire(App\Middleware\ValidationMiddleware::class)
        ->constructorParameter('validationRules', function () {
            return require __DIR__ . '/validation.php';
        }),
    App\Middleware\RateLimitMiddleware::class => DI\autowire()
        ->constructorParameter('generalLimiter', DI\get('rate_limiter.general'))
        ->constructorParameter('authLimiter', DI\get('rate_limiter.auth'))
        ->constructorParameter('apiLimiter', DI\get('rate_limiter.api'))
        ->constructorParameter('consoleLimiter', DI\get('rate_limiter.console'))
        ->constructorParameter('logger', DI\get('logger.security')),
    App\Middleware\JwtOwnerMiddleware::class => DI\autowire()
        ->constructorParameter('logger', DI\get('logger.auth')),
    App\Middleware\JwtKeyMiddleware::class => DI\autowire()
        ->constructorParameter('logger', DI\get('logger.auth')),
    App\Middleware\UseKeyLimitMiddleware::class => DI\autowire(),
    App\Middleware\HttpsMiddleware::class => DI\autowire(),
    App\Middleware\CorsMiddleware::class => DI\autowire(),
    App\Middleware\CspMiddleware::class => DI\autowire(),
    App\Middleware\CsrfExposeMiddleware::class => DI\autowire(),
    App\Middleware\RouteParameterValidatorMiddleware::class => DI\autowire(),
    
    // RequestLoggingMiddleware container definition
    App\Middleware\RequestLoggingMiddleware::class => DI\autowire()
        ->constructorParameter('apiLogger', DI\get('logger.api')),
    
    // Services (T5.2, T6.2, T8.1, T9.1, T10.1, T10.2, T13.1, T17.2)
    App\Services\AuthService::class => DI\autowire(),
    App\Services\KeyService::class => DI\autowire()
        ->constructorParameter('logger', DI\get('logger.db')),
    App\Services\PostService::class => DI\autowire(),
    App\Services\CommentService::class => DI\autowire(),
    App\Services\GroupService::class => DI\autowire(),
    App\Services\KeychainService::class => DI\autowire(),
    App\Services\FeedService::class => DI\autowire(),
    App\Services\AuditService::class => DI\autowire(),
    
    // Controllers
    App\Controllers\HealthController::class => DI\autowire()
        ->constructorParameter('container', DI\get(Psr\Container\ContainerInterface::class)),
    App\Controllers\OwnerController::class => DI\autowire(),
    App\Controllers\JwksController::class => DI\autowire(),
    App\Controllers\Gateway\PostController::class => DI\autowire(),
    App\Controllers\Gateway\CommentController::class => DI\autowire(),
    App\Controllers\Gateway\GroupController::class => DI\autowire(),
    App\Controllers\Gateway\KeychainController::class => DI\autowire(),
    App\Controllers\Gateway\FeedController::class => DI\autowire(),
    App\Controllers\Gateway\RouteCatalogController::class => DI\autowire(),
    App\Controllers\Gateway\GatewayController::class => DI\autowire(),
    App\Controllers\Console\ConsoleController::class => DI\autowire(),
    App\Controllers\Console\PostController::class => DI\autowire(),
    App\Controllers\Console\GroupController::class => DI\autowire(),
    App\Controllers\Console\KeychainController::class => DI\autowire(),
    App\Controllers\Console\RouteCatalogController::class => DI\autowire(),
    App\Controllers\Console\KeyController::class => DI\autowire(),
    App\Controllers\Gateway\KeyController::class => DI\autowire(),
    
    // Repositories
    App\Repositories\OwnerRepository::class => DI\autowire(),
    App\Repositories\KeyRepository::class => DI\autowire(),
    App\Repositories\KeyPublicIdRepository::class => DI\autowire(),
    App\Repositories\KeyDeviceRepository::class => DI\autowire(),
    App\Repositories\PostRepository::class => DI\autowire(),
    App\Repositories\CommentRepository::class => DI\autowire(),
    App\Repositories\PostAccessRepository::class => DI\autowire(),
    App\Repositories\GroupRepository::class => DI\autowire(),
    App\Repositories\GroupMemberRepository::class => DI\autowire(),
    App\Repositories\KeychainRepository::class => DI\autowire(),
    App\Repositories\KeychainMemberRepository::class => DI\autowire(),
    App\Repositories\RefreshTokenRepository::class => DI\autowire(),
    App\Repositories\AuditEventRepository::class => DI\autowire(),
    
    // Logger (T17.1)
    LoggerInterface::class => function (Container $c): LoggerInterface {
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
        $level = $_ENV['LOG_LEVEL'] ?? 'INFO';
        return App\Services\LoggingService::createLogger('api', $logPath, $level);
    },
    
    // Channel-specific loggers (T17.1)
    'logger.api' => function (Container $c): LoggerInterface {
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
        $level = $_ENV['LOG_LEVEL'] ?? 'INFO';
        return App\Services\LoggingService::createLogger('api', $logPath, $level);
    },
    'logger.auth' => function (Container $c): LoggerInterface {
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
        $level = $_ENV['LOG_LEVEL'] ?? 'INFO';
        return App\Services\LoggingService::createLogger('auth', $logPath, $level);
    },
    'logger.security' => function (Container $c): LoggerInterface {
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
        $level = $_ENV['LOG_LEVEL'] ?? 'INFO';
        return App\Services\LoggingService::createLogger('security', $logPath, $level);
    },
    'logger.db' => function (Container $c): LoggerInterface {
        $logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs';
        $level = $_ENV['LOG_LEVEL'] ?? 'INFO';
        return App\Services\LoggingService::createLogger('db', $logPath, $level);
    },
    
    // JWT utilities (T5.1)
    App\Security\JwtService::class => function (Container $c) {
        $baseUrl = $_ENV['APP_URL'] ?? '';
        return new App\Security\JwtService(
            privateKeyPath: $_ENV['JWT_PRIVATE_KEY_PATH'] ?? '',
            publicKeyPath: $_ENV['JWT_PUBLIC_KEY_PATH'] ?? '',
            issuer: $_ENV['JWT_ISSUER'] ?? '',
            audience: $_ENV['JWT_AUDIENCE'] ?? $baseUrl, // Base audience, will be overridden per middleware
            accessTtl: (int)($_ENV['JWT_ACCESS_TTL'] ?? 900),
            refreshTtl: (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000),
            leeway: (int)($_ENV['JWT_LEEWAY'] ?? 10),
            keyId: $_ENV['JWT_KEY_ID'] ?? ''
        );
    },
    
    // Hashing service (T5.2)
    App\Security\HashingService::class => function (Container $c) {
        return new App\Security\HashingService(
            memoryCost: (int)($_ENV['PASSWORD_MEMORY_COST'] ?? 65536), // 64 MB
            timeCost: (int)($_ENV['PASSWORD_TIME_COST'] ?? 4),
            parallelism: (int)($_ENV['PASSWORD_PARALLELISM'] ?? 1)
        );
    },
    
    // JWT middleware (T5.1)
    App\Middleware\JwtOwnerMiddleware::class => DI\autowire()
        ->constructorParameter('jwtService', DI\get(App\Security\JwtService::class))
        ->constructorParameter('expectedAudience', function () {
            // Console audience: use JWT_AUDIENCE if set, otherwise derive from APP_URL
            if (!empty($_ENV['JWT_AUDIENCE'])) {
                return $_ENV['JWT_AUDIENCE'];
            }
            $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            return $baseUrl . '/console';
        }),
    App\Middleware\JwtKeyMiddleware::class => DI\autowire()
        ->constructorParameter('jwtService', DI\get(App\Security\JwtService::class))
        ->constructorParameter('expectedAudience', function () {
            // Gateway audience: use JWT_AUDIENCE_API if set, otherwise derive from APP_URL
            if (!empty($_ENV['JWT_AUDIENCE_API'])) {
                return $_ENV['JWT_AUDIENCE_API'];
            }
            $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            return $baseUrl . '/api';
        }),
];

/**
 * Parse rate limit configuration string
 * 
 * @param string $config Rate limit config (e.g., "100 per minute")
 * @return array Rate limiter configuration
 */
function parseRateLimitConfig(string $config): array
{
    if (preg_match('/^(\d+)\s+per\s+(minute|hour|second)$/i', $config, $matches)) {
        $limit = (int)$matches[1];
        $interval = strtolower($matches[2]);
        
        $intervalSeconds = match ($interval) {
            'second' => 1,
            'minute' => 60,
            'hour' => 3600,
            default => 60,
        };
        
        return [
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => "{$intervalSeconds} seconds",
        ];
    }
    
    // Default: 100 per minute
    return [
        'policy' => 'fixed_window',
        'limit' => 100,
        'interval' => '60 seconds',
    ];
}
