<?php

namespace Maharlika\Http\Outbound;

class Response
{
    protected string $body;
    protected int $status;
    protected array $headers;
    protected array $info;

    public function __construct(string $body, int $status, array $headers = [], array $info = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
        $this->info = $info;
    }

    /**
     * Get response body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Get JSON decoded response.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = json_decode($this->body, true);
        
        if ($key === null) {
            return $data;
        }
        
        return data_get($data, $key, $default);
    }

    /**
     * Get response as object.
     */
    public function object(): ?object
    {
        return json_decode($this->body);
    }

    /**
     * Get response as array.
     */
    public function array(): array
    {
        return json_decode($this->body, true) ?? [];
    }

    /**
     * Get response status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Check if response was successful (2xx).
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Check if response was OK (200).
     */
    public function ok(): bool
    {
        return $this->status === 200;
    }

    /**
     * Check if response was redirect (3xx).
     */
    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * Check if response was client error (4xx).
     */
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Check if response was server error (5xx).
     */
    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Check if response failed (4xx or 5xx).
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Get response headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header.
     */
    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get cURL info.
     */
    public function info(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->info;
        }
        
        return $this->info[$key] ?? null;
    }

    /**
     * Throw an exception if response failed.
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new \RuntimeException(
                "HTTP request failed with status {$this->status}: {$this->body}"
            );
        }
        
        return $this;
    }

    /**
     * Throw an exception if response failed, with callback.
     */
    public function throwIf(bool|callable $condition): static
    {
        $shouldThrow = is_callable($condition) ? $condition($this) : $condition;
        
        if ($shouldThrow) {
            return $this->throw();
        }
        
        return $this;
    }

    /**
     * Execute callback if response was successful.
     */
    public function onSuccess(callable $callback): static
    {
        if ($this->successful()) {
            $callback($this);
        }
        
        return $this;
    }

    /**
     * Execute callback if response failed.
     */
    public function onError(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }
        
        return $this;
    }

    /**
     * Convert response to string.
     */
    public function __toString(): string
    {
        return $this->body;
    }
}