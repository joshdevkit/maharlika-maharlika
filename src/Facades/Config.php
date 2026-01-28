<?php

namespace Maharlika\Facades;

/**
 * @method static get(string $key, mixed $default = null)
 * @method static set(string $key, mixed $value)
 * @method static has(string $key)
 * @method static all(): array
 * @method static load(string $path): void
 * @method static loadDirectory(string $directory): void
 */
class Config extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
