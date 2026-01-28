<?php

namespace Maharlika\Routing;

class RouteDiscovery
{
    private Router $router;
    private array $controllerNamespaces = [];
    private bool $discovered = false;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function addControllerNamespace(string $namespace, string $directory): void
    {
        $this->controllerNamespaces[] = [
            'namespace' => rtrim($namespace, '\\'),
            'directory' => rtrim($directory, '/'),
        ];
    }

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        foreach ($this->controllerNamespaces as $config) {
            $this->scanDirectory($config['directory'], $config['namespace']);
        }

        $this->discovered = true;
    }

    public function isDiscovered(): bool
    {
        return $this->discovered || empty($this->controllerNamespaces);
    }

    /**
     * Cache the discovered routes to a file
     */
    public function cache(): bool
    {
        // Ensure routes are discovered first
        if (!$this->discovered) {
            $this->discover();
        }

        $cachePath = $this->getCachePath();
        $cacheDir = dirname($cachePath);

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                return false;
            }
        }

        $routes = $this->router->getRoutes();
        $authRoutes = $this->router->getAuthRoutes();

        $cacheContent = "<?php\n\nreturn " . var_export([
            'routes' => $routes,
            'auth_routes' => $authRoutes,
            'cached_at' => date('Y-m-d H:i:s'),
        ], true) . ";\n";

        return file_put_contents($cachePath, $cacheContent, LOCK_EX) !== false;
    }

    /**
     * Load routes from cache
     */
    public function loadFromCache(): bool
    {
        $cachePath = $this->getCachePath();

        if (!file_exists($cachePath)) {
            return false;
        }

        try {
            $cached = require $cachePath;

            if (!is_array($cached) || empty($cached['routes'])) {
                return false;
            }

            // Restore routes to router
            foreach ($cached['routes'] as $route) {
                $this->router->addRoute(
                    $route['method'],
                    $route['uri'],
                    $route['action']
                );

                // Restore named route if it exists
                if (isset($route['name']) && $route['name'] !== null) {
                    $this->router->name($route['name']);
                }
            }

            // Restore auth routes if they exist
            if (!empty($cached['auth_routes'])) {
                foreach ($cached['auth_routes'] as $type => $path) {
                    $this->router->setAuthRoute($type, $path);
                }
            }

            $this->discovered = true;
            return true;
        } catch (\Throwable $e) {
            @unlink($cachePath);
            return false;
        }
    }

    /**
     * Clear the route cache
     */
    public function clearCache(): bool
    {
        $cachePath = $this->getCachePath();
        
        if (file_exists($cachePath)) {
            return unlink($cachePath);
        }
        
        return true;
    }

    /**
     * Get the cache file path
     */
    private function getCachePath(): string
    {
        return storage_path('framework/cache/routes.php');
    }

    private function scanDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->registerControllerFromFile($file->getPathname(), $namespace, $directory);
            }
        }
    }

    private function registerControllerFromFile(string $filepath, string $baseNamespace, string $baseDirectory): void
    {
        $relativePath = str_replace($baseDirectory, '', $filepath);
        $relativePath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $controllerClass = $baseNamespace . $relativePath;

        if (class_exists($controllerClass)) {
            $this->router->registerController($controllerClass);
        }
    }
}