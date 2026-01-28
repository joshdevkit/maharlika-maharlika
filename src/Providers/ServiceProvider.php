<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $app;
    
    protected array $publishable = [];

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
        if ($this->app->has('view')) {
            $viewFactory = $this->app->make('view');
            $viewFinder = $viewFactory->getFinder();
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
        if ($this->app->has('translator')) {
            $translator = $this->app->make('translator');

            if (method_exists($translator, 'addNamespace')) {
                $translator->addNamespace($namespace, $path);
            }
        }
    }

    /**
     * Register paths to be published by the "vendor:publish" command.
     *
     * @param array $paths Paths to publish (source => destination)
     * @param mixed $groups Publishing groups/tags
     */
    protected function publishes(array $paths, $groups = null): void
    {
        $this->publishable[] = [
            'paths' => $paths,
            'groups' => $groups,
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

    public function provides(): array
    {
        return [];
    }
}