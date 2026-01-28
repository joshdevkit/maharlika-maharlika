<?php 

namespace Maharlika\Http\Concerns;

trait InteractsWithHeaders
{
    /**
     * Get all request headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers->all();
    }

    /**
     * Get a specific header value by name.
     *
     * @param  string  $name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers->get($name);
    }

    /**
     * Determine if the request contains a given header.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Alias for getHeader.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers->get($key, $default);
    }
}
