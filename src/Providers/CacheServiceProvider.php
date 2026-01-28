<?php

namespace Maharlika\Providers;

use Maharlika\Cache\CacheManager;
use Maharlika\Contracts\Cache\CacheInterface;

class CacheServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
    /**
     * Register the cache services.
     *
     * @param \Maharlika\Contracts\Container\ContainerInterface $container
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, function ($container) {
            $config = require base_path('config/cache.php');
            return new CacheManager($container, $config);
        });

        $this->app->singleton('cache', function ($container) {
            return $container->make(CacheManager::class);
        });

        $this->app->singleton(CacheInterface::class, function ($container) {
            return $container->make(CacheManager::class)->store();
        });
    }
}