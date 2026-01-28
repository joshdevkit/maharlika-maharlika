<?php

namespace Maharlika\Routing;

use Maharlika\Routing\Attributes\Authenticated;
use Maharlika\Routing\Attributes\Guest;
use Maharlika\Routing\Attributes\Middleware;
use Maharlika\Routing\Attributes\Throttle;
use Maharlika\Routing\Attributes\Verified;
use Maharlika\Routing\Attributes\OnceVerified;
use ReflectionClass;
use ReflectionMethod;

class MiddlewareResolver
{
    /**
     * Built-in middleware aliases
     */
    private array $aliases = [
        'auth' => \Maharlika\Http\Middlewares\AuthMiddleware::class,
        'guest' => \Maharlika\Http\Middlewares\GuestMiddleware::class,
        'verified' => \Maharlika\Http\Middlewares\VerifiedMiddleware::class,
        'throttle' => \Maharlika\Http\Middlewares\RateLimitMiddleware::class
    ];

    /**
     * Attribute to middleware class mapping
     */
    private array $attributeMap = [
        Authenticated::class => \Maharlika\Http\Middlewares\AuthMiddleware::class,
        Guest::class => \Maharlika\Http\Middlewares\GuestMiddleware::class,
        Verified::class => \Maharlika\Http\Middlewares\VerifiedMiddleware::class,
        Throttle::class => \Maharlika\Http\Middlewares\RateLimitMiddleware::class,
        OnceVerified::class => \Maharlika\Http\Middlewares\RedirectIfVerified::class,
    ];

    public function resolve(string $controllerClass, string $method): array
    {
        $middlewares = [];

        // 1. Get middleware from constructor (if controller uses HasMiddleware trait)
        $constructorMiddlewares = $this->resolveFromConstructor($controllerClass, $method);
        $middlewares = array_merge($middlewares, $constructorMiddlewares);

        // 2. Get middleware from attributes (class-level and method-level)
        $attributeMiddlewares = $this->resolveFromAttributes($controllerClass, $method);
        $middlewares = array_merge($middlewares, $attributeMiddlewares);

        // Remove duplicates and return flat array of middleware class names
        return array_values(array_unique($middlewares, SORT_REGULAR));
    }

    /**
     * Resolve middleware from controller constructor
     */
    private function resolveFromConstructor(string $controllerClass, string $method): array
    {
        $middlewares = [];

        try {
            // Use the DI container to properly resolve all dependencies
            $controller = app()->make($controllerClass);

            // Get registered middleware (all controllers use ConstructMiddlewareIfAvailable trait)
            $registeredMiddleware = $controller->getMiddleware();

            foreach ($registeredMiddleware as $item) {
                $middlewareName = $item['middleware'];
                $options = $item['options'];

                // Check if middleware should apply to this method
                if (!$this->shouldApplyMiddleware($method, $options)) {
                    continue;
                }

                // Resolve middleware with parameters if provided
                if (isset($options['parameters'])) {
                    $resolved = $this->resolveMiddleware($middlewareName);

                    if (is_string($resolved)) {
                        $middlewares[] = [$resolved, 'handle', $options['parameters']];
                    } else {
                        $middlewares[] = $resolved;
                    }
                } else {
                    $middlewares[] = $this->resolveMiddleware($middlewareName);
                }
            }
        } catch (\Exception $e) {
            // Controller instantiation failed, return empty array to fail gracefully
        }

        return $middlewares;
    }

    /**
     * Check if middleware should apply to the given method
     */
    private function shouldApplyMiddleware(string $method, array $options): bool
    {
        // Check 'only' option
        if (isset($options['only'])) {
            return in_array($method, $options['only']);
        }

        // Check 'except' option
        if (isset($options['except'])) {
            return !in_array($method, $options['except']);
        }

        // Apply to all methods by default
        return true;
    }

    /**
     * Resolve middleware from PHP attributes
     */
    private function resolveFromAttributes(string $controllerClass, string $method): array
    {
        $middlewares = [];

        try {
            $reflectionClass = new ReflectionClass($controllerClass);

            // Get class-level attributes
            $classMiddlewares = $this->getAttributeMiddlewares($reflectionClass);
            $middlewares = array_merge($middlewares, $classMiddlewares);

            // Get method-level attributes
            if ($reflectionClass->hasMethod($method)) {
                $reflectionMethod = $reflectionClass->getMethod($method);
                $methodMiddlewares = $this->getAttributeMiddlewares($reflectionMethod);
                $middlewares = array_merge($middlewares, $methodMiddlewares);
            }
        } catch (\ReflectionException $e) {
            // Controller class not found, skip attribute resolution
        }

        return $middlewares;
    }

    /**
     * Extract middleware from reflection attributes
     */
    private function getAttributeMiddlewares(ReflectionClass|ReflectionMethod $reflection): array
    {
        $middlewares = [];
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            $instance = $attribute->newInstance();

            // Handle generic Middleware attribute
            if ($attributeName === Middleware::class) {
                foreach ($instance->getMiddlewares() as $middleware) {
                    $middlewares[] = $this->resolveMiddleware($middleware);
                }
                continue;
            }

            // Handle Throttle attribute with parameters
            if ($attributeName === Throttle::class) {
                $middlewares[] = [
                    $this->attributeMap[$attributeName],
                    'handle',
                    [
                        'maxAttempts' => $instance->maxAttempts,
                        'decayMinutes' => $instance->decayMinutes
                    ]
                ];
                continue;
            }

            // Handle OnceVerified attribute with redirectTo parameter
            if ($attributeName === OnceVerified::class) {
                $middlewares[] = [
                    $this->attributeMap[$attributeName],
                    'handle',
                    [
                        'redirectTo' => $instance->redirectTo
                    ]
                ];
                continue;
            }

            // Handle shorthand attributes (Auth, Guest, Verified)
            if (isset($this->attributeMap[$attributeName])) {
                $middlewares[] = $this->attributeMap[$attributeName];
            }
        }

        return $middlewares;
    }

    /**
     * Resolve middleware alias or [Class, method] format to proper format
     * 
     * @param string|array $middleware Either an alias (e.g., 'auth'), full class name, or [Class, method]
     * @return string|array The resolved middleware
     */
    private function resolveMiddleware(string|array $middleware): string|array
    {
        // Handle array format [Class::class, 'method']
        if (is_array($middleware)) {
            return $middleware;
        }

        // Parse middleware with parameters (e.g., 'throttle:6,1')
        if (str_contains($middleware, ':')) {
            return $this->parseMiddlewareWithParameters($middleware);
        }

        // If it's an alias, resolve it
        if (isset($this->aliases[$middleware])) {
            return $this->aliases[$middleware];
        }

        // Otherwise, assume it's already a full class name
        return $middleware;
    }

    /**
     * Parse middleware string with parameters
     * 
     * @param string $middleware e.g., 'throttle:6,1' or 'throttle:10'
     * @return array [MiddlewareClass, 'handle', [parameters]]
     */
    private function parseMiddlewareWithParameters(string $middleware): array
    {
        // Split middleware name and parameters
        [$name, $parametersString] = explode(':', $middleware, 2);

        // Resolve the middleware class
        $class = $this->aliases[$name] ?? $name;

        // Parse parameters
        $parameters = array_map('trim', explode(',', $parametersString));

        // Handle specific middleware parameter formats
        if ($name === 'throttle') {
            // throttle:maxAttempts,decayMinutes
            // Convert decayMinutes to decaySeconds for the constructor
            $maxAttempts = (int)($parameters[0] ?? 60);
            $decayMinutes = (int)($parameters[1] ?? 1);
            
            return [
                $class,
                'handle',
                [
                    'maxAttempts' => $maxAttempts,
                    'decayMinutes' => $decayMinutes
                ]
            ];
        }

        // Generic parameter handling for other middleware
        return [$class, 'handle', $parameters];
    }

    /**
     * Register a custom middleware alias
     */
    public function alias(string $alias, string $class): self
    {
        $this->aliases[$alias] = $class;
        return $this;
    }

    /**
     * Register a custom attribute mapping
     */
    public function mapAttribute(string $attributeClass, string $middlewareClass): self
    {
        $this->attributeMap[$attributeClass] = $middlewareClass;
        return $this;
    }

    /**
     * Get all registered middleware aliases
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get all registered attribute mappings
     */
    public function getAttributeMappings(): array
    {
        return $this->attributeMap;
    }
}