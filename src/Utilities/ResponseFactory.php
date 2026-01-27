<?php
/**
 * CRE8.pw Response Factory Utility
 * 
 * Provides standardized response envelope creation for all API endpoints.
 * Ensures consistent response format across the application.
 * 
 * @see docs/canon/10-Response-Schemas-and-Error-Handling.md
 */

declare(strict_types=1);

namespace App\Utilities;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Response Factory Utility
 * 
 * Creates standardized JSON response envelopes following CRE8.pw API conventions.
 */
class ResponseFactory
{
    /**
     * Create a single object response
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param mixed $data Response data (will be wrapped in 'data' key)
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    public static function single(
        ResponseFactoryInterface $factory,
        mixed $data,
        int $statusCode = 200
    ): ResponseInterface {
        $response = $factory->createResponse($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');
        
        $body = ['data' => $data];
        
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        return $response;
    }

    /**
     * Create a list response (without pagination)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param array $data Array of items (will be wrapped in 'data' key)
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    public static function list(
        ResponseFactoryInterface $factory,
        array $data,
        int $statusCode = 200
    ): ResponseInterface {
        $response = $factory->createResponse($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');
        
        $body = ['data' => $data];
        
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        return $response;
    }

    /**
     * Create a paginated list response
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param array $data Array of items
     * @param int $limit Number of items per page
     * @param string|null $cursor Opaque cursor value for next page (typically last item ID)
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    public static function paginated(
        ResponseFactoryInterface $factory,
        array $data,
        int $limit,
        ?string $cursor = null,
        int $statusCode = 200
    ): ResponseInterface {
        $response = $factory->createResponse($statusCode);
        $response = $response->withHeader('Content-Type', 'application/json');
        
        $body = [
            'data' => $data,
            'paging' => [
                'limit' => $limit,
            ]
        ];
        
        if ($cursor !== null) {
            $body['paging']['cursor'] = $cursor;
        }
        
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
        return $response;
    }

    /**
     * Create a created response (201 Created)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @param mixed $data Created resource data
     * @return ResponseInterface
     */
    public static function created(
        ResponseFactoryInterface $factory,
        mixed $data
    ): ResponseInterface {
        return self::single($factory, $data, 201);
    }

    /**
     * Create a no content response (204 No Content)
     * 
     * @param ResponseFactoryInterface $factory PSR-7 response factory
     * @return ResponseInterface
     */
    public static function noContent(ResponseFactoryInterface $factory): ResponseInterface
    {
        return $factory->createResponse(204);
    }
}
