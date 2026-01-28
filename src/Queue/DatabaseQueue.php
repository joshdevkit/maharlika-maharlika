<?php

namespace Maharlika\Queue;

class DatabaseQueue implements PersistentQueue
{

    public function __construct(
        protected array $config
        )
    {
        
    }

    public function push(array $payload, string $queue, ?int $delay = null): void
    {
        $table = $this->config['table'] ?? 'jobs';

        app('db')->table($table)->insert([
            'queue' => $queue,
            'payload' => json_encode($payload),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $payload['available_at'],
            'created_at' => $payload['created_at'],
        ]);
    }

    public function pop(string $queue): ?array
    {
        $table = $this->config['table'] ?? 'jobs';

        $job = app('db')->table($table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereNull('reserved_at')
            ->orderBy('id')
            ->first();

        if (!$job) {
            return null;
        }

        // Convert stdClass to array
        $jobArray = (array) $job;

        // Mark as reserved
        app('db')->table($table)
            ->where('id', $jobArray['id'])
            ->update([
                'reserved_at' => time(),
                'attempts' => $jobArray['attempts'] + 1,
            ]);

        $payload = json_decode($jobArray['payload'], true);

        return [
            'id' => $jobArray['id'],
            'job' => $payload['job'],
            'data' => $payload['data'],
            'attempts' => $jobArray['attempts'] + 1,
        ];
    }

    public function delete(int $id): void
    {
        $table = $this->config['table'] ?? 'jobs';

        app('db')->table($table)
            ->where('id', $id)
            ->delete();
    }

    public function retry(int $id, int $attempts): void
    {
        $table = $this->config['table'] ?? 'jobs';
        $retryDelay = $this->config['retry_delay'] ?? 60; // Default 60 seconds

        app('db')->table($table)
            ->where('id', $id)
            ->update([
                'attempts' => $attempts,
                'reserved_at' => null, // Release the reservation
                'available_at' => time() + $retryDelay, // Retry after delay
            ]);
    }

    /**
     * Release a job back to the queue
     */
    public function release(int $id, int $delay = 0): void
    {
        $table = $this->config['table'] ?? 'jobs';

        app('db')->table($table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
            ]);
    }

    /**
     * Get the size of the queue
     */
    public function size(string $queue = 'default'): int
    {
        $table = $this->config['table'] ?? 'jobs';

        return app('db')->table($table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    /**
     * Clear all jobs from the queue
     */
    public function clear(string $queue = 'default'): void
    {
        $table = $this->config['table'] ?? 'jobs';

        app('db')->table($table)
            ->where('queue', $queue)
            ->delete();
    }

    /**
     * Move failed jobs to failed_jobs table
     */
    public function fail(int $id, \Exception $exception): void
    {
        $table = $this->config['table'] ?? 'jobs';
        $failedTable = $this->config['failed_table'] ?? 'failed_jobs';

        // Get the job
        $job = app('db')->table($table)
            ->where('id', $id)
            ->first();

        if ($job) {
            // Convert stdClass to array
            $jobArray = (array) $job;

            // Insert into failed jobs
            app('db')->table($failedTable)->insert([
                'queue' => $jobArray['queue'],
                'payload' => $jobArray['payload'],
                'exception' => $exception->getMessage(),
                'failed_at' => time(),
            ]);

            // Delete from jobs table
            $this->delete($id);
        }
    }
}