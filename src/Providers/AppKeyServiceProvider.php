<?php

declare(strict_types=1);

namespace Maharlika\Providers;

use Maharlika\Contracts\ServiceProviderInterface;
use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Framework\AppKeyValidator;

class AppKeyServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        $this->container->singleton('app.key.validator', function ($c) {
            return new AppKeyValidator($c->get('config'));
        });
    }

    public function boot(): void
    {
        // Don't validate here - let middleware handle it
        // This allows the exception handler to catch validation errors
    }
}
