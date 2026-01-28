<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\ServiceProviderInterface;
use Maharlika\Storage\StorageManager;

class StorageServiceProvider implements ServiceProviderInterface
{
    protected $container;

    public function setContainer($container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        $this->container->singleton('storage', function ($c) {
            return new StorageManager(
                $c->get('config')->get('storage', []),
                storage_path()
            );
        });
    }

    public function boot(): void
    {
        //
    }
}