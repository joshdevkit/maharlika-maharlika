<?php

namespace Maharlika\Http\Middlewares;

use Closure;
use Maharlika\Contracts\Auth\AuthManagerContract;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;

class GuestMiddleware implements Middleware
{
    protected AuthManagerContract $auth;
    protected string $redirectTo;

    /**
     * GuestMiddleware constructor.
     *
     * @param AuthManagerContract $auth       The authentication manager instance.
     * @param string      $redirectTo Redirect path when user is already authenticated.
     */
    public function __construct(AuthManagerContract $auth, string $redirectTo)
    {
        $this->auth = $auth;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Handle an incoming request.
     *
     * If the user is already authenticated, redirect them to the configured
     * "home" route (defined in config/auth.php under 'redirect.home').
     * Otherwise, continue to the next middleware or request handler.
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        if ($this->auth->check()) {
            return response()->redirect($this->redirectTo);
        }

        return $next($request);
    }
}
