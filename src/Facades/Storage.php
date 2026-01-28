<?php

namespace Maharlika\Facades;

/**
 * Class Storage
 *
 * Facade for the filesystem storage service.
 *
 * Provides a static interface to the underlying filesystem
 * implementation, allowing interaction with local or remote
 * storage drivers through a unified API.
 *
 * @method static disk(?string $name = null)
 * @method static bool exists(string $path)
 * @method static string|null get(string $path)
 * @method static bool put(string $path, string $contents, array $options = [])
 * @method static bool delete(string $paths)
 * @method static array files(string $directory = '')
 * @method static array directories(string $directory = '')
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 *
 * @package Maharlika\Facades
 */

class Storage extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'storage';
    }
}