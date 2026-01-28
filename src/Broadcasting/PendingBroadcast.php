<?php

namespace Maharlika\Broadcasting;

use Maharlika\Contracts\Events\ShouldBroadcast;

class PendingBroadcast
{
    protected BroadcastManager $manager;
    protected ShouldBroadcast $event;

    public function __construct(BroadcastManager $manager, ShouldBroadcast $event)
    {
        $this->manager = $manager;
        $this->event = $event;
    }

    /**
     * Broadcast the event to everyone
     */
    public function toOthers(): self
    {
        // This would be handled by passing socket_id to exclude sender
        return $this;
    }

    /**
     * Broadcast the event immediately (synchronously)
     */
    public function now(): void
    {
        $this->manager->broadcast($this->event);
    }

    /**
     * Broadcast the event via queue (asynchronously)
     */
    public function later(): void
    {
        $this->manager->queue($this->event);
    }

    /**
     * Destructor broadcasts the event
     */
    public function __destruct()
    {
        $this->manager->queue($this->event);
    }
}
