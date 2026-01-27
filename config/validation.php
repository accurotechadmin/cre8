<?php
/**
 * CRE8.pw Validation Rules Configuration
 * 
 * This file defines validation schemas for all endpoints.
 * Keys are in format "METHOD /pattern" (e.g., "POST /api/posts").
 * 
 * See docs/canon/01-Architecture-and-Request-Pipeline.md section 6 for validation selection rules.
 */

declare(strict_types=1);

use Respect\Validation\Validator as v;

return [
    // Owner registration (T5.2)
    "POST /console/owners" => [
        'body' => v::key('email', v::email()->notEmpty())
                 ->key('password', v::stringType()->length(8, null)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Owner login (T5.2)
    "POST /console/login" => [
        'body' => v::key('email', v::email()->notEmpty())
                 ->key('password', v::stringType()->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Refresh token rotation (T5.4)
    "POST /api/auth/refresh" => [
        'body' => v::key('refresh_token', v::stringType()->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Post creation (T8.1)
    "POST /api/posts" => [
        'body' => v::key('content', v::stringType()->length(1, 10000)->notEmpty())
                 ->key('title', v::optional(v::stringType()->length(1, 255))),
        'rejectUnknown' => false, // Allow optional title field
    ],
    
    // Post access grant (T8.2)
    "POST /api/posts/{postId}/access" => [
        'body' => v::key('target_type', v::in(['key', 'group'])->notEmpty())
                 ->key('target_id', v::stringType()->length(32, 32)->notEmpty()) // hex32 format
                 ->key('permission_mask', v::intType()->min(0)->max(15)), // Valid masks: 0x01, 0x02, 0x08, combinations
        'rejectUnknown' => true,
    ],
    
    // Post access revoke (T8.2)
    "DELETE /api/posts/{postId}/access/{targetType}/{targetId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for DELETE
    ],
    
    // Owner admin post list (T8.3)
    "GET /console/posts" => [
        // Query parameters validated in controller (limit, before_id)
        // No body validation needed for GET
    ],
    
    // Owner admin post detail (T8.3)
    "GET /console/posts/{postId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    
    // Owner admin grant group access (T8.3)
    "POST /console/posts/{postId}/access/grant-group" => [
        'body' => v::key('group_id', v::stringType()->length(32, 32)->notEmpty()) // hex32 format
                 ->key('permission_mask', v::intType()->min(0)->max(15)), // Valid masks: 0x01, 0x02, 0x08, combinations
        'rejectUnknown' => true,
    ],
    
    // Owner admin revoke group access (T8.3)
    "POST /console/posts/{postId}/access/revoke-group" => [
        'body' => v::key('group_id', v::stringType()->length(32, 32)->notEmpty()), // hex32 format
        'rejectUnknown' => true,
    ],
    
    // Comment creation (T9.1)
    "POST /api/posts/{postId}/comments" => [
        'body' => v::key('body', v::stringType()->length(1, 5000)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Comment listing (T9.2)
    "GET /api/posts/{postId}/comments" => [
        // Query parameters validated in controller (limit, before_id)
        // No body validation needed for GET
    ],
    
    // Group creation (T10.1)
    "POST /console/groups" => [
        'body' => v::key('name', v::stringType()->length(1, 255)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Group list (T10.1)
    "GET /console/groups" => [
        // No query parameters or body validation needed
    ],
    
    // Group detail (T10.1)
    "GET /console/groups/{groupId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    
    // Group rename (T10.1)
    "POST /console/groups/{groupId}/rename" => [
        'body' => v::key('name', v::stringType()->length(1, 255)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Group delete (T10.1)
    "DELETE /console/groups/{groupId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for DELETE
    ],
    
    // Group add member (T10.1)
    "POST /console/groups/{groupId}/members" => [
        'body' => v::key('key_id', v::stringType()->length(32, 32)->notEmpty()), // hex32 format
        'rejectUnknown' => true,
    ],
    
    // Group remove member (T10.1)
    "DELETE /console/groups/{groupId}/members/{keyId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for DELETE
    ],
    
    // Keychain list (T10.2)
    "GET /console/keychains" => [
        // No query parameters or body validation needed
    ],
    
    // Keychain creation (T10.2)
    "POST /console/keychains" => [
        'body' => v::key('name', v::stringType()->length(1, 255)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Keychain add member (T10.2)
    "POST /console/keychains/{id}/members" => [
        'body' => v::key('key_id', v::stringType()->length(32, 32)->notEmpty()), // hex32 format
        'rejectUnknown' => true,
    ],
    
    // Keychain remove member (T10.2)
    "DELETE /console/keychains/{id}/members/{keyId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for DELETE
    ],
    
    // Gateway groups list (T10.3)
    "GET /api/groups" => [
        // No query parameters or body validation needed
    ],
    
    // Gateway group detail (T10.3)
    "GET /api/groups/{groupId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    
    // Gateway group members (T10.3)
    "GET /api/groups/{groupId}/members" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    
    // Gateway keychain creation (T10.4)
    "POST /api/keychains" => [
        'body' => v::key('name', v::stringType()->length(1, 255)->notEmpty()),
        'rejectUnknown' => true,
    ],
    
    // Gateway keychain add member (T10.4)
    "POST /api/keychains/{id}/members" => [
        'body' => v::key('key_id', v::stringType()->length(32, 32)->notEmpty()), // hex32 format
        'rejectUnknown' => true,
    ],
    
    // Gateway keychain remove member (T10.4)
    "DELETE /api/keychains/{id}/members/{keyId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for DELETE
    ],
    
    // Gateway Use Key feed (T13.1)
    "GET /api/feed/use/{useKeyId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // Query parameters (limit, before_id, since_id) validated in controller
        // No body validation needed for GET
    ],
    
    // Gateway Author feed (T13.2)
    "GET /api/feed/author" => [
        // Query parameters (limit, before_id, since_id) validated in controller
        // No body validation needed for GET
    ],
    
    // Gateway route catalog (T11.1)
    "GET /api/routes" => [
        // No query parameters or body validation needed for GET
    ],
    
    // Console route catalog (T12.1)
    "GET /console/routes" => [
        // No query parameters or body validation needed for GET
    ],
    
    // Key management (T7.1, T7.3)
    "POST /console/keys/primary" => [
        'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
                 ->key('label', v::optional(v::stringType()->length(1, 255))),
        'rejectUnknown' => true,
    ],
    "GET /console/keys" => [
        // Query parameters (limit, before_id) validated in controller
        // No body validation needed for GET
    ],
    "GET /console/keys/{keyId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    "GET /console/keys/{keyId}/lineage" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
    "POST /console/keys/{keyId}/rotate" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed (rotation uses key properties)
    ],
    "POST /console/keys/{keyId}/activate" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed
    ],
    "POST /console/keys/{keyId}/deactivate" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        'body' => v::key('cascade', v::optional(v::boolType())),
        'rejectUnknown' => true,
    ],
    
    // Gateway key minting (T7.1)
    "POST /api/keys/{authorKeyId}/secondary" => [
        'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
                 ->key('label', v::optional(v::stringType()->length(1, 255))),
        'rejectUnknown' => true,
    ],
    "POST /api/keys/{authorKeyId}/use" => [
        'body' => v::key('permissions', v::arrayType()->each(v::stringType()))
                 ->key('label', v::optional(v::stringType()->length(1, 255)))
                 ->key('use_count', v::optional(v::intType()->min(1)))
                 ->key('device_limit', v::optional(v::intType()->min(1))),
        'rejectUnknown' => true,
    ],
    
    // Gateway post routes (missing routes)
    "GET /api/posts" => [
        // Query parameters (limit, before_id) validated in controller
        // No body validation needed for GET
    ],
    "GET /api/posts/{postId}" => [
        // Route parameters validated by RouteParameterValidatorMiddleware
        // No body validation needed for GET
    ],
];
