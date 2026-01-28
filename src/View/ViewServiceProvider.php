<?php

namespace Maharlika\View;

use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Contracts\View\ViewFinderInterface;
use Maharlika\Contracts\View\EngineInterface;
use Maharlika\Providers\ServiceProvider;
use Maharlika\View\Engines\PhpEngine;
use Maharlika\View\Engines\TemplateEngine;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerComponentResolver();
        $this->registerViewFinder();
        $this->registerEngines();
        $this->registerViewFactory();
        
        // Register Inertia directive helper - moved here so it's available
        $this->app->singleton('jsrender.directive', function ($app) {
            return new \Maharlika\JsRender\InertiaDirective();
        });
    }

    protected function registerComponentResolver(): void
    {
        $this->app->singleton('view.component.resolver', function ($c) {
            $app = $c->get('app');
            $resolver = new ComponentResolver();

            // Register component namespaces in priority order
            // 1. App components (highest priority)
            $appComponentPath = $app->basePath('app/View/Components');
            $resolver->addNamespace('App\\View\\Components', $appComponentPath);

            // 2. Maharlika Mail components - use alias mapping
            $resolver->addAlias('mail', 'Maharlika\\Mail\\Components');

            // 3. Maharlika Pagination components - use alias mapping
            $resolver->addAlias('pagination', 'Maharlika\\Pagination\\Components');

            // Allow extensions via config
            $customNamespaces = config('view.component_namespaces', []);
            foreach ($customNamespaces as $namespace => $path) {
                $resolver->addNamespace($namespace, $path);
            }

            return $resolver;
        });

        $this->app->singleton(ComponentResolver::class, function ($c) {
            return $c->get('view.component.resolver');
        });
    }

    protected function registerViewFinder(): void
    {
        $this->app->singleton('view.finder', function ($c) {
            $app = $c->get('app');
            $paths = [$app->basePath('resources/views')];

            $finder = new ViewFinder($paths);

            // Allow additional namespaces via config
            $additionalNamespaces = config('view.namespaces', []);
            if (is_array($additionalNamespaces)) {
                foreach ($additionalNamespaces as $namespace => $namespacePaths) {
                    $finder->addNamespace($namespace, $namespacePaths);
                }
            }

            return $finder;
        });

        $this->app->singleton(ViewFinderInterface::class, function ($c) {
            return $c->get('view.finder');
        });
    }

    protected function registerEngines(): void
    {
        // Register PHP Engine
        $this->app->singleton('view.engine.php', function () {
            return new PhpEngine();
        });

        // Register Template Engine with ComponentResolver
        $this->app->singleton('view.engine.template', function ($c) {
            $app = $c->get('app');
            $cachePath = $app->basePath('storage/views');

            // Get the component resolver
            $resolver = $c->has('view.component.resolver')
                ? $c->get('view.component.resolver')
                : null;

            return new TemplateEngine($cachePath, $resolver);
        });

        // Bind the interface to the default engine (TemplateEngine)
        $this->app->singleton(EngineInterface::class, function ($c) {
            return $c->get('view.engine.template');
        });
    }

    protected function registerViewFactory(): void
    {
        $this->app->singleton('view', function ($c) {
            $finder = $c->get('view.finder');
            $engine = $c->get('view.engine.template');

            return new ViewFactory($finder, $engine);
        });

        $this->app->singleton(ViewFactoryInterface::class, function ($c) {
            return $c->get('view');
        });
    }

    public function boot(): void
    {
        /** @var ViewFactory $view */
        $view = $this->app->get('view');

        $view->share('app_name', config('app.name', 'Framework'));
        $view->share('app_url', config('app.url', 'http://localhost'));
        
        // Register Inertia directives in boot() when everything is ready
        $engine = $this->app->get('view.engine.template');
        
        $engine->directive('inertia', function () {
            return '<?php echo app("jsrender.directive")->render(); ?>';
        });

        $engine->directive('inertiaHead', function () {
            return '<?php echo app("jsrender.directive")->renderHead(); ?>';
        });
    }
}