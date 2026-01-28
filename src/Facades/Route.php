<?php

namespace Maharlika\Facades;

class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     * @see Maharlika\Routing\Router
     */
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}