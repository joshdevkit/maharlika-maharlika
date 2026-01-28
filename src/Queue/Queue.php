<?php

namespace Maharlika\Queue;

class Queue
{
    protected array $config;
    protected array $connections = [];
    protected string $defaultConnection;
    protected bool $shouldQuit = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'database';
    }

    /**
     * Push a job onto the queue
     */
    public function push(Job|string $job, array $data = [], ?string $queue = null): void
    {
        $connection = $this->connection();

        if (is_string($job)) {
            $job = new $job(...array_values($data));
        }

        $payload = $this->createPayload($job, $queue);

        $connection->push($payload, $queue ?? 'default', $job->delay);
    }

    /**
     * Push a job with delay
     */
    public function later(int $delay, Job|string $job, array $data = [], ?string $queue = null): void
    {
        if (is_string($job)) {
            $job = new $job(...array_values($data));
        }

        $job->delay = $delay;
        $this->push($job, [], $queue);
    }

    /**
     * Process jobs from the queue
     */
    public function work(?string $queue = null, int $sleep = 3, int $maxJobs = 0): void
    {
        $connection = $this->connection();
        $queue = $queue ?? 'default';
        $processedJobs = 0;

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->shouldQuit = true;
            });
            pcntl_signal(SIGINT, function () {
                $this->shouldQuit = true;
            });
        }

        while (!$this->shouldQuit) {
            // Get next job from queue
            $job = $connection->pop($queue);

            if ($job === null) {
                // No jobs available, sleep and continue
                sleep($sleep);

                // Check for signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                // Process the job
                $this->process($job);

                // Delete the job after successful processing
                $connection->delete($job['id']);

                $processedJobs++;

                // Check if we've reached max jobs limit
                if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                    break;
                }
            } catch (\Exception $e) {
                // Handle failed job
                $this->handleFailedJob($connection, $job, $e);
            }

            // Check for signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * Process a single job
     */
    protected function process(array $jobData): void
    {
        $job = unserialize($jobData['data']);

        if (!($job instanceof Job)) {
            throw new \RuntimeException('Invalid job data');
        }

        // Execute the job
        $job->handle();
    }

    /**
     * Handle a failed job
     */
    protected function handleFailedJob(QueueConnection $connection, array $job, \Exception $e): void
    {
        $maxTries = 3; // You can make this configurable
        $attempts = ($job['attempts'] ?? 0) + 1;

        if ($attempts >= $maxTries) {
            // Move to failed jobs table or delete
            $connection->delete($job['id']);

            // Log the failure
            if (function_exists('logger')) {
                logger()->error("Job failed after {$attempts} attempts", [
                    'job' => $job['job'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            // Retry the job with exponential backoff
            $retryDelay = $this->calculateRetryDelay($attempts);
            $connection->retry($job['id'], $attempts);

            if (function_exists('logger')) {
                logger()->warning("Job failed, retrying in {$retryDelay} seconds", [
                    'job' => $job['job'] ?? 'unknown',
                    'attempt' => $attempts,
                    'max_tries' => $maxTries,
                ]);
            }
        }
    }


    /**
     * Calculate retry delay with exponential backoff
     */
    protected function calculateRetryDelay(int $attempts): int
    {
        $baseDelay = $this->config['retry_delay'] ?? 60;

        // Exponential backoff: 60s, 120s, 240s, etc.
        return $baseDelay * pow(2, $attempts - 1);
    }

    /**
     * Stop the worker gracefully
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * Create job payload
     */
    protected function createPayload(Job $job, ?string $queue = null): array
    {
        return [
            'job' => get_class($job),
            'data' => serialize($job),
            'queue' => $queue ?? $job->queue ?? 'default',
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time() + ($job->delay ?? 0),
        ];
    }

    /**
     * Get queue connection
     */
    public function connection(?string $name = null): QueueConnection
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Resolve queue connection
     */
    protected function resolve(string $name): QueueConnection
    {
        $config = $this->config['connections'][$name] ?? [];
        $driver = $config['driver'] ?? 'database';

        return match ($driver) {
            'database' => new DatabaseQueue($config),
            'sync' => new SyncQueue($config),
            default => throw new \InvalidArgumentException("Unsupported queue driver: {$driver}"),
        };
    }
}
