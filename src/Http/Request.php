<?php

namespace Maharlika\Http;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\RouterInterface;
use Maharlika\Contracts\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @method array validate(array $rules, ...$params)
 * @method array validateWithBag(string $errorBag, array $rules, ...$params)
 * @method bool hasValidSignature(bool $absolute = true)
 * @method bool hasValidRelativeSignature()
 * @method bool hasValidSignatureWhileIgnoring($ignoreQuery = [], $absolute = true)
 * @method bool hasValidRelativeSignatureWhileIgnoring($ignoreQuery = [])
 */
class Request extends SymfonyRequest implements RequestInterface
{
    use \Maharlika\Support\Traits\Macroable;
    use \Maharlika\Http\Concerns\InteractsWithInput,
        \Maharlika\Http\Concerns\InteractsWithFiles,
        \Maharlika\Http\Concerns\InteractsWithContentTypes,
        \Maharlika\Http\Concerns\InteractsWithHeaders,
        \Maharlika\Http\Concerns\CanBePrecognitive;

    /**
     * The user resolver callback.
     *
     * @var \Closure|null
     */
    protected static ?\Closure $userResolver = null;

    /**
     * The resolved user instance.
     *
     * @var mixed
     */
    protected mixed $userResolved = null;

    /**
     * Indicates if the user has been resolved.
     *
     * @var bool
     */
    protected bool $userResolverCalled = false;

    public static function capture(): self
    {
        static::enableHttpMethodParameterOverride();

        return static::createFromBase(SymfonyRequest::createFromGlobals());
    }

    public function method()
    {
        return $this->getMethod();
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method()) === strtoupper($method);
    }

    public function getUri(): string
    {
        return $this->getRequestUri();
    }

    public function getPath(): string
    {
        return $this->getPathInfo();
    }

    public function getPathInfo(): string
    {
        return parent::getPathInfo();
    }

    public function getSchemeAndHttpHost(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->getServer('HTTP_HOST', 'localhost');
        return $scheme . '://' . $host;
    }

    public function getContent(bool $asResource = false): string|false
    {
        return parent::getContent($asResource);
    }

    /**
     * Get a route parameter value.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function route(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->attributes->all();
        }

        return $this->attributes->get($key, $default);
    }

    public function routeIs(string $route): bool
    {
        return app(RouterInterface::class)->routeIs($route);
    }

    /**
     * Get all route parameters.
     *
     * @return array
     */
    public function routes(): array
    {
        return $this->attributes->all();
    }

    /**
     * Get all cookies from the request.
     *
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->cookies->all();
    }

    /**
     * Get a specific cookie value by name.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies->get($key, $default);
    }

    /**
     * Get all server variables.
     *
     * @return array<string, mixed>
     */
    public function server(): array
    {
        return $this->server->all();
    }

    /**
     * Get a specific server variable by name.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function getServer(string $key, mixed $default = null): mixed
    {
        return $this->server->get($key, $default);
    }

    public function ajax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    public function isAjax(): bool
    {
        return $this->ajax();
    }

    public function isPjax(): bool
    {
        $pjax = $this->headers->get('X-Pjax');
        return $pjax !== null && $pjax !== '' && strtolower($pjax) !== 'false';
    }

    public function isSecure(): bool
    {
        return parent::isSecure();
    }

    public function ip(): ?string
    {
        return $this->getClientIp();
    }

    public function userAgent(): ?string
    {
        return $this->headers->get('User-Agent');
    }

    /**
     * Create an Maharlika request from a Symfony instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return static
     */
    public static function createFromBase(SymfonyRequest $request)
    {
        $newRequest = new static(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            (new static)->filterFiles($request->files->all()) ?? [],
            $request->server->all()
        );

        $newRequest->headers->replace($request->headers->all());

        $newRequest->content = $request->content;

        // If JSON, merge JSON data into the request parameters
        if ($newRequest->isJson()) {
            $jsonData = $newRequest->json()->all();
            foreach ($jsonData as $key => $value) {
                $newRequest->request->set($key, $value);
            }
        }

        return $newRequest;
    }

    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param  mixed  $files
     * @return mixed
     */
    protected function filterFiles($files)
    {
        if (! $files) {
            return;
        }

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = $this->filterFiles($files[$key]);
            }

            if (empty($files[$key])) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    /**
     * Set the default resolver callback.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function setResolver(\Closure $callback): void
    {
        static::$userResolver = $callback;
    }

    /**
     * Get the user resolver callback.
     *
     * @return \Closure|null
     */
    public static function getUserResolver(): ?\Closure
    {
        return static::$userResolver;
    }

    /**
     * Get the user making the request.
     *
     * @param  string|null  $guard
     * @return \Maharlika\Contracts\Auth\AuthManagerContract|null
     */
    public function user(?string $guard = null)
    {
        if ($this->userResolverCalled) {
            return $this->userResolved;
        }

        $this->userResolverCalled = true;

        if (static::$userResolver !== null) {
            $this->userResolved = call_user_func(static::$userResolver, $guard);
        }

        return $this->userResolved;
    }

    /**
     * Set the user for the request.
     *
     * @param  mixed  $user
     * @return $this
     */
    public function setUser(mixed $user): static
    {
        $this->userResolved = $user;
        $this->userResolverCalled = true;

        return $this;
    }

    /**
     * Check if the request has an authenticated user.
     * 
     * @return bool
     */
    public function hasUser(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the session instance.
     * 
     * @return \Maharlika\Contracts\Session\SessionInterface
     */
    public function session()
    {
        return session();
    }

    /**
     * Whether the request contains a Session object.
     *
     * This method does not give any information about the state of the session object,
     * like whether the session is started or not. It is just a way to check if this Request
     * is associated with a Session instance.
     *
     * @param bool $skipIfUninitialized When true, ignores factories injected by `setSessionFactory`
     */
    public function hasSession(bool $skipIfUninitialized = false): bool
    {
        return null !== $this->session && (!$skipIfUninitialized || $this->session instanceof SessionInterface);
    }


    public function __get(string $key): mixed
    {
        return $this->input($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->request->set($key, $value);
    }

    public function __unset(string $key): void
    {
        $this->request->remove($key);
        $this->query->remove($key);
    }
}
