<?php

namespace Maharlika\Queue;

interface QueueConnection
{
    /**
     * Push a job onto the queue
     */
    public function push(array $payload, string $queue, ?int $delay = null): void;

    /**
     * Pop the next job off the queue
     */
    public function pop(string $queue): ?array;
}