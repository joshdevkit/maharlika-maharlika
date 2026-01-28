<?php

namespace Maharlika\Auth\Access;

use Maharlika\Contracts\Auth\Access\Gate;

/**
 * Trait Authorizable
 * 
 * Provides authorization methods to determine user abilities.
 */
trait Authorizable
{
    /**
     * Determine if the entity has the given abilities.
     *
     * @param  iterable|\BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($abilities, $arguments = []): bool
    {
        return app(Gate::class)->forUser($this)->check($abilities, $arguments);
    }

    /**
     * Determine if the entity has any of the given abilities.
     *
     * @param  iterable|\BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function canAny($abilities, $arguments = []): bool
    {
        return app(Gate::class)->forUser($this)->any($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|\BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cant($abilities, $arguments = []): bool
    {
        return ! $this->can($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|\BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cannot($abilities, $arguments = []): bool
    {
        return $this->cant($abilities, $arguments);
    }
}