<?php
/**
 * CRE8.pw Route Parameter Validator Middleware
 * 
 * Validates route parameters to ensure correct ID format:
 * - Parameters ending in "Id" must be hex32 format
 * - Parameters named "keyPublicId" must be apub_... format
 * 
 * @see docs/APPENDIX/A-Identifier-Encoding-Matrix.md Section 2
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Utilities\Ids;
use App\Utilities\ErrorFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Route Parameter Validator Middleware
 * 
 * Validates route parameters according to CRE8.pw ID format rules.
 */
class RouteParameterValidatorMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseFactoryInterface $responseFactory PSR-7 response factory
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory
    ) {}

    /**
     * Process request and validate route parameters
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler Request handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeParams = $request->getAttribute('route')?->getArguments() ?? [];

        foreach ($routeParams as $paramName => $paramValue) {
            // Special case: keyPublicId must be apub_... format
            if ($paramName === 'keyPublicId') {
                if (!Ids::isValidKeyPublicId($paramValue)) {
                    return ErrorFactory::create(
                        $this->responseFactory,
                        ErrorFactory::CODE_BAD_REQUEST,
                        "Invalid key public ID format. Expected apub_... format.",
                        [],
                        400
                    );
                }
                continue;
            }

            // All parameters ending in "Id" must be hex32
            if (str_ends_with($paramName, 'Id')) {
                if (!Ids::isValidHex32($paramValue)) {
                    return ErrorFactory::create(
                        $this->responseFactory,
                        ErrorFactory::CODE_BAD_REQUEST,
                        "Invalid ID format for parameter '{$paramName}'. Expected 32-character hex string.",
                        [],
                        400
                    );
                }
            }
        }

        return $handler->handle($request);
    }
}
