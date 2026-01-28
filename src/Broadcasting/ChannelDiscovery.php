<?php

namespace Maharlika\Broadcasting;

use Maharlika\Routing\Attributes\BroadcastChannel;
use Maharlika\Routing\Attributes\PresenceChannel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

class ChannelDiscovery
{
    protected ChannelManager $manager;
    protected array $channelNamespaces = [];
    protected bool $discovered = false;

    public function __construct(ChannelManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Add a channel namespace to scan
     */
    public function addChannelNamespace(string $namespace, string $directory): void
    {
        $this->channelNamespaces[] = [
            'namespace' => rtrim($namespace, '\\'),
            'directory' => rtrim($directory, '/'),
        ];
    }

    /**
     * Discover all channels
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        foreach ($this->channelNamespaces as $config) {
            $this->scanDirectory($config['directory'], $config['namespace']);
        }

        $this->discovered = true;
    }

    /**
     * Check if channels have been discovered
     */
    public function isDiscovered(): bool
    {
        return $this->discovered || empty($this->channelNamespaces);
    }

    /**
     * Scan directory for channel authorization classes
     */
    protected function scanDirectory(string $directory, string $namespace): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->registerChannelFromFile($file->getPathname(), $namespace, $directory);
            }
        }
    }

    /**
     * Register channels from a file
     */
    protected function registerChannelFromFile(string $filepath, string $baseNamespace, string $baseDirectory): void
    {
        $relativePath = str_replace($baseDirectory, '', $filepath);
        $relativePath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $class = $baseNamespace . $relativePath;

        if (!class_exists($class)) {
            return;
        }

        $this->registerChannelsFromClass($class);
    }

    /**
     * Register channels from a class
     */
    protected function registerChannelsFromClass(string $class): void
    {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->registerChannelFromMethod($class, $method);
        }
    }

    /**
     * Register channel from a method
     */
    protected function registerChannelFromMethod(string $class, ReflectionMethod $method): void
    {
        // Check for BroadcastChannel attribute
        $broadcastAttributes = $method->getAttributes(BroadcastChannel::class);
        foreach ($broadcastAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->manager->channel($instance->channel, [$class, $method->getName()]);
        }

        // Check for PresenceChannel attribute
        $presenceAttributes = $method->getAttributes(PresenceChannel::class);
        foreach ($presenceAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->manager->channel($instance->channel, [$class, $method->getName()], true);
        }
    }
}
