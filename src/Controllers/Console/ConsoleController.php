<?php
/**
 * CRE8.pw Console Controller (HTML)
 * 
 * Handles HTML page rendering for Console UI.
 * Renders landing, registration, login, dashboard, and management pages.
 * 
 * @see docs/canon/04-Routes-and-API-Reference.md Section 2
 */

declare(strict_types=1);

namespace App\Controllers\Console;

use App\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Console Controller (HTML)
 * 
 * Handles HTML page rendering for Console UI.
 */
class ConsoleController extends BaseController
{
    private string $templateDir;

    public function __construct(ResponseFactoryInterface $responseFactory, string $templateDir = null)
    {
        parent::__construct($responseFactory);
        $this->templateDir = $templateDir ?? __DIR__ . '/../../../templates';
    }

    /**
     * Render a template
     * 
     * @param string $template Template filename (without .php extension)
     * @param array $data Template variables
     * @return string Rendered HTML
     */
    private function render(string $template, array $data = []): string
    {
        $templatePath = $this->templateDir . '/' . $template . '.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        
        // Include permission helpers for all templates
        $helpersPath = $this->templateDir . '/_permission_helpers.php';
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
        
        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Landing page
     * 
     * 
     * Endpoint: GET /
     * Auth: None
     * CSRF: Not required (no form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function landing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('landing', [
            'title' => 'CRE8.pw - Secure Content Sharing',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Registration form
     * 
     * 
     * Endpoint: GET /console/register
     * Auth: None
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('register', [
            'title' => 'Register - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Login form
     * 
     * 
     * Endpoint: GET /console/login
     * Auth: None
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('login', [
            'title' => 'Login - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Dashboard
     * 
     * 
     * Endpoint: GET /console/dashboard
     * Auth: Owner session/JWT (will be enforced in T14.2)
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Extract permissions from request attributes (set by JwtOwnerMiddleware if added to HTML routes)
        $permissions = $request->getAttribute('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $html = $this->render('dashboard', [
            'title' => 'Dashboard - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'permissions' => $permissions,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Keys list page
     * 
     * 
     * Endpoint: GET /console/keys
     * Auth: Owner session/JWT
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function keysList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $html = $this->render('keys_list', [
            'title' => 'Keys - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'permissions' => $permissions,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Groups list page
     * 
     * 
     * Endpoint: GET /console/groups
     * Auth: Owner session/JWT
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function groupsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $html = $this->render('groups_list', [
            'title' => 'Groups - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'permissions' => $permissions,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Keychains list page
     * 
     * 
     * Endpoint: GET /console/keychains
     * Auth: Owner session/JWT
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function keychainsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $html = $this->render('keychains_list', [
            'title' => 'Keychains - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'permissions' => $permissions,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Posts list page
     * 
     * 
     * Endpoint: GET /console/posts
     * Auth: Owner session/JWT
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function postsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        $html = $this->render('posts_list', [
            'title' => 'Posts - CRE8.pw',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'permissions' => $permissions,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
