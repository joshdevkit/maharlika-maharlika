<?php

namespace Maharlika\Publishing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Publisher
{
    protected array $publishable = [];
    protected array $publishableByTag = [];
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Register paths to be published.
     *
     * @param array $paths Array of source => destination paths
     * @param string|array|null $groups Publishing groups/tags
     * @param string|null $provider The provider class name
     */
    public function register(array $paths, $groups = null, ?string $provider = null): void
    {
        $groups = is_null($groups) ? [] : (array) $groups;

        // Register by provider
        if ($provider) {
            if (!isset($this->publishable[$provider])) {
                $this->publishable[$provider] = [];
            }
            $this->publishable[$provider] = array_merge(
                $this->publishable[$provider],
                $paths
            );
        }

        // Register by tags
        foreach ($groups as $group) {
            if (!isset($this->publishableByTag[$group])) {
                $this->publishableByTag[$group] = [];
            }
            $this->publishableByTag[$group] = array_merge(
                $this->publishableByTag[$group],
                $paths
            );
        }
    }

    /**
     * Publish assets for a specific provider.
     *
     * @param string $provider The provider class name
     * @param bool $force Whether to overwrite existing files
     * @return array Published file paths
     */
    public function publishProvider(string $provider, bool $force = false): array
    {
        if (!isset($this->publishable[$provider])) {
            throw new \InvalidArgumentException("Provider [{$provider}] has no publishable assets.");
        }

        return $this->publishPaths($this->publishable[$provider], $force);
    }

    /**
     * Publish assets for a specific tag.
     *
     * @param string $tag The tag name
     * @param bool $force Whether to overwrite existing files
     * @return array Published file paths
     */
    public function publishTag(string $tag, bool $force = false): array
    {
        if (!isset($this->publishableByTag[$tag])) {
            throw new \InvalidArgumentException("Tag [{$tag}] has no publishable assets.");
        }

        return $this->publishPaths($this->publishableByTag[$tag], $force);
    }

    /**
     * Publish all registered assets.
     *
     * @param bool $force Whether to overwrite existing files
     * @return array Published file paths
     */
    public function publishAll(bool $force = false): array
    {
        $allPaths = [];

        foreach ($this->publishable as $paths) {
            $allPaths = array_merge($allPaths, $paths);
        }

        // Remove duplicates
        $allPaths = array_unique($allPaths, SORT_REGULAR);

        return $this->publishPaths($allPaths, $force);
    }

    /**
     * Publish the given paths.
     *
     * @param array $paths Array of source => destination paths
     * @param bool $force Whether to overwrite existing files
     * @return array Published file paths
     */
    protected function publishPaths(array $paths, bool $force = false): array
    {
        $published = [];

        foreach ($paths as $source => $destination) {
            $source = $this->normalizePath($source);
            $destination = $this->normalizePath($destination);

            if (is_dir($source)) {
                $published = array_merge(
                    $published,
                    $this->publishDirectory($source, $destination, $force)
                );
            } else {
                if ($this->publishFile($source, $destination, $force)) {
                    $published[] = $destination;
                }
            }
        }

        return $published;
    }

    /**
     * Publish a single file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param bool $force Whether to overwrite existing files
     * @return bool Whether the file was published
     */
    protected function publishFile(string $source, string $destination, bool $force = false): bool
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("Source file does not exist: {$source}");
        }

        if (file_exists($destination) && !$force) {
            return false;
        }

        // Create destination directory if it doesn't exist
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        return copy($source, $destination);
    }

    /**
     * Publish an entire directory.
     *
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @param bool $force Whether to overwrite existing files
     * @return array Published file paths
     */
    protected function publishDirectory(string $source, string $destination, bool $force = false): array
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("Source directory does not exist: {$source}");
        }

        $published = [];

        // Create destination directory if it doesn't exist
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $destPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                if ($this->publishFile($item->getPathname(), $destPath, $force)) {
                    $published[] = $destPath;
                }
            }
        }

        return $published;
    }

    /**
     * Get all publishable providers.
     *
     * @return array
     */
    public function getPublishableProviders(): array
    {
        return $this->publishable;
    }

    /**
     * Get all publishable tags.
     *
     * @return array
     */
    public function getPublishableTags(): array
    {
        return $this->publishableByTag;
    }

    /**
     * Normalize a file path.
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // Convert relative paths to absolute paths
        if (!$this->isAbsolutePath($path)) {
            $path = $this->basePath . DIRECTORY_SEPARATOR . $path;
        }

        return rtrim($path, '/\\');
    }

    /**
     * Check if a path is absolute.
     *
     * @param string $path
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        return strpos($path, '/') === 0 || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path);
    }
}