<?php

namespace Maharlika\Auth;

use Maharlika\Auth\Access\Gate as AccessGate;
use Maharlika\Contracts\Auth\Access\Gate;
use Maharlika\Providers\ServiceProvider;

class GateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('gate', function ($c) {
            return new AccessGate($c, function () use ($c) {
                return $c->get('auth')->user();
            });
        });

        // Alias both interface and concrete class to 'gate'
        $this->app->alias('gate', Gate::class);
        $this->app->alias('gate', AccessGate::class);
    }

    public function boot(): void
    {
        //
    }
}
