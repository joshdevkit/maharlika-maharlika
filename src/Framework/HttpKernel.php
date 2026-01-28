<?php

namespace Maharlika\Framework;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\Middlewares\EncryptCookies;
use Maharlika\Http\Middlewares\MiddlewareCollection;
use Maharlika\Http\Middlewares\PreventRequestsDuringMaintenance;
use Maharlika\Http\Middlewares\ValidateAppKey;
use Maharlika\Http\Middlewares\VerifyCsrfToken;

class HttpKernel
{
    protected ContainerInterface $app;
    protected MiddlewareCollection $middleware;
    protected array $middlewareAliases = [];
    protected array $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];
    protected array $routeMiddleware = [];

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
        $this->middleware = new MiddlewareCollection();
        $this->registerMiddleware();
    }

    /**
     * Register all middleware for the application
     */
    protected function registerMiddleware(): void
    {
        // Global middleware - runs on every request
        $this->registerGlobalMiddleware();

        // Middleware groups
        $this->registerMiddlewareGroups();

        // Route-specific middleware
        $this->registerRouteMiddleware();
    }

    /**
     * Register global middleware that runs on every request
     */
    protected function registerGlobalMiddleware(): void
    {
        // App key validation middleware runs first
        $this->prependMiddleware(ValidateAppKey::class);

        // Encrypt cookies for security
        $this->addMiddleware(EncryptCookies::class);

        // CSRF protection for state-changing requests
        $this->addMiddleware(VerifyCsrfToken::class);

        // Check maintenance mode last (after security checks)
        $this->addMiddleware(PreventRequestsDuringMaintenance::class);
    }

    /**
     * Register middleware groups
     */
    protected function registerMiddlewareGroups(): void
    {
        $this->middlewareGroups = [
            'web' => [
                \Maharlika\Http\Middlewares\EncryptCookies::class,
                \Maharlika\Http\Middlewares\VerifyCsrfToken::class,
            ],

            'api' => [
                // 'throttle:api',
                // \Maharlika\Http\Middlewares\ForceJsonResponse::class,
            ],
        ];
    }

    /**
     * Register route-specific middleware
     * 
     * Note: Route middleware is handled by MiddlewareResolver in the Router.
     * This method is kept for consistency and potential future route middleware groups.
     */
    protected function registerRouteMiddleware(): void
    {
        // Route-specific middleware like 'auth', 'guest', 'throttle' are handled
        // by MiddlewareResolver which reads from controller attributes.
        // This array is kept for defining middleware groups if needed.
        $this->routeMiddleware = [];
    }

    /**
     * Handle an incoming HTTP request
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        // Start with the router dispatcher as the final handler
        $handler = fn($req) => $this->app->make('router')->dispatch($req);

        // Wrap with middleware in reverse order (last added executes first)
        foreach (array_reverse($this->middleware->all()) as $middlewareClass) {
            $middleware = $this->app->make($middlewareClass);
            $handler = fn($req) => $middleware->handle($req, $handler);
        }

        try {
            return $handler($request);
        } catch (\Throwable $e) {
            return $this->app->make('exception.handler')->render($request, $e);
        }
    }

    /**
     * Add middleware to the stack
     */
    public function addMiddleware(string $middleware, int $priority = 0): self
    {
        $this->middleware->add($middleware, $priority);
        return $this;
    }

    /**
     * Prepend middleware to the beginning of the stack
     */
    public function prependMiddleware(string $middleware): self
    {
        $this->middleware->prepend($middleware);
        return $this;
    }

    /**
     * Remove middleware from the stack
     */
    public function removeMiddleware(string $middleware): self
    {
        $this->middleware->remove($middleware);
        return $this;
    }

    /**
     * Replace one middleware with another
     */
    public function replaceMiddleware(string $old, string $new): self
    {
        $this->middleware->replace($old, $new);
        return $this;
    }

    /**
     * Get all middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware->all();
    }

    /**
     * Check if middleware exists
     */
    public function hasMiddleware(string $middleware): bool
    {
        return $this->middleware->contains($middleware);
    }

    /**
     * Get middleware collection
     */
    public function getMiddlewareCollection(): MiddlewareCollection
    {
        return $this->middleware;
    }

    /**
     * Get middleware groups
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get route middleware
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Get middleware class from alias
     */
    public function getMiddlewareClassFromAlias(string $alias): string
    {
        return $this->middlewareAliases[$alias] ?? $alias;
    }

    /**
     * Merge additional middleware into the stack
     */
    public function withMiddleware(array $middleware): self
    {
        $this->middleware->merge($middleware);
        return $this;
    }
}