<?php

namespace Maharlika\Auth;

use Maharlika\Http\Middlewares\AuthMiddleware;
use Maharlika\Http\Middlewares\GuestMiddleware;
use Maharlika\Http\Request;
use Maharlika\Providers\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Auth Manager
        $this->app->singleton('auth', function ($c) {
            $session = $c->get('session');
            $config = $c->get('config');
            $model = $config->get('auth.model', 'App\\Models\\User');

            return new AuthManager($session, $model);
        });

        // Register aliases for Auth Manager (combine into one call to avoid duplicates)
        $this->app->alias('auth', AuthManager::class);
        $this->app->alias(AuthManager::class, \Maharlika\Contracts\Auth\AuthManagerContract::class);

        // Register Auth Middleware
        $this->app->singleton('middleware.auth', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.login', '/login');

            return new AuthMiddleware($auth, $redirectTo);
        });

        // Register alias for Auth Middleware (single alias only)
        $this->app->alias('middleware.auth', AuthMiddleware::class);

        // Register Guest Middleware
        $this->app->singleton('middleware.guest', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.home', '/dashboard');

            return new GuestMiddleware($auth, $redirectTo);
        });

        // Register alias for Guest Middleware (single alias only)
        $this->app->alias('middleware.guest', GuestMiddleware::class);

        // Register Password Broker
        $this->app->singleton('password.broker', function ($app) {
            return new \Maharlika\Auth\Passwords\PasswordBroker();
        });
    }

    public function boot(): void
    {
        Request::setResolver(function (?string $guard = null) {
            $auth = app('auth');
            if ($guard !== null && method_exists($auth, 'guard')) {
                return $auth->guard($guard)->user();
            }
            return $auth->user();
        });
    }
}