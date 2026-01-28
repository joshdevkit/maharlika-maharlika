<?php

namespace Maharlika\Events;

use Maharlika\Providers\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('events', function ($app) {
            return new Dispatcher($app);
        });

        $this->app->alias('events', Dispatcher::class);
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        //
    }
}
