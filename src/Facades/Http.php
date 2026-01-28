<?php

namespace Maharlika\Facades;

/**
 * @method static \Maharlika\Http\Client make(array $options = [])
 * @method static \Maharlika\Http\Client baseUrl(string $url)
 * @method static \Maharlika\Http\Client timeout(int $seconds)
 * @method static \Maharlika\Http\Client withHeaders(array $headers)
 * @method static \Maharlika\Http\Client withHeader(string $name, string $value)
 * @method static \Maharlika\Http\Client withQueryParameters(array $parameters)
 * @method static \Maharlika\Http\Client withQueryParameter(string $key, mixed $value)
 * @method static \Maharlika\Http\Client withToken(string $token, string $type = 'Bearer')
 * @method static \Maharlika\Http\Client withBasicAuth(string $username, string $password)
 * @method static \Maharlika\Http\Client acceptJson()
 * @method static \Maharlika\Http\Client asJson()
 * @method static \Maharlika\Http\Client asForm()
 * @method static \Maharlika\Http\Client asMultipart()
 * @method static \Maharlika\Http\Client withoutVerifying()
 * @method static \Maharlika\Http\Client withProxy(string $proxy)
 * @method static \Maharlika\Http\Response get(string $url, array $query = [])
 * @method static \Maharlika\Http\Response post(string $url, array $data = [])
 * @method static \Maharlika\Http\Response put(string $url, array $data = [])
 * @method static \Maharlika\Http\Response patch(string $url, array $data = [])
 * @method static \Maharlika\Http\Response delete(string $url, array $data = [])
 * @method static \Maharlika\Http\Response head(string $url)
 * @method static \Maharlika\Http\Response send(string $method, string $url, array $options = [])
 * @method static array pool(callable $callback)
 *
 * @see \Maharlika\Http\Outbound\Client
 */
class Http extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'http';
    }

    /**
     * Get a new HTTP client instance.
     */
    protected static function resolveFacadeInstance(): object
    {
        return new \Maharlika\Http\Outbound\Client();
    }
}