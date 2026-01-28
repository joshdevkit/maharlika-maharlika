<?php

namespace Maharlika\Translation;

use Maharlika\Contracts\ApplicationInterface;

class Translator
{
    protected ApplicationInterface $app;
    protected string $locale;
    protected string $fallbackLocale;
    protected array $loaded = [];
    protected string $translationsPath;

    public function __construct(ApplicationInterface $app, string $locale = 'en', string $fallbackLocale = 'en')
    {
        $this->app = $app;
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->translationsPath = $app->basePath('resources/lang');
    }

    /**
     * Get a translation by key
     *
     * @param string $key Format: "file.key" or "file.nested.key"
     * @param array $replace Replacement values for placeholders
     * @param string|null $locale Override locale
     * @return string|array
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string|array
    {
        $locale = $locale ?? $this->locale;

        // Parse the key to get file and nested keys
        [$file, $item] = $this->parseKey($key);

        // Load translations for this file if not already loaded
        $this->load($file, $locale);

        // Get the translation
        $line = $this->getLine($file, $item, $locale);

        // Try fallback locale if not found
        if ($line === null && $locale !== $this->fallbackLocale) {
            $this->load($file, $this->fallbackLocale);
            $line = $this->getLine($file, $item, $this->fallbackLocale);
        }

        // If still not found, return the key itself
        if ($line === null) {
            return $key;
        }

        // If it's an array, return as is
        if (is_array($line)) {
            return $line;
        }

        // Replace placeholders
        return $this->makeReplacements($line, $replace);
    }

    /**
     * Check if a translation exists
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;
        [$file, $item] = $this->parseKey($key);
        
        $this->load($file, $locale);
        $line = $this->getLine($file, $item, $locale);

        return $line !== null;
    }

    /**
     * Get translation (alias for get)
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string|array
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * Get translation with pluralization
     */
    public function choice(string $key, int|float $number, array $replace = [], ?string $locale = null): string
    {
        $line = $this->get($key, $replace, $locale);

        if (!is_string($line)) {
            return $key;
        }

        // Replace {count} placeholder
        $replace['count'] = $number;

        // Simple pluralization logic
        if (str_contains($line, '|')) {
            $line = $this->getPluralForm($line, $number);
        }

        return $this->makeReplacements($line, $replace);
    }

    /**
     * Set the current locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        app()->setLocale($locale);
    }

    /**
     * Get the current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the fallback locale
     */
    public function setFallbackLocale(string $locale): void
    {
        $this->fallbackLocale = $locale;
    }

    /**
     * Get the fallback locale
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Add translation lines dynamically
     */
    public function addLines(array $lines, string $file, string $locale): void
    {
        if (!isset($this->loaded[$locale][$file])) {
            $this->loaded[$locale][$file] = [];
        }

        $this->loaded[$locale][$file] = array_merge(
            $this->loaded[$locale][$file],
            $lines
        );
    }

    /**
     * Load translations from file
     */
    protected function load(string $file, string $locale): void
    {
        if (isset($this->loaded[$locale][$file])) {
            return;
        }

        $path = $this->getTranslationPath($locale, $file);

        if (!file_exists($path)) {
            $this->loaded[$locale][$file] = [];
            return;
        }

        $translations = require $path;

        $this->loaded[$locale][$file] = is_array($translations) ? $translations : [];
    }

    /**
     * Get translation file path
     */
    protected function getTranslationPath(string $locale, string $file): string
    {
        return "{$this->translationsPath}/{$locale}/{$file}.php";
    }

    /**
     * Parse translation key into file and item
     */
    protected function parseKey(string $key): array
    {
        $segments = explode('.', $key, 2);
        
        if (count($segments) === 1) {
            return [$segments[0], null];
        }

        return [$segments[0], $segments[1]];
    }

    /**
     * Get a line from loaded translations
     */
    protected function getLine(string $file, ?string $item, string $locale): mixed
    {
        if (!isset($this->loaded[$locale][$file])) {
            return null;
        }

        $translations = $this->loaded[$locale][$file];

        if ($item === null) {
            return $translations;
        }

        // Support nested keys using dot notation
        return $this->getNestedValue($translations, $item);
    }

    /**
     * Get nested array value using dot notation
     */
    protected function getNestedValue(array $array, string $key): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return null;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Make replacements in translation string
     */
    protected function makeReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [$value, strtoupper($value), ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * Get the appropriate plural form
     */
    protected function getPluralForm(string $line, int|float $number): string
    {
        $forms = explode('|', $line);

        // Handle complex plural rules
        foreach ($forms as $form) {
            $form = trim($form);

            // Check for range syntax: [1,5]
            if (preg_match('/^\[(\d+),(\d+)\]\s*(.+)$/', $form, $matches)) {
                if ($number >= $matches[1] && $number <= $matches[2]) {
                    return $matches[3];
                }
                continue;
            }

            // Check for exact number: {0}, {1}, {2}
            if (preg_match('/^\{(\d+)\}\s*(.+)$/', $form, $matches)) {
                if ($number == $matches[1]) {
                    return $matches[2];
                }
                continue;
            }

            // Check for wildcard: *
            if (preg_match('/^\*\s*(.+)$/', $form, $matches)) {
                return $matches[1];
            }
        }

        // Simple plural logic: first form for singular, second for plural
        if ($number === 1 || $number === 1.0) {
            return $forms[0] ?? $line;
        }

        return $forms[1] ?? $forms[0] ?? $line;
    }

    /**
     * Get all loaded translations for a locale
     */
    public function getLoadedTranslations(?string $locale = null): array
    {
        $locale = $locale ?? $this->locale;
        return $this->loaded[$locale] ?? [];
    }

    /**
     * Clear loaded translations
     */
    public function clearCache(): void
    {
        $this->loaded = [];
    }
}