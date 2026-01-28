<?php

declare(strict_types=1);

namespace Maharlika\Http\Middlewares;

use Closure;
use Maharlika\Contracts\Http\Middleware;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\RedirectResponse;

class RedirectIfVerified implements Middleware
{
    public string $redirectTo = '/';

    public function handle(
        RequestInterface $request,
        Closure $next
    ): ResponseInterface {
        $user = $request->user();

        if ($user !== null && $user->email_verified_at !== null) {
            return new RedirectResponse($this->redirectTo);
        }

        return $next($request);
    }
}
