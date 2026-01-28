<?php

namespace Maharlika\JsRender;

use Maharlika\Providers\ServiceProvider;

/**
 * JsRender Service Provider
 * 
 * Registers JsRender as a singleton and sets up middleware
 * for handling JsRender requests.
 */
class JsRenderServiceProvider extends ServiceProvider
{
    /**
     * Register JsRender services
     */
    public function register(): void
    {
        $this->app->singleton('jsrender', function ($app) {
            return new MountSpaPhp();
        });

        $this->app->alias('jsrender', MountSpaPhp::class);

        // Register the middleware class in the container
        $this->app->singleton(HandleJsRenderRequests::class, function ($app) {
            return new HandleJsRenderRequests();
        });

        // Register Inertia directive helper
        $this->app->singleton('jsrender.directive', function ($app) {
            return new InertiaDirective();
        });
    }

    /**
     * Bootstrap JsRender services
     */
    public function boot(): void
    {
        // Share common data with all JsRender responses
        $this->shareCommonData();

        // Register middleware with the HTTP kernel
        $this->registerMiddleware();
    }

    /**
     * Share data that should be available on all pages
     */
    protected function shareCommonData(): void
    {
        $jsrender = $this->app->make('jsrender');

        // Share auth data - can be overridden in AppServiceProvider
        $this->shareAuthData($jsrender);

        // Share flash messages (using lazy loading)
        $jsrender->share('flash', $jsrender->lazy(function () {
            if (!function_exists('session')) {
                return [];
            }
            return [
                'success' => session()->get('success'),
                'error' => session()->get('error'),
                'warning' => session()->get('warning'),
                'info' => session()->get('info'),
            ];
        }));

        // Share validation errors (using lazy loading)
        $jsrender->share('errors', $jsrender->lazy(function () {
            if (!function_exists('session')) {
                return [];
            }

            $errors = session()->get('errors', []);
            return is_array($errors) ? $errors : [];
        }));

        // Share old input for form repopulation (using lazy loading)
        $jsrender->share('old', $jsrender->lazy(function () {
            if (!function_exists('session')) {
                return [];
            }

            return session()->get('_old_input', []);
        }));

        // Share CSRF token (using always)
        $jsrender->share('csrf', $jsrender->always(function () {
            if (function_exists('csrf_token')) {
                return csrf_token();
            }
            return null;
        }));
    }

    /**
     * Share authentication data with JsRender
     * 
     * This method can be overridden in child classes or
     * the 'auth' key can be re-shared in AppServiceProvider
     * to customize the user data structure.
     * 
     * @param MountSpaPhp $jsrender
     * @return void
     */
    protected function shareAuthData(MountSpaPhp $jsrender): void
    {
        $jsrender->share('auth', $jsrender->lazy(function () {
            return $this->resolveAuthData();
        }));
    }

    /**
     * Resolve authentication data
     * 
     * Override this method in a custom service provider or
     * re-share 'auth' in AppServiceProvider to customize.
     * 
     * @return array
     */
    protected function resolveAuthData(): array
    {
        $authData = ['user' => null];

        try {
            if (function_exists('auth') && auth()->check()) {
                $authData['user'] = auth()->user();
            }
        } catch (\Exception $e) {
            // Silently fail if auth is not available
        }

        return $authData;
    }

    /**
     * Register JsRender middleware with the HTTP kernel
     */
    protected function registerMiddleware(): void
    {
        // Add the HandleJsRenderRequests middleware to the web middleware group
        // This ensures it runs for web routes
        if ($this->app->has('kernel') || method_exists($this->app, 'addMiddleware')) {
            app()->addMiddleware(HandleJsRenderRequests::class, 50);
        }
    }
}