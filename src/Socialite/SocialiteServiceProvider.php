<?php

namespace Maharlika\Socialite;

use Maharlika\Providers\ServiceProvider;
use Maharlika\Contracts\Session\SessionInterface;

class SocialiteServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('socialite', function ($app) {
            $session = $app->make(SessionInterface::class);
            $config = $app->make('config')->get('services', []);

            return new SocialiteManager($session, $config);
        });

        $this->app->singleton(SocialiteManager::class, function ($app) {
            return $app->make('socialite');
        });
    }

    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration if needed
        // $this->publishes([
        //     __DIR__ . '/../../config/services.php' => config_path('services.php'),
        // ], 'config');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['socialite', SocialiteManager::class];
    }
}