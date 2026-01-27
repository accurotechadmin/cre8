<?php
/**
 * CRE8.pw Validation Middleware
 * 
 * Validates request body, query, and headers per centralized schemas.
 * Selects validator by "METHOD /pattern" from config/validation.php.
 * 
 * @see docs/canon/01-Architecture-and-Request-Pipeline.md Section 5.5
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Utilities\ErrorFactory;
use Respect\Validation\Validator as v;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Validation Middleware
 * 
 * Validates requests per centralized validation schemas.
 */
class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param array<string, array> $validationRules Validation rules from config/validation.php
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private array $validationRules
    ) {}

    /**
     * Process request and validate
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $key = "{$method} {$path}";

        // Find matching validation rule
        $rule = $this->findMatchingRule($method, $path);
        
        if ($rule === null) {
            // No validation rule for this route, proceed
            return $handler->handle($request);
        }

        // Validate request
        $errors = $this->validate($request, $rule);
        
        if (!empty($errors)) {
            return ErrorFactory::validationFailed($this->responseFactory, $errors);
        }

        return $handler->handle($request);
    }

    /**
     * Find matching validation rule for method and path
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array|null Validation rule or null if no match
     */
    private function findMatchingRule(string $method, string $path): ?array
    {
        // Exact match first
        $key = "{$method} {$path}";
        if (isset($this->validationRules[$key])) {
            return $this->validationRules[$key];
        }

        // Pattern match (simple implementation - can be enhanced)
        foreach ($this->validationRules as $pattern => $rule) {
            if ($this->matchesPattern($pattern, $method, $path)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if pattern matches method and path
     * 
     * @param string $pattern Pattern (e.g., "POST /api/posts/{postId}")
     * @param string $method HTTP method
     * @param string $path Request path
     * @return bool
     */
    private function matchesPattern(string $pattern, string $method, string $path): bool
    {
        // Simple pattern matching - convert {param} to regex
        $parts = explode(' ', $pattern, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $patternMethod = $parts[0];
        $patternPath = $parts[1];

        if ($patternMethod !== $method) {
            return false;
        }

        // Convert {param} to regex
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $patternPath);
        $regex = '#^' . $regex . '$#';

        return preg_match($regex, $path) === 1;
    }

    /**
     * Validate request against rule
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param array $rule Validation rule
     * @return array<string, string[]> Field errors
     */
    private function validate(ServerRequestInterface $request, array $rule): array
    {
        $errors = [];

        // Validate body
        if (isset($rule['body']) && $rule['body'] instanceof v) {
            $body = $request->getParsedBody() ?? [];
            try {
                $rule['body']->assert($body);
            } catch (\Respect\Validation\Exceptions\ValidationException $e) {
                $errors = array_merge($errors, $this->extractValidationErrors($e));
            }

            // Check for unknown fields if rejectUnknown is true
            if (($rule['rejectUnknown'] ?? false) && is_array($body)) {
                // Use explicit allowedFields if provided, otherwise introspect validator
                $allowedFields = $rule['allowedFields'] ?? $this->extractAllowedFields($rule['body']);
                foreach (array_keys($body) as $field) {
                    if (!in_array($field, $allowedFields, true)) {
                        $errors[$field][] = "Unknown field";
                    }
                }
            }
        }

        // Validate query
        if (isset($rule['query']) && $rule['query'] instanceof v) {
            $query = $request->getQueryParams();
            try {
                $rule['query']->assert($query);
            } catch (\Respect\Validation\Exceptions\ValidationException $e) {
                $errors = array_merge($errors, $this->extractValidationErrors($e));
            }
        }

        return $errors;
    }

    /**
     * Extract validation errors from exception
     * 
     * @param \Respect\Validation\Exceptions\ValidationException $e Validation exception
     * @return array<string, string[]> Field errors
     */
    private function extractValidationErrors(\Respect\Validation\Exceptions\ValidationException $e): array
    {
        $errors = [];
        
        foreach ($e->getMessages() as $field => $messages) {
            $errors[$field] = is_array($messages) ? $messages : [$messages];
        }

        return $errors;
    }

    /**
     * Extract allowed field names from validator
     * 
     * Introspects Respect\Validation validator to extract Key rules.
     * 
     * @param v $validator Respect\Validation validator
     * @return array<string> Allowed field names
     */
    private function extractAllowedFields(v $validator): array
    {
        $fields = [];
        
        // Introspect validator to extract Key rules
        // Respect\Validation validators can be accessed via reflection or getRules() if available
        try {
            // Try to get rules using reflection (Respect\Validation v2.x approach)
            $reflection = new \ReflectionClass($validator);
            
            // Check if validator has a rules property or getRules method
            if ($reflection->hasMethod('getRules')) {
                $rules = $validator->getRules();
                foreach ($rules as $rule) {
                    $this->extractFieldsFromRule($rule, $fields);
                }
            } elseif ($reflection->hasProperty('rules')) {
                $rulesProperty = $reflection->getProperty('rules');
                $rulesProperty->setAccessible(true);
                $rules = $rulesProperty->getValue($validator);
                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        $this->extractFieldsFromRule($rule, $fields);
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // If introspection fails, return empty array (will reject all fields if rejectUnknown=true)
            // This is safer than allowing all fields
        }
        
        return $fields;
    }
    
    /**
     * Extract field names from a validation rule
     * 
     * Helper to recursively extract Key rules from validator
     * 
     * @param mixed $rule Validation rule
     * @param array<string> &$fields Output array to collect field names
     * @return void
     */
    private function extractFieldsFromRule(mixed $rule, array &$fields): void
    {
        if (is_object($rule)) {
            $ruleClass = get_class($rule);
            
            // Check if it's a Key rule
            if ($ruleClass === 'Respect\Validation\Rules\Key' || 
                str_ends_with($ruleClass, '\Key')) {
                try {
                    $reflection = new \ReflectionClass($rule);
                    // Key rule has a 'name' or 'reference' property
                    if ($reflection->hasProperty('name')) {
                        $nameProperty = $reflection->getProperty('name');
                        $nameProperty->setAccessible(true);
                        $fieldName = $nameProperty->getValue($rule);
                        if (is_string($fieldName)) {
                            $fields[] = $fieldName;
                        }
                    } elseif ($reflection->hasMethod('getReference')) {
                        $fieldName = $rule->getReference();
                        if (is_string($fieldName)) {
                            $fields[] = $fieldName;
                        }
                    }
                } catch (\ReflectionException $e) {
                    // Skip if we can't extract
                }
            }
            
            // Check if it's an AllOf rule (contains multiple rules)
            if ($ruleClass === 'Respect\Validation\Rules\AllOf' || 
                str_ends_with($ruleClass, '\AllOf')) {
                try {
                    $reflection = new \ReflectionClass($rule);
                    if ($reflection->hasProperty('rules')) {
                        $rulesProperty = $reflection->getProperty('rules');
                        $rulesProperty->setAccessible(true);
                        $subRules = $rulesProperty->getValue($rule);
                        if (is_array($subRules)) {
                            foreach ($subRules as $subRule) {
                                $this->extractFieldsFromRule($subRule, $fields);
                            }
                        }
                    } elseif ($reflection->hasMethod('getRules')) {
                        $subRules = $rule->getRules();
                        if (is_array($subRules)) {
                            foreach ($subRules as $subRule) {
                                $this->extractFieldsFromRule($subRule, $fields);
                            }
                        }
                    }
                } catch (\ReflectionException $e) {
                    // Skip if we can't extract
                }
            }
        }
    }
}
