# CRE8.pw SDK Specification

**Version:** 1.0.0  
**Last Updated:** 2026-01-25  
**Status:** Specification Document

**Current Implementation:** PHP 8.3+ SDK is available via Composer (`cre8/sdk`).  
**Planned:** Python and Go SDKs will be released in the near future. This specification serves as the reference for all language implementations.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Supported Languages & Platforms](#2-supported-languages--platforms)
3. [Core Features](#3-core-features)
4. [Architecture & Design Principles](#4-architecture--design-principles)
5. [Authentication](#5-authentication)
6. [API Client Structure](#6-api-client-structure)
7. [Type Definitions & Models](#7-type-definitions--models)
8. [Error Handling](#8-error-handling)
9. [Configuration](#9-configuration)
10. [Usage Examples](#10-usage-examples)
11. [Best Practices](#11-best-practices)
12. [Versioning & Compatibility](#12-versioning--compatibility)
13. [Testing & Development](#13-testing--development)
14. [Appendix A: Language-Specific Implementations](#appendix-a-language-specific-implementations)
15. [Appendix B: Migration Guide](#appendix-b-migration-guide)
16. [Appendix C: FAQ](#appendix-c-faq)

---

## 1. Overview

### 1.1 Purpose

The CRE8.pw SDK provides a high-level, type-safe, developer-friendly interface for integrating CRE8.pw authentication and authorization into third-party applications. The SDK abstracts away HTTP details, handles token management, provides automatic retry logic, and offers comprehensive error handling.

### 1.2 Goals

- **Developer Experience:** Simple, intuitive API that follows language idioms
- **Type Safety:** Strong typing and compile-time checks where applicable
- **Security:** Secure credential storage, automatic token refresh, no secrets in logs
- **Reliability:** Automatic retries, connection pooling, rate limit handling
- **Completeness:** Full coverage of CRE8.pw API endpoints
- **Extensibility:** Plugin architecture for custom middleware and interceptors

### 1.3 Target Use Cases

- **Web Applications:** Server-side PHP web apps (Laravel, Symfony, Slim, etc.) using Owner or Key authentication
- **CLI Tools:** Command-line utilities for key management
- **Server Applications:** Backend services integrating CRE8.pw authorization
- **Desktop Applications:** Native desktop apps with CRE8.pw integration (via PHP)

**Note:** Mobile and browser applications will be supported when Python and Go SDKs are released.

---

## 2. Supported Languages & Platforms

### 2.1 Current Implementation

| Language | Platform | Package Manager | Status |
|----------|----------|----------------|---------|
| **PHP** | PHP 8.3+ | Composer | **Available Now** |

### 2.2 Planned Implementations (Future)

| Language | Platform | Package Manager | Status |
|----------|----------|----------------|---------|
| **Python** | Python 3.9+ | pip | Planned |
| **Go** | Go 1.21+ | go modules | Planned |
| **Rust** | Rust 1.70+ | Cargo | Future |
| **Java** | Java 17+ | Maven/Gradle | Future |
| **C#** | .NET 8+ | NuGet | Future |
| **Ruby** | Ruby 3.0+ | gem | Future |

**Note:** The PHP SDK is the reference implementation. Other language SDKs will follow the same API patterns and design principles outlined in this specification.

### 2.3 Platform-Specific Considerations

**PHP (Current Implementation):**
- Environment variable support via `$_ENV`
- File-based credential storage
- PSR-7/PSR-18 compatibility
- Composer dependency management
- PHP 8.3+ type system with strict types
- Async support via ReactPHP (optional)

**Python (Planned):**
- Environment variable support via `os.environ`
- File-based credential storage
- Server-side token caching
- Async/await support

**Go (Planned):**
- Environment variable support
- File-based credential storage
- Context support for cancellation
- Zero dependencies (except stdlib)

**Mobile (iOS/Android) - Future:**
- Keychain/Keystore integration (via language-specific SDKs)
- Background token refresh
- Network reachability handling

---

## 3. Core Features

### 3.1 Authentication Management

- **Owner Authentication:** Email/password login with automatic token refresh
- **Key Authentication:** ApiKey exchange with automatic token refresh
- **Token Storage:** Secure, platform-appropriate storage (keychain, secure storage, environment variables)
- **Automatic Refresh:** Background token refresh before expiration
- **Token Rotation:** Single-use refresh token handling

### 3.2 API Client

- **Type-Safe Methods:** All endpoints have strongly-typed request/response models
- **Automatic Retries:** Configurable retry logic for transient failures
- **Rate Limit Handling:** Automatic backoff and retry on 429 responses
- **Request/Response Interceptors:** Middleware for logging, metrics, custom headers
- **Connection Pooling:** Efficient HTTP connection reuse

### 3.3 Key Management

- **Key Minting:** Create Primary, Secondary, and Use Keys
- **Key Rotation:** Rotate keys while preserving lineage
- **Key Activation/Deactivation:** Manage key lifecycle
- **Lineage Tracking:** View key provenance and descendant tree

### 3.4 Content Management

- **Post Creation:** Create posts with content and optional title
- **Post Access Control:** Grant/revoke access using bitmasks
- **Comment Management:** Create and list comments
- **Feed Access:** Retrieve Use Key feeds and Author feeds

### 3.5 Group & Keychain Management

- **Groups:** Create, manage, and query groups
- **Keychains:** Manage external keychains
- **Membership:** Add/remove members from groups and keychains

### 3.6 Error Handling

- **Typed Exceptions:** Language-appropriate exception types
- **Error Recovery:** Automatic retry for retryable errors
- **Detailed Error Context:** Request ID, error codes, field-level validation errors

---

## 4. Architecture & Design Principles

### 4.1 Layered Architecture

```
┌─────────────────────────────────────┐
│      Application Code               │
│  (Uses SDK High-Level API)          │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      SDK Client Layer                │
│  - Authentication                    │
│  - Resource Clients                  │
│  - Error Handling                    │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      HTTP Transport Layer            │
│  - Request Building                  │
│  - Response Parsing                  │
│  - Retry Logic                       │
│  - Rate Limiting                     │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      Network Layer                   │
│  - HTTP Client (Guzzle/PSR-18)  │
│  - Connection Pooling                │
│  - TLS/SSL                           │
└─────────────────────────────────────┘
```

### 4.2 Design Principles

1. **Immutability:** Request/response objects are immutable where possible
2. **Composability:** Small, focused methods that can be composed
3. **Fail Fast:** Validate inputs early, provide clear error messages
4. **Idiomatic:** Follow language conventions and best practices
5. **Observable:** Support logging, metrics, and debugging hooks

### 4.3 Resource-Oriented Design

The SDK organizes API methods by resource:

- `client.keys.*` - Key management
- `client.posts.*` - Post operations
- `client.comments.*` - Comment operations
- `client.groups.*` - Group management
- `client.keychains.*` - Keychain management
- `client.feeds.*` - Feed access
- `client.auth.*` - Authentication operations

---

## 5. Authentication

### 5.1 Owner Authentication Flow

```php
<?php
// PHP Example
use Cre8\Sdk\Cre8Client;

$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
]);

// Register new owner
$owner = $client->auth()->owners()->register([
    'email' => 'alice@example.com',
    'password' => 'SecurePassword123!',
]);

// Login
$session = $client->auth()->owners()->login([
    'email' => 'alice@example.com',
    'password' => 'SecurePassword123!',
]);

// Client automatically stores tokens and uses them for subsequent requests
```

### 5.2 Key Authentication Flow

```php
<?php
// Exchange ApiKey for JWT
$session = $client->auth()->keys()->exchange([
    'key_public_id' => 'apub_8cd1a2b3c4d5e6f7',
    'key_secret' => 'sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
]);

// Client automatically stores tokens and uses them for subsequent requests
```

### 5.3 Token Storage

**PHP:**
```php
<?php
// Uses file-based storage or environment variables
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'token_storage' => 'file',  // or 'env'
    'token_storage_path' => '~/.cre8/tokens.json',
]);
```

**Python (Planned):**
- Environment variable support via `os.environ`
- File-based credential storage
- Server-side token caching
- Async/await support

**Go (Planned):**
- Environment variable support
- File-based credential storage
- Context support for cancellation
- Zero dependencies (except stdlib)
```

### 5.4 Automatic Token Refresh

The SDK automatically refreshes tokens before expiration:

```php
<?php
// Configure refresh behavior
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'token_refresh' => [
        'enabled' => true,
        'refresh_before_expiry' => 60,  // seconds before expiry
        'max_retries' => 3,
    ],
]);
```

### 5.5 Custom Token Storage

```php
<?php
// Implement custom token storage
use Cre8\Sdk\TokenStorage\TokenStorageInterface;

class CustomTokenStorage implements TokenStorageInterface {
    public function getAccessToken(): ?string {
        // Custom retrieval logic
        return null;
    }
    
    public function setAccessToken(string $token): void {
        // Custom storage logic
    }
    
    public function getRefreshToken(): ?string {
        // Custom retrieval logic
        return null;
    }
    
    public function setRefreshToken(string $token): void {
        // Custom storage logic
    }
    
    public function clear(): void {
        // Custom cleanup logic
    }
}

$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'token_storage' => new CustomTokenStorage(),
]);
```

---

## 6. API Client Structure

### 6.1 Client Initialization

```php
<?php
// Basic initialization
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
]);

// With authentication
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'auth' => [
        'type' => 'key',
        'key_public_id' => 'apub_8cd1a2b3c4d5e6f7',
        'key_secret' => 'sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
    ],
]);

// With custom configuration
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'timeout' => 30000,
    'retries' => [
        'max_attempts' => 3,
        'backoff' => 'exponential',
        'initial_delay' => 1000,
    ],
    'rate_limit' => [
        'enabled' => true,
        'max_retries' => 5,
    ],
]);
```

### 6.2 Resource Clients

#### 6.2.1 Keys Client

```php
<?php
// Mint Primary Author Key (Owner only)
$key = $client->keys()->mintPrimary([
    'permissions' => ['posts:create', 'keys:issue', 'comments:write'],
    'label' => 'My Content Key',
]);

// Mint Secondary Author Key
$secondaryKey = $client->keys()->mintSecondary([
    'author_key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
    'permissions' => ['posts:create', 'comments:write'],
    'label' => 'Delegated Key',
]);

// Mint Use Key
$useKey = $client->keys()->mintUse([
    'author_key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
    'permissions' => ['posts:read', 'comments:write'],
    'label' => 'Share Link for Alice',
    'use_count' => 1,
    'device_limit' => null,
]);

// List keys
$keys = $client->keys()->list(['limit' => 20, 'before_id' => null]);

// Get key details
$keyDetails = $client->keys()->get('b5a1e8c0d9f04c3aa1b2c3d4e5f60718');

// Get key lineage
$lineage = $client->keys()->getLineage('b5a1e8c0d9f04c3aa1b2c3d4e5f60718');

// Rotate key
$rotated = $client->keys()->rotate('b5a1e8c0d9f04c3aa1b2c3d4e5f60718');

// Activate/Deactivate key
$client->keys()->activate('b5a1e8c0d9f04c3aa1b2c3d4e5f60718');
$client->keys()->deactivate('b5a1e8c0d9f04c3aa1b2c3d4e5f60718', ['cascade' => false]);
```

#### 6.2.2 Posts Client

```php
<?php
use Cre8\Sdk\PostAccessMask;

// Create post
$post = $client->posts()->create([
    'content' => 'This is my first post!',
    'title' => 'Hello CRE8.pw',
]);

// Get post
$postDetails = $client->posts()->get('c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2');

// List posts
$posts = $client->posts()->list(['limit' => 20, 'before_id' => null]);

// Grant access
$client->posts()->grantAccess([
    'post_id' => 'c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2',
    'target_type' => 'key',
    'target_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
    'permission_mask' => PostAccessMask::VIEW | PostAccessMask::COMMENT,
]);

// Revoke access
$client->posts()->revokeAccess([
    'post_id' => 'c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2',
    'target_type' => 'key',
    'target_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
]);
```

#### 6.2.3 Comments Client

```php
<?php
// Create comment
$comment = $client->comments()->create([
    'post_id' => 'c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2',
    'body' => 'Great post!',
]);

// List comments
$comments = $client->comments()->list([
    'post_id' => 'c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2',
    'limit' => 20,
    'before_id' => null,
]);
```

#### 6.2.4 Feeds Client

```php
<?php
// Get Use Key feed
$feed = $client->feeds()->getUseFeed([
    'use_key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
    'limit' => 20,
    'before_id' => null,
    'since_id' => null,
]);

// Get Author feed
$authorFeed = $client->feeds()->getAuthorFeed([
    'limit' => 20,
    'before_id' => null,
    'since_id' => null,
]);
```

#### 6.2.5 Groups Client

```php
<?php
// Create group (Console)
$group = $client->groups()->create(['name' => 'Content Creators']);

// List groups
$groups = $client->groups()->list();

// Get group
$groupDetails = $client->groups()->get('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6');

// Add member
$client->groups()->addMember([
    'group_id' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
    'key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
]);

// Remove member
$client->groups()->removeMember([
    'group_id' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
    'key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
]);
```

#### 6.2.6 Keychains Client

```php
<?php
// Create keychain
$keychain = $client->keychains()->create(['name' => 'External Partners']);

// List keychains
$keychains = $client->keychains()->list();

// Add member
$client->keychains()->addMember([
    'keychain_id' => 'e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6',
    'key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
]);

// Remove member
$client->keychains()->removeMember([
    'keychain_id' => 'e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6',
    'key_id' => 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
]);
```

---

## 7. Type Definitions & Models

### 7.1 Core Types

```php
<?php
namespace Cre8\Sdk;

// ID Types
type Hex32 = string;  // 32-character lowercase hex
type KeyPublicId = string;  // apub_... format
type OwnerId = Hex32;
type KeyId = Hex32;
type PostId = Hex32;
type CommentId = Hex32;
type GroupId = Hex32;
type KeychainId = Hex32;

// Permission Types
type Permission = string;  // e.g., 'posts:create', 'keys:issue', etc.

// Post Access Masks
class PostAccessMask {
    public const VIEW = 0x01;
    public const COMMENT = 0x02;
    public const MANAGE_ACCESS = 0x08;
}

// Key Types
type KeyType = 'primary' | 'secondary' | 'use';

// Target Types
type AccessTargetType = 'key' | 'group';
```

### 7.2 Request Models

```php
<?php
namespace Cre8\Sdk\Requests;

// Owner Registration
class OwnerRegisterRequest {
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

// Owner Login
class OwnerLoginRequest {
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

// Key Exchange
class KeyExchangeRequest {
    public function __construct(
        public string $key_public_id,
        public string $key_secret,
    ) {}
}

// Mint Primary Key
class MintPrimaryKeyRequest {
    public function __construct(
        public array $permissions,
        public ?string $label = null,
    ) {}
}

// Mint Secondary Key
class MintSecondaryKeyRequest {
    public function __construct(
        public string $author_key_id,
        public array $permissions,
        public ?string $label = null,
    ) {}
}

// Mint Use Key
class MintUseKeyRequest {
    public function __construct(
        public string $author_key_id,
        public array $permissions,
        public ?string $label = null,
        public ?int $use_count = null,
        public ?int $device_limit = null,
    ) {}
}

// Create Post
class CreatePostRequest {
    public function __construct(
        public string $content,
        public ?string $title = null,
    ) {}
}

// Grant Access
class GrantAccessRequest {
    public function __construct(
        public string $post_id,
        public string $target_type,
        public string $target_id,
        public int $permission_mask,  // Bitmask
    ) {}
}

// Create Comment
class CreateCommentRequest {
    public function __construct(
        public string $post_id,
        public string $body,
    ) {}
}

// Pagination
class PaginationParams {
    public function __construct(
        public ?int $limit = null,  // Default: 20, Max: 100
        public ?string $before_id = null,
        public ?string $since_id = null,
    ) {}
}
```

### 7.3 Response Models

```php
<?php
namespace Cre8\Sdk\Models;

// Standard Response Envelope
class Response {
    public function __construct(
        public mixed $data,
    ) {}
}

// Paginated Response
class PaginatedResponse {
    public function __construct(
        public array $data,
        public ?array $paging = null,  // ['limit' => int, 'cursor' => ?string]
    ) {}
}

// Error Response
class ErrorResponse {
    public function __construct(
        public array $error,  // ['code' => string, 'message' => string, 'details' => ?array, 'request_id' => ?string]
    ) {}
}

// Owner
class Owner {
    public function __construct(
        public string $owner_id,
        public string $email,
        public string $created_at,
    ) {}
}

// Key
class Key {
    public function __construct(
        public string $key_id,
        public ?string $key_public_id,
        public string $type,
        public array $permissions,
        public bool $active,
        public ?string $label,
        public ?int $use_count_limit,
        public ?int $use_count_current,
        public ?int $device_limit,
        public ?string $issued_by_key_id,
        public ?string $parent_key_id,
        public string $initial_author_key_id,
        public string $created_at,
        public string $updated_at,
    ) {}
}

// Post
class Post {
    public function __construct(
        public string $post_id,
        public string $author_key_id,
        public string $initial_author_key_id,
        public string $content,
        public ?string $title,
        public string $created_at,
        public string $updated_at,
    ) {}
}

// Comment
class Comment {
    public function __construct(
        public string $comment_id,
        public string $post_id,
        public string $created_by_key_id,
        public string $body,
        public string $created_at,
    ) {}
}

// Group
class Group {
    public function __construct(
        public string $group_id,
        public string $name,
        public string $created_at,
        public string $updated_at,
    ) {}
}

// Keychain
class Keychain {
    public function __construct(
        public string $keychain_id,
        public string $name,
        public string $created_at,
        public string $updated_at,
    ) {}
}

// Authentication Session
class AuthSession {
    public function __construct(
        public string $access_token,
        public string $refresh_token,
        public int $expires_in,  // seconds
    ) {}
}
```

---

## 8. Error Handling

### 8.1 Error Types

```php
<?php
namespace Cre8\Sdk\Exceptions;

// Base Error
abstract class Cre8Exception extends \Exception {
    public function __construct(
        public string $code,
        string $message,
        public ?array $details = null,
        public ?string $request_id = null,
        public ?int $status_code = null,
    ) {
        parent::__construct($message);
    }
}

// Specific Error Types
class UnauthorizedException extends Cre8Exception {
    public function __construct(string $message = "Unauthorized", ...$args) {
        parent::__construct('unauthorized', $message, status_code: 401, ...$args);
    }
}

class ForbiddenException extends Cre8Exception {
    public function __construct(
        string $message = "Forbidden",
        public ?array $required_permissions = null,
        public ?string $required_mask = null,
        ...$args
    ) {
        parent::__construct('forbidden', $message, status_code: 403, ...$args);
    }
}

class NotFoundException extends Cre8Exception {
    public function __construct(string $message = "Not Found", ...$args) {
        parent::__construct('not_found', $message, status_code: 404, ...$args);
    }
}

class ValidationException extends Cre8Exception {
    public function __construct(
        string $message = "Validation Failed",
        public ?array $field_errors = null,
        ...$args
    ) {
        parent::__construct('validation_failed', $message, status_code: 422, ...$args);
    }
}

class RateLimitException extends Cre8Exception {
    public function __construct(
        string $message = "Rate Limited",
        public ?int $retry_after_seconds = null,
        ...$args
    ) {
        parent::__construct('rate_limited', $message, status_code: 429, ...$args);
    }
}

class ServerException extends Cre8Exception {
    public function __construct(string $message = "Internal Server Error", ...$args) {
        parent::__construct('internal_error', $message, status_code: 500, ...$args);
    }
}
```

### 8.2 Error Handling Examples

```php
<?php
use Cre8\Sdk\Exceptions\UnauthorizedException;
use Cre8\Sdk\Exceptions\ForbiddenException;
use Cre8\Sdk\Exceptions\ValidationException;
use Cre8\Sdk\Exceptions\RateLimitException;
use Cre8\Sdk\Exceptions\ServerException;

try {
    $post = $client->posts()->create(['content' => 'Hello world']);
} catch (UnauthorizedException $e) {
    // Token expired or invalid - refresh and retry
    $client->auth()->refresh();
    $post = $client->posts()->create(['content' => 'Hello world']);
} catch (ForbiddenException $e) {
    // Missing permissions
    error_log('Required permissions: ' . json_encode($e->required_permissions));
    error_log('Required mask: ' . $e->required_mask);
} catch (ValidationException $e) {
    // Field-level validation errors
    error_log('Field errors: ' . json_encode($e->field_errors));
} catch (RateLimitException $e) {
    // Rate limited - wait and retry
    $waitTime = $e->retry_after_seconds ?? 60;
    sleep($waitTime);
    $post = $client->posts()->create(['content' => 'Hello world']);
} catch (\Exception $e) {
    // Unexpected error
    error_log('Unexpected error: ' . $e->getMessage());
}
```

### 8.3 Retry Logic

```php
<?php
// Automatic retry configuration
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'retries' => [
        'max_attempts' => 3,
        'backoff' => 'exponential',  // 'linear' | 'exponential' | 'fixed'
        'initial_delay' => 1000,  // milliseconds
        'max_delay' => 10000,
        'retryable_status_codes' => [429, 500, 502, 503, 504],
        'retryable_errors' => ['ECONNRESET', 'ETIMEDOUT'],
    ],
]);
```

---

## 9. Configuration

### 9.1 Client Configuration

```php
<?php
namespace Cre8\Sdk;

class Cre8ClientConfig {
    public function __construct(
        // Required
        public string $base_url,
        
        // Authentication
        public ?array $auth = null,  // ['type' => 'owner'|'key', 'email' => string, 'password' => string,
                                     //  'key_public_id' => string, 'key_secret' => string]
        
        // Token Management
        public ?string $token_storage = null,  // 'file' | 'env'
        public ?string $token_storage_path = null,
        public ?array $token_refresh = null,  // ['enabled' => bool, 'refresh_before_expiry' => int, 'max_retries' => int]
        
        // HTTP Configuration
        public ?int $timeout = null,  // milliseconds
        public ?array $headers = null,
        public ?string $user_agent = null,
        
        // Retry Configuration
        public ?array $retries = null,  // ['max_attempts' => int, 'backoff' => string, 'initial_delay' => int,
                                         //  'max_delay' => int, 'retryable_status_codes' => array, 
                                         //  'retryable_errors' => array]
        
        // Rate Limiting
        public ?array $rate_limit = null,  // ['enabled' => bool, 'max_retries' => int]
        
        // Logging
        public ?object $logger = null,
        public ?string $log_level = null,  // 'debug' | 'info' | 'warn' | 'error'
        
        // Interceptors
        public ?array $request_interceptors = null,
        public ?array $response_interceptors = null,
    ) {}
}
```

### 9.2 Environment Variables

```bash
# Base URL
CRE8_BASE_URL=https://cre8.pw

# Authentication (for key-based auth)
CRE8_KEY_PUBLIC_ID=apub_8cd1a2b3c4d5e6f7
CRE8_KEY_SECRET=sec_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6

# Token Storage
CRE8_TOKEN_STORAGE=file
CRE8_TOKEN_STORAGE_PATH=~/.cre8/tokens.json

# Logging
CRE8_LOG_LEVEL=info
```

### 9.3 Configuration Loading

```php
<?php
// Load from environment variables
$client = Cre8Client::fromEnvironment();

// Load from config file
$client = Cre8Client::fromConfigFile('~/.cre8/config.json');

// Load from custom source
$config = loadConfigFromCustomSource();
$client = new Cre8Client($config);
```

---

## 10. Usage Examples

### 10.1 Complete Application Example

```php
<?php
use Cre8\Sdk\Cre8Client;
use Cre8\Sdk\PostAccessMask;

function main() {
    // Initialize client
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
        'content' => 'Welcome to my blog!',
        'title' => 'First Post',
    ]);
    
    echo "Created post: {$post->post_id}\n";
    
    // Mint a Use Key for sharing
    $useKey = $client->keys()->mintUse([
        'author_key_id' => $_ENV['AUTHOR_KEY_ID'],
        'permissions' => ['posts:read', 'comments:write'],
        'label' => 'Share Link for Readers',
        'use_count' => 100,
    ]);
    
    echo "Created Use Key: {$useKey->key_public_id}\n";
    
    // Grant access to the post
    $client->posts()->grantAccess([
        'post_id' => $post->post_id,
        'target_type' => 'key',
        'target_id' => $useKey->key_id,
        'permission_mask' => PostAccessMask::VIEW | PostAccessMask::COMMENT,
    ]);
    
    echo "Granted access to Use Key\n";
    
    // Create a comment (using Use Key)
    $useKeyClient = new Cre8Client([
        'base_url' => 'https://cre8.pw',
        'auth' => [
            'type' => 'key',
            'key_public_id' => $useKey->key_public_id,
            'key_secret' => $useKey->key_secret,
        ],
    ]);
    
    $comment = $useKeyClient->comments()->create([
        'post_id' => $post->post_id,
        'body' => 'Great post!',
    ]);
    
    echo "Created comment: {$comment->comment_id}\n";
}

main();
```

### 10.2 Error Recovery Example

```php
<?php
use Cre8\Sdk\Exceptions\UnauthorizedException;
use Cre8\Sdk\Exceptions\RateLimitException;
use Cre8\Sdk\Exceptions\ServerException;

function createPostWithRetry(Cre8Client $client, string $content): Post {
    $attempts = 0;
    $maxAttempts = 3;
    
    while ($attempts < $maxAttempts) {
        try {
            return $client->posts()->create(['content' => $content]);
        } catch (UnauthorizedException $e) {
            // Token expired - refresh and retry
            $attempts++;
            $client->auth()->refresh();
            continue;
        } catch (RateLimitException $e) {
            // Rate limited - wait and retry
            $attempts++;
            $waitTime = $e->retry_after_seconds ?? 60;
            echo "Rate limited. Waiting {$waitTime} seconds...\n";
            sleep($waitTime);
            continue;
        } catch (ServerException $e) {
            if ($attempts < $maxAttempts) {
                // Server error - retry with exponential backoff
                $attempts++;
                $delay = (2 ** $attempts) * 1000 / 1000;  // Convert to seconds
                echo "Server error. Retrying in {$delay}s...\n";
                sleep((int)$delay);
                continue;
            } else {
                throw $e;
            }
        }
    }
    
    throw new \Exception('Max retry attempts reached');
}
```

### 10.3 Batch Operations Example

```php
<?php
function createMultiplePosts(Cre8Client $client, array $contents): array {
    $results = [];
    $successful = [];
    $failed = [];
    
    foreach ($contents as $content) {
        try {
            $successful[] = $client->posts()->create(['content' => $content]);
        } catch (\Exception $e) {
            $failed[] = $e;
        }
    }
    
    echo "Created " . count($successful) . " posts\n";
    echo "Failed: " . count($failed) . "\n";
    
    return ['successful' => $successful, 'failed' => $failed];
}
```

---

## 11. Best Practices

### 11.1 Security

1. **Never commit secrets:** Use environment variables or secure credential stores
2. **Use secure token storage:** Prefer platform-native secure storage (keychain, keystore)
3. **Validate inputs:** Always validate user inputs before sending to API
4. **Handle errors securely:** Don't expose sensitive information in error messages
5. **Use HTTPS:** Always use HTTPS in production

### 11.2 Performance

1. **Connection pooling:** Reuse HTTP connections
2. **Batch operations:** Use batch endpoints when available
3. **Pagination:** Use pagination for large lists
4. **Caching:** Cache static data (e.g., group lists)
5. **Lazy loading:** Load data on demand

### 11.3 Reliability

1. **Retry logic:** Configure appropriate retry strategies
2. **Timeout handling:** Set reasonable timeouts
3. **Circuit breakers:** Implement circuit breakers for repeated failures
4. **Health checks:** Monitor API health
5. **Graceful degradation:** Handle API unavailability gracefully

### 11.4 Code Organization

1. **Resource clients:** Use resource-specific clients
2. **Error handling:** Centralize error handling logic
3. **Configuration:** Externalize configuration
4. **Logging:** Use structured logging
5. **Testing:** Write unit and integration tests

---

## 12. Versioning & Compatibility

### 12.1 SDK Versioning

- **Semantic Versioning:** Major.Minor.Patch (e.g., 1.2.3)
- **Major:** Breaking API changes
- **Minor:** New features, backward compatible
- **Patch:** Bug fixes, backward compatible

### 12.2 API Versioning

- **API Version:** Specified in `Accept` header or URL path
- **Default:** Latest stable version
- **Deprecation:** 6-month notice before removal

### 12.3 Compatibility Matrix

| SDK Version | API Version | Status |
|-------------|-------------|--------|
| 1.x | v1 | Supported |
| 2.x | v1, v2 | Supported |
| 3.x | v2 | Supported (v1 deprecated) |

---

## 13. Testing & Development

### 13.1 Mock Client

```php
<?php
use Cre8\Sdk\Testing\Cre8MockClient;

$mockClient = new Cre8MockClient([
    'responses' => [
        'POST /api/posts' => [
            'status' => 201,
            'body' => [
                'data' => [
                    'post_id' => 'test-post-id',
                    'content' => 'Test content',
                ],
            ],
        ],
    ],
]);

// Use in tests
$post = $mockClient->posts()->create(['content' => 'Test']);
```

### 13.2 Test Utilities

```php
<?php
use Cre8\Sdk\Testing\createTestClient;
use Cre8\Sdk\Testing\createTestKey;

// Create test client with test credentials
$testClient = createTestClient();

// Create test key
$testKey = createTestKey(['permissions' => ['posts:create']]);
```

### 13.3 Development Tools

- **CLI:** Command-line tool for key management
- **Dev Server:** Local development server with mock API
- **Debug Mode:** Verbose logging and request/response inspection
- **Type Definitions:** Full PHP 8.3+ type hints and PHPDoc annotations

---

## Appendix A: Language-Specific Implementations

### A.1 PHP (Current Implementation)

**Package:** `cre8/sdk`

**Installation:**
```bash
composer require cre8/sdk
```

**Features:**
- PSR-7/PSR-18 compatible
- PHP 8.3+ type system with strict types
- Async support via ReactPHP (optional)
- Doctrine annotations for models
- Full PHPDoc type hints
- Composer dependency management

**Status:** Available now. This is the reference implementation.

### A.2 Python (Planned)

**Package:** `cre8-sdk`

**Installation:**
```bash
pip install cre8-sdk
```

**Features (Planned):**
- Type hints (PEP 484)
- Async/await support
- Context managers for resource cleanup
- Pydantic models for validation

**Status:** Planned for near future.

### A.3 Go (Planned)

**Package:** `github.com/cre8/sdk-go`

**Installation:**
```bash
go get github.com/cre8/sdk-go
```

**Features (Planned):**
- Strong typing
- Context support for cancellation
- Interface-based design
- Zero dependencies (except stdlib)

**Status:** Planned for near future.

---

## Appendix B: Migration Guide

### B.1 From REST API to SDK

**Before (Raw HTTP):**
```php
<?php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'https://cre8.pw']);
$response = $client->post('/api/posts', [
    'headers' => [
        'Authorization' => "Bearer {$token}",
        'Content-Type' => 'application/json',
    ],
    'json' => ['content' => 'Hello world'],
]);
$data = json_decode($response->getBody(), true);
```

**After (SDK):**
```php
<?php
$post = $client->posts()->create(['content' => 'Hello world']);
```

### B.2 Breaking Changes

When upgrading SDK versions, check:
1. **CHANGELOG.md** for breaking changes
2. **Migration guides** for version-specific instructions
3. **Deprecation warnings** in code

---

## Appendix C: FAQ

### C.1 How do I handle token expiration?

The SDK automatically refreshes tokens before expiration. If a token expires during a request, the SDK will:
1. Attempt to refresh the token
2. Retry the original request
3. Throw an error if refresh fails

### C.2 How do I customize retry behavior?

Configure retry settings in client initialization:

```php
<?php
$client = new Cre8Client([
    'base_url' => 'https://cre8.pw',
    'retries' => [
        'max_attempts' => 5,
        'backoff' => 'exponential',
        'initial_delay' => 1000,
    ],
]);
```

### C.3 How do I handle rate limits?

The SDK automatically handles rate limits by:
1. Detecting 429 responses
2. Reading `Retry-After` header
3. Waiting and retrying
4. Throwing `RateLimitError` if max retries exceeded

### C.4 Can I use the SDK in web applications?

Yes! The PHP SDK can be used in web applications (e.g., Laravel, Symfony, Slim). Use secure token storage appropriate for your framework (session storage, secure cookies, etc.).

### C.5 How do I test my integration?

Use the mock client for unit tests and a test CRE8.pw instance for integration tests.

---

**End of Specification**
