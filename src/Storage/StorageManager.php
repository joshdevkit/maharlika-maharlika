<?php

namespace Maharlika\Storage;

use Maharlika\Contracts\Storage\Disk;

class StorageManager
{
    protected array $config;
    protected string $basePath;
    protected array $disks = [];

    public function __construct(array $config, string $basePath)
    {
        $this->config = $config;
        $this->basePath = rtrim($basePath, '\/');
    }

    /**
     * Get a disk instance.
     */
    public function disk(?string $name = null): Disk
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolve($name);
        }

        return $this->disks[$name];
    }

    /**
     * Resolve the given disk.
     */
    protected function resolve(string $name): Disk
    {
        $config = $this->config['disks'][$name] ?? null;

        if (!$config) {
            throw new \InvalidArgumentException("Disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => new LocalDisk($config),
            'public' => new LocalDisk($config),
            default => throw new \InvalidArgumentException("Driver [{$driver}] is not supported."),
        };
    }

    /**
     * Get the default driver name.
     */
    protected function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'local';
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}