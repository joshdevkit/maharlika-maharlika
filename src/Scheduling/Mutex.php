<?php

namespace Maharlika\Scheduling;

class Mutex
{
    protected string $name;
    protected string $path;

    public function __construct(string $name, ?string $path = null)
    {
        $this->name = $name;
        $this->path = $path ?: sys_get_temp_dir();
    }

    /**
     * Check if mutex exists.
     */
    public function exists(): bool
    {
        return file_exists($this->getPath());
    }

    /**
     * Create mutex file.
     */
    public function create(): bool
    {
        $path = $this->getPath();
        
        // Create directory if it doesn't exist
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Create mutex file with current timestamp
        return file_put_contents($path, time()) !== false;
    }

    /**
     * Remove mutex file.
     */
    public function forget(): bool
    {
        if ($this->exists()) {
            return @unlink($this->getPath());
        }

        return true;
    }

    /**
     * Check if mutex has expired.
     */
    public function isExpired(int $expiresAt): bool
    {
        if (!$this->exists()) {
            return true;
        }

        $createdAt = (int)file_get_contents($this->getPath());
        $expiresAtTimestamp = time() - ($expiresAt * 60); // Convert minutes to seconds

        return $createdAt < $expiresAtTimestamp;
    }

    /**
     * Get mutex file path.
     */
    protected function getPath(): string
    {
        return $this->path . '/schedule-' . sha1($this->name);
    }

    /**
     * Get creation time.
     */
    public function getCreatedAt(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        return (int)file_get_contents($this->getPath());
    }
}
