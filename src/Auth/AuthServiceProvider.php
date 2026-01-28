<?php

namespace Maharlika\Auth;

use Maharlika\Contracts\Auth\AuthManagerContract;
use Maharlika\Http\Middlewares\AuthMiddleware;
use Maharlika\Http\Middlewares\GuestMiddleware;
use Maharlika\Http\Request;
use Maharlika\Providers\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     * 
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    public function register(): void
    {
        // Register Auth Manager
        $this->app->singleton('auth', function ($c) {
            $session = $c->get('session');
            $config = $c->get('config');
            $model = $config->get('auth.model', 'App\\Models\\User');

            return new AuthManager($session, $model);
        });

        // Register aliases for Auth Manager
        $this->app->alias('auth', AuthManager::class);
        $this->app->alias(AuthManager::class, AuthManagerContract::class);

        // Register Auth Middleware
        $this->app->singleton('middleware.auth', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.login', '/auth/login');

            return new AuthMiddleware($auth, $redirectTo);
        });

        $this->app->alias('middleware.auth', AuthMiddleware::class);

        // Register Guest Middleware
        $this->app->singleton('middleware.guest', function ($c) {
            $auth = $c->get('auth');
            $config = $c->get('config');
            $redirectTo = $config->get('auth.redirect.home', '/dashboard');

            return new GuestMiddleware($auth, $redirectTo);
        });

        $this->app->alias('middleware.guest', GuestMiddleware::class);

        // Register Password Broker
        $this->app->singleton('password.broker', function ($app) {
            return new \Maharlika\Auth\Passwords\PasswordBroker();
        });

        // Register Gate Service Provider
        $this->app->register(GateServiceProvider::class);
    }

    public function boot(): void
    {
        Request::setResolver(function (?string $guard = null) {
            $auth = app(AuthManagerContract::class);
            if ($guard !== null && method_exists($auth, 'guard')) {
                return $auth->guard($guard)->user();
            }
            return $auth->user();
        });

        // Register manually defined policies
        $this->registerPolicies();
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        $gate = $this->app->get('gate');

        foreach ($this->policies() as $model => $policy) {
            $gate->policy($model, $policy);
        }
    }

    /**
     * Get the policies defined in this provider.
     * Override this method to manually register policies.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return $this->policies;
    }
}