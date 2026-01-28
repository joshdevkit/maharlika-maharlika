<?php

namespace Maharlika\Queue;

interface PersistentQueue extends QueueConnection
{
    /**
     * Delete a job from the queue
     */
    public function delete(int $id): void;

    /**
     * Retry a failed job
     */
    public function retry(int $id, int $attempts): void;
}
