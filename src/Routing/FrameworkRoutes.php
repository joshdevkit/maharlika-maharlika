<?php

namespace Maharlika\Routing;

use Maharlika\Http\JsonResponse;
use Maharlika\Http\Request;

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
        $this->registerIgnitionRoutes();
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

    /**
     * Register Ignition-related routes
     */
    private function registerIgnitionRoutes(): void
    {
        // Only register in local/development environment
        if (!app()->isLocal() && !app()->hasDebugModeEnabled()) {
            return;
        }

        $this->router->post('/_ignition/update-config', function (Request $request) {
            try {
                // Get the base path (project root)
                $basePath = app()->basePath();
                $configPath = $basePath . '/ignition.json';

                // Get the configuration data from request
                $config = $request->all();

                // Validate that we have data
                if (empty($config)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'No configuration data provided',
                    ], 400);
                }

                // Save the configuration to project root
                $jsonContent = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
                if ($jsonContent === false) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Failed to encode configuration data',
                    ], 500);
                }

                $result = file_put_contents($configPath, $jsonContent);

                if ($result === false) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Failed to write configuration file',
                        'path' => $configPath,
                    ], 500);
                }

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Configuration saved successfully',
                    'path' => $configPath,
                    'size' => $result,
                ]);

            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Error saving configuration: ' . $e->getMessage(),
                    'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
                ], 500);
            }
        })->name('ignition.update-config');

        // Optional: Add a GET endpoint to retrieve current config
        $this->router->get('/_ignition/config', function () {
            try {
                $basePath = app()->basePath();
                $configPath = $basePath . '/ignition.json';

                if (!file_exists($configPath)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Configuration file not found',
                        'path' => $configPath,
                    ], 404);
                }

                $config = json_decode(file_get_contents($configPath), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Invalid JSON in configuration file',
                        'error' => json_last_error_msg(),
                    ], 500);
                }

                return new JsonResponse([
                    'success' => true,
                    'config' => $config,
                    'path' => $configPath,
                ]);

            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Error reading configuration: ' . $e->getMessage(),
                ], 500);
            }
        })->name('ignition.get-config');
    }
}