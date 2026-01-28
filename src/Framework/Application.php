<?php

namespace Maharlika\Framework;

use Maharlika\Auth\ApiAuthServiceProvider;
use Maharlika\Auth\AuthServiceProvider;
use Maharlika\Auth\GateServiceProvider;
use Maharlika\Broadcasting\BroadcastServiceProvider;
use Maharlika\Contracts\ApplicationInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Container\Container;
use Maharlika\Database\Capsule;
use Maharlika\Database\DatabaseServiceProvider;
use Maharlika\Database\Schema\Schema;
use Maharlika\Events\EventServiceProvider;
use Maharlika\Hashing\HashServiceProvider;
use Maharlika\Http\Request;
use Maharlika\Http\Server;
use Maharlika\JsRender\JsRenderServiceProvider;
use Maharlika\Mail\Mailer;
use Maharlika\Mail\MailServiceProvider;
use Maharlika\Pagination\PaginationServiceProvider;
use Maharlika\Pipeline\Pipeline;
use Maharlika\Providers\CacheServiceProvider;
use Maharlika\Providers\LogServiceProvider;
use Maharlika\Providers\AppKeyServiceProvider;
use Maharlika\Providers\CorsServiceProvider;
use Maharlika\Providers\EncryptionServiceProvider;
use Maharlika\Providers\ErrorHandlerServiceProvider;
use Maharlika\Providers\HttpServiceProvider;
use Maharlika\Providers\PublisherServiceProvider;
use Maharlika\Providers\RequestServiceProvider;
use Maharlika\Providers\StorageServiceProvider;
use Maharlika\RateLimit\RateLimitServiceProvider;
use Maharlika\Routing\Redirector;
use Maharlika\Routing\Router;
use Maharlika\Routing\RoutingServiceProvider;
use Maharlika\Scheduling\ScheduleServiceProvider;
use Maharlika\Session\SessionServiceProvider;
use Maharlika\Socialite\SocialiteServiceProvider;
use Maharlika\Support\AliasLoader;
use Maharlika\Translation\TranslationServiceProvider;
use Maharlika\Validation\ValidationServiceProvider;
use Maharlika\View\ViewServiceProvider;
use Dotenv\Dotenv;

use function Maharlika\Filesystem\join_paths;

class Application extends Container implements ApplicationInterface
{
    const VERSION = '1.0.0';

    protected string $locale = 'en';
    protected string $basePath;
    protected string $environment = 'production';
    protected array $bootstrapProviders = [];
    protected bool $booted = false;
    protected bool $bootstrapped = false;
    protected ?HttpKernel $kernel = null;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->registerBaseBindings();
        $this->loadEnvironment();
        $this->detectEnvironment();
        $this->registerCoreServices();
        $this->loadConfiguration();
        $this->registerCoreServiceProviders();
        $this->bootstrap();
    }

    /**
     * Register base bindings into the container.
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance(ApplicationInterface::class, $this);
        $this->instance('app', $this);
        $this->instance('container', $this);
        $this->instance('path', $this->path());
        $this->instance('server', new Server($_SERVER));
    }

    /**
     * Register core framework services.
     */
    protected function registerCoreServices(): void
    {
        $services = [
            'config'    => fn() => new \Maharlika\Config\Repository(),
            'router'    => fn($c) => new Router($c, $c->get('config')->get('routing', [])),
            'request'   => fn() => Request::capture(),
            'url'       => fn($c) => new \Maharlika\Routing\UrlGenerator($c->get('router'), $c->get('request')),
            'pipeline'  => fn($c) => new Pipeline($c),
            'schema'    => fn() => new Schema(Capsule::connection()),
            'migrator'  => fn($c) => new \Maharlika\Database\Schema\MigrationRunner($c->basePath('database/migrations')),
            'redirect'  => fn($c) => new Redirector($c->get('url')),
            'mailer'    => fn($c) => new Mailer($c->get('config')->get('mail', [])),
            'queue'     => fn($c) => new \Maharlika\Queue\Queue($c->get('config')->get('queue', [])),
        ];

        foreach ($services as $alias => $resolver) {
            $this->singleton($alias, $resolver);
        }
    }

    /**
     * Register core service providers.
     */
    protected function registerCoreServiceProviders(): void
    {
        $providers = [
            ErrorHandlerServiceProvider::class,
            LogServiceProvider::class,
            DatabaseServiceProvider::class,
            CacheServiceProvider::class,
            RoutingServiceProvider::class,
            ScheduleServiceProvider::class,
            StorageServiceProvider::class,
            RateLimitServiceProvider::class,
            SessionServiceProvider::class,
            AuthServiceProvider::class,
            GateServiceProvider::class,
            CorsServiceProvider::class,
            ApiAuthServiceProvider::class,
            HashServiceProvider::class,
            AppKeyServiceProvider::class,
            EncryptionServiceProvider::class,
            EventServiceProvider::class,
            BroadcastServiceProvider::class,
            RequestServiceProvider::class,
            ViewServiceProvider::class,
            ValidationServiceProvider::class,
            MailServiceProvider::class,
            PaginationServiceProvider::class,
            JsRenderServiceProvider::class,
            PublisherServiceProvider::class,
            SocialiteServiceProvider::class,
            HttpServiceProvider::class,
            ApiFrameworkProvider::class,
            TranslationServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Bootstrap the application.
     */
    public function bootstrap(): ApplicationInterface
    {
        if ($this->bootstrapped) {
            return $this;
        }

        $this->bind(RequestInterface::class, Request::class);
        $this->instance('appPath', $this->basePath . '/app');
        $this->instance('app_version', self::VERSION);
        $this->singleton('exception.handler', fn() => new \Maharlika\Exceptions\Handler());

        $this->loadBootstrapProviders();
        $this->registerBootstrapProviders();
        $this->configureRouter();
        $this->initializeKernel();
        
        // Boot all registered providers
        $this->boot();

        $this->addMiddleware('middleware.cors', -100);
        $this->registerAliases();

        $this->bootstrapped = true;

        return $this;
    }

    /**
     * Initialize the HTTP kernel.
     */
    protected function initializeKernel(): void
    {
        $this->kernel = new HttpKernel($this);
        $this->singleton(HttpKernel::class, fn() => $this->kernel);
    }

    /**
     * Load environment variables from .env file.
     */
    protected function loadEnvironment(): void
    {
        if (!file_exists($this->basePath . '/.env')) {
            return;
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();
    }

    /**
     * Detect and set the application environment.
     */
    protected function detectEnvironment(): void
    {
        $this->environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        $this->instance('env', $this->environment);

        if (!defined('APP_ENV')) {
            define('APP_ENV', $this->environment);
        }

        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', $this->hasDebugModeEnabled());
        }
    }

    /**
     * Load configuration files.
     */
    protected function loadConfiguration(): void
    {
        $config = $this->get('config');

        $configPath = $this->basePath('config');
        if (is_dir($configPath)) {
            $config->loadDirectory($configPath);
        }

        $this->locale = $config->get('app.locale', 'en');
    }

    /**
     * Load bootstrap providers from configuration.
     */
    protected function loadBootstrapProviders(): void
    {
        $providersFile = $this->basePath . '/bootstrapper/providers.php';

        if (!file_exists($providersFile)) {
            return;
        }

        $providers = require $providersFile;

        if (is_array($providers)) {
            $this->bootstrapProviders = $providers;
        }
    }

    /**
     * Register bootstrap providers.
     */
    protected function registerBootstrapProviders(): void
    {
        foreach ($this->bootstrapProviders as $providerClass) {
            if (class_exists($providerClass)) {
                $this->register($providerClass);
            }
        }
    }

    /**
     * Configure the router with auto-discovery settings.
     */
    protected function configureRouter(): void
    {
        $router = $this->get('router');
        $config = $this->get('config');

        $routingConfig = $config->get('routing', []);

        if (!($routingConfig['auto_discovery']['enabled'] ?? true)) {
            return;
        }

        $namespaces = $routingConfig['namespaces'] ?? $this->getDefaultControllerNamespaces();

        foreach ($namespaces as $namespace => $directory) {
            if (is_dir($directory)) {
                $router->addControllerNamespace($namespace, $directory);
            }
        }

        // Only eager load in production when not in debug mode
        if (($routingConfig['auto_discovery']['eager'] ?? false) && 
            $this->isProduction() && 
            !$this->hasDebugModeEnabled()) {
            $router->discoverRoutes();
        }
    }

    /**
     * Get default controller namespaces.
     */
    protected function getDefaultControllerNamespaces(): array
    {
        return [
            'App\\Controllers' => $this->basePath . app_path('Controllers'),
        ];
    }

    /**
     * Register class aliases.
     */
    protected function registerAliases(): void
    {
        $aliases = [
            'Auth'    => \Maharlika\Facades\Auth::class,
            'Gate'    => \Maharlika\Facades\Gate::class,
            'View'    => \Maharlika\Facades\View::class,
            'DB'      => \Maharlika\Facades\DB::class,
            'Hash'    => \Maharlika\Facades\Hash::class,
            'Str'     => \Maharlika\Support\Str::class,
            'Route'   => \Maharlika\Facades\Route::class,
            'Log'     => \Maharlika\Facades\Log::class,
            'Mail'    => \Maharlika\Facades\Mail::class,
            'Cache'   => \Maharlika\Facades\Cache::class,
            'Storage' => \Maharlika\Facades\Storage::class,
            'Carbon'  => \Maharlika\Support\Carbon::class,
            'Lang'    => \Maharlika\Facades\Lang::class,
        ];

        $loader = AliasLoader::getInstance($aliases);
        $loader->register();
    }

    /**
     * Boot all service providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        parent::boot();

        $this->booted = true;
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        return $this->kernel->handle($request);
    }

    /**
     * Run the application.
     */
    public function run(): void
    {
        $request = $this->get('request');
        $response = $this->handle($request);
        $response->send();
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments, true);
    }

    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    public function isLocal(): bool
    {
        return $this->environment === 'local';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function isTesting(): bool
    {
        return $this->environment === 'testing';
    }

    public function hasDebugModeEnabled(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }

    public function path(string $path = ''): string
    {
        $base = $this->has('appPath')
            ? $this->get('appPath')
            : $this->basePath('app');

        return $this->joinPaths($base, $path);
    }

    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    public function joinPaths(string $basePath, string $path = ''): string
    {
        return join_paths($basePath, $path);
    }


    public function registerMiddleware(array $middleware): self
    {
        foreach ($middleware as $alias => $class) {
            if (is_int($alias)) {
                $alias = $class;
            }
            $this->singleton($alias, fn() => new $class());
        }
        return $this;
    }

    public function addMiddleware(string $middleware, int $priority = 0): self
    {
        if ($this->kernel === null) {
            throw new \RuntimeException('Cannot add middleware before HttpKernel is initialized.');
        }

        $this->kernel->addMiddleware($middleware, $priority);
        return $this;
    }

    public function prependMiddleware(string $middleware): self
    {
        if ($this->kernel === null) {
            throw new \RuntimeException('Cannot prepend middleware before HttpKernel is initialized.');
        }

        $this->kernel->prependMiddleware($middleware);
        return $this;
    }

    public function removeMiddleware(string $middleware): self
    {
        if ($this->kernel === null) {
            throw new \RuntimeException('Cannot remove middleware before HttpKernel is initialized.');
        }

        $this->kernel->removeMiddleware($middleware);
        return $this;
    }

    public function replaceMiddleware(string $old, string $new): self
    {
        if ($this->kernel === null) {
            throw new \RuntimeException('Cannot replace middleware before HttpKernel is initialized.');
        }

        $this->kernel->replaceMiddleware($old, $new);
        return $this;
    }

    public function withMiddleware(array $middleware): self
    {
        if ($this->kernel === null) {
            throw new \RuntimeException('Cannot merge middleware before HttpKernel is initialized.');
        }

        $this->kernel->withMiddleware($middleware);
        return $this;
    }

    public function getMiddleware(): array
    {
        if ($this->kernel === null) {
            return [];
        }

        return $this->kernel->getMiddleware();
    }

    public function hasMiddleware(string $middleware): bool
    {
        if ($this->kernel === null) {
            return false;
        }

        return $this->kernel->hasMiddleware($middleware);
    }


    public function getContainer(): static
    {
        return $this;
    }

    public function getKernel(): HttpKernel
    {
        return $this->kernel;
    }

    public function hasBeenBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public function getApplicationVersion(): string
    {
        return self::VERSION;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getBootstrapProviders(): array
    {
        return $this->bootstrapProviders;
    }

    /**
     * Get all registered service providers.
     */
    public function getRegisteredProviders(): array
    {
        return $this->getProviders();
    }

    /**
     * Get registered provider class names.
     */
    public function getRegisteredProviderClasses(): array
    {
        return array_keys($this->getProviders());
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }
}