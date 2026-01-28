<?php

namespace Maharlika\Queue;

class SyncQueue implements QueueConnection
{

    public function __construct(protected array $config)
    {
    }

    public function push(array $payload, string $queue, ?int $delay = null): void
    {
        // Execute immediately
        $job = unserialize($payload['data']);
        $job->handle();
    }

    public function pop(string $queue): ?array
    {
        return null; // Sync queue doesn't store jobs
    }
}