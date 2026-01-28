<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\RouterInterface;
use Maharlika\Contracts\Container\ContainerInterface;

class Router implements RouterInterface
{
    private array $routes = [];
    private array $namedRoutes = [];
    private ContainerInterface $container;
    private ?RequestInterface $currentRequest = null;
    private ?string $currentRouteName = null;
    private RouteDiscovery $routeDiscovery;
    private RouteDispatcher $routeDispatcher;
    private RouteMatcher $routeMatcher;
    private array $authRoutes = [];
    private array $config = [];
    private bool $frameworkRoutesRegistered = false;

    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->container = $container;
        $this->config = $config;
        $this->routeDiscovery = new RouteDiscovery($this);
        $this->routeDispatcher = new RouteDispatcher($container);
        $this->routeMatcher = new RouteMatcher();

        // Register framework routes
        $this->registerFrameworkRoutes();
    }

    /**
     * Register framework-level routes
     */
    private function registerFrameworkRoutes(): void
    {
        if ($this->frameworkRoutesRegistered) {
            return;
        }

        $frameworkRoutes = new FrameworkRoutes($this, $this->config);
        $frameworkRoutes->register();

        $this->frameworkRoutesRegistered = true;
    }

    public function addControllerNamespace(string $namespace, string $directory): self
    {
        $this->routeDiscovery->addControllerNamespace($namespace, $directory);
        return $this;
    }

    public function discoverRoutes(): self
    {
        $this->routeDiscovery->discover();
        return $this;
    }

    /**
     * Cache the routes
     */
    public function cache(): bool
    {
        return $this->routeDiscovery->cache();
    }

    /**
     * Load routes from cache
     */
    public function loadFromCache(): bool
    {
        if (!empty($this->config) && !($this->config['cache']['enabled'] ?? false)) {
            return false;
        }

        return $this->routeDiscovery->loadFromCache();
    }

    /**
     * Clear the route cache
     */
    public function clearCache(): bool
    {
        if (!$this->routeDiscovery->clearCache()) {
            return false;
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $cachePath = storage_path('framework/cache/routes.php');
        if (function_exists('opcache_invalidate') && file_exists($cachePath)) {
            opcache_invalidate($cachePath, true);
        }

        return true;
    }

    public function registerController(string $controllerClass): self
    {
        $registrar = new ControllerRegistrar($this);
        $registrar->register($controllerClass);
        return $this;
    }

    public function get(string $uri, mixed $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, mixed $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function patch(string $uri, mixed $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function addRoute(string $method, string $uri, mixed $action): self
    {
        $uri = '/' . trim($uri, '/');

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'action' => $action,
            'name' => null,
        ];

        return $this;
    }

    public function name(string $name): self
    {
        if (!empty($this->routes)) {
            $index = count($this->routes) - 1;
            $this->routes[$index]['name'] = $name;
            $this->namedRoutes[$name] = $index;
        }
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function getByName(string $name): ?array
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        return $this->routes[$this->namedRoutes[$name]] ?? null;
    }

    public function route(string $name, array $params = []): string
    {
        if (!$this->routeDiscovery->isDiscovered()) {
            $this->routeDiscovery->discover();
        }

        $route = $this->getByName($name);

        if (!$route) {
            throw new \InvalidArgumentException("Route [{$name}] not found.");
        }

        $uri = $route['uri'];

        // Replace route parameters
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $uri, $matches);
        $placeholders = $matches[1] ?? [];

        foreach ($placeholders as $key) {
            if (!array_key_exists($key, $params)) {
                throw new \InvalidArgumentException(
                    "Missing required route parameter [{$key}] for route [{$name}]."
                );
            }

            $value = $params[$key];

            // Extract primary key from model instances
            $value = $this->extractRouteKey($value);

            $uri = str_replace('{' . $key . '}', rawurlencode((string) $value), $uri);
            unset($params[$key]);
        }

        // Add remaining params as query string
        if (!empty($params)) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($params);
        }

        return $uri;
    }

    /**
     * Extract the route key from a value (handles model instances)
     */
    protected function extractRouteKey(mixed $value): mixed
    {
        // If it's already a scalar value, return it
        if (is_scalar($value)) {
            return $value;
        }

        // If it's an object, try to get the primary key
        if (is_object($value)) {
            // Try getKey()
            if (method_exists($value, 'getKey')) {
                return $value->getKey();
            }

            // Try getRouteKey() method
            if (method_exists($value, 'getRouteKey')) {
                return $value->getRouteKey();
            }

            // Try getPrimaryKey() and get the value
            if (method_exists($value, 'getPrimaryKey')) {
                $primaryKey = $value->getPrimaryKey();
                if (isset($value->$primaryKey)) {
                    return $value->$primaryKey;
                }
            }

            // Try common primary key property names
            foreach (['id', 'uuid', '_id'] as $keyName) {
                if (isset($value->$keyName)) {
                    return $value->$keyName;
                }
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
        }

        // Fallback: convert to string
        return (string) $value;
    }

    /**
     * Check if the current route matches the given name(s)
     * 
     * @param string|array $names Route name(s) to check (supports wildcards)
     * @return bool
     */
    public function routeIs(string|array $names): bool
    {
        if ($this->currentRouteName === null) {
            return false;
        }

        $names = is_array($names) ? $names : [$names];

        foreach ($names as $name) {
            // Convert wildcard pattern to regex
            $pattern = str_replace('\*', '.*', preg_quote($name, '/'));

            if (preg_match('/^' . $pattern . '$/', $this->currentRouteName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current route name
     * 
     * @return string|null
     */
    public function currentRouteName(): ?string
    {
        return $this->currentRouteName;
    }

    /**
     * Set the current route name (called during dispatch)
     * 
     * @param string|null $name
     * @return void
     */
    public function setCurrentRouteName(?string $name): void
    {
        $this->currentRouteName = $name;
    }

    public function dispatch(RequestInterface $request): ResponseInterface
    {
        if (!$this->routeDiscovery->isDiscovered()) {
            $this->routeDiscovery->discover();
        }

        if ($request->method() === 'POST' && $request->input('_method')) {
            $request->setMethod(strtoupper($request->input('_method')));
        }

        $this->currentRequest = $request;
        $this->container->instance(RequestInterface::class, $request);

        $route = $this->routeMatcher->match(
            $request->method(),
            $request->getPath(),
            $this->routes
        );

        // Set current route name if available
        $this->currentRouteName = $route['name'] ?? null;

        return $this->routeDispatcher->dispatch($route, $request);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    public function setAuthRoute(string $type, string $path): void
    {
        $this->authRoutes[$type] = $path;
    }

    public function authRoute(string $type = 'login'): ?string
    {
        return $this->authRoutes[$type] ?? null;
    }

    /**
     * Get all auth routes
     */
    public function getAuthRoutes(): array
    {
        return $this->authRoutes;
    }
}
