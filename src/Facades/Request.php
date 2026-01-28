<?php

namespace Maharlika\Facades;

/**
 * Request Facade
 * 
 * @method static mixed input(string $key = null, mixed $default = null)
 * @method static array all()
 * @method static bool has(string $key)
 * @method static bool filled(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static mixed post(string $key, mixed $default = null)
 * @method static string method()
 * @method static bool isMethod(string $method)
 * @method static string path()
 * @method static string url()
 * @method static string fullUrl()
 * @method static bool is(string ...$patterns)
 * @method static bool ajax()
 * @method static bool wantsJson()
 * @method static bool expectsJson()
 * @method static string ip()
 * @method static array validate(array $rules, array $messages = [])
 * @method static bool hasFile(string $key)
 * @method static \Maharlika\Http\Request|array|null file(string $key = null)
 * @method static string|null header(string $key, string|null $default = null)
 * @method static string|null bearerToken()
 * @method static array|null json(string $key = null, mixed $default = null)
 * @method static bool isJson()
 * @method static array only(array|string $keys)
 * @method static array except(array|string $keys)
 * @method static mixed query(string $key = null, mixed $default = null)
 * @method static mixed cookie(string $key = null, mixed $default = null)
 * @method static mixed server(string $key = null, mixed $default = null)
 * @method static string|null userAgent()
 * @method static bool secure()
 * @method static string scheme()
 * @method static string host()
 * @method static int|null port()
 * @method static array segments()
 * @method static string|null segment(int $index, string|null $default = null)
 * @method static \Maharlika\Http\Request merge(array $input)
 * @method static \Maharlika\Http\Request replace(array $input)
 * @method static bool isXmlHttpRequest()
 * @method static bool prefetch()
 * @method static string decodedPath()
 * @method static string|null route(string $param = null, mixed $default = null)
 * @method static object|null user()
 * @method static mixed old(string $key = null, mixed $default = null)
 * @method static void flash()
 * @method static void flashOnly(array|string $keys)
 * @method static void flashExcept(array|string $keys)
 * @method static void flush()
 * @method static \Maharlika\Http\Request instance()
 * 
 * @see \Maharlika\Http\Request
 */
class Request extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'request';
    }
}