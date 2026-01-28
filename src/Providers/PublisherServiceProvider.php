<?php

namespace Maharlika\Providers;

use Maharlika\Publishing\Publisher;

class PublisherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('publisher', function ($app) {
            return new Publisher($app->basePath());
        });
    }

    public function boot(): void
    {
        // Collect publishable assets from all registered providers
        $this->collectPublishableAssets();
    }

    /**
     * Collect publishable assets from all service providers.
     */
    protected function collectPublishableAssets(): void
    {   
        $providers = app()->getRegisteredProviders();
        
        $publisher = $this->app->get('publisher');

        foreach ($providers as $provider) {
            // Check if provider has a publishes method or property
            if (method_exists($provider, 'getPublishable')) {
                $publishable = $provider->getPublishable();
                
                if (!empty($publishable)) {
                    $providerClass = get_class($provider);
                    
                    foreach ($publishable as $item) {
                        $paths = $item['paths'] ?? [];
                        $groups = $item['groups'] ?? null;
                        
                        if (!empty($paths)) {
                            $publisher->register($paths, $groups, $providerClass);
                        }
                    }
                }
            }
        }
    }
}