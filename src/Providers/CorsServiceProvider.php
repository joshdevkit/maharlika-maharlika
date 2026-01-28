<?php

namespace Maharlika\Providers;

use Maharlika\Http\Middlewares\CorsMiddleware;
use Maharlika\Providers\ServiceProvider;

class CorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register CORS middleware
        $this->app->singleton('middleware.cors', function ($c) {
            $config = $c->get('config')->get('cors', []);
            return new CorsMiddleware($config);
        });

        // Register alias
        $this->app->alias('middleware.cors', CorsMiddleware::class);
    }

    public function boot(): void
    {
        // CORS middleware is typically added globally or per route
    }
}
