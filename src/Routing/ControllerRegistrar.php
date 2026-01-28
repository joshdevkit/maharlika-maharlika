<?php

namespace Maharlika\Routing;

use Maharlika\Routing\Attributes\ApiRoute;
use Maharlika\Routing\Attributes\Name;
use Maharlika\Routing\Attributes\AuthRoute;

class ControllerRegistrar
{
    private Router $router;
    private RouteAttributeResolver $attributeResolver;
    private ConventionRouter $conventionRouter;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->attributeResolver = new RouteAttributeResolver();
        $this->conventionRouter = new ConventionRouter();
    }

    /**
     * Register all routes from a controller class
     */
    public function register(string $controllerClass): void
    {
        if (!class_exists($controllerClass)) {
            return;
        }

        $reflection = new \ReflectionClass($controllerClass);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        $classPrefix = $this->getClassPrefix($reflection);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            
            // Skip magic methods EXCEPT __invoke
            if (str_starts_with($methodName, '__') && $methodName !== '__invoke') {
                continue;
            }

            $this->registerMethodRoutes($controllerClass, $method, $classPrefix);
        }
    }

    /**
     * Get API prefix from class-level ApiRoute attribute
     */
    private function getClassPrefix(\ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(ApiRoute::class);

        if (empty($attributes)) {
            return '';
        }

        $instance = $attributes[0]->newInstance();
        return $instance->prefix;
    }

    /**
     * Register routes for a specific method
     */
    private function registerMethodRoutes(
        string $controller,
        \ReflectionMethod $method,
        string $classPrefix = ''
    ): void {
        $routeAttributes = $this->attributeResolver->resolveFromMethod($method);

        if (!empty($routeAttributes)) {
            // Has route attributes - use explicit routing
            foreach ($routeAttributes as $routeData) {
                $fullPath = $this->applyPrefix($routeData['path'], $classPrefix);
                
                // Check for AuthRoute attribute
                $authAttr = $method->getAttributes(AuthRoute::class);
                if (!empty($authAttr)) {
                    $instance = $authAttr[0]->newInstance();
                    $this->router->setAuthRoute($instance->type, $fullPath);
                }

                // Add the route
                $route = $this->router->addRoute(
                    $routeData['method'],
                    $fullPath,
                    [$controller, $method->getName()]
                );

                // Check for Name attribute and register named route
                $nameAttr = $method->getAttributes(Name::class);
                if (!empty($nameAttr)) {
                    $nameInstance = $nameAttr[0]->newInstance();
                    $route->name($nameInstance->name);
                }
            }
        } elseif ($this->shouldAutoRoute($method)) {
            // No attributes - use convention-based routing
            $routeData = $this->conventionRouter->generateRoute($controller, $method, $classPrefix);
            
            // Check for AuthRoute attribute (can exist without route attributes)
            $authAttr = $method->getAttributes(AuthRoute::class);
            if (!empty($authAttr)) {
                $instance = $authAttr[0]->newInstance();
                $this->router->setAuthRoute($instance->type, $routeData['path']);
            }

            // Add the route
            $route = $this->router->addRoute(
                $routeData['method'], 
                $routeData['path'], 
                $routeData['action']
            );

            // Check for Name attribute (can exist without route attributes)
            $nameAttr = $method->getAttributes(Name::class);
            if (!empty($nameAttr)) {
                $nameInstance = $nameAttr[0]->newInstance();
                $route->name($nameInstance->name);
            }
        }
    }

    /**
     * Apply prefix to path
     */
    private function applyPrefix(string $path, string $prefix): string
    {
        if (empty($prefix) || $prefix === '/') {
            return $path;
        }

        $prefix = '/' . trim($prefix, '/');
        $path = '/' . trim($path, '/');

        if (str_starts_with($path, $prefix)) {
            return $path;
        }

        return $prefix . $path;
    }

    /**
     * Determine if a method should be auto-routed
     */
    private function shouldAutoRoute(\ReflectionMethod $method): bool
    {
        $declaringClass = $method->getDeclaringClass()->getName();
        
        // Don't auto-route if it's a base Controller class
        return !str_ends_with($declaringClass, '\\Controller');
    }
}