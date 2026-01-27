# Implementation Guide

**Document Set:** CRE8.pw Documentation v1.0.0
**Last Updated:** 2026-01-21
**Status:** Canonical (SSoT)

**Scope:** Developer manual for implementing CRE8.pw: project structure, technology stack, layering rules, DI wiring, validation configuration, repository/service/controller patterns, and implementation checklists.

**SSoT Ownership:**
- Project directory structure
- Technology stack and package versions
- Layering rules (Controller/Service/Repository/Middleware responsibilities)
- DI container wiring
- Validation configuration
- Implementation patterns and best practices
- Local development setup

---

## 1. Project Structure

```
cre8pw/
├── public/
│   └── index.php              # Entry point
├── src/
│   ├── Controllers/           # HTTP adapters
│   │   ├── Console/          # Console HTML + JSON
│   │   └── Gateway/          # Gateway JSON
│   ├── Services/              # Business logic
│   ├── Repositories/          # Data access (PDO)
│   ├── Middleware/            # PSR-15 middleware
│   ├── Utilities/             # Helpers (Ids, Csrf, etc.)
│   └── Security/              # Hashing utilities
├── config/
│   ├── container.php          # DI wiring
│   ├── routes.php             # Route definitions
│   └── validation.php         # Validation rules
├── migrations/
│   ├── 001_create_owners.php
│   ├── 002_create_keys.php
│   └── ...
├── logs/                      # Monolog output
├── keys/                      # JWT private/public keys
├── vendor/                    # Composer dependencies
├── .env                       # Environment variables
├── .env.example               # Environment template
└── composer.json
```

---

## 2. Technology Stack

### 2.1 Core

```json
{
  "php": "^8.3",
  "slim/slim": "^4.15",
  "php-di/php-di": "^7.1",
  "slim/psr7": "^1.7"
}
```

### 2.2 Database

```json
{
  "ext-pdo": "*"
}
```

**Database:** MariaDB 11.4.x, utf8mb4_bin

### 2.3 Security

```json
{
  "firebase/php-jwt": "^6.11",
  "ext-sodium": "*"
}
```

### 2.4 Validation & Utilities

```json
{
  "respect/validation": "^2.4",
  "vlucas/phpdotenv": "^5.6"
}
```

### 2.5 HTTP & Middleware

```json
{
  "guzzlehttp/guzzle": "^7.10",
  "neomerx/cors-psr7": "^3.0",
  "slim/csrf": "^1.5"
}
```

### 2.6 Logging & Rate Limiting

```json
{
  "monolog/monolog": "^3.9",
  "symfony/rate-limiter": "^7.3",
  "symfony/cache": "^7.3"
}
```

---

## 3. Layering Rules

### 3.1 Controllers (HTTP Adapters)

**Responsibilities:**
- Extract params (route, query, body, headers)
- Call exactly ONE service method
- Shape response using standardized envelopes
- Return PSR-7 response

**Forbidden:**
- Business logic
- Direct database access
- Multi-service orchestration
- Permission enforcement (services handle this)

**Example:**
```php
class PostController {
    public function __construct(
        private PostService $postService,
        private ResponseFactory $responseFactory
    ) {}

    public function create(Request $req, Response $res): Response {
        $keyId = $req->getAttribute('key_id'); // from JwtKeyMiddleware
        $body = $req->getParsedBody();

        $post = $this->postService->createPost($keyId, $body['content'], $body['title'] ?? null);

        return $this->responseFactory->json($res, ['data' => $post], 201);
    }
}
```

### 3.2 Services (Business Logic)

**Responsibilities:**
- Enforce global permissions (check JWT permissions)
- Enforce post bitmasks (for post-scoped actions)
- Enforce invariants (key type, lineage, immutability)
- Orchestrate multiple repositories (transactions)
- Emit audit events
- Throw deterministic exceptions

**Forbidden:**
- HTTP concerns (request/response objects)
- Direct SQL queries

**Example:**
```php
class PostService {
    public function createPost(string $keyIdHex32, string $content, ?string $title): array {
        // 1. Load key and verify type
        $key = $this->keyRepository->findById($keyIdHex32);
        if (!$key) throw new NotFoundException('Key not found');
        if ($key['type'] === 'use') throw new ForbiddenException('Use Keys cannot create posts');

        // 2. Verify permission
        if (!in_array('posts:create', $key['permissions'])) {
            throw new ForbiddenException('Missing permission: posts:create');
        }

        // 3. Create post
        $postIdHex32 = $this->keyRepository->generateId();
        $this->postRepository->create([
            'id' => $postIdHex32,
            'author_key_id' => $keyIdHex32,
            'initial_author_key_id' => $key['initial_author_key_id'],
            'content' => $content,
            'title' => $title,
        ]);

        // 4. Emit audit event
        $this->auditRepository->log('posts:create', 'key', $keyIdHex32, 'post', $postIdHex32);

        // 5. Return
        return $this->postRepository->findById($postIdHex32);
    }
}
```

### 3.3 Repositories (Data Access)

**Responsibilities:**
- PDO prepared statements exclusively
- Convert hex32 ↔ BINARY(16) at boundary
- Return arrays or DTOs
- Handle database-level errors

**Forbidden:**
- Business logic
- Permission checks
- HTTP concerns
- Logging secrets

**Example:**
```php
class PostRepository {
    public function create(array $data): void {
        $sql = "INSERT INTO posts (id, author_key_id, initial_author_key_id, content, title, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            Ids::hex32ToBinary($data['id']),
            Ids::hex32ToBinary($data['author_key_id']),
            Ids::hex32ToBinary($data['initial_author_key_id']),
            $data['content'],
            $data['title'],
        ]);
    }

    public function findById(string $postIdHex32): ?array {
        $sql = "SELECT * FROM posts WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([Ids::hex32ToBinary($postIdHex32)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'post_id' => Ids::binaryToHex32($row['id']),
            'author_key_id' => Ids::binaryToHex32($row['author_key_id']),
            'content' => $row['content'],
            'title' => $row['title'],
            'created_at' => $row['created_at'],
        ];
    }
}
```

### 3.4 Middleware (Cross-Cutting Concerns)

**Responsibilities:**
- HTTPS enforcement, CORS, rate limiting
- JWT verification, token typing
- Validation (request schemas)
- Error normalization

**Forbidden:**
- Business logic
- Database access (except rate limiter storage)

---

## 4. Dependency Injection (config/container.php)

### 4.1 Container Setup

```php
use DI\Container;
use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // PDO
    PDO::class => function() {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'], $_ENV['DB_NAME']);
        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_bin");
        return $pdo;
    },

    // Monolog
    LoggerInterface::class => function() {
        $logger = new Logger('cre8pw');
        $logger->pushHandler(new StreamHandler($_ENV['LOG_PATH'] . '/app.log'));
        return $logger;
    },

    // JWT Helper (Singleton)
    JwtHelper::class => DI\create()->singleton(),

    // Repositories
    KeyRepository::class => DI\autowire(),
    PostRepository::class => DI\autowire(),
    // ...

    // Services
    KeyService::class => DI\autowire(),
    PostService::class => DI\autowire(),
    // ...

    // Controllers
    PostController::class => DI\autowire(),
    // ...
]);

return $builder->build();
```

### 4.2 Singletons

**Use singletons for:**
- JwtHelper (stateless, expensive setup)
- Loggers
- Rate limiter factories
- Guzzle client factory

**Autowire for:**
- Controllers, Services, Repositories
- Middleware (if they have dependencies)

---

## 5. Validation Configuration (config/validation.php)

```php
use Respect\Validation\Validator as v;

return [
    "POST /api/posts" => [
        'body' => v::key('content', v::stringType()->notEmpty()->length(1, 10000))
                    ->key('title', v::optional(v::stringType()->length(1, 255))),
        'rejectUnknown' => true,
    ],

    "POST /console/keys/primary" => [
        'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
                    ->key('label', v::optional(v::stringType()->length(1, 100))),
        'rejectUnknown' => true,
    ],

    "POST /api/keys/{authorKeyId}/use" => [
        'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
                    ->key('label', v::optional(v::stringType()))
                    ->key('use_count', v::optional(v::intType()->positive()))
                    ->key('device_limit', v::optional(v::intType()->positive())),
        'rejectUnknown' => true,
    ],
];
```

**Key:** `"METHOD /pattern"`
**Validation:** Applied by ValidationMiddleware

---

## 6. Route Configuration (config/routes.php)

```php
use Slim\Routing\RouteCollectorProxy;

// Public API
$app->group('/api/auth', function (RouteCollectorProxy $group) {
    $group->post('/exchange', [ApiKeyController::class, 'exchange']);
    $group->post('/refresh', [AuthController::class, 'refresh']);
});

// Gateway JSON (Key-protected)
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->post('/posts', [PostController::class, 'create']);
    $group->get('/posts/{postId}', [PostController::class, 'show']);
    // ...
})->add(JwtKeyMiddleware::class)
  ->add(ValidationMiddleware::class);

// Console JSON (Owner-protected)
$app->group('/console', function (RouteCollectorProxy $group) {
    $group->post('/keys/primary', [KeyController::class, 'mintPrimary']);
    $group->get('/keys', [KeyController::class, 'list']);
    // ...
})->add(JwtOwnerMiddleware::class)
  ->add(ValidationMiddleware::class);

// Console HTML (Browser)
$app->get('/console/dashboard', [ConsoleController::class, 'dashboard'])
    ->add(CsrfGuard::class);
```

---

## 7. Local Development Setup

### 7.1 Prerequisites

- PHP 8.3+
- MariaDB 11.4+
- Composer
- OpenSSL (for JWT key generation)

### 7.2 Installation

```bash
# Clone repository
git clone https://github.com/yourorg/cre8pw.git
cd cre8pw

# Install dependencies
composer install

# Copy environment template
cp .env.example .env

# Generate JWT keys
mkdir -p keys
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -outform PEM -pubout -out keys/public.pem

# Configure database
# Edit .env with DB credentials

# Run migrations
php migrations/run.php

# Start local server
php -S localhost:8000 -t public/
```

### 7.3 Smoke Tests

```bash
# Health check
curl http://localhost:8000/health

# JWKS
curl http://localhost:8000/.well-known/jwks.json

# Register Owner
curl -X POST http://localhost:8000/console/owners \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'

# Login
curl -X POST http://localhost:8000/console/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"SecurePass123!"}'
```

---

## 8. Adding a New Capability (Checklist)

**Example:** Add ability to edit post titles.

1. ✅ **Update permissions** (if needed)
   - Add `posts:update` to permission catalog (**[authorization.md](../05-authentication-authorization/authorization.md)**)

2. ✅ **Add route**
   ```php
   $group->patch('/posts/{postId}/title', [PostController::class, 'updateTitle']);
   ```

3. ✅ **Add validation**
   ```php
   "PATCH /api/posts/{postId}/title" => [
       'body' => v::key('title', v::stringType()->length(1, 255)),
       'rejectUnknown' => true,
   ],
   ```

4. ✅ **Implement controller method**
   ```php
   public function updateTitle(Request $req, Response $res, array $args): Response {
       $postId = $args['postId'];
       $keyId = $req->getAttribute('key_id');
       $title = $req->getParsedBody()['title'];

       $post = $this->postService->updateTitle($postId, $keyId, $title);
       return $this->responseFactory->json($res, ['data' => $post]);
   }
   ```

5. ✅ **Implement service method**
   ```php
   public function updateTitle(string $postId, string $keyId, string $title): array {
       // Verify ownership or MANAGE_ACCESS
       $post = $this->postRepository->findById($postId);
       if ($post['author_key_id'] !== $keyId) {
           $mask = $this->postRepository->getAccessMask($postId, $keyId);
           if (!($mask & 0x08)) throw new ForbiddenException();
       }

       $this->postRepository->updateTitle($postId, $title);
       $this->auditRepository->log('posts:update:title', 'key', $keyId, 'post', $postId);

       return $this->postRepository->findById($postId);
   }
   ```

6. ✅ **Implement repository method**
   ```php
   public function updateTitle(string $postIdHex32, string $title): void {
       $sql = "UPDATE posts SET title = ?, updated_at = NOW() WHERE id = ?";
       $stmt = $this->pdo->prepare($sql);
       $stmt->execute([$title, Ids::hex32ToBinary($postIdHex32)]);
   }
   ```

7. ✅ **Test**
   - Unit tests for service logic
   - Integration test for full flow
   - Test authorization (403 when lacking permission)
   - Test validation (422 on invalid title)

8. ✅ **Update documentation**
   - Add route to **[api-reference.md](../06-api-reference/api-reference.md)**
   - Update audit events in **[logging-and-audit.md](../09-operations/logging-and-audit.md)**

---

## 9. Using the CRE8.pw SDK

For developers building applications that integrate with CRE8.pw, the official SDK provides a high-level, type-safe interface that abstracts away HTTP details and handles token management automatically.

**SDK Benefits:**
- **Type Safety:** Strong typing and compile-time checks
- **Automatic Token Management:** Handles authentication and token refresh
- **Error Handling:** Comprehensive error handling with typed exceptions
- **Retry Logic:** Automatic retries with configurable backoff
- **Rate Limit Handling:** Automatic backoff on rate limit responses

**Supported Languages:**
- **PHP 8.3+** (Available Now)
- Python 3.9+ (Planned)
- Go 1.21+ (Planned)

**Quick Start Example (PHP):**
```php
<?php
use Cre8\Sdk\Cre8Client;

$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'auth' => [
        'type' => 'key',
        'key_public_id' => $_ENV['CRE8_KEY_PUBLIC_ID'],
        'key_secret' => $_ENV['CRE8_KEY_SECRET'],
    ],
]);

// Create a post
$post = $client->posts()->create([
    'content' => 'Hello CRE8.pw!',
    'title' => 'First Post',
]);
```

**See:** **[sdk-specification.md](../11-development/sdk-specification.md)** for complete SDK documentation, including:
- Authentication flows (Owner and Key)
- Complete API client reference
- Type definitions and models
- Error handling patterns
- Configuration options
- Usage examples
- Best practices
- Language-specific implementations

---

**End of Main Documentation**

**Total Documents:** 11 main + 4 appendices = 15 documents complete!
