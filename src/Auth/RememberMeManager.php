<?php

namespace Maharlika\Auth;

use Maharlika\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Maharlika\Database\FluentORM\Model;
use Maharlika\Facades\Hash;

class RememberMeManager
{
    protected string $userEntity;
    protected string $cookieName = 'remember_me';
    protected int $duration = 2592000; // 30 days

    public function __construct(string $userEntity)
    {
        $this->userEntity = $userEntity;
    }

    /**
     * Queue a remember cookie for the user
     */
    public function queue(AuthenticatableContract $user): void
    {
        $token = $this->generateToken();
        $this->updateUserToken($user, $token);
        $this->setCookie($token);
    }

    /**
     * Retrieve user from remember cookie
     */
    public function retrieve(): ?AuthenticatableContract
    {
        $token = $this->getToken();

        if (!$token) {
            return null;
        }

        $user = $this->findUserByToken($token);

        if (!$user) {
            $this->cancel();
        }

        return $user;
    }

    /**
     * Cycle the remember token (regenerate for security)
     */
    public function cycle(AuthenticatableContract $user): void
    {
        $token = $this->generateToken();
        $this->updateUserToken($user, $token);
        $this->setCookie($token);
    }

    /**
     * Clear user's remember token
     */
    public function clear(AuthenticatableContract $user): void
    {
        // Use the Authenticatable trait's setRememberToken method
        $user->setRememberToken(null);
        
        // Save changes
        if ($user instanceof Model) {
            $user->save();
        }
    }

    /**
     * Cancel the remember cookie
     */
    public function cancel(): void
    {
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie($this->cookieName, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[$this->cookieName]);
        }
    }

    /**
     * Check if remember token exists
     */
    public function hasToken(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Set remember duration
     */
    public function setDuration(int $seconds): self
    {
        $this->duration = $seconds;
        return $this;
    }

    /**
     * Generate a secure token
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get token from cookie
     */
    protected function getToken(): ?string
    {
        return $_COOKIE[$this->cookieName] ?? null;
    }

    /**
     * Set the remember cookie
     */
    protected function setCookie(string $token): void
    {
        setcookie($this->cookieName, $token, [
            'expires' => time() + $this->duration,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Update user's remember token
     */
    protected function updateUserToken(AuthenticatableContract $user, string $token): void
    {
        $hashedToken = Hash::make($token);
        
        // Use the Authenticatable trait's setRememberToken method
        $user->setRememberToken($hashedToken);
        
        // Save changes (trait only sets the attribute, doesn't persist)
        if ($user instanceof Model) {
            $user->save();
        }
    }

    /**
     * Find user by remember token
     */
    protected function findUserByToken(string $token): ?AuthenticatableContract
    {
        $model = $this->userEntity;
        $users = $model::query()->get();

        foreach ($users as $user) {
            if (!$user instanceof AuthenticatableContract) {
                continue;
            }

            // Use the Authenticatable trait's getRememberToken method
            $hashedToken = $user->getRememberToken();

            if ($hashedToken && Hash::check($token, $hashedToken)) {
                return $user;
            }
        }

        return null;
    }
}