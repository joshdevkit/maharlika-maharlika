<?php

namespace Maharlika\Http\Concerns;

use Symfony\Component\HttpFoundation\ParameterBag;

trait InteractsWithInput
{
    /**
     * Framework-specific keys that should be excluded from input.
     *
     * @var array
     */
    protected array $excludeFromInput = [
        '_token',
        '_method',
    ];

    /**
     * The decoded JSON content for the request.
     *
     * @var \Symfony\Component\HttpFoundation\ParameterBag|null
     */
    protected ?ParameterBag $json = null;

    /**
     * Retrieve a specific input value from the request body or query string.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // Check request body first (POST data)
        if ($this->request->has($key)) {
            return $this->request->get($key, $default);
        }

        // Then check query string (GET data)
        if ($this->query->has($key)) {
            return $this->query->get($key, $default);
        }

        // Check JSON data if this is a JSON request
        if ($this->isJson() && isset($this->json)) {
            $jsonValue = $this->json->get($key);
            if ($jsonValue !== null) {
                return $jsonValue;
            }
        }

        return $default;
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return \Symfony\Component\HttpFoundation\ParameterBag|mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->json)) {
            $content = $this->getContent();
            $data = !empty($content) ? json_decode($content, true) : [];
            $this->json = new ParameterBag(is_array($data) ? $data : []);
        }

        if (is_null($key)) {
            return $this->json;
        }

        return data_get($this->json->all(), $key, $default);
    }

    /**
     * Get all input data (body + query parameters) as an array.
     * Automatically excludes framework-specific keys by default.
     *
     * @param  array|null  $keys  Optionally specify which keys to retrieve
     * @return array<string, mixed>
     */
    public function all(?array $keys = null): array
    {
        $input = $this->request->all() + $this->query->all();

        // Automatically exclude framework-specific keys
        $input = array_diff_key($input, array_flip($this->excludeFromInput));

        if ($keys === null) {
            return $input;
        }

        return array_intersect_key($input, array_flip($keys));
    }

    /**
     * Get all input including framework-specific keys.
     * Use this when you actually need _token, _method, etc.
     *
     * @return array<string, mixed>
     */
    public function allWithFrameworkKeys(): array
    {
        return $this->request->all() + $this->query->all();
    }

    /**
     * Get all input except framework-specific keys.
     * This is useful for mass assignment to models.
     *
     * @return array<string, mixed>
     */
    public function safe(): array
    {
        return $this->except($this->excludeFromInput);
    }

    /**
     * Check if a key exists in the request data.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (!($this->request->has($value) || $this->query->has($value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any of the given keys exist in the request data.
     *
     * @param  string|array  $keys
     * @return bool
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the request contains a non-empty value for an input item.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function filled(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request is missing a given input item.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function missing(string|array $key): bool
    {
        return !$this->has($key);
    }

    /**
     * Get only specific keys from request.
     *
     * @param  array|string  $keys
     * @return array|string|null
     */
    public function only(array|string $keys): array|string|null
    {
        if (is_string($keys)) {
            return $this->input($keys);
        }

        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get all except specific keys from request.
     *
     * @param  array|string  $keys
     * @return array
     */
    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Determine if the given input key is an empty string.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isEmptyString(string $key): bool
    {
        $value = $this->input($key);

        return !is_bool($value) && !is_array($value) && trim((string) $value) === '';
    }

    /**
     * Get the query parameters.
     *
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query->all();
    }

    /**
     * Get query parameters as an array (alias for getQuery).
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->getQuery();
    }

    /**
     * Get the request body.
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->request->all();
    }

    /**
     * Get the keys that should be excluded from input.
     *
     * @return array
     */
    public function getExcludedKeys(): array
    {
        return $this->excludeFromInput;
    }

    /**
     * Add keys to exclude from input.
     *
     * @param  array|string  $keys
     * @return $this
     */
    public function excludeKeys(array|string $keys): static
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $this->excludeFromInput = array_unique(array_merge($this->excludeFromInput, $keys));

        return $this;
    }
}
