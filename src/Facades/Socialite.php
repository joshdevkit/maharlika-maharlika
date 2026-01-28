<?php

namespace Maharlika\Facades;

/**
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface driver(string $driver = null)
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface extend(string $driver, \Closure $callback)
 * @method static \Maharlika\Http\RedirectResponse redirect()
 * @method static \Maharlika\Contracts\Socialite\UserInterface user()
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface scopes(array $scopes)
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface setScopes(array $scopes)
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface stateless()
 * @method static \Maharlika\Contracts\Socialite\ProviderInterface with(array $parameters)
 *
 * @see \Maharlika\Socialite\SocialiteManager
 */
class Socialite extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'socialite';
    }
}