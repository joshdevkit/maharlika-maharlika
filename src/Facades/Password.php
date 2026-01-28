<?php

namespace Maharlika\Facades;

/**
 * @method static string sendResetLink(array $credentials)
 * @method static string reset(array $credentials, \Closure $callback)
 * @method static \Maharlika\Auth\Passwords\PasswordBroker setExpiresInMinutes(int $minutes)
 *
 * @see \Maharlika\Auth\Passwords\PasswordBroker
 */
class Password extends Facade
{
    const RESET_LINK_SENT = 'passwords.sent';
    const PASSWORD_RESET = 'passwords.reset';
    const INVALID_USER = 'passwords.user';
    const INVALID_TOKEN = 'passwords.token';
    const INVALID_PASSWORD = 'passwords.password';
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'password.broker';
    }
}
