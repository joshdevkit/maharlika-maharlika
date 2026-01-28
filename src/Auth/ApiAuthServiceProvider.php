<?php

namespace Maharlika\Auth;

use Maharlika\Http\Middlewares\ApiTokenMiddleware;
use Maharlika\Providers\ServiceProvider;

class ApiAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register API Token Guard
        $this->app->singleton('auth.api', function ($c) {
            $request = $c->get('request');
            $config = $c->get('config');
            $model = $config->get('auth.model', 'App\\Models\\User');

            return new ApiTokenGuard($request, $model);
        });

        // Register alias
        $this->app->alias('auth.api', ApiTokenGuard::class);

        // Register API Token Middleware
        $this->app->singleton('api.token', function ($c) {
            $guard = $c->get('auth.api');
            return new ApiTokenMiddleware($guard);
        });

        // Register alias
        $this->app->alias('api.token', ApiTokenMiddleware::class);
    }

    public function boot(): void
    {
        // You can add any boot logic here if needed
    }
}