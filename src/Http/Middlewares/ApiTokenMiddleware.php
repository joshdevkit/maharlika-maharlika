<?php

namespace Maharlika\Http\Middlewares;

use Closure;
use Maharlika\Auth\ApiTokenGuard;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;
use Maharlika\Http\JsonResponse;

class ApiTokenMiddleware implements Middleware
{
    protected ApiTokenGuard $guard;
    protected array $abilities;

    public function __construct(ApiTokenGuard $guard, array $abilities = [])
    {
        $this->guard = $guard;
        $this->abilities = $abilities;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, Closure $next, ...$abilities): ResponseInterface
    {
        // Merge constructor abilities with method abilities
        $requiredAbilities = array_merge($this->abilities, $abilities);

        if ($this->guard->guest()) {
            return new JsonResponse([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Check abilities if specified
        if (!empty($requiredAbilities)) {
            $token = $this->guard->token();

            foreach ($requiredAbilities as $ability) {
                if ($token->cant($ability)) {
                    return new JsonResponse([
                        'message' => 'Forbidden.',
                        'error' => "Token lacks required ability: {$ability}"
                    ], 403);
                }
            }
        }

        // Set user on request
        $request->setUser($this->guard->user());

        return $next($request);
    }
}
