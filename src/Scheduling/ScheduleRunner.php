<?php

namespace Maharlika\Scheduling;

use Maharlika\Contracts\ApplicationInterface;
use Exception;

class ScheduleRunner
{
    protected ApplicationInterface $app;
    protected bool $loggingEnabled = true;

    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Run all scheduled tasks that are due.
     */
    public function run(): array
    {
        $schedule = $this->app->get('schedule');
        $events = $schedule->dueEvents();
        $results = [];

        foreach ($events as $event) {
            $results[] = $this->runEvent($event);
        }

        return $results;
    }

    /**
     * Run a single scheduled event.
     */
    protected function runEvent(Event $event): array
    {
        $startTime = microtime(true);
        $success = true;
        $output = null;
        $error = null;

        try {
            $event->run();
            $output = $this->getEventOutput($event);
        } catch (Exception $e) {
            $success = false;
            $error = $e->getMessage();
            $this->logError($event, $e);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $result = [
            'command' => $event->buildCommand(),
            'description' => $event->getSummary()['description'] ?? null,
            'success' => $success,
            'duration' => $duration . 'ms',
            'output' => $output,
            'error' => $error,
            'ran_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->loggingEnabled) {
            $this->logResult($result);
        }

        return $result;
    }

    /**
     * Get event output if available.
     */
    protected function getEventOutput(Event $event): ?string
    {
        $summary = $event->getSummary();
        
        if (!isset($summary['output'])) {
            return null;
        }

        $outputFile = $summary['output'];

        if (file_exists($outputFile)) {
            return file_get_contents($outputFile);
        }

        return null;
    }

    /**
     * Log error.
     */
    protected function logError(Event $event, Exception $e): void
    {
        if ($this->app->has('log')) {
            $logger = $this->app->get('log');
            $logger->error('Scheduled task failed', [
                'command' => $event->buildCommand(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log result to database if available.
     */
    protected function logResult(array $result): void
    {
        try {
            if ($this->app->has('db')) {
                $db = $this->app->get('db');
                
                $db->table('schedule_runs')->insert([
                    'command' => $result['command'],
                    'description' => $result['description'],
                    'success' => $result['success'],
                    'duration' => $result['duration'],
                    'output' => $result['output'],
                    'error' => $result['error'],
                    'ran_at' => $result['ran_at'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (Exception $e) {
            // Silently fail if logging to database fails
        }
    }

    /**
     * Enable/disable logging.
     */
    public function setLogging(bool $enabled): self
    {
        $this->loggingEnabled = $enabled;
        return $this;
    }
}
