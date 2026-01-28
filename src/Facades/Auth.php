<?php

namespace Maharlika\Facades;

/**
 *
 * @method static bool validate(array $credentials) Validate a user's credentials without logging them in.
 * @method static bool attempt(array $credentials, bool $remember = false) Attempt to authenticate a user with the given credentials.
 * @method static void login(object $user, bool $remember = false) Log in the specified user instance.
 * @method static void logout() Log out the currently authenticated user.
 * @method static void logoutFromAllDevices() Log out from all devices (database sessions only).
 * @method static \Maharlika\Contracts\Auth\Authenticatable|null user() Get the currently authenticated user instance.
 * @method static bool check() Determine if the current user is authenticated.
 * @method static bool guest() Determine if no user is currently authenticated.
 * @method static mixed id() Get the ID of the currently authenticated user.
 * @method static bool viaRemember() Check if logged in via remember cookie.
 * @method static \Maharlika\Auth\AuthManager setRememberDuration(int $seconds) Set remember duration.
 * @method static mixed getUserSessions() Get all active sessions for the current user.
 * @method static bool destroySession(string $sessionId) Destroy a specific session by ID.
 *
 * @see \Maharlika\Auth\AuthManager
 * @package Maharlika\Facades
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}