<?php

namespace Maharlika\Providers;

use Maharlika\Http\Outbound\Client;

class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('http', function ($app) {
            return new Client();
        });
    }
}