<?php

namespace Maharlika\View;

use Maharlika\Contracts\View\ViewFinderInterface;
use Maharlika\Exceptions\ViewException;

class ViewFinder implements ViewFinderInterface
{
    protected array $locations = [];
    protected array $namespaces = [];
    protected array $extensions = ['template.php', 'blade.php', 'php', 'html'];
    protected array $cache = [];

    public function __construct(array $locations = [])
    {
        $this->locations = $locations;
    }

    public function find(string $view): string
    {
        if (isset($this->cache[$view])) {
            return $this->cache[$view];
        }

        // Check for namespaced view
        if (str_contains($view, '::')) {
            return $this->cache[$view] = $this->findNamespacedView($view);
        }

        return $this->cache[$view] = $this->findInLocations($view);
    }

    protected function findNamespacedView(string $view): string
    {
        [$namespace, $view] = explode('::', $view, 2);

        if (!isset($this->namespaces[$namespace])) {
            throw new ViewException("Namespace [{$namespace}] is not registered.");
        }

        $paths = (array) $this->namespaces[$namespace];

        foreach ($paths as $path) {
            $found = $this->findInPath($view, $path);
            if ($found) {
                return $found;
            }
        }

        throw new ViewException("View [{$namespace}::{$view}] not found in registered namespace paths.");
    }

    protected function findInLocations(string $view): string
    {
        foreach ($this->locations as $location) {
            $path = $this->findInPath($view, $location);
            if ($path) {
                return $path;
            }
        }

        throw new ViewException("View [{$view}] not found.");
    }

    protected function findInPath(string $view, string $location): ?string
    {
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $view);

        foreach ($this->extensions as $extension) {
            $path = $location . DIRECTORY_SEPARATOR . $viewPath . '.' . $extension;

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function addLocation(string $location): void
    {
        $this->locations[] = rtrim($location, '/\\');
    }

    public function prependLocation(string $location): void
    {
        array_unshift($this->locations, rtrim($location, '/\\'));
    }

    public function addNamespace(string $namespace, string|array $paths): void
    {
        $paths = (array) $paths;
        
        if (isset($this->namespaces[$namespace])) {
            $existing = (array) $this->namespaces[$namespace];
            $this->namespaces[$namespace] = array_unique(array_merge($existing, $paths));
        } else {
            $this->namespaces[$namespace] = $paths;
        }
    }

    public function prependNamespace(string $namespace, string|array $paths): void
    {
        $paths = (array) $paths;
        
        if (isset($this->namespaces[$namespace])) {
            $existing = (array) $this->namespaces[$namespace];
            $this->namespaces[$namespace] = array_unique(array_merge($paths, $existing));
        } else {
            $this->namespaces[$namespace] = $paths;
        }
    }

    public function replaceNamespace(string $namespace, string|array $paths): void
    {
        $this->namespaces[$namespace] = (array) $paths;
    }

    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function addExtension(string $extension): void
    {
        if (!in_array($extension, $this->extensions, true)) {
            $this->extensions[] = $extension;
        }
    }

    public function prependExtension(string $extension): void
    {
        if (!in_array($extension, $this->extensions, true)) {
            array_unshift($this->extensions, $extension);
        }
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function exists(string $view): bool
    {
        try {
            $this->find($view);
            return true;
        } catch (ViewException $e) {
            return false;
        }
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    public function flushFinderCache(): void
    {
        $this->flush();
    }
}