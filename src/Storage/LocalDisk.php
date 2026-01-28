<?php

namespace Maharlika\Storage;

use Maharlika\Contracts\Storage\Disk;
use Maharlika\Exceptions\Storage\StorageException;
use Maharlika\Exceptions\Storage\FileNotFoundException;
use Maharlika\Exceptions\Storage\DirectoryNotFoundException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LocalDisk implements Disk
{
    protected string $root;
    protected string $visibility;
    protected ?string $url = null;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'] ?? storage_path('app'), '\/');
        $this->visibility = $config['visibility'] ?? 'private';
        $this->url = $config['url'] ?? null;

        if (!is_dir($this->root)) {
            if (!mkdir($this->root, 0755, true)) {
                throw new StorageException("Failed to create root directory: {$this->root}");
            }
        }
    }

    public function exists(string $path): bool
    {
        $this->validatePath($path);
        return file_exists($this->path($path));
    }

    public function get(string $path): ?string
    {
        $this->validatePath($path);
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File does not exist: {$path}");
        }

        if (!is_readable($fullPath)) {
            throw new StorageException("File is not readable: {$path}");
        }

        $contents = file_get_contents($fullPath);
        
        if ($contents === false) {
            throw new StorageException("Failed to read file: {$path}");
        }

        return $contents;
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $this->validatePath($path);
        
        $fullPath = $this->path($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new StorageException("Failed to create directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new StorageException("Directory is not writable: {$directory}");
        }

        $result = file_put_contents($fullPath, $contents);
        
        if ($result === false) {
            throw new StorageException("Failed to write file: {$path}");
        }

        if (isset($options['visibility'])) {
            $this->setVisibility($path, $options['visibility']);
        }

        return true;
    }

    public function delete(string $path): bool
    {
        $this->validatePath($path);
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File does not exist: {$path}");
        }

        if (!is_file($fullPath)) {
            throw new StorageException("Path is not a file: {$path}");
        }

        if (!unlink($fullPath)) {
            throw new StorageException("Failed to delete file: {$path}");
        }

        return true;
    }

    public function copy(string $from, string $to): bool
    {
        $this->validatePath($from);
        $this->validatePath($to);
        
        $fromPath = $this->path($from);
        $toPath = $this->path($to);

        if (!file_exists($fromPath)) {
            throw new FileNotFoundException("Source file does not exist: {$from}");
        }

        if (!is_readable($fromPath)) {
            throw new StorageException("Source file is not readable: {$from}");
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new StorageException("Failed to create destination directory: {$directory}");
            }
        }

        if (!copy($fromPath, $toPath)) {
            throw new StorageException("Failed to copy file from {$from} to {$to}");
        }

        return true;
    }

    public function move(string $from, string $to): bool
    {
        $this->validatePath($from);
        $this->validatePath($to);
        
        $fromPath = $this->path($from);
        $toPath = $this->path($to);

        if (!file_exists($fromPath)) {
            throw new FileNotFoundException("Source file does not exist: {$from}");
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new StorageException("Failed to create destination directory: {$directory}");
            }
        }

        if (!rename($fromPath, $toPath)) {
            throw new StorageException("Failed to move file from {$from} to {$to}");
        }

        return true;
    }

    public function size(string $path): int
    {
        $this->validatePath($path);
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File does not exist: {$path}");
        }

        $size = filesize($fullPath);
        
        if ($size === false) {
            throw new StorageException("Failed to get file size: {$path}");
        }

        return $size;
    }

    public function lastModified(string $path): int
    {
        $this->validatePath($path);
        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File does not exist: {$path}");
        }

        $time = filemtime($fullPath);
        
        if ($time === false) {
            throw new StorageException("Failed to get file modification time: {$path}");
        }

        return $time;
    }

    public function files(?string $directory = null): array
    {
        if ($directory !== null) {
            $this->validatePath($directory);
        }
        
        $path = $this->path($directory ?? '');

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("Directory does not exist: " . ($directory ?? '/'));
        }

        if (!is_readable($path)) {
            throw new StorageException("Directory is not readable: " . ($directory ?? '/'));
        }

        $files = [];
        $items = scandir($path);
        
        if ($items === false) {
            throw new StorageException("Failed to scan directory: " . ($directory ?? '/'));
        }

        foreach ($items as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_file($fullPath)) {
                $relativePath = $directory ? $directory . '/' . $file : $file;
                $files[] = str_replace('\\', '/', $relativePath);
            }
        }

        return $files;
    }

    public function allFiles(?string $directory = null): array
    {
        if ($directory !== null) {
            $this->validatePath($directory);
        }
        
        $path = $this->path($directory ?? '');

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("Directory does not exist: " . ($directory ?? '/'));
        }

        if (!is_readable($path)) {
            throw new StorageException("Directory is not readable: " . ($directory ?? '/'));
        }

        try {
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $files[] = str_replace('\\', '/', $relativePath);
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new StorageException("Failed to iterate directory: " . ($directory ?? '/') . " - " . $e->getMessage());
        }
    }

    public function directories(?string $directory = null): array
    {
        if ($directory !== null) {
            $this->validatePath($directory);
        }
        
        $path = $this->path($directory ?? '');

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("Directory does not exist: " . ($directory ?? '/'));
        }

        if (!is_readable($path)) {
            throw new StorageException("Directory is not readable: " . ($directory ?? '/'));
        }

        $directories = [];
        $items = scandir($path);
        
        if ($items === false) {
            throw new StorageException("Failed to scan directory: " . ($directory ?? '/'));
        }

        foreach ($items as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($fullPath)) {
                $relativePath = $directory ? $directory . '/' . $dir : $dir;
                $directories[] = str_replace('\\', '/', $relativePath);
            }
        }

        return $directories;
    }

    public function allDirectories(?string $directory = null): array
    {
        if ($directory !== null) {
            $this->validatePath($directory);
        }
        
        $path = $this->path($directory ?? '');

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("Directory does not exist: " . ($directory ?? '/'));
        }

        if (!is_readable($path)) {
            throw new StorageException("Directory is not readable: " . ($directory ?? '/'));
        }

        try {
            $directories = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $relativePath = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $directories[] = str_replace('\\', '/', $relativePath);
                }
            }

            return $directories;
        } catch (\Exception $e) {
            throw new StorageException("Failed to iterate directory: " . ($directory ?? '/') . " - " . $e->getMessage());
        }
    }

    public function makeDirectory(string $path): bool
    {
        $this->validatePath($path);
        $fullPath = $this->path($path);

        if (is_dir($fullPath)) {
            return true;
        }

        if (file_exists($fullPath)) {
            throw new StorageException("Path exists but is not a directory: {$path}");
        }

        if (!mkdir($fullPath, 0755, true)) {
            throw new StorageException("Failed to create directory: {$path}");
        }

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        $this->validatePath($directory);
        $path = $this->path($directory);

        if (!is_dir($path)) {
            throw new DirectoryNotFoundException("Directory does not exist: {$directory}");
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    if (!rmdir($file->getPathname())) {
                        throw new StorageException("Failed to delete directory: {$file->getPathname()}");
                    }
                } else {
                    if (!unlink($file->getPathname())) {
                        throw new StorageException("Failed to delete file: {$file->getPathname()}");
                    }
                }
            }

            if (!rmdir($path)) {
                throw new StorageException("Failed to delete directory: {$directory}");
            }

            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to delete directory: {$directory} - " . $e->getMessage());
        }
    }

    public function url(?string $path): string
    {
        if (empty($path)) {
            return '';
        }

        $this->validatePath($path);

        if ($this->url) {
            return rtrim($this->url, '/') . '/' . ltrim($path, '/');
        }

        if (str_contains($this->root, 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public')) {
            return '/storage/' . ltrim($path, '/');
        }

        return '/storage/' . ltrim($path, '/');
    }

    public function path(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, '\/');
    }

    protected function setVisibility(string $path, string $visibility): bool
    {
        $this->validatePath($path);
        
        if (!in_array($visibility, ['public', 'private'], true)) {
            throw new StorageException("Invalid visibility value: {$visibility}. Must be 'public' or 'private'.");
        }

        $fullPath = $this->path($path);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File does not exist: {$path}");
        }

        $permissions = $visibility === 'public' ? 0644 : 0600;

        if (!chmod($fullPath, $permissions)) {
            throw new StorageException("Failed to set visibility for: {$path}");
        }

        return true;
    }

    /**
     * Validate that the path is not empty and doesn't contain dangerous characters
     */
    protected function validatePath(string $path): void
    {
        if (empty(trim($path))) {
            throw new StorageException("Path cannot be empty");
        }

        // Prevent directory traversal attacks
        if (str_contains($path, '..')) {
            throw new StorageException("Path cannot contain '..' (directory traversal)");
        }

        // Prevent null bytes
        if (str_contains($path, "\0")) {
            throw new StorageException("Path cannot contain null bytes");
        }
    }
}