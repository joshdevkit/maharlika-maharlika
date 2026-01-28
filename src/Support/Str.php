<?php

namespace Maharlika\Support;

use Maharlika\Support\Traits\Macroable;
use Ramsey\Uuid\Uuid;
use voku\helper\ASCII;

class Str
{
    use Macroable;


    /**
     * The list of characters that are considered "invisible" in strings.
     *
     * @var string
     */
    const INVISIBLE_CHARACTERS = '\x{0009}\x{0020}\x{00A0}\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2000}\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}\x{202F}\x{205F}\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}\x{2065}\x{206A}\x{206B}\x{206C}\x{206D}\x{206E}\x{206F}\x{3000}\x{2800}\x{3164}\x{FEFF}\x{FFA0}\x{1D159}\x{1D173}\x{1D174}\x{1D175}\x{1D176}\x{1D177}\x{1D178}\x{1D179}\x{1D17A}\x{E0020}';

    /**
     * The cache of snake-cased words.
     */
    protected static array $snakeCache = [];

    /**
     * The cache of camel-cased words.
     */
    protected static array $camelCache = [];

    /**
     * The cache of studly-cased words.
     */
    protected static array $studlyCache = [];

    /**
     * The callback that should be used to generate UUIDs.
     *
     * @var (callable(): \Ramsey\Uuid\UuidInterface)|null
     */
    protected static $uuidFactory;

    /**
     * Convert a string to snake_case
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Convert a string to camelCase
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Convert a string to StudlyCase (PascalCase)
     */
    public static function studly(string $value): string
    {
        if (isset(static::$studlyCache[$value])) {
            return static::$studlyCache[$value];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$value] = str_replace(' ', '', $value);
    }

    /**
     * Convert a string to kebab-case
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }


    /**
     * Get the character at the specified index.
     *
     * @param  string  $subject
     * @param  int  $index
     * @return string|false
     */
    public static function charAt($subject, $index)
    {
        $length = mb_strlen($subject);

        if ($index < 0 ? $index < -$length : $index > $length - 1) {
            return false;
        }

        return mb_substr($subject, $index, 1);
    }


    /**
     * Pluralize a word
     */
    public static function plural($value, $count = 2, $prependCount = false)
    {
        if (is_countable($count)) {
            $count = count($count);
        }

        return ($prependCount ? Number::format($count) . ' ' : '') . Pluralizer::plural($value, $count);
    }

    /**
     * Pluralize the last word of an English, studly caps case string.
     *
     * @param  string  $value
     * @param  int|array|\Countable  $count
     * @return string
     */
    public static function pluralStudly($value, $count = 2)
    {
        $parts = preg_split('/(.)(?=[A-Z])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $lastWord = array_pop($parts);

        return implode('', $parts) . self::plural($lastWord, $count);
    }

    /**
     * Pluralize the last word of an English, Pascal caps case string.
     *
     * @param  string  $value
     * @param  int|array|\Countable  $count
     * @return string
     */
    public static function pluralPascal($value, $count = 2)
    {
        return static::pluralStudly($value, $count);
    }

    /**
     * Get the singular form of an English word.
     *
     * @param  string  $value
     * @return string
     */
    public static function singular($value)
    {
        return Pluralizer::singular($value);
    }

    /**
     * Match the case of the target word
     */
    protected static function matchCase(string $value, string $comparison): string
    {
        $functions = ['mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords'];

        foreach ($functions as $function) {
            if ($function($comparison) === $comparison) {
                return $function($value);
            }
        }

        return $value;
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param  string  $haystack
     * @param  string|iterable<string>  $needles
     * @param  bool  $ignoreCase
     * @return bool
     */
    public static function contains($haystack, $needles, $ignoreCase = false)
    {
        if (is_null($haystack)) {
            return false;
        }

        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Create a fluent stringable object.
     *
     * @param  string  $value
     * @return \Maharlika\Support\Stringable
     */
    public static function of(string $value)
    {
        return new \Maharlika\Support\Stringable($value);
    }

    /**
     * Replace consecutive instances of a given character with a single character in the given string.
     *
     * @param  string  $string
     * @param  array<string>|string  $characters
     * @return string
     */
    public static function deduplicate(string $string, array|string $characters = ' ')
    {
        if (is_string($characters)) {
            return preg_replace('/' . preg_quote($characters, '/') . '+/u', $characters, $string);
        }

        return array_reduce(
            $characters,
            fn($carry, $character) => preg_replace('/' . preg_quote($character, '/') . '+/u', $character, $carry),
            $string
        );
    }

    /**
     * Determine if a string starts with a given substring
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a string ends with a given substring
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limit the number of characters in a string
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    /**
     * Limit the number of words in a string
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Generate a random string
     */
    public static function random(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Convert string to title case
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert string to lowercase
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert string to uppercase
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Replace the first occurrence of a value in a string
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a value in a string
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }


    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @param  string  $value
     * @param  string  $language
     * @return string
     */
    public static function ascii($value, $language = 'en')
    {
        return ASCII::to_ascii((string) $value, $language, replace_single_chars_only: false);
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param  string  $title
     * @param  string  $separator
     * @param  string|null  $language
     * @param  array<string, string>  $dictionary
     * @return string
     */
    public static function slug($title, $separator = '-', $language = 'en', $dictionary = ['@' => 'at'])
    {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convert all dashes/undersMaharlikas into separator
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace dictionary words
        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $separator . $value . $separator;
        }

        $title = str_replace(array_keys($dictionary), array_values($dictionary), $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Get the portion of a string before a given value
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Get the portion of a string after a given value
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }


    /**
     * Remove all whitespace from both ends of a string.
     *
     * @param  string  $value
     * @param  string|null  $charlist
     * @return string
     */
    public static function trim($value, $charlist = null)
    {
        if ($charlist === null) {
            $trimDefaultCharacters = " \n\r\t\v\0";

            return preg_replace('~^[\s' . self::INVISIBLE_CHARACTERS . $trimDefaultCharacters . ']+|[\s' . self::INVISIBLE_CHARACTERS . $trimDefaultCharacters . ']+$~u', '', $value) ?? trim($value);
        }

        return trim($value, $charlist);
    }

    /**
     * Generate a UUID (version 4).
     *
     * @return \Ramsey\Uuid\UuidInterface
     */
    public static function uuid()
    {
        return static::$uuidFactory
            ? call_user_func(static::$uuidFactory)
            : Uuid::uuid4();
    }

    /**
     * Generate an ordered UUID (version 4) with timestamp prefix.
     * This provides better database indexing performance.
     *
     * @return string
     */
    public static function orderedUuid(): string
    {
        $timestamp = (int) (microtime(true) * 10000);
        $randomBytes = random_bytes(10);

        $timestampHex = str_pad(dechex($timestamp), 12, '0', STR_PAD_LEFT);
        $randomHex = bin2hex($randomBytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timestampHex . $randomHex, 0, 8),
            substr($timestampHex . $randomHex, 8, 4),
            '4' . substr($timestampHex . $randomHex, 13, 3),
            dechex(8 + (ord($randomBytes[0]) % 4)) . substr($randomHex, 3, 3),
            substr($randomHex, 6, 12)
        );
    }
}
