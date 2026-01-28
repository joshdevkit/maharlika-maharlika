<?php

namespace Maharlika\Facades;

use Maharlika\Validation\ValidationFactory;

/**
 * Class Validator
 * @see \Maharlika\Validation\Validation
 */

class Validator extends Facade
{
    /**
     * Get the registered name of the component in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return ValidationFactory::class;
    }
}
