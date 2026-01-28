<?php

namespace Maharlika\Session\Handlers;

use Maharlika\Contracts\Session\SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    protected string $savePath;
    
    public function __construct(string $savePath)
    {
        $this->savePath = rtrim($savePath, '/');
        $this->ensureDirectoryExists();
    }

    /**
     * Ensure the session directory exists and is writable
     */
    protected function ensureDirectoryExists(): void
    {
        if (!is_dir($this->savePath)) {
            if (!mkdir($this->savePath, 0755, true)) {
                throw new \RuntimeException("Failed to create session directory: {$this->savePath}");
            }
        }

        if (!is_writable($this->savePath)) {
            throw new \RuntimeException("Session directory is not writable: {$this->savePath}");
        }
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $file = $this->getFilePath($sessionId);
        
        if (!file_exists($file)) {
            return '';
        }
        
        $data = file_get_contents($file);
        return $data !== false ? $data : '';
    }

    public function write(string $sessionId, string $data): bool
    {
        // Ensure directory still exists (in case it was deleted during runtime)
        $this->ensureDirectoryExists();
        
        $file = $this->getFilePath($sessionId);
        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    public function destroy(string $sessionId): bool
    {
        $file = $this->getFilePath($sessionId);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        // Ensure directory exists before trying to garbage collect
        if (!is_dir($this->savePath)) {
            return 0;
        }

        $files = glob($this->savePath . '/*');
        
        if ($files === false) {
            return 0;
        }

        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxLifetime) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    protected function getFilePath(string $sessionId): string
    {
        return $this->savePath . '/' . $sessionId;
    }
}