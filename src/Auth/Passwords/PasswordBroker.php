<?php

namespace Maharlika\Auth\Passwords;

use Maharlika\Facades\Hash;
use Maharlika\Support\Str;
use Maharlika\Contracts\Auth\Passwords\PasswordBrokerContract;

class PasswordBroker
{
    protected string $userEntity;
    protected int $expiresInMinutes = 60;

    public function __construct(string $userEntity = 'App\\Models\\User')
    {
        $this->userEntity = $userEntity;
    }

    /**
     * Send a password reset link to a user.
     */
    public function sendResetLink(array $credentials): string
    {
        $user = $this->getUser($credentials);

        if (!$user) {
            return PasswordBrokerContract::INVALID_USER;
        }

        // Delete old tokens
        $this->deleteExistingTokens($user);

        // Create new token
        $token = $this->createToken($user);

        // Send notification
        $user->sendPasswordResetNotification($token);

        return PasswordBrokerContract::RESET_LINK_SENT;
    }

    /**
     * Reset the password for the given token.
     */
    public function reset(array $credentials, \Closure $callback): string
    {
        $user = $this->validateReset($credentials);

        if (!$user) {
            return PasswordBrokerContract::INVALID_TOKEN;
        }

        // Call the callback to actually reset the password
        $callback($user, $credentials['password']);

        // Delete the token
        $this->deleteToken($user);

        return PasswordBrokerContract::PASSWORD_RESET;
    }

    /**
     * Validate a password reset for the given credentials.
     */
    protected function validateReset(array $credentials): mixed
    {
        if (!isset($credentials['email'], $credentials['token'], $credentials['password'])) {
            return null;
        }

        $user = $this->getUser(['email' => $credentials['email']]);

        if (!$user) {
            return null;
        }

        $token = $this->getToken($user);

        if (!$token || !$this->tokenValid($token, $credentials['token'])) {
            return null;
        }

        return $user;
    }

    /**
     * Create a new password reset token.
     */
    protected function createToken($user): string
    {
        $token = Str::random(60);

        PasswordResetToken::create([
            'email' => $user->getEmailForPasswordReset(),
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Get the password reset token record.
     */
    protected function getToken($user): ?PasswordResetToken
    {
        return PasswordResetToken::where('email', $user->getEmailForPasswordReset())
            ->first();
    }

    /**
     * Check if token is valid.
     */
    protected function tokenValid(?PasswordResetToken $token, string $inputToken): bool
    {
        if (!$token) {
            return false;
        }

        // Check if token is expired
        if ($this->tokenExpired($token)) {
            return false;
        }

        return Hash::check($inputToken, $token->token);
    }

    /**
     * Check if token has expired.
     */
    protected function tokenExpired(PasswordResetToken $token): bool
    {
        $expiresAt = $token->created_at->addMinutes($this->expiresInMinutes);
        return now()->isAfter($expiresAt);
    }

    /**
     * Delete existing tokens for user.
     */
    protected function deleteExistingTokens($user): void
    {
        PasswordResetToken::where('email', $user->getEmailForPasswordReset())
            ->delete();
    }

    /**
     * Delete token after successful reset.
     */
    protected function deleteToken($user): void
    {
        $this->deleteExistingTokens($user);
    }

    /**
     * Get user by credentials.
     */
    protected function getUser(array $credentials): mixed
    {
        $model = $this->userEntity;
        $query = $model::query();

        foreach ($credentials as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    /**
     * Set token expiration time in minutes.
     */
    public function setExpiresInMinutes(int $minutes): self
    {
        $this->expiresInMinutes = $minutes;
        return $this;
    }
}
