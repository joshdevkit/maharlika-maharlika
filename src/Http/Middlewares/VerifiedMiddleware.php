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

        if (!$user) {
            return new RedirectResponse('/login');
        }

        if (!$user instanceof MustVerifyEmail) {
            return $next($request);
        }

        if (!$user->hasVerifiedEmail()) {
            return new RedirectResponse('/email/verify');
        }

        return $next($request);
    }
}