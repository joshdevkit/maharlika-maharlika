<?php

namespace Maharlika\Session;

use Maharlika\Contracts\Session\SessionInterface;

/**
 * Null Session implementation for CLI contexts where sessions aren't needed
 */
class NullSession implements SessionInterface
{
    protected array $data = [];

    public function start(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function flash(string $key, mixed $value): void
    {
        // Flash data doesn't make sense in CLI, but we'll store it anyway
        $this->put($key, $value);
    }

    public function token(): ?string
    {
        return $this->get('_token');
    }

    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->put('_token', $token);
        return $token;
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        return true;
    }

    public function invalidate(): bool
    {
        $this->data = [];
        return true;
    }

    public function getId(): string
    {
        return 'null-session';
    }

    public function setId(string $id): void
    {
        // Do nothing
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function ageFlashData(): void
    {
        // Flash data aging doesn't apply in CLI context
        // Do nothing
    }
}
