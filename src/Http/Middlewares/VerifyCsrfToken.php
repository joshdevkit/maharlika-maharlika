<?php

namespace Maharlika\Http\Middlewares;

use Maharlika\Contracts\Http\Middleware;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Exceptions\HttpErrorRenderer;
use Maharlika\Http\Response;
use Maharlika\Http\Server;

class VerifyCsrfToken implements Middleware
{
    /**
     * URIs that should be excluded from CSRF verification
     * 
     * @var array
     */
    protected array $except = [
        '/api/*',              // All API routes are exempt
        '/broadcasting/auth',  // Broadcasting auth endpoint
        '/webhooks/*',         // Common webhook pattern
    ];

    /**
     * Handle an incoming request
     * 
     * @param RequestInterface $request
     * @param \Closure $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        // Skip CSRF for API routes (they should use token/bearer auth instead)
        if ($this->isApiRequest($request)) {
            return $next($request);
        }

        // Skip CSRF check for safe methods
        if ($this->isSafeMethod($request->method())) {
            return $next($request);
        }

        // Skip CSRF check for excluded routes
        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        $session = $request->session();

        // Generate token if it doesn't exist (only once)
        if (!$session->has('_token')) {
            $session->put('_token', $this->generateToken());
        }

        // Verify CSRF token
        if (!$this->tokensMatch($request)) {
            return $this->handleTokenMismatch($request);
        }

        return $next($request);
    }

    /**
     * Determine if this is an API request
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function isApiRequest(RequestInterface $request): bool
    {
        $path = $request->getPath();
        
        // Check if path starts with /api
        if (str_starts_with($path, Server::apiPrefix())) {
            return true;
        }

        // Check Accept header
        $accept = $request->header('Accept');
        if ($accept && str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the HTTP method is safe (doesn't modify data)
     * 
     * @param string $method
     * @return bool
     */
    protected function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Determine if the request has a URI that should be excluded
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function inExceptArray(RequestInterface $request): bool
    {
        $path = $request->getPath();

        foreach ($this->except as $except) {
            // Convert wildcards to regex pattern
            $pattern = $this->convertToRegex($except);

            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a URI pattern with wildcards to a regex pattern
     * 
     * @param string $pattern
     * @return string
     */
    protected function convertToRegex(string $pattern): string
    {
        // Escape special regex characters except *
        $pattern = preg_quote($pattern, '#');
        
        // Convert * to regex wildcard
        $pattern = str_replace('\*', '.*', $pattern);
        
        return '#^' . $pattern . '$#';
    }

    /**
     * Determine if the session and input CSRF tokens match
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function tokensMatch(RequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $request->session()->get('_token');

        if (!$token || !$sessionToken) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Get the CSRF token from the request
     * 
     * @param RequestInterface $request
     * @return string|null
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        // 1. Try POST/request body first (most common for forms)
        $token = $request->input('_token');
        if (!empty($token)) {
            return $token;
        }

        // 2. Try query parameters (less common, but supported)
        $queryParams = $request->query();
        if (isset($queryParams['_token']) && is_string($queryParams['_token']) && $queryParams['_token'] !== '') {
            return $queryParams['_token'];
        }

        // 3. Try headers for AJAX/SPA requests
        $headers = [
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Handle token mismatch
     * 
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function handleTokenMismatch(RequestInterface $request): ResponseInterface
    {
        // For JSON/AJAX requests, return JSON error
        if ($request->isJson() || $request->expectsJson()) {
            return Response::json([
                'message' => 'CSRF token mismatch.',
                'error' => 'TokenMismatchException'
            ], 419);
        }

        // For regular web requests, use security error renderer
        return HttpErrorRenderer::renderSecurityError(
            'CSRF Token Mismatch',
            [
                'missing_middleware' => 'CSRF Protection',
                'solution' => 'Please include a valid CSRF token in your request. Refresh the page and try again.',
                'severity' => 'High',
                'impact' => 'This request cannot be processed due to missing or invalid CSRF token. This protection prevents Cross-Site Request Forgery attacks.',
            ]
        );
    }

    /**
     * Generate a new CSRF token
     * 
     * @return string
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Regenerate the CSRF token
     * Call this after login/logout to prevent session fixation
     * 
     * @return string
     */
    public static function regenerateToken(): string
    {
        $session = app('session');
        $token = bin2hex(random_bytes(32));
        $session->put('_token', $token);
        return $token;
    }

    /**
     * Get the current CSRF token
     * 
     * @return string|null
     */
    public static function getToken(): ?string
    {
        $session = app('session');
        return $session->get('_token');
    }
}