<?php

namespace Maharlika\Queue;

use Maharlika\Database\Capsule;

class Worker
{
    public function __construct(protected Queue $queue)
    {
    }

    /**
     * Process jobs from the queue
     */
    public function work(string $queue = 'default', int $maxJobs = 0): void
    {
        $processed = 0;

        while (true) {
            $job = $this->queue->connection()->pop($queue);

            if (!$job) {
                sleep(3); // Wait before checking again
                continue;
            }

            $this->process($job);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /**
     * Process a single job
     */
    protected function process(array $job): void
    {
        try {
            $payload = $job['payload'];
            $instance = unserialize($payload['data']);

            echo "Processing job: {$payload['job']}\n";

            $instance->handle();

            $this->delete($job['id']);

            echo "Job completed successfully\n";
        } catch (\Throwable $e) {
            echo "Job failed: {$e->getMessage()}\n";
            $this->failed($job['id'], $e);
        }
    }

    /**
     * Delete job from queue
     */
    protected function delete(int $id): void
    {
        $table = config('queue.connections.database.table', 'jobs');
        Capsule::table($table)->where('id', $id)->delete();
    }

    /**
     * Mark job as failed
     */
    protected function failed(int $id, \Throwable $e): void
    {
        $table = config('queue.connections.database.table', 'jobs');
        $failedTable = config('queue.failed.table', 'failed_jobs');

        $job = Capsule::table($table)->find($id);

        if ($job) {
            Capsule::table($failedTable)->insert([
                'queue' => $job['queue'],
                'payload' => $job['payload'],
                'exception' => $e->getMessage(),
                'failed_at' => time(),
            ]);

            $this->delete($id);
        }
    }
}
