<?php
/**
 * CRE8.pw Health Controller
 * 
 * Provides health check endpoint for monitoring and load balancer health checks.
 * 
 * @see docs/canon/10-Response-Schemas-and-Error-Handling.md
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;

/**
 * Health Controller
 * 
 * Handles health check requests.
 */
class HealthController extends BaseController
{
    public function __construct(
        \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
        private ?ContainerInterface $container = null
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * Health check endpoint
     * 
     * Returns application health status.
     * Used by load balancers, monitoring systems, and deployment health checks.
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = [
            'status' => 'ok',
            'timestamp' => date('c'), // ISO 8601 format
        ];
        
        // Check database connectivity (T2.1)
        if ($this->container !== null) {
            try {
                $pdo = $this->container->get(PDO::class);
                // Simple connectivity check
                $pdo->query('SELECT 1');
                $data['database'] = 'ok';
            } catch (\Throwable $e) {
                $data['database'] = 'error';
                $data['status'] = 'degraded';
            }
        }
        
        $statusCode = $data['status'] === 'ok' ? 200 : 503;
        return $this->single($data, $statusCode);
    }
}
