<?php

use Maharlika\Container\Container;
use Maharlika\Contracts\Hashing\HasherContract;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Contracts\Http\RouterInterface;
use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Framework\Application;
use Maharlika\Http\Response;
use Maharlika\Log\LogManager;
use Maharlika\Support\Carbon;
use Maharlika\Support\Env;
use Maharlika\Support\HigherOrderTapProxy;
use Maharlika\View\ViewFactory;
use Psr\Log\LoggerInterface;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|null  $abstract
     * @return ($abstract is class-string<TClass> ? TClass : ($abstract is null ? \Maharlika\Framework\Application : mixed))
     */
    function app($abstract = null, array $parameters = [])
    {
        $container = \Maharlika\Container\Container::getInstance();

        if (is_null($container)) {
            throw new RuntimeException('Container instance has not been initialized.');
        }
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        /** @var Application $app */
        $app = app();

        return $app->path($path);
    }
}


if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get base path
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}


if (!function_exists('database_path')) {
    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}


if (!function_exists('response')) {
    /**
     * Create a new response instance
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Maharlika\Http\Response
    {
        return new \Maharlika\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('json')) {
    /**
     * Create a JSON response
     */
    function json(mixed $data, int $status = 200): \Maharlika\Http\Response
    {
        return \Maharlika\Http\Response::json($data, $status);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response.
     * 
     * If no URL is provided, it falls back to the HTTP_REFERER or '/'.
     */
    function redirect(?string $url = null, int $status = 302): \Maharlika\Http\Response
    {
        return \Maharlika\Http\Response::redirect($url, $status);
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with an error page.
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @param array $details Additional details for debugging (only shown in non-production)
     * @return never
     */
    function abort(int $code, string $message = '', array $details = [])
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        Response::abort($code, $message, $uri, $details)->send();
    }
}

if (!function_exists('abort_if')) {
    /**
     * Throw an HTTP exception if a given condition is true.
     *
     * @param bool $condition
     * @param int $code
     * @param string $message
     * @param array $details
     * @return void
     */
    function abort_if(bool $condition, int $code, string $message = '', array $details = []): void
    {
        if ($condition) {
            abort($code, $message, $details);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * Throw an HTTP exception unless a given condition is true.
     *
     * @param bool $condition
     * @param int $code
     * @param string $message
     * @param array $details
     * @return void
     */
    function abort_unless(bool $condition, int $code, string $message = '', array $details = []): void
    {
        if (!$condition) {
            abort($code, $message, $details);
        }
    }
}

if (!function_exists('forbidden')) {
    /**
     * Return a 403 Forbidden response.
     *
     * @param string $message
     * @return never
     */
    function forbidden(string $message = ''): never
    {
        abort(403, $message);
    }
}

if (!function_exists('back')) {
    function back(): \Maharlika\Http\Response
    {
        return \Maharlika\Http\Response::back();
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash a password using the default Hash driver (e.g. BCRYPT).
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    function bcrypt(string $value, array $options = []): string
    {
        return app(HasherContract::class)->make($value, $options);
    }
}


if (!function_exists('now')) {
    /**
     * Get the current date and time as a Carbon instance.
     *
     * @param  string|null  $timezone
     * @return \Maharlika\Support\Carbon
     */
    function now(?string $timezone = null): Carbon
    {
        $timezone = config('app.timezone', $timezone);
        return Carbon::now($timezone);
    }
}


if (!function_exists('view')) {
    /**
     * Create a view instance or return the factory
     * 
     * @param string|null $view View name
     * @param array $data View data
     * @return ($view is null ? \Maharlika\Contracts\View\ViewFactoryInterface : \Maharlika\Contracts\View\ViewInterface)
     */
    function view(?string $view = null, array $data = [])
    {
        $factory = app(ViewFactoryInterface::class);

        if ($view === null) {
            return $factory;
        }

        return $factory->make($view, $data);
    }
}


if (!function_exists('request')) {

    function request()
    {
        return app()->make(RequestInterface::class);
    }
}


if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        $baseUrl = getBaseUrl();
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL or return UrlGenerator instance
     */
    function url(?string $path = null, array $parameters = [])
    {
        $generator = app('url');

        if ($path === null) {
            return $generator;
        }

        return $generator->to($path, $parameters);
    }
}


if (!function_exists('router')) {
    /**
     * Generate URL for a route
     * 
     *
     * @param string $target Route name or action (method@Controller)
     * @param array|string $params Route parameters
     * @return string
     * @throws InvalidArgumentException
     */
    function router(string $target, $params = []): string
    {
        // Ensure $params is an array
        if (!is_array($params)) {
            $params = is_string($params) ? ['param' => $params] : (array)$params;
        }

        $router = app(RouterInterface::class);
        $router->discoverRoutes();

        if (!str_contains($target, '@')) {
            return $router->route($target, $params);
        }

        return resolveByAction($router, $target, $params);
    }
}

if (!function_exists('to_route')) {
    function to_route(string $name, mixed $params = [], int $status = 302): ResponseInterface
    {
        return Response::toAction($name, $params, $status);
    }
}

if (!function_exists('resolveByAction')) {
    /**
     * Resolve route by action (method@Controller format)
     * This is the legacy format, kept for backward compatibility
     */
    function resolveByAction(\Maharlika\Routing\Router $router, string $action, array $params): string
    {
        [$method, $controller] = explode('@', $action, 2);

        // Normalize controller name
        $controller = trim($controller);
        if (!str_ends_with($controller, 'Controller')) {
            $controller .= 'Controller';
        }

        $routes = $router->getRoutes();
        $targetRoute = null;

        foreach ($routes as $route) {
            if (!is_array($route['action'])) continue;

            [$routeController, $routeMethod] = $route['action'];

            if (
                (str_ends_with($routeController, $controller) ||
                    str_contains($routeController, $controller)) &&
                strtolower($routeMethod) === strtolower($method)
            ) {
                $targetRoute = $route;
                break;
            }
        }

        if (!$targetRoute) {
            throw new InvalidArgumentException(
                sprintf("No route found for [%s@%s].", $controller, $method)
            );
        }

        $uri = $targetRoute['uri'];

        // Replace placeholders
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $uri, $matches);
        $placeholders = $matches[1] ?? [];

        foreach ($placeholders as $key) {
            if (!array_key_exists($key, $params)) {
                throw new InvalidArgumentException(
                    "Missing required route parameter [{$key}] for [{$controller}@{$method}]."
                );
            }

            $uri = str_replace('{' . $key . '}', rawurlencode((string) $params[$key]), $uri);
            unset($params[$key]);
        }

        // Add leftover query params
        if (!empty($params)) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($params);
        }

        return $uri;
    }
}

if (!function_exists('route')) {
    /**
     * Alias for router() 
     */
    function route(string $name, array $params = []): string
    {
        return router($name, $params);
    }
}


if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param  TValue  $value
     * @param  (callable(TValue): mixed)|null  $callback
     * @return ($callback is null ? \Maharlika\Support\HigherOrderTapProxy : TValue)
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get the base URL from the current request
     */
    function getBaseUrl(): string
    {
        // Try to get from config first
        $configUrl = config('app.url', null);

        if ($configUrl && $configUrl !== 'http://localhost') {
            return rtrim($configUrl, '/');
        }

        // Build from current request
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        return $protocol . '://' . $host;
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Get all traits used by a class, its parent classes and trait of traits.
     *
     * @param object|string $class
     * @return array
     */
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * Get all traits used by a trait and its traits.
     *
     * @param string $trait
     * @return array
     */
    function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}


if (!function_exists('class_basename')) {
    function class_basename($class)
    {
        // If it's an object, get its class name
        if (is_object($class)) {
            $class = get_class($class);
        }

        // Handle namespaces and return the last part
        return basename(str_replace('\\', '/', $class));
    }
}


if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back(): \Maharlika\Http\Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return redirect($referer);
    }
}


if (!function_exists('str')) {
    /**
     * Get Str helper instance or perform string operation
     */
    function str(?string $value = null): \Maharlika\Support\Str|string
    {
        if ($value === null) {
            return new \Maharlika\Support\Str();
        }

        return $value;
    }
}


if (!function_exists('logger')) {
    /**
     * Log a message to the logs
     *
     * @param string|null $message
     * @param array $context
     * @return LogManager|LoggerInterface|null
     */
    function logger(?string $message = null, array $context = []): LogManager|LoggerInterface|null
    {
        if (is_null($message)) {
            return app('log');
        }

        return app('log')->debug($message, $context);
    }
}

if (!function_exists('logs')) {
    /**
     * Alias for logger function
     *
     * @param string|null $message
     * @param array $context
     * @return LogManager|LoggerInterface|null
     */
    function logs(?string $message = null, array $context = []): LogManager|LoggerInterface|null
    {
        return logger($message, $context);
    }
}

if (!function_exists('auth')) {

    /**
     * Get the authentication manager instance.
     *
     * Provides access to the AuthManager for authentication operations such as
     * checking authentication status, retrieving the current user, logging in/out,
     * and managing user sessions.
     *
     * @return \Maharlika\Contracts\Auth\AuthManagerContract
     *
     * 
     **/
    function auth(): \Maharlika\Contracts\Auth\AuthManagerContract
    {
        return app('auth');
    }
}

if (!function_exists('user')) {
    function user()
    {
        return auth()->user();
    }
}


if (!function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string|array
    {
        return app('translator')->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate the given message with pluralization.
     *
     * @param string $key
     * @param int|float $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function trans_choice(string $key, int|float $number, array $replace = [], ?string $locale = null): string
    {
        return app('translator')->choice($key, $number, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message (alias for trans).
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array
     */
    function __(string $key, array $replace = [], ?string $locale = null): string|array
    {
        return trans($key, $replace, $locale);
    }
}


if (!function_exists('getLanguagePath')) {
    /**
     * Get the path to language files.
     *
     * @return string
     */
    function getLanguagePath(): string
    {
        // Check if using Laravel-style paths
        if (defined('BASE_PATH')) {
            return BASE_PATH . '/resources/lang';
        }

        // Check for custom lang path in config
        if (function_exists('config')) {
            $path = config('app.lang_path');
            if ($path && is_dir($path)) {
                return $path;
            }
        }

        return __DIR__ . '/../resources/lang';
    }
}

if (!function_exists('getDefaultLocale')) {
    /**
     * Get the default application locale.
     *
     * @return string
     */
    function getDefaultLocale(): string
    {
        // Try to get from config
        if (function_exists('config')) {
            return config('app.locale', 'en');
        }

        // Try to get from app container
        if (function_exists('app') && app()->has('config')) {
            return app('config')->get('app.locale', 'en');
        }

        return 'en';
    }
}

if (!function_exists('setLocale')) {
    /**
     * Set the default locale.
     *
     * @param string $locale
     * @return void
     */
    function setLocale(string $locale): void
    {
        if (function_exists('app') && app()->has('translator')) {
            app('translator')->setLocale($locale);
        }
    }
}

if (!function_exists('getLocale')) {
    /**
     * Get the current locale.
     *
     * @return string
     */
    function getLocale(): string
    {
        if (function_exists('app') && app()->has('translator')) {
            return app('translator')->getLocale();
        }

        return getDefaultLocale();
    }
}