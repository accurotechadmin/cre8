<?php
/**
 * CRE8.pw Error Factory Utility
 * 
 * Provides standardized error response creation with proper error code taxonomy
 * and HTTP status code mapping.
 * 
 * @see docs/canon/10-Response-Schemas-and-Error-Handling.md
 */

declare(strict_types=1);

namespace App\Utilities;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Error Factory Utility
 * 
 * Creates standardized error responses following CRE8.pw error taxonomy.
 */
class ErrorFactory
{
    // Error codes (from error taxonomy)
    public const CODE_BAD_REQUEST = 'bad_request';
    public const CODE_UNAUTHORIZED = 'unauthorized';
    public const CODE_FORBIDDEN = 'forbidden';
    public const CODE_NOT_FOUND = 'not_found';
    public const CODE_CONFLICT = 'conflict';
    public const CODE_VALIDATION_FAILED = 'validation_failed';
    public const CODE_RATE_LIMITED = 'rate_limited';
    public const CODE_INTERNAL_ERROR = 'internal_error';
    public const CODE_SERVICE_UNAVAILABLE = 'service_unavailable';

    /**
     * HTTP status code mapping for error codes
     * 
     * @var array<string, int>
     */
    private const STATUS_CODE_MAP = [
        self::CODE_BAD_REQUEST => 400,
        self::CODE_UNAUTHORIZED => 401,
        self::CODE_FORBIDDEN => 403,
        self::CODE_NOT_FOUND => 404,
        self::CODE_CONFLICT => 409,
        self::CODE_VALIDATION_FAILED => 422,
        self::CODE_RATE_LIMITED => 429,
        self::CODE_INTERNAL_ERROR => 500,
        self::CODE_SERVICE_UNAVAILABLE => 503,
    ];

    /**
     * Create a standardized error response
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param string $code Error code (from constants)
     * @param string $message Human-readable error message
     * @param array $details Structured error details
     * @param int|null $statusCode HTTP status code (will be mapped from code if not provided)
     * @param string|null $requestId Request correlation ID (if tracing enabled)
     * @return ResponseInterface
     */
    public static function create(
        ResponseFactoryInterface $factory,
        string $code,
        string $message,
        array $details = [],
        ?int $statusCode = null,
        ?string $requestId = null
    ): ResponseInterface {
        // Map status code from error code if not provided
        if ($statusCode === null) {
            $statusCode = self::STATUS_CODE_MAP[$code] ?? 500;
        }

        $response = $factory->createResponse($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');

        $body = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ]
        ];

        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        return $response;
    }

    /**
     * Create a validation error response (422)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param array<string, string[]> $fieldErrors Field-level validation errors
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function validationFailed(
        ResponseFactoryInterface $factory,
        array $fieldErrors,
        ?string $requestId = null
    ): ResponseInterface {
        return self::create(
            $factory,
            self::CODE_VALIDATION_FAILED,
            'Validation failed',
            ['fields' => $fieldErrors],
            422,
            $requestId
        );
    }

    /**
     * Create a bad request error response (400)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param string $message Error message
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function badRequest(
        ResponseFactoryInterface $factory,
        string $message = 'Bad request',
        ?string $requestId = null
    ): ResponseInterface {
        return self::create(
            $factory,
            self::CODE_BAD_REQUEST,
            $message,
            [],
            400,
            $requestId
        );
    }

    /**
     * Create an unauthorized error response (401)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param string $message Error message (should be generic to prevent oracle leakage)
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function unauthorized(
        ResponseFactoryInterface $factory,
        string $message = 'Invalid credentials',
        ?string $requestId = null
    ): ResponseInterface {
        return self::create(
            $factory,
            self::CODE_UNAUTHORIZED,
            $message,
            [],
            401,
            $requestId
        );
    }

    /**
     * Create a forbidden error response (403)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param array<string>|null $requiredPermissions Required permissions that are missing
     * @param string|null $requiredMask Required post mask (e.g., "COMMENT")
     * @param int|null $requiredMaskValue Required mask value (e.g., 2)
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function forbidden(
        ResponseFactoryInterface $factory,
        ?array $requiredPermissions = null,
        ?string $requiredMask = null,
        ?int $requiredMaskValue = null,
        ?string $requestId = null
    ): ResponseInterface {
        $details = [];
        
        if ($requiredPermissions !== null) {
            $details['required'] = $requiredPermissions;
        }
        
        if ($requiredMask !== null) {
            $details['required_mask'] = $requiredMask;
            if ($requiredMaskValue !== null) {
                $details['required_mask_value'] = $requiredMaskValue;
            }
        }

        $message = !empty($requiredPermissions)
            ? 'Insufficient permissions'
            : ($requiredMask !== null ? 'Insufficient post access' : 'Access denied');

        return self::create(
            $factory,
            self::CODE_FORBIDDEN,
            $message,
            $details,
            403,
            $requestId
        );
    }

    /**
     * Create a not found error response (404)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param string $message Error message
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function notFound(
        ResponseFactoryInterface $factory,
        string $message = 'Resource not found',
        ?string $requestId = null
    ): ResponseInterface {
        return self::create(
            $factory,
            self::CODE_NOT_FOUND,
            $message,
            [],
            404,
            $requestId
        );
    }

    /**
     * Create a rate limited error response (429)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param int|null $retryAfterSeconds Seconds until retry is allowed
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function rateLimited(
        ResponseFactoryInterface $factory,
        ?int $retryAfterSeconds = null,
        ?string $requestId = null
    ): ResponseInterface {
        $details = [];
        if ($retryAfterSeconds !== null) {
            $details['retry_after_seconds'] = $retryAfterSeconds;
        }

        return self::create(
            $factory,
            self::CODE_RATE_LIMITED,
            'Too many requests',
            $details,
            429,
            $requestId
        );
    }

    /**
     * Create an internal error response (500)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param string|null $requestId Request correlation ID
     * @return ResponseInterface
     */
    public static function internalError(
        ResponseFactoryInterface $factory,
        ?string $requestId = null
    ): ResponseInterface {
        return self::create(
            $factory,
            self::CODE_INTERNAL_ERROR,
            'An unexpected error occurred',
            [],
            500,
            $requestId
        );
    }
}
