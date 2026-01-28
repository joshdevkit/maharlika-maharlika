<?php

namespace Maharlika\Broadcasting;

use Maharlika\Broadcasting\Broadcasters\Broadcaster;
use Maharlika\Broadcasting\Broadcasters\NullBroadcaster;
use Maharlika\Broadcasting\Broadcasters\PusherBroadcaster;
use Maharlika\Broadcasting\Broadcasters\RedisBroadcaster;
use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Events\ShouldBroadcast;
use Maharlika\Queue\BroadcastJob;

class BroadcastManager
{
    protected ContainerInterface $container;
    protected array $config;
    protected array $drivers = [];
    protected ?string $defaultDriver = null;

    public function __construct(ContainerInterface $container, array $config = [])
    {
        $this->container = $container;
        $this->config = $config;
        $this->defaultDriver = $config['default'] ?? 'null';
    }

    /**
     * Get a broadcaster instance
     */
    public function connection(?string $name = null): Broadcaster
    {
        $name = $name ?? $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Resolve a broadcaster instance
     */
    protected function resolve(string $name): Broadcaster
    {
        $config = $this->config['connections'][$name] ?? [];

        if (empty($config)) {
            return new NullBroadcaster();
        }

        $driver = $config['driver'] ?? 'null';

        return match ($driver) {
            'pusher' => $this->createPusherDriver($config),
            'redis' => $this->createRedisDriver($config),
            'null' => new NullBroadcaster(),
            default => throw new \InvalidArgumentException("Unsupported broadcast driver: {$driver}"),
        };
    }

    /**
     * Create Pusher driver
     */
    protected function createPusherDriver(array $config): PusherBroadcaster
    {
        // Validate required config
        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $appId = $config['app_id'] ?? null;

        if (!$key || !$secret || !$appId) {
            throw new \InvalidArgumentException(
                'Pusher configuration is missing. 
                Please set PUSHER_APP_KEY, PUSHER_APP_SECRET, and PUSHER_APP_ID in your .env file.'
            );
        }

        $options = $config['options'] ?? [];

        // For local development on Windows - disable SSL verification
        if ($this->container->get('app')->isLocal() && PHP_OS_FAMILY === 'Windows') {
            $options['curl_options'] = [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ];
        }

        $pusher = new \Pusher\Pusher(
            $key,
            $secret,
            $appId,
            $options
        );

        $authorizer = $this->container->make(ChannelAuthorizer::class);

        return new PusherBroadcaster($pusher, $authorizer);
    }

    /**
     * Create Redis driver
     */
    protected function createRedisDriver(array $config): RedisBroadcaster
    {
        $redis = $this->container->make('redis');
        $authorizer = $this->container->make(ChannelAuthorizer::class);

        return new RedisBroadcaster($redis, $authorizer);
    }

    /**
     * Broadcast an event immediately
     */
    public function broadcast(ShouldBroadcast $event): void
    {
        $channels = $this->formatChannels($event->broadcastOn());
        $eventName = $this->getEventName($event);
        $payload = $event->broadcastWith();

        $this->connection()->broadcast($channels, $eventName, $payload);
    }

    /**
     * Queue an event for broadcasting
     */
    public function queue(ShouldBroadcast $event): void
    {
        if (!$this->container->has('queue')) {
            // If no queue, broadcast immediately
            $this->broadcast($event);
            return;
        }

        $job = new BroadcastJob($event);
        $this->container->get('queue')->push($job);
    }

    /**
     * Format channels for broadcasting
     */
    protected function formatChannels(mixed $channels): array
    {
        if (is_string($channels)) {
            $channels = [$channels];
        }

        return array_map(function ($channel) {
            if ($channel instanceof Channel) {
                return $channel->getName();
            }
            return (string) $channel;
        }, (array) $channels);
    }

    /**
     * Get event name for broadcasting
     */
    protected function getEventName(ShouldBroadcast $event): string
    {
        $name = $event->broadcastAs();

        if ($name) {
            return $name;
        }

        // Use class name without namespace
        $className = get_class($event);
        return substr($className, strrpos($className, '\\') + 1);
    }

    /**
     * Create a pending broadcast
     */
    public function event(ShouldBroadcast $event): PendingBroadcast
    {
        return new PendingBroadcast($this, $event);
    }

    /**
     * Get the default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver name
     */
    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }
}
