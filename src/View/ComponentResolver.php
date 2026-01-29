<?php

declare(strict_types=1);

namespace Maharlika\View;

/**
 * Resolves component classes from multiple namespaces.
 * Supports fallback resolution from app to Maharlika components.
 */
class ComponentResolver
{
    protected array $namespaces = [];
    protected array $aliasMap = [];
    protected array $cache = [];

    public function __construct(array $namespaces = [])
    {
        $this->namespaces = $namespaces;
    }

    /**
     * Add a component alias mapping.
     */
    public function addAlias(string $alias, string $namespace): void
    {
        $this->aliasMap[$alias] = $namespace;
    }

    /**
     * Add a namespace with its base path for component discovery.
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Resolve a component name to its fully qualified class name.
     * Returns the first class that exists.
     * Supports both dot notation (mail.html.button) and :: notation (pagination::page-link)
     */
    public function resolve(string $component): ?string
    {
        // Check cache first
        if (isset($this->cache[$component])) {
            return $this->cache[$component];
        }

        // Handle :: notation (e.g., 'pagination::page-link')
        if (str_contains($component, '::')) {
            [$alias, $componentName] = explode('::', $component, 2);
            
            if (isset($this->aliasMap[$alias])) {
                $aliasNamespace = $this->aliasMap[$alias];
                // Convert component-name to ComponentName
                // Supports both 'page-link' and 'page.link' after ::
                $className = $this->componentNameToClassName($componentName);
                $fqcn = $aliasNamespace . '\\' . $className;
                
                if (class_exists($fqcn)) {
                    $this->cache[$component] = $fqcn;
                    return $fqcn;
                }
            }
        }

        // Check for alias prefix (e.g., 'mail.html.button')
        $parts = explode('.', $component);
        if (count($parts) > 1 && isset($this->aliasMap[$parts[0]])) {
            $aliasNamespace = $this->aliasMap[$parts[0]];
            array_shift($parts); // Remove the alias part
            $remainingPath = implode('\\', array_map(function ($part) {
                return implode('', array_map('ucfirst', explode('-', $part)));
            }, $parts));
            
            $fqcn = $aliasNamespace . '\\' . $remainingPath;
            
            
            if (class_exists($fqcn)) {
                $this->cache[$component] = $fqcn;
                return $fqcn;
            }
        }

        $className = $this->componentNameToClassName($component);
        

        // Try each namespace in order
        foreach ($this->namespaces as $namespace => $path) {
            $fqcn = $namespace . '\\' . $className;

            if (class_exists($fqcn)) {
                $this->cache[$component] = $fqcn;
                return $fqcn;
            }
        }


        return null;
    }

    /**
     * Convert component name (e.g., 'mail.html.layout') to class name (e.g., 'Mail\Html\Layout').
     */
    protected function componentNameToClassName(string $component): string
    {
        if (strpos($component, '.') !== false) {
            $parts = explode('.', $component);
            return implode('\\', array_map(function ($part) {
                return implode('', array_map('ucfirst', explode('-', $part)));
            }, $parts));
        }

        $parts = explode('-', $component);
        return implode('', array_map('ucfirst', $parts));
    }

    /**
     * Get all registered namespaces.
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Clear the resolution cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}