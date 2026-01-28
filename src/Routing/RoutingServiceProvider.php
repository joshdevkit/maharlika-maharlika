<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Http\RouterInterface;
use Maharlika\Providers\ServiceProvider;
use Maharlika\Routing\Router;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind RouterInterface to Router singleton
        $this->app->singleton(RouterInterface::class, function ($app) {
            // Load route configuration
            $config = require config_path('routing.php');
            
            // Create router instance
            $router = new Router($app);
            
            // Add controller namespaces from config
            foreach ($config['namespaces'] as $namespace => $directory) {
                $router->addControllerNamespace($namespace, $directory);
            }
            
            return $router;
        });
        
        // Also bind concrete class
        $this->app->singleton(Router::class, function ($app) {
            return $app->make(RouterInterface::class);
        });
    }
    
    public function boot(): void
    {
        $config = require config_path('routing.php');
        $router = $this->app->make(RouterInterface::class);
        
        // Try to load from cache first (if enabled and cache exists)
        $cacheEnabled = $config['cache']['enabled'] ?? false;
        if ($cacheEnabled && $router->loadFromCache()) {
            // Routes loaded from cache successfully
            return;
        }
        
        // Otherwise, discover routes from controllers
        if ($config['auto_discovery']['enabled']) {
            $router->discoverRoutes();
            // Auto-cache if enabled
            if ($cacheEnabled) {
                $router->cache();
            }
        }
    }
}