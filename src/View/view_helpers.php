<?php


use Maharlika\Support\HtmlString;
use Maharlika\Support\Vite;

if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string.
     * If the value is an HtmlString instance, return it as-is (don't escape).
     */
    function e($value): string
    {
        // If it's an HtmlString, don't escape it
        if ($value instanceof HtmlString) {
            return (string) $value;
        }

        // If it's null, return empty string
        if ($value === null) {
            return '';
        }

        // Otherwise, escape it
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('html')) {
    /**
     * Create an HtmlString instance.
     */
    function html(string $html): HtmlString
    {
        return new HtmlString($html);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve old input value from flash data
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $session = session();

        // Ensure session is started
        if (!$session->isStarted()) {
            $session->start();
        }

        // Debug - check what's in session
        // $allData = $session->all();
        // $flashOld = $allData['_flash.old'] ?? [];
        // $oldInputRoot = $allData['old_input'] ?? [];
        // $flashOldInput = $allData['_flash']['old_input'] ?? [];

        // error_log("=== DEBUG old('$key') ===");
        // error_log("Flash old keys: " . json_encode($flashOld));
        // error_log("Root old_input: " . json_encode($oldInputRoot));
        // error_log("Flash old_input: " . json_encode($flashOldInput));

        $result = $session->old($key, $default);

        // error_log("Result: " . json_encode($result));
        // error_log("=== END DEBUG ===");

        return $result;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash data to the session
     */
    function flash(string $key, mixed $value): void
    {
        session()->flash($key, $value);
    }
}

if (!function_exists('flash_input')) {
    /**
     * Flash all input data to the session (for old input)
     * This should be called when validation fails or before redirecting back
     */
    function flash_input(array $input): void
    {
        session()->flashInput($input);
    }
}

if (!function_exists('has_flash')) {
    /**
     * Check if a flash message exists
     */
    function has_flash(string $key): bool
    {
        /** @var \Maharlika\Session\Session $session */
        $session = app('session');
        $session->start();

        $flashOld = $session->get('_flash.old', []);
        return isset($flashOld[$key]);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value
     * 
     * @return string
     */
    function csrf_token(): string
    {
        return app('session')->token() ?? '';
    }
}


if (!function_exists('vite')) {
    /**
     * Get the Vite instance or render assets
     * 
     * @param string|array|null $entrypoints
     * @param string|null $buildDirectory
     * @return Vite|string
     */
    function vite($entrypoints = null, ?string $buildDirectory = null)
    {
        $vite = new Vite();

        if ($entrypoints === null) {
            return $vite;
        }

        return $vite->renderAssets($entrypoints, $buildDirectory);
    }
}

if (!function_exists('vite_asset')) {
    /**
     * Get a Vite asset URL
     * 
     * @param string $asset
     * @param string|null $buildDirectory
     * @return string
     */
    function vite_asset(string $asset, ?string $buildDirectory = null): string
    {
        return vite()->asset($asset, $buildDirectory);
    }
}


if (!function_exists('resource_path')) {
    function resource_path($path = "")
    {
        $app = app();
        $resourcePath = $app->basePath('resources');

        return $path ? $resourcePath . DIRECTORY_SEPARATOR . $path : $resourcePath;
    }
}
