<?php

namespace Maharlika\Facades;

/**
 * Session Facade
 * 
 * @method static void start()
 * @method static string getId()
 * @method static void setId(string $id)
 * @method static bool has(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array all()
 * @method static void put(string|array $key, mixed $value = null)
 * @method static void push(string $key, mixed $value)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void forget(string|array $keys)
 * @method static void flush()
 * @method static void flash(string $key, mixed $value)
 * @method static void reflash()
 * @method static void keep(array|string $keys)
 * @method static mixed old(string $key = null, mixed $default = null)
 * @method static bool exists(string $key)
 * @method static bool missing(string $key)
 * @method static void regenerate(bool $destroy = false)
 * @method static bool invalidate()
 * @method static bool migrate(bool $destroy = false)
 * @method static bool isStarted()
 * @method static string|null previousUrl()
 * @method static void setPreviousUrl(string $url)
 * @method static void save()
 * @method static void ageFlashData()
 * @method static array getOldInput(string $key = null, mixed $default = null)
 * @method static void setOldInput(array $value)
 * @method static bool hasOldInput(string $key = null)
 * @method static string token()
 * @method static void regenerateToken()
 * @method static string getSessionConfigName()
 * @method static mixed handler()
 * @method static void setExists(bool $value)
 * @method static string getName()
 * @method static void setName(string $name)
 * @method static void increment(string $key, int $amount = 1)
 * @method static void decrement(string $key, int $amount = 1)
 * @method static void now(string $key, mixed $value)
 * @method static array remove(string $key)
 * 
 * @see \Maharlika\Session\Session
 */
class Session extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'session';
    }
}