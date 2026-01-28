<?php

namespace Maharlika\Contracts\Http;

/**
 * Interface RequestInterface
 *
 * Defines the contract for an HTTP request within the framework.
 * Provides methods to access HTTP method, URI, headers, query parameters,
 * request body, and authenticated user information.
 *
 * @package Maharlika\Contracts\Http
 */
interface RequestInterface
{
    /**
     * Get the HTTP method (e.g., GET, POST, PUT, DELETE).
     *
     * @return string
     */
    public function method();

    /**
     * Set the HTTP method.
     *
     */
    public function setMethod(string $method): void;

    /**
     * Determine if the current request method matches the given method.
     *
     * @param  string  $method  The HTTP method to compare (case-insensitive).
     * @return bool
     */
    public function isMethod(string $method): bool;

    /**
     * Get the full request URI.
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Get the request path (without query string).
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     * @return string The raw path (i.e. not urldecoded)
     * @method override (symfony request)
     */
    public function getPathInfo(): string;

    /**
     * Get the scheme and HTTP host (e.g., https://example.com).
     *
     * @return string
     */
    public function getSchemeAndHttpHost(): string;

    /**
     * Get all query parameters from the request.
     *
     * @return array<string, mixed>
     */
    public function getQuery(): array;

    /**
     * Get query parameters as an array (alias for getQuery).
     *
     * @return array<string, mixed>
     */
    public function query(): array;

    /**
     * Set header
     * 
     * @return string
     */
    public function header(string $key, mixed $default = null): mixed;
    /**
     * Get all request headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Get a specific header value by name.
     *
     * @param  string  $name
     * @return string|null
     */
    public function getHeader(string $name): ?string;



    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken(): ?string;

    /**
     * Get the raw body of the request.
     *
     * @return mixed
     */
    public function getBody();

    /**
     * Get the raw request content (body as string).
     *
     * @return string|null
     */
    public function getContent(bool $asResource = false): string|false;


    /**
     * Retrieve a specific input value from the request body or query string.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed;


    /**
     * Retrieve a specific input value using body + query lookup.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get all input data (body + query parameters) as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Check if a key exists in the request data.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get only specific keys from request.
     *
     * @param  array|string  $keys
     * @return array|string|null
     */
    public function only(array|string $keys): array|string|null;

    /**
     * Get all except specific keys from request.
     *
     * @param  array  $keys
     * @return array
     */
    public function except(array $keys): array;

    /**
     * Get all uploaded files from the request.
     *
     * @return array<string, mixed>
     */
    public function files(): array;

    /**
     * Get a specific uploaded file by name.
     *
     * @param  string  $key
     * @return mixed
     */
    public function file(string $key): mixed;

    /**
     * Get all cookies from the request.
     *
     * @return array<string, mixed>
     */
    public function cookies(): array;

    /**
     * Get a specific cookie value by name.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed;

    /**
     * Get all server variables.
     *
     * @return array<string, mixed>
     */
    public function server(): array;


    /**
     * Set the session instance from the request
     * 
     * @return \Maharlika\Contracts\Session\SessionInterface
     */

    public function session();


    /**
     * Get the route name if available to the route configurations
     * @return bool
     */
    public function routeIs(string $route): bool;
    /**
     * Get a specific server variable by name.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function getServer(string $key, mixed $default = null): mixed;

    /**
     * Determine if the request expects a JSON response.
     * Checks Accept header and X-Requested-With header.
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Determine if the request is sending JSON data.
     * Checks Content-Type header.
     *
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Determine if the request is an AJAX request.
     * Checks X-Requested-With header.
     *
     * @return bool
     */
    public function ajax(): bool;

    /**
     * Alias for ajax() method.
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * Determine if the request is over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool;

    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    public function ip(): ?string;

    /**
     * Get the user agent string.
     *
     * @return string|null
     */
    public function userAgent(): ?string;

    /**
     * Get the currently authenticated user for the request.
     *
     * @param  string|null  $guard  Authentication guard name.
     * @return \Maharlika\Contracts\Auth\Authenticatable|null
     */
    public function user(?string $guard = null);

    /**
     * Set the authenticated user manually (useful for middleware/testing).
     *
     * @param  mixed  $user
     * @return static
     */
    public function setUser(mixed $user): static;
}
