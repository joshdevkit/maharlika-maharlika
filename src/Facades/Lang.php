<?php

namespace Maharlika\Facades;

/**
 * @method static string|array get(string $key, array $replace = [], ?string $locale = null)
 * @method static string|array trans(string $key, array $replace = [], ?string $locale = null)
 * @method static string choice(string $key, int|float $number, array $replace = [], ?string $locale = null)
 * @method static bool has(string $key, ?string $locale = null)
 * @method static void setLocale(string $locale)
 * @method static string getLocale()
 * @method static void setFallbackLocale(string $locale)
 * @method static string getFallbackLocale()
 * @method static void addLines(array $lines, string $file, string $locale)
 * @method static array getLoadedTranslations(?string $locale = null)
 * @method static void clearCache()
 *
 * @see \Maharlika\Translation\Translator
 */
class Lang extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'translator';
    }
}