<?php
/**
 * CRE8.pw Gateway Controller (HTML)
 * 
 * Handles HTML page rendering for Gateway API client example UI.
 * These are example/reference pages that demonstrate Gateway API usage.
 * 
 * @see docs/dev/UI_html_pages.md Section B
 */

declare(strict_types=1);

namespace App\Controllers\Gateway;

use App\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Gateway Controller (HTML)
 * 
 * Handles HTML page rendering for Gateway API client example UI.
 */
class GatewayController extends BaseController
{
    private string $templateDir;

    public function __construct(ResponseFactoryInterface $responseFactory, string $templateDir = null)
    {
        parent::__construct($responseFactory);
        $this->templateDir = $templateDir ?? __DIR__ . '/../../../templates/gateway';
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
        
        // Include permission helpers
        $helpersPath = __DIR__ . '/../../../templates/_permission_helpers.php';
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
        
        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * API Key Exchange page
     * 
     * 
     * Endpoint: GET /gateway/auth/exchange
     * Auth: None (public page)
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function authExchange(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('auth_exchange', [
            'title' => 'API Key Exchange - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Token Refresh page
     * 
     * 
     * Endpoint: GET /gateway/auth/refresh
     * Auth: None (public page)
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function authRefresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('auth_refresh', [
            'title' => 'Token Refresh - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Mint Secondary Key page
     * 
     * 
     * Endpoint: GET /gateway/keys/{authorKeyId}/secondary
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function mintSecondaryKey(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $authorKeyId = $routeParams['authorKeyId'] ?? '';
        
        $html = $this->render('keys_mint_secondary', [
            'title' => 'Mint Secondary Key - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'authorKeyId' => $authorKeyId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Mint Use Key page
     * 
     * 
     * Endpoint: GET /gateway/keys/{authorKeyId}/use
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function mintUseKey(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $authorKeyId = $routeParams['authorKeyId'] ?? '';
        
        $html = $this->render('keys_mint_use', [
            'title' => 'Mint Use Key - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'authorKeyId' => $authorKeyId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Posts list page
     * 
     * 
     * Endpoint: GET /gateway/posts
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function postsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('posts_list', [
            'title' => 'Posts - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Post detail page
     * 
     * 
     * Endpoint: GET /gateway/posts/{postId}
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function postDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $postId = $routeParams['postId'] ?? '';
        
        $html = $this->render('post_detail', [
            'title' => 'Post Detail - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'postId' => $postId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Grant access page
     * 
     * 
     * Endpoint: GET /gateway/posts/{postId}/access
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submission)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function grantAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $postId = $routeParams['postId'] ?? '';
        
        $html = $this->render('post_grant_access', [
            'title' => 'Grant Access - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'postId' => $postId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Comments list page
     * 
     * 
     * Endpoint: GET /gateway/posts/{postId}/comments
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function commentsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $postId = $routeParams['postId'] ?? '';
        
        $html = $this->render('comments_list', [
            'title' => 'Comments - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'postId' => $postId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Use Key feed page
     * 
     * 
     * Endpoint: GET /gateway/feed/use/{useKeyId}
     * Auth: Key JWT (via Bearer token, must match useKeyId)
     * CSRF: Not required (read-only)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function useKeyFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $useKeyId = $routeParams['useKeyId'] ?? '';
        
        $html = $this->render('feed_use', [
            'title' => 'Use Key Feed - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'useKeyId' => $useKeyId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Author feed page
     * 
     * 
     * Endpoint: GET /gateway/feed/author
     * Auth: Key JWT (via Bearer token, Author Key)
     * CSRF: Not required (read-only)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function authorFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('feed_author', [
            'title' => 'Author Feed - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Groups list page
     * 
     * 
     * Endpoint: GET /gateway/groups
     * Auth: Key JWT (via Bearer token)
     * CSRF: Not required (read-only)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function groupsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('groups_list', [
            'title' => 'Groups - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Group detail page
     * 
     * 
     * Endpoint: GET /gateway/groups/{groupId}
     * Auth: Key JWT (via Bearer token)
     * CSRF: Not required (read-only)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function groupDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $routeParams = $route ? $route->getArguments() : [];
        $groupId = $routeParams['groupId'] ?? '';
        
        $html = $this->render('group_detail', [
            'title' => 'Group Detail - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
            'groupId' => $groupId,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Keychains list page
     * 
     * 
     * Endpoint: GET /gateway/keychains
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submissions)
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function keychainsList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('keychains_list', [
            'title' => 'Keychains - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Sharing workflow page
     * 
     * 
     * Endpoint: GET /gateway/share
     * Auth: Key JWT (via Bearer token)
     * CSRF: Required (form submissions)
     * 
     * A wizard-style page that guides users through the complete sharing workflow:
     * 1. Create post
     * 2. Mint use key
     * 3. Grant access
     * 4. Share credentials
     * 
     * @param ServerRequestInterface $request PSR-7 request
     * @param ResponseInterface $response PSR-7 response
     * @return ResponseInterface
     */
    public function shareWorkflow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = $this->render('share_workflow', [
            'title' => 'Share Post - CRE8.pw Gateway',
            'csrf_name' => $request->getAttribute('csrf_name'),
            'csrf_value' => $request->getAttribute('csrf_value'),
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
