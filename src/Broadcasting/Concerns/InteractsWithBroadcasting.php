<?php

namespace Maharlika\Broadcasting\Concerns;

trait InteractsWithBroadcasting
{
    /**
     * The event's broadcast name.
     *
     * @return string|null
     */
    public function broadcastAs(): ?string
    {
        return null;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        // By default, broadcast all public properties
        $properties = [];

        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            $properties[$name] = $this->{$name};
        }

        return $properties;
    }

    /**
     * Determine if this event should broadcast now (synchronously).
     *
     * @return bool
     */
    public function shouldBroadcastNow(): bool
    {
        return false; // Default to queued broadcasting
    }
}
