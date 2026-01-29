<?php

namespace Maharlika\Session;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Contracts\Session\SessionHandlerInterface;

class Session implements SessionInterface
{
    protected bool $started = false;
    protected array $config;
    protected SessionHandlerInterface $handler;
    protected string $id;
    protected array $data = [];
    protected array $attributes = [];

    public function __construct(SessionHandlerInterface $handler, array $config = [])
    {
        $this->handler = $handler;
        $this->config = array_merge([
            'name' => config('app.name', 'MAHARLIKA-SESSION'),
            'lifetime' => 120,
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
        ], $config);


        $cookieId = $this->getSessionIdFromCookie();
        $this->id = $cookieId ?: $this->generateSessionId();
    }

    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // Try to read session data from handler
        $data = $this->handler->read($this->id);

        if ($data !== false && $data !== '') {
            // Session exists and has data
            $this->data = $this->unserializeData($data);
        } else {
            // Session doesn't exist in handler
            // This is normal for new sessions or expired sessions
            $this->data = [];
        }

        $this->started = true;

        // Set session cookie (ensures cookie matches current session ID)
        $this->setCookie();

        return true;
    }

    protected function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    // ----------------------------
    // Maharlika Session Methods
    // ----------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->data[$key] = $value;
        $this->save();
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        $this->ensureStarted();
        unset($this->data[$key]);
        $this->save();
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);
        $this->save();
        return $value;
    }

    public function all(): array
    {
        $this->ensureStarted();
        return $this->data;
    }

    /**
     * Get the session handler instance
     */
    public function getHandler(): SessionHandlerInterface
    {
        return $this->handler;
    }

    // ----------------------------
    // Flash Methods
    // ----------------------------

    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();

        // Store directly in session for current request
        $this->data[$key] = $value;

        // Mark for next request
        $flashNew = $this->data['_flash.new'] ?? [];
        $flashNew[] = $key;
        $this->data['_flash.new'] = array_unique($flashNew);

        $this->save();
    }

    public function flashInput(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->flash($key, $value);
        }
    }

    /**
     * Retrieve old input value from flash data
     */
    public function old(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $flashOld = $this->data['_flash.old'] ?? [];

        if (isset($this->data['old_input'][$key]) && $this->data['old_input'][$key] !== '') {
            return $this->data['old_input'][$key];
        }

        if (in_array($key, $flashOld) && isset($this->data['_flash'][$key]) && $this->data['_flash'][$key] !== '') {
            return $this->data['_flash'][$key];
        }
        if (in_array('old_input', $flashOld) && isset($this->data['_flash']['old_input'][$key]) && $this->data['_flash']['old_input'][$key] !== '') {
            return $this->data['_flash']['old_input'][$key];
        }

        return $default;
    }

    public function ageFlashData(): void
    {
        $this->ensureStarted();

        // Get flash key arrays
        $flashOld = $this->data['_flash.old'] ?? [];
        $flashNew = $this->data['_flash.new'] ?? [];

        // Remove old flash data (from previous request)
        foreach ($flashOld as $key) {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
            }
        }

        // Move "new" flash to "old" flash (available for current request)
        $this->data['_flash.old'] = $flashNew;

        // Clear "new" flash
        $this->data['_flash.new'] = [];

        $this->save();
    }

    // ----------------------------
    // CSRF Token
    // ----------------------------

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

    // ----------------------------
    // Session Management
    // ----------------------------

    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->ensureStarted();

        $oldId = $this->id;

        // Preserve important session data
        $token = $this->data['_token'] ?? null;
        $flashNew = $this->data['_flash.new'] ?? [];
        $flashOld = $this->data['_flash.old'] ?? [];
        $lastRegeneration = $this->data['_last_regeneration'] ?? null;

        if ($deleteOldSession) {
            $this->handler->destroy($oldId);
        }

        // Generate new ID
        $this->id = $this->generateSessionId();

        // Restore preserved data
        if ($token) {
            $this->data['_token'] = $token;
        }
        if (!empty($flashNew)) {
            $this->data['_flash.new'] = $flashNew;
        }
        if (!empty($flashOld)) {
            $this->data['_flash.old'] = $flashOld;
        }
        if ($lastRegeneration) {
            $this->data['_last_regeneration'] = $lastRegeneration;
        }

        $this->save();
        $this->setCookie();

        return true;
    }

    public function invalidate(): bool
    {
        $this->ensureStarted();
        $this->handler->destroy($this->id);
        $this->data = [];
        $this->started = false;
        $this->deleteCookie();
        return true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        if ($this->started) {
            throw new \RuntimeException('Cannot set session ID after session has started.');
        }
        $this->id = $id;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    // ----------------------------
    // Protected Helper Methods
    // ----------------------------

    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        $data = $this->serializeData($this->data);
        $this->handler->write($this->id, $data);
    }

    protected function serializeData(array $data): string
    {
        return serialize($data);
    }

    protected function unserializeData(string $data): array
    {
        $unserialized = @unserialize($data);
        return is_array($unserialized) ? $unserialized : [];
    }

    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function getSessionIdFromCookie(): ?string
    {
        return $_COOKIE[$this->config['name']] ?? null;
    }

    protected function setCookie(): void
    {
        $lifetime = $this->config['lifetime'] * 60;
        $expires = $lifetime > 0 ? time() + $lifetime : 0;

        setcookie(
            $this->config['name'],
            $this->id,
            [
                'expires' => $expires,
                'path' => $this->config['path'],
                'domain' => $this->config['domain'] ?? '',
                'secure' => $this->config['secure'],
                'httponly' => $this->config['http_only'],
                'samesite' => $this->config['same_site'],
            ]
        );
    }

    protected function deleteCookie(): void
    {
        setcookie(
            $this->config['name'],
            '',
            [
                'expires' => time() - 3600,
                'path' => $this->config['path'],
                'domain' => $this->config['domain'] ?? '',
            ]
        );
    }

    public function __destruct()
    {
        if ($this->started) {
            $this->save();
            $this->handler->gc($this->config['lifetime'] * 60);
        }
    }
}
