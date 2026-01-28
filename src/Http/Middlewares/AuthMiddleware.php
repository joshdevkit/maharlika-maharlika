<?php

namespace Maharlika\Http\Middlewares;

use Closure;
use Maharlika\Auth\AuthManager;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;

/**
 * ---------------------------------------------------------------
 * Middleware: AuthMiddleware
 * ---------------------------------------------------------------
 *
 * This middleware ensures that a user is authenticated before
 * accessing protected routes. If the user is not authenticated,
 * they are redirected to the login page.
 *
 * @package Maharlika\Auth
 */
class AuthMiddleware implements Middleware
{
    protected AuthManager $auth;
    protected string $redirectTo;

    /**
     * Create a new AuthMiddleware instance.
     *
     * @param  AuthManager  $auth
     * @param  string  $redirectTo
     */
    public function __construct(AuthManager $auth, string $redirectTo = '/login')
    {
        $this->auth = $auth;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Handle an incoming request.
     *
     * @param  RequestInterface  $request
     * @param  Closure  $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        if ($this->auth->guest()) {

            if ($request->isMethod('get')) {
                // Save the current requested URL
                session()->put('url.intended', $request->getUri());

                // Save the previous URL if available
                $previous = $request->getHeader('referer') ?? null;

                if ($previous) {
                    session()->put('url.previous', $previous);
                }
            }

            return redirect(app('router')->authRoute('login') ?? $this->redirectTo);
        }

        return $next($request);
    }
}
