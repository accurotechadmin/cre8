<?php
/**
 * CRE8.pw Error Handling Middleware
 * 
 * Catches exceptions and normalizes to standardized error responses.
 * Never leaks sensitive details (stack traces, internal paths, secrets).
 * 
 * @see docs/canon/10-Response-Schemas-and-Error-Handling.md
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Utilities\ErrorFactory;
use App\Services\LoggingService;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Error Handling Middleware
 * 
 * Catches exceptions and normalizes to standardized error responses.
 */
class ErrorHandlingMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     * @param LoggerInterface $logger Logger instance (api channel)
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger
    ) {}

    /**
     * Process request and catch exceptions
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle exception and return standardized error response
     * 
     * @param \Throwable $exception Exception to handle
     * @param ServerRequestInterface $request PSR-7 request
     * @return ResponseInterface
     */
    private function handleException(\Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        // Extract request ID from request attribute (set by RequestLoggingMiddleware if present)
        $requestId = $request->getAttribute('request_id');
        
        // Log exception to appropriate channel
        $logContext = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ];
        
        if ($requestId) {
            $logContext['request_id'] = $requestId;
        }
        
        // Add authentication context if available
        $ownerId = $request->getAttribute('owner_id');
        $keyId = $request->getAttribute('key_id');
        if ($ownerId) {
            $logContext['owner_id'] = $ownerId;
        }
        if ($keyId) {
            $logContext['key_id'] = $keyId;
        }
        
        // Map exception to error response
        if ($exception instanceof NotFoundException) {
            LoggingService::log($this->logger, 'INFO', 'Not found', $logContext);
            return ErrorFactory::notFound(
                $this->responseFactory,
                $exception->getMessage(),
                $requestId
            );
        }

        if ($exception instanceof ForbiddenException) {
            LoggingService::log($this->logger, 'WARNING', 'Forbidden', $logContext);
            return ErrorFactory::forbidden(
                $this->responseFactory,
                $exception->getRequiredPermissions(),
                $exception->getRequiredMask(),
                null,
                $requestId
            );
        }

        if ($exception instanceof \InvalidArgumentException) {
            LoggingService::log($this->logger, 'WARNING', 'Bad request', $logContext);
            return ErrorFactory::create(
                $this->responseFactory,
                ErrorFactory::CODE_BAD_REQUEST,
                $exception->getMessage(),
                [],
                400,
                $requestId
            );
        }

        // Default: internal error
        LoggingService::log($this->logger, 'ERROR', 'Internal error', $logContext);
        return ErrorFactory::internalError($this->responseFactory, $requestId);
    }
}
