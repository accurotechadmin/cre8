<?php
/**
 * CRE8.pw Base Controller Abstract Class
 * 
 * Base class for all controllers. Enforces responsibility boundaries:
 * - Controllers are HTTP adapters (extract params, call service, shape response)
 * - Controllers MUST NOT contain business logic, database access, or authorization checks
 * 
 * @see docs/canon/09-Implementation-Guide.md Section 3.1
 * @see docs/canon/10-Response-Schemas-and-Error-Handling.md
 * @see ARCHITECTURE.md Layer Responsibilities
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Utilities\ResponseFactory as ResponseFactoryUtil;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base Controller
 * 
 * All controllers extend this class to ensure consistent response handling
 * and enforce architectural boundaries.
 */
abstract class BaseController
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     */
    public function __construct(
        protected ResponseFactoryInterface $responseFactory
    ) {}

    /**
     * Create a single object response
     * 
     * @param mixed $data Response data (will be wrapped in 'data' key)
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    protected function single(mixed $data, int $statusCode = 200): ResponseInterface
    {
        return ResponseFactoryUtil::single($this->responseFactory, $data, $statusCode);
    }

    /**
     * Create a list response
     * 
     * @param array $data Array of items (will be wrapped in 'data' key)
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    protected function list(array $data, int $statusCode = 200): ResponseInterface
    {
        return ResponseFactoryUtil::list($this->responseFactory, $data, $statusCode);
    }

    /**
     * Create a paginated list response
     * 
     * @param array $data Array of items
     * @param int $limit Number of items per page
     * @param string|null $cursor Opaque cursor value for next page
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    protected function paginated(
        array $data,
        int $limit,
        ?string $cursor = null,
        int $statusCode = 200
    ): ResponseInterface {
        return ResponseFactoryUtil::paginated($this->responseFactory, $data, $limit, $cursor, $statusCode);
    }

    /**
     * Create a created response (201 Created)
     * 
     * @param mixed $data Created resource data
     * @return ResponseInterface
     */
    protected function created(mixed $data): ResponseInterface
    {
        return ResponseFactoryUtil::created($this->responseFactory, $data);
    }

    /**
     * Create a no content response (204 No Content)
     * 
     * @return ResponseInterface
     */
    protected function noContent(): ResponseInterface
    {
        return ResponseFactoryUtil::noContent($this->responseFactory);
    }

    /**
     * Create a JSON error response
     * 
     * @param string $code Error code (from ErrorFactory constants)
     * @param string $message Error message
     * @param array $details Additional error details
     * @param int $statusCode HTTP status code (will be mapped from error code if not provided)
     * @return ResponseInterface
     */
    protected function error(
        string $code,
        string $message,
        array $details = [],
        ?int $statusCode = null
    ): ResponseInterface {
        return ErrorFactory::create($this->responseFactory, $code, $message, $details, $statusCode);
    }
}
