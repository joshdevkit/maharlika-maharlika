<?php

namespace Maharlika\Contracts\Events;

interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array|\Maharlika\Broadcasting\Channel|string
     */
    public function broadcastOn(): array|string;

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array;

    /**
     * Get the event name for broadcasting.
     *
     * @return string|null
     */
    public function broadcastAs(): ?string;

    /**
     * Determine if this event should broadcast synchronously.
     *
     * @return bool
     */
    public function shouldBroadcastNow(): bool;
}
