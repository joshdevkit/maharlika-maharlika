<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\ServiceProviderInterface;
use Maharlika\Log\LogManager;

class LogServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        $this->container->singleton('log', function ($c) {
            return new LogManager($c);
        });

        $this->container->alias('log', LogManager::class);
    }

    public function boot(): void
    {
        //
    }
}