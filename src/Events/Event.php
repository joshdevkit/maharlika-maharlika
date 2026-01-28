<?php

namespace Maharlika\Events;

abstract class Event
{
    /**
     * The name of the queue connection to use when broadcasting.
     */
    public ?string $connection = null;

    /**
     * The name of the queue to use when broadcasting.
     */
    public ?string $queue = null;

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): ?string
    {
        return null;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [];
    }

    /**
     * Determine if this event should broadcast synchronously.
     */
    public function shouldBroadcastNow(): bool
    {
        return false;
    }
}
