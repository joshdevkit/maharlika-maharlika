<?php

namespace Maharlika\Routing;

use Maharlika\Http\JsonResponse;

class FrameworkRoutes
{
    private Router $router;
    private array $config;

    public function __construct(Router $router, array $config = [])
    {
        $this->router = $router;
        $this->config = $config;
    }

    /**
     * Register all framework routes
     */
    public function register(): void
    {
        // Only register if enabled in config (enabled by default)
        $enabled = $this->config['framework_routes']['enabled'] ?? true;

        if (!$enabled) {
            return;
        }

        $this->registerHealthCheck();
        $this->registerApiStatus();
        $this->registerApiRoutes();
        $this->registerApiDocumentation();
    }

    /**
     * Register health check endpoint
     */
    private function registerHealthCheck(): void
    {
        $this->router->get('/health', function () {
            $status = [
                'status' => 'ok',
                'timestamp' => now(),
                'framework' => 'Maharlika Framework',
                'version' => \Maharlika\Framework\Application::VERSION,
                'php_version' => PHP_VERSION,
                'environment' => config('app.env', 'production'),
            ];

            // Check database connection
            try {
                \Maharlika\Database\Capsule::connection()->getPdo();
                $status['database'] = 'connected';
            } catch (\Exception $e) {
                $status['database'] = 'disconnected';
                $status['status'] = 'degraded';
            }

            // Check cache
            try {
                $cache = app('cache');
                $cache->put('health_check', true, 1);
                $status['cache'] = $cache->get('health_check') ? 'working' : 'failed';
            } catch (\Exception $e) {
                $status['cache'] = 'unavailable';
            }

            $httpStatus = $status['status'] === 'ok' ? 200 : 503;

            return new JsonResponse($status, $httpStatus);
        })->name('framework.health');
    }

    /**
     * Register API status endpoint
     */
    private function registerApiStatus(): void
    {
        $this->router->get('/api', function () {
            $router = app('router');
            $allRoutes = $router->getRoutes();

            $apiRoutes = array_filter($allRoutes, function ($route) {
                return str_starts_with($route['uri'], '/api');
            });

            return new JsonResponse([
                'message' => 'Maharlika Framework API is running',
                'version' => \Maharlika\Framework\Application::VERSION,
                'timestamp' => now(),
                'endpoints' => [
                    'health' => url('/health'),
                    'api_routes' => url('/api/routes'),
                    'api_routes_count' => count($apiRoutes),
                ],
                'documentation' => config('app.url') . '/api/documentation',
            ]);
        })->name('framework.api.status');
    }

    /**
     * Register API routes listing endpoint
     */
    private function registerApiRoutes(): void
    {
        $this->router->get('/api/routes', function () {
            $router = app('router');
            $allRoutes = $router->getRoutes();

            $apiRoutes = array_filter($allRoutes, function ($route) {
                return str_starts_with($route['uri'], '/api') 
                    && $route['uri'] !== '/api' 
                    && $route['uri'] !== '/api/routes';
            });

            $formatted = array_map(function ($route) {
                return [
                    'method' => $route['method'],
                    'uri' => $route['uri'],
                    'name' => $route['name'] ?? null,
                ];
            }, $apiRoutes);

            return new JsonResponse([
                'routes' => array_values($formatted),
                'total' => count($formatted),
            ]);
        })->name('framework.api.routes');
    }

    /**
     * Register API documentation endpoint
     */
    private function registerApiDocumentation(): void
    {
        $this->router->get('/api/documentation', function () {
            $router = app('router');
            $allRoutes = $router->getRoutes();

            // Filter API routes (exclude framework routes)
            $apiRoutes = array_filter($allRoutes, function ($route) {
                return str_starts_with($route['uri'], '/api')
                    && $route['uri'] !== '/api'
                    && $route['uri'] !== '/api/routes'
                    && $route['uri'] !== '/api/documentation';
            });

            // Group routes by prefix/resource
            $groupedRoutes = [];
            foreach ($apiRoutes as $route) {
                // Extract resource name from URI (e.g., /api/users -> users)
                $parts = explode('/', trim($route['uri'], '/'));
                $resource = $parts[1] ?? 'default';

                if (!isset($groupedRoutes[$resource])) {
                    $groupedRoutes[$resource] = [];
                }

                $groupedRoutes[$resource][] = $route;
            }

            return view('framework::api-documentation', [
                'routes' => $apiRoutes,
                'groupedRoutes' => $groupedRoutes,
                'totalRoutes' => count($apiRoutes),
            ]);
        })->name('framework.api.documentation');
    }
}