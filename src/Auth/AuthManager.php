<?php

namespace Maharlika\Auth;

use Maharlika\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Maharlika\Contracts\Auth\AuthManagerContract;
use Maharlika\Database\Collection;
use Maharlika\Session\Session;
use Maharlika\Facades\Hash;
use Maharlika\Session\Handlers\DatabaseSessionHandler;
use RuntimeException;

class AuthManager implements AuthManagerContract
{
    protected Session $session;
    protected ?AuthenticatableContract $user = null;
    protected string $userEntity;
    protected string $sessionKey = 'auth_id';
    protected RememberMeManager $rememberMe;
    protected array $guards = [];
    protected ?string $currentGuard = null;

    public function __construct(Session $session, string $userEntity = 'App\\Models\\User')
    {
        $this->session = $session;
        $this->userEntity = $userEntity;
        $this->rememberMe = new RememberMeManager($userEntity);
    }

    /**
     * Get a guard instance by name.
     */
    public function guard(?string $name = null): self
    {
        $name = $name ?: $this->getDefaultGuard();

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolveGuard($name);
        }

        return $this->guards[$name];
    }

    /**
     * Resolve a guard instance.
     */
    protected function resolveGuard(string $name): self
    {
        // For now, create a new instance with a different session key
        $guard = new static($this->session, $this->userEntity);
        $guard->sessionKey = "auth_{$name}_id";
        $guard->currentGuard = $name;
        
        return $guard;
    }

    /**
     * Get the default guard name.
     */
    protected function getDefaultGuard(): string
    {
        return 'web';
    }

    /**
     * Get the current guard name.
     */
    public function getGuardName(): ?string
    {
        return $this->currentGuard;
    }

    /**
     * Validate a user's credentials without logging them in.
     */
    public function validate(array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        $password = $credentials['password'];
        unset($credentials['password']);

        if (empty($credentials)) {
            return false;
        }

        $user = $this->getUserByCredentials($credentials);

        if (!$user || !Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        return true;
    }

    /**
     * Attempt to authenticate a user using credentials.
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $this->session->start();

        if (!isset($credentials['password'])) {
            return false;
        }

        $password = $credentials['password'];
        unset($credentials['password']);

        if (empty($credentials)) {
            return false;
        }

        $user = $this->getUserByCredentials($credentials);

        if (!$user || !Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        $this->login($user, $remember);
        return true;
    }

    /**
     * Log in a user
     */
    public function login(AuthenticatableContract $user, bool $remember = false): void
    {
        $this->session->start();

        $userId = $user->getAuthIdentifier();

        if (empty($userId)) {
            throw new RuntimeException('User must have a valid authentication identifier');
        }

        $this->session->put($this->sessionKey, $userId);
        $this->session->regenerate();
        $this->user = $user;

        if (method_exists($this->session, 'save')) {
            $this->session->save();
        }

        if ($remember) {
            $this->rememberMe->queue($user);
        } else {
            $this->rememberMe->cancel();
        }
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        $this->session->start();

        $user = $this->user();

        if ($user !== null) {
            $this->rememberMe->clear($user);
        }

        $this->session->forget($this->sessionKey);
        $this->rememberMe->cancel();
        $this->session->regenerate();
        $this->user = null;
    }

    /**
     * Log out from all devices (database sessions only)
     */
    public function logoutFromAllDevices(): void
    {

        $user = $this->user();
        if ($user === null) {
            return;
        }

        // Get the session handler
        $handler = $this->getSessionHandler();

        // Only works with database session handler
        if ($handler instanceof DatabaseSessionHandler) {
            $handler->destroyUserSessions($user->getAuthIdentifier());
        }

        // Also logout from current session
        $this->logout();
    }

    /**
     * Get all active sessions for the current user (database sessions only)
     */
    public function getUserSessions()
    {

        $user = $this->user();
        if ($user === null) {
            return [];
        }

        $handler = $this->getSessionHandler();

        if ($handler instanceof DatabaseSessionHandler) {
            return $handler->getUserSessions($user->getAuthIdentifier());
        }

        return new Collection();
    }

    /**
     * Destroy a specific session by ID (database sessions only)
     */
    public function destroySession(string $sessionId): bool
    {

        $handler = $this->getSessionHandler();

        if ($handler instanceof DatabaseSessionHandler) {
            return $handler->destroy($sessionId);
        }

        return false;
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?AuthenticatableContract
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $this->session->start();
        $userId = $this->session->get($this->sessionKey);

        if (!$userId) {
            if ($this->loginViaRemember()) {
                return $this->user;
            }
            return null;
        }

        $this->user = $this->getUserById($userId);

        if (!$this->user) {
            $this->session->forget($this->sessionKey);
        }

        return $this->user;
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check if user is a guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get user ID
     */
    public function id(): mixed
    {
        $user = $this->user();
        return $user ? $user->getAuthIdentifier() : null;
    }

    /**
     * Check if logged in via remember cookie
     */
    public function viaRemember(): bool
    {
        return $this->rememberMe->hasToken() && $this->check();
    }

    /**
     * Set remember duration
     */
    public function setRememberDuration(int $seconds): self
    {
        $this->rememberMe->setDuration($seconds);
        return $this;
    }

    /**
     * Login using remember cookie
     */
    protected function loginViaRemember(): bool
    {
        $user = $this->rememberMe->retrieve();

        if (!$user) {
            return false;
        }

        $this->session->start();
        $this->session->put($this->sessionKey, $user->getAuthIdentifier());
        $this->user = $user;
        $this->rememberMe->cycle($user);

        return true;
    }

    /**
     * Get user by credentials
     */
    protected function getUserByCredentials(array $credentials): ?AuthenticatableContract
    {
        $model = $this->userEntity;
        $query = $model::query();

        foreach ($credentials as $field => $value) {
            $query->where($field, $value);
        }

        $user = $query->first();
        return ($user instanceof AuthenticatableContract) ? $user : null;
    }

    /**
     * Get user by ID
     */
    protected function getUserById(mixed $id): ?AuthenticatableContract
    {
        $model = $this->userEntity;
        $user = $model::find($id);
        return ($user instanceof AuthenticatableContract) ? $user : null;
    }

    /**
     * Get the session handler from the session
     */
    protected function getSessionHandler()
    {
        $reflection = new \ReflectionClass($this->session);
        $property = $reflection->getProperty('handler');
        return $property->getValue($this->session);
    }
}