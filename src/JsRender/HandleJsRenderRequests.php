<?php

namespace Maharlika\JsRender;

use Closure;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;

/**
 * Handle JsRender Requests Middleware
 * 
 * Processes JsRender-specific headers and manages asset versioning
 * to ensure clients stay in sync with the server.
 */
class HandleJsRenderRequests
{
    /**
     * Handle an incoming request
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $response = $next($request);

        // Only process JsRender responses
        if (!$this->isJsRenderResponse($response)) {
            return $response;
        }

        // Check for version mismatch
        if ($this->hasVersionMismatch($request)) {
            return $this->forceReload($request);
        }

        return $response;
    }

    /**
     * Check if response is a JsRender response
     */
    protected function isJsRenderResponse(ResponseInterface $response): bool
    {
        return $response->hasHeader('X-JsRender');
    }

    /**
     * Check if there's a version mismatch between client and server
     */
    protected function hasVersionMismatch(RequestInterface $request): bool
    {
        $clientVersion = $request->header('X-JsRender-Version');
        $serverVersion = app('jsrender')->version();

        return $clientVersion && $clientVersion !== $serverVersion;
    }

    /**
     * Force a full page reload due to version mismatch
     */
    protected function forceReload(RequestInterface $request): ResponseInterface
    {
        return response('', 409)
            ->withHeader('X-JsRender-Location', $request->getUri());
    }
}