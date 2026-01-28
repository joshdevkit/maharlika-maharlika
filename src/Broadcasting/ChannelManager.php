<?php

namespace Maharlika\Broadcasting;

use Maharlika\Contracts\Container\ContainerInterface;

class ChannelManager
{
    protected ContainerInterface $container;
    protected array $channels = [];
    protected array $presenceChannels = [];
    protected ChannelMatcher $matcher;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->matcher = new ChannelMatcher();
    }

    /**
     * Register a channel authorization callback
     */
    public function channel(string $channel, callable|array $callback, bool $isPresence = false): void
    {
        if ($isPresence) {
            $this->presenceChannels[$channel] = $callback;
        } else {
            $this->channels[$channel] = $callback;
        }
    }

    /**
     * Find a channel authorization callback
     */
    public function find(string $channelName): ?array
    {
        $isPresence = $this->matcher->isPresenceChannel($channelName);
        $patterns = $isPresence ? $this->presenceChannels : $this->channels;

        return $this->matcher->match($channelName, $patterns);
    }

    /**
     * Check if a channel exists
     */
    public function has(string $channelName): bool
    {
        return $this->find($channelName) !== null;
    }

    /**
     * Execute channel authorization callback
     */
    public function authorize(mixed $user, string $channelName, array $params = []): mixed
    {
        $match = $this->find($channelName);

        if (!$match) {
            return false;
        }

        $callback = $match['callback'];
        $params = $match['params'];

        return $this->executeCallback($callback, $user, $params);
    }

    /**
     * Execute the authorization callback
     */
    protected function executeCallback(callable|array $callback, mixed $user, array $params): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;

            if (is_string($class)) {
                $class = $this->container->make($class);
            }

            // Bind parameters similar to route execution
            $reflection = new \ReflectionMethod($class, $method);
            $boundParams = $this->bindParameters($reflection, $user, $params);

            return $class->$method(...$boundParams);
        }

        if (is_callable($callback)) {
            return $callback($user, ...array_values($params));
        }

        return false;
    }

    /**
     * Bind method parameters
     */
    protected function bindParameters(\ReflectionMethod $reflection, mixed $user, array $params): array
    {
        $methodParams = $reflection->getParameters();
        $boundParams = [];

        foreach ($methodParams as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // First parameter is always the authenticated user
            if ($parameter->getPosition() === 0) {
                $boundParams[] = $user;
                continue;
            }

            // Try to match route parameter
            if (isset($params[$name])) {
                $value = $params[$name];

                // Type cast if needed
                if ($type && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if (class_exists($typeName)) {
                        $value = $this->container->make($typeName, ['id' => $value]);
                    }
                } elseif ($type) {
                    $value = $this->castToType($value, $type->getName());
                }

                $boundParams[] = $value;
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $boundParams[] = $parameter->getDefaultValue();
                continue;
            }

            // Try to resolve from container
            if ($type && !$type->isBuiltin()) {
                $boundParams[] = $this->container->make($type->getName());
                continue;
            }

            $boundParams[] = null;
        }

        return $boundParams;
    }

    /**
     * Cast value to type
     */
    protected function castToType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            default => $value,
        };
    }

    /**
     * Get all registered channels
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get all registered presence channels
     */
    public function getPresenceChannels(): array
    {
        return $this->presenceChannels;
    }

    /**
     * Get the channel matcher
     */
    public function getMatcher(): ChannelMatcher
    {
        return $this->matcher;
    }
}
