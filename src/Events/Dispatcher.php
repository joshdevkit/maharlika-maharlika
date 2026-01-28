<?php

namespace Maharlika\Events;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Events\ShouldBroadcast;

class Dispatcher
{
    protected ContainerInterface $container;
    protected array $listeners = [];
    protected array $wildcards = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Register an event listener
     */
    public function listen(string|array $events, mixed $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->wildcards[$event][] = $listener;
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }

    /**
     * Dispatch an event
     */
    public function dispatch(string|object $event, mixed $payload = [], bool $halt = false): ?array
    {
        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        // If event implements ShouldBroadcast, broadcast it
        if (is_object($event) && $event instanceof ShouldBroadcast) {
            $this->broadcastEvent($event);
        }

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $this->callListener($listener, $event, $payload);

            if ($halt && !is_null($response)) {
                return [$response];
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    /**
     * Fire an event (alias for dispatch)
     */
    public function fire(string|object $event, mixed $payload = [], bool $halt = false): ?array
    {
        return $this->dispatch($event, $payload, $halt);
    }

    /**
     * Broadcast the event
     */
    protected function broadcastEvent(ShouldBroadcast $event): void
    {
        if (!$this->container->has('broadcast')) {
            return;
        }

        $broadcaster = $this->container->get('broadcast');

        // Check if should broadcast now or queue it
        if (method_exists($event, 'shouldBroadcastNow') && $event->shouldBroadcastNow()) {
            $broadcaster->broadcast($event);
        } else {
            $broadcaster->queue($event);
        }
    }

    /**
     * Parse event and payload
     */
    protected function parseEventAndPayload(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            return [$event, [$event]];
        }

        return [$event, (array) $payload];
    }

    /**
     * Get all listeners for an event
     */
    protected function getListeners(string|object $event): array
    {
        $eventName = is_string($event) ? $event : get_class($event);

        $listeners = $this->listeners[$eventName] ?? [];

        // Add wildcard listeners
        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if ($this->matchesWildcard($pattern, $eventName)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    /**
     * Check if event matches wildcard pattern
     */
    protected function matchesWildcard(string $pattern, string $event): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#', $event);
    }

    /**
     * Call a listener
     */
    protected function callListener(mixed $listener, string|object $event, array $payload): mixed
    {
        if (is_string($listener)) {
            $listener = $this->container->make($listener);
        }

        if (is_callable($listener)) {
            return $listener($event, ...$payload);
        }

        if (is_object($listener) && method_exists($listener, 'handle')) {
            return $listener->handle($event, ...$payload);
        }

        return null;
    }

    /**
     * Check if event has listeners
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) || !empty($this->getWildcardListeners($event));
    }

    /**
     * Get wildcard listeners for event
     */
    protected function getWildcardListeners(string $event): array
    {
        $listeners = [];

        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if ($this->matchesWildcard($pattern, $event)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    /**
     * Remove all listeners for an event
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Remove all registered listeners
     */
    public function forgetAll(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
    }
}
