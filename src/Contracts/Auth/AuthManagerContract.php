<?php

namespace Maharlika\Contracts\Auth;

use Maharlika\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * Interface AuthManagerContract
 *
 * Defines the Maharlika authentication operations that must be implemented
 * by any authentication manager class within the application.
 *
 * @package Maharlika\Contracts\Auth
 */
interface AuthManagerContract
{
    /**
     * Attempt to authenticate a user using the provided credentials.
     *
     * @param array $credentials 
     * @return bool  True if authentication is successful, false otherwise.
     */
    public function attempt(array $credentials): bool;

    /**
     * Validate a user's credentials without logging them in.
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials): bool;

    
    /**
     * Log in the specified user and store their ID in the session.
     *
     * @param object $user  The user object to log in.
     * @return void
     */
    public function login(AuthenticatableContract $user, bool $remember = false): void;

    /**
     * Log out the currently authenticated user and clear their session.
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Retrieve the currently authenticated user instance.
     *
     * @return object|null  The authenticated user, or null if no user is logged in.
     */
    public function user(): ?object;

    /**
     * Determine if a user is currently authenticated.
     *
     * @return bool  True if a user is authenticated, false otherwise.
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest (not authenticated).
     *
     * @return bool  True if no user is authenticated, false otherwise.
     */
    public function guest(): bool;

    /**
     * Retrieve the unique identifier of the authenticated user.
     *
     * @return mixed  The user ID (could be int, string, or null if no user).
     */
    public function id(): mixed;
}
