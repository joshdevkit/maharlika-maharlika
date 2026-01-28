<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $app;
    
    protected array $publishable = [];
    
    protected bool $defer = false;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    public function setContainer(ContainerInterface $app): void
    {
        $this->app = $app;
    }

    public function register(): void
    {
        // 
    }

    public function boot(): void
    {
        //
    }

    /**
     * Register a view file namespace.
     *
     * @param string $path The path to the views directory
     * @param string $namespace The namespace for the views
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        if (!$this->app->has('view')) {
            return;
        }

        $viewFactory = $this->app->make('view');
        
        if (!method_exists($viewFactory, 'getFinder')) {
            return;
        }

        $viewFinder = $viewFactory->getFinder();
        
        if (method_exists($viewFinder, 'addNamespace')) {
            $viewFinder->addNamespace($namespace, $path);
        }
    }

    /**
     * Register a translation file namespace.
     *
     * @param string $path The path to the translations directory
     * @param string $namespace The namespace for translations
     */
    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        if (!$this->app->has('translator')) {
            return;
        }

        $translator = $this->app->make('translator');

        if (method_exists($translator, 'addNamespace')) {
            $translator->addNamespace($namespace, $path);
        }
    }

    /**
     * Register a configuration file.
     *
     * @param string $path Path to the config file
     * @param string $key Config key to merge into
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!$this->app->has('config')) {
            return;
        }

        if (!file_exists($path)) {
            return;
        }

        $config = $this->app->make('config');
        $configData = require $path;

        if (method_exists($config, 'set')) {
            $existing = $config->get($key, []);
            $merged = array_merge(
                is_array($existing) ? $existing : [],
                is_array($configData) ? $configData : []
            );
            $config->set($key, $merged);
        }
    }

    /**
     * Register paths to be published by the "vendor:publish" command.
     *
     * @param array $paths Paths to publish (source => destination)
     * @param mixed $groups Publishing groups/tags
     */
    protected function publishes(array $paths, mixed $groups = null): void
    {
        $this->publishable[] = [
            'paths' => $paths,
            'groups' => is_array($groups) ? $groups : [$groups],
        ];
    }

    /**
     * Get all publishable assets for this provider.
     *
     * @return array
     */
    public function getPublishable(): array
    {
        return $this->publishable;
    }

    /**
     * Get publishable assets by group.
     *
     * @param string $group
     * @return array
     */
    public function getPublishableByGroup(string $group): array
    {
        $paths = [];

        foreach ($this->publishable as $publishable) {
            if (in_array($group, $publishable['groups'], true)) {
                $paths = array_merge($paths, $publishable['paths']);
            }
        }

        return $paths;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Get the events that trigger this service provider to register.
     *
     * @return array
     */
    public function when(): array
    {
        return [];
    }

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     * @param bool $shared
     */
    protected function registerIfMissing(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if (!$this->app->bound($abstract)) {
            $this->app->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a singleton if it hasn't already been registered.
     *
     * @param string $abstract
     * @param \Closure|string|null $concrete
     */
    protected function singletonIfMissing(string $abstract, mixed $concrete = null): void
    {
        if (!$this->app->bound($abstract)) {
            $this->app->singleton($abstract, $concrete);
        }
    }

    /**
     * Get the base path of the application.
     *
     * @param string $path
     * @return string
     */
    protected function basePath(string $path = ''): string
    {
        if (method_exists($this->app, 'basePath')) {
            return app()->basePath($path);
        }

        return $path;
    }

    /**
     * Get the config path of the application.
     *
     * @param string $path
     * @return string
     */
    protected function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the database path of the application.
     *
     * @param string $path
     * @return string
     */
    protected function databasePath(string $path = ''): string
    {
        return $this->basePath('database' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the resources path of the application.
     *
     * @param string $path
     * @return string
     */
    protected function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Get the public path of the application.
     *
     * @param string $path
     * @return string
     */
    protected function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /**
     * Call the booting callbacks.
     */
    protected function callBootingCallbacks(): void
    {
        // Override in child classes if needed
    }

    /**
     * Call the booted callbacks.
     */
    protected function callBootedCallbacks(): void
    {
        // Override in child classes if needed
    }
}