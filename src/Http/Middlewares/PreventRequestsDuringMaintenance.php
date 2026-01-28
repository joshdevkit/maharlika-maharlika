<?php

namespace Maharlika\Http\Middlewares;

use Maharlika\Contracts\Http\Middleware;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Exceptions\HttpErrorRenderer;

class PreventRequestsDuringMaintenance implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $maintenanceEnabled = config('app.maintenance', false);
        if ($maintenanceEnabled) {
            return HttpErrorRenderer::render(
                503,
                'Service Unavailable - We are currently undergoing maintenance.'
            );
        }

        return $next($request);
    }
}
