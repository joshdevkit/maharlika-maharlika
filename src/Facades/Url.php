<?php

namespace Maharlika\Facades;

/**
 * Class Url
 *
 * Facade for the URL generation service. Provides convenient static access
 * to the URL generator, allowing creation of absolute URLs, secure URLs,
 * signed URLs, and access to the current/previous URL.
 *
 * @method static string to(string $path, array $parameters = [])
 * @method static string secure(string $path, array $parameters = [])
 * @method static string current()
 * @method static string|null previous()
 * @method static string signedRoute(string $path, array $parameters = [])
 * @method static string temporarySignedRoute(string $path, int $expiration, array $parameters = [])
 * @method static bool hasValidSignature(\Maharlika\Contracts\Http\RequestInterface $request)
 *
 * @see \Maharlika\Routing\UrlGenerator
 */
class Url extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key for the URL generator.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'url';
    }
}
