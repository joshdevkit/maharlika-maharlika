<?php

namespace Maharlika\Contracts\Auth\Passwords;

interface PasswordBrokerContract
{
    const RESET_LINK_SENT = 'passwords.sent';
    const PASSWORD_RESET = 'passwords.reset';
    const INVALID_USER = 'passwords.user';
    const INVALID_TOKEN = 'passwords.token';
    const INVALID_PASSWORD = 'passwords.password';

    public function sendResetLink(array $credentials): string;
    public function reset(array $credentials, \Closure $callback): string;
}
