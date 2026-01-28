<?php

namespace Maharlika\Facades;

/**
 * @method static bool has(string $ability)
 * @method static \Maharlika\Contracts\Auth\Access\Gate define(\UnitEnum|string $ability, callable|string $callback)
 * @method static \Maharlika\Contracts\Auth\Access\Gate resource(string $name, string $class, array|null $abilities = null)
 * @method static \Maharlika\Contracts\Auth\Access\Gate policy(string $class, string $policy)
 * @method static \Maharlika\Contracts\Auth\Access\Gate before(callable $callback)
 * @method static \Maharlika\Contracts\Auth\Access\Gate after(callable $callback)
 * @method static bool allows(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static bool denies(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static bool check(iterable|\UnitEnum|string $abilities, mixed $arguments = [])
 * @method static bool any(iterable|\UnitEnum|string $abilities, mixed $arguments = [])
 * @method static \Maharlika\Auth\Access\Response authorize(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static \Maharlika\Auth\Access\Response inspect(\UnitEnum|string $ability, mixed $arguments = [])
 * @method static mixed raw(string $ability, mixed $arguments = [])
 * @method static mixed getPolicyFor(object|string $class)
 * @method static \Maharlika\Contracts\Auth\Access\Gate forUser(\Maharlika\Contracts\Auth\Authenticatable|mixed $user)
 * @method static array abilities()
 *
 * @see \Maharlika\Contracts\Auth\Access\Gate
 */
class Gate extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor()
    {
        return \Maharlika\Contracts\Auth\Access\Gate::class;
    }
}