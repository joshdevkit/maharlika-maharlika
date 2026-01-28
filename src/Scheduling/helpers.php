<?php

if (!function_exists('windows_os')) {
    /**
     * Check if running on Windows OS.
     */
    function windows_os(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
