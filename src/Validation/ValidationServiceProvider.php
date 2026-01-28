<?php

namespace Maharlika\Validation;

use Maharlika\Providers\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    /**
     * Register the validation services.
     *
     * @param \Maharlika\Contracts\Container\ContainerInterface $container
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ValidationFactory::class, function ($container) {
            return new ValidationFactory($container);
        });

        $this->app->singleton('validator', function ($container) {
            return $container->make(ValidationFactory::class);
        });
    }
}
