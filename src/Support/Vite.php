<?php

declare(strict_types=1);

namespace Maharlika\Support;

use RuntimeException;

/**
 * Handles both development (with HMR) and production builds
 */
class Vite
{
    protected string $buildDirectory = 'build';
    protected string $hotFile = 'hot';
    protected string $manifestFile = 'manifest.json';
    protected ?array $manifest = null;
    protected ?string $nonce = null;

    /**
     * Get the Vite development server URL or null if not running
     */
    public function getDevServerUrl(): ?string
    {
        $hotFilePath = public_path($this->hotFile); // Looks for public/hot

        if (file_exists($hotFilePath)) {
            $url = trim(file_get_contents($hotFilePath));
            return $url ?: null;
        }

        return null;
    }

    /**
     * Check if Vite development server is running
     */
    public function isRunningHot(): bool
    {
        return $this->getDevServerUrl() !== null;
    }

    /**
     * Generate HTML tags for Vite assets
     * 
     * @param string|array $entrypoints
     * @param string|null $buildDirectory
     * @return string
     * @throws RuntimeException
     */
    public function renderAssets($entrypoints, ?string $buildDirectory = null): string
    {
        if ($buildDirectory) {
            $this->buildDirectory = $buildDirectory;
        }

        $entrypoints = is_array($entrypoints) ? $entrypoints : [$entrypoints];

        if ($this->isRunningHot()) {
            return $this->makeTagsForDevelopment($entrypoints);
        }

        return $this->makeTags($entrypoints);
    }

    /**
     * Generate script/link tags for development (with HMR)
     */
    protected function makeTagsForDevelopment(array $entrypoints): string
    {
        $devServerUrl = $this->getDevServerUrl();
        $tags = [];

        // Vite client for HMR
        $tags[] = $this->makeScriptTag("{$devServerUrl}/@vite/client");

        // Entry points
        foreach ($entrypoints as $entrypoint) {
            $tags[] = $this->makeScriptTag("{$devServerUrl}/{$entrypoint}");
        }

        return implode("\n    ", $tags);
    }

    /**
     * Generate script/link tags for production build
     * 
     * @throws RuntimeException
     */
    protected function makeTags(array $entrypoints): string
    {
        $manifest = $this->getManifest();

        if (!$manifest) {
            throw new RuntimeException(
                "Vite manifest not found at: " . public_path("{$this->buildDirectory}/{$this->manifestFile}") . ""
            );
        }

        $tags = [];
        $processedFiles = [];
        $missingEntrypoints = [];

        foreach ($entrypoints as $entrypoint) {
            if (!isset($manifest[$entrypoint])) {
                $missingEntrypoints[] = $entrypoint;
                continue;
            }

            $entry = $manifest[$entrypoint];

            // Add CSS files
            if (isset($entry['css'])) {
                foreach ($entry['css'] as $css) {
                    if (!in_array($css, $processedFiles)) {
                        $tags[] = $this->makeStylesheetTag($this->assetUrl($css));
                        $processedFiles[] = $css;
                    }
                }
            }

            // Add main file
            if (isset($entry['file'])) {
                $file = $entry['file'];
                if (!in_array($file, $processedFiles)) {
                    if (str_ends_with($file, '.css')) {
                        $tags[] = $this->makeStylesheetTag($this->assetUrl($file));
                    } else {
                        $tags[] = $this->makeScriptTag($this->assetUrl($file));
                    }
                    $processedFiles[] = $file;
                }
            }


            // Add preload for imports
            if (isset($entry['imports'])) {
                foreach ($entry['imports'] as $import) {
                    if (isset($manifest[$import]['file'])) {
                        $importFile = $manifest[$import]['file'];
                        if (!in_array($importFile, $processedFiles)) {
                            $tags[] = $this->makePreloadTag($this->assetUrl($importFile));
                            $processedFiles[] = $importFile;
                        }
                    }
                }
            }
        }

        // Throw exception if any entrypoints are missing
        if (!empty($missingEntrypoints)) {
            throw new RuntimeException(
                "Unable to locate file(s) in Vite manifest: " . implode(', ', $missingEntrypoints) . ". " .
                    "Available entries: " . implode(', ', array_keys($manifest))
            );
        }

        return implode("\n    ", $tags);
    }

    /**
     * Get the manifest file contents
     * 
     * @return array|null
     */
    protected function getManifest(): ?array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifestPath = public_path("{$this->buildDirectory}/{$this->manifestFile}");

        if (!file_exists($manifestPath)) {
            return null;
        }

        $manifestContent = file_get_contents($manifestPath);
        $this->manifest = json_decode($manifestContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Failed to parse Vite manifest at: {$manifestPath}. " .
                    "Error: " . json_last_error_msg()
            );
        }

        return $this->manifest;
    }

    /**
     * Generate asset URL for production
     */
    protected function assetUrl(string $path): string
    {
        return asset("{$this->buildDirectory}/{$path}");
    }

    /**
     * Create a script tag
     */
    protected function makeScriptTag(string $url): string
    {
        $nonce = $this->nonce ? " nonce=\"{$this->nonce}\"" : '';
        return "<script type=\"module\" src=\"{$url}\"{$nonce}></script>";
    }

    /**
     * Create a stylesheet link tag
     */
    protected function makeStylesheetTag(string $url): string
    {
        $nonce = $this->nonce ? " nonce=\"{$this->nonce}\"" : '';
        return "<link rel=\"stylesheet\" href=\"{$url}\"{$nonce}>";
    }

    /**
     * Create a preload link tag
     */
    protected function makePreloadTag(string $url): string
    {
        $nonce = $this->nonce ? " nonce=\"{$this->nonce}\"" : '';
        return "<link rel=\"modulepreload\" href=\"{$url}\"{$nonce}>";
    }

    /**
     * Set CSP nonce for script/style tags
     */
    public function useCspNonce(?string $nonce = null): self
    {
        $this->nonce = $nonce;
        return $this;
    }

    /**
     * Set custom build directory
     */
    public function useBuildDirectory(string $directory): self
    {
        $this->buildDirectory = trim($directory, '/');
        return $this;
    }

    /**
     * Set custom manifest file name
     */
    public function useManifestFilename(string $filename): self
    {
        $this->manifestFile = $filename;
        return $this;
    }

    /**
     * Get the URL for a specific asset from the manifest
     * 
     * @throws RuntimeException
     */
    public function asset(string $asset, ?string $buildDirectory = null): string
    {
        if ($buildDirectory) {
            $this->buildDirectory = $buildDirectory;
        }

        if ($this->isRunningHot()) {
            return "{$this->getDevServerUrl()}/{$asset}";
        }

        $manifest = $this->getManifest();

        if (!$manifest) {
            throw new RuntimeException(
                "Vite manifest not found. Cannot locate asset: {$asset}"
            );
        }

        if (!isset($manifest[$asset]['file'])) {
            throw new RuntimeException(
                "Unable to locate file in Vite manifest: {$asset}. " .
                    "Available entries: " . implode(', ', array_keys($manifest))
            );
        }

        return $this->assetUrl($manifest[$asset]['file']);
    }

    /**
     * Generate React Refresh preamble for development
     */
    public function reactRefresh(): string
    {
        if (!$this->isRunningHot()) {
            return '';
        }

        $devServerUrl = $this->getDevServerUrl();

        return <<<HTML
        <script type="module">
            import RefreshRuntime from '{$devServerUrl}/@react-refresh'
            RefreshRuntime.injectIntoGlobalHook(window)
            window.\$RefreshReg$ = () => {}
            window.\$RefreshSig$ = () => (type) => type
            window.__vite_plugin_react_preamble_installed__ = true
        </script>
        HTML;
    }
}
