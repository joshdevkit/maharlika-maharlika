<?php

namespace Maharlika\Http\Middlewares;

use Maharlika\Contracts\Auth\MustVerifyEmail;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\Middleware;
use Maharlika\Http\RedirectResponse;

class VerifiedMiddleware implements Middleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $user = $request->user();

        // ✅ FIXED: If no user, redirect to login (require authentication first)
        if (!$user) {
            return new RedirectResponse('/login');
        }

        // If user model doesn't implement MustVerifyEmail, allow through
        if (!$user instanceof MustVerifyEmail) {
            return $next($request);
        }

        // If user hasn't verified email, redirect to verification page
        if (!$user->hasVerifiedEmail()) {
            return new RedirectResponse('/email/verify');
        }

        return $next($request);
    }
}