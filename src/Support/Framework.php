<?php

namespace Maharlika\Support;

class Framework
{
    /**
     * Get the base path of the Maharlika framework.
     *
     * @param string $path
     * @return string
     */
    public static function path(string $path = ''): string
    {
        $frameworkBase = dirname(__DIR__);

        if (empty($path)) {
            return $frameworkBase;
        }

        // Normalize path separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        return $frameworkBase . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Get the path to framework stubs.
     *
     * @param string $stub
     * @return string
     */
    public static function stub(string $stub): string
    {
        return static::path("Console/Commands/stubs/{$stub}");
    }
}