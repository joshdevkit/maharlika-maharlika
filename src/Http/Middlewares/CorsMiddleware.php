<?php

namespace Maharlika\Http\Middlewares;

use Closure;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;

class CorsMiddleware implements Middleware
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
            'exposed_headers' => [],
            'max_age' => 86400, // 24 hours
            'supports_credentials' => false,
        ], $config);
    }

    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        // Handle actual request
        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     */
    protected function handlePreflightRequest(RequestInterface $request): ResponseInterface
    {
        $response = response('', 204);

        $this->addCorsHeaders($request, $response);

        return $response;
    }

    /**
     * Add CORS headers to response
     */
    protected function addCorsHeaders(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->header('Origin');

        // Add Access-Control-Allow-Origin
        if ($this->isOriginAllowed($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin ?: '*');
        } elseif (in_array('*', $this->config['allowed_origins'])) {
            $response->header('Access-Control-Allow-Origin', '*');
        }

        // Add Access-Control-Allow-Credentials
        if ($this->config['supports_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        // Add Access-Control-Allow-Methods
        $response->header(
            'Access-Control-Allow-Methods',
            implode(', ', $this->config['allowed_methods'])
        );

        // Add Access-Control-Allow-Headers
        $allowedHeaders = $this->config['allowed_headers'];
        
        // Include requested headers if using wildcard
        $requestedHeaders = $request->header('Access-Control-Request-Headers');
        if ($requestedHeaders && in_array('*', $allowedHeaders)) {
            $allowedHeaders = array_merge($allowedHeaders, explode(', ', $requestedHeaders));
        }

        $response->header(
            'Access-Control-Allow-Headers',
            implode(', ', array_unique($allowedHeaders))
        );

        // Add Access-Control-Expose-Headers
        if (!empty($this->config['exposed_headers'])) {
            $response->header(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        // Add Access-Control-Max-Age for preflight caching
        $response->header('Access-Control-Max-Age', (string) $this->config['max_age']);

        return $response;
    }

    /**
     * Check if origin is allowed
     */
    protected function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        $allowedOrigins = $this->config['allowed_origins'];

        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        // Check wildcard patterns
        foreach ($allowedOrigins as $allowed) {
            if ($this->matchesPattern($origin, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match origin against pattern (supports wildcards)
     */
    protected function matchesPattern(string $origin, string $pattern): bool
    {
        // Escape special regex characters except *
        $pattern = preg_quote($pattern, '#');
        
        // Replace escaped \* with .* for wildcard matching
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#i', $origin);
    }
}