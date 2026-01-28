<?php

namespace Maharlika\Scheduling;

use Maharlika\Contracts\ApplicationInterface;

class Schedule
{
    protected ApplicationInterface $app;
    protected array $events = [];

    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Schedule a command to run.
     */
    public function command(string $command, array $parameters = []): Event
    {
        return $this->exec(
            $this->buildCommand($command, $parameters)
        );
    }

    /**
     * Schedule a shell command to run.
     */
    public function exec(string $command, array $parameters = []): Event
    {
        if (count($parameters)) {
            $command .= ' ' . $this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($this->app, $command);

        return $event;
    }

    /**
     * Schedule a closure to run.
     */
    public function call(callable|string $callback, array $parameters = []): CallbackEvent
    {
        $this->events[] = $event = new CallbackEvent($this->app, $callback, $parameters);

        return $event;
    }

    /**
     * Schedule a job to be pushed to the queue.
     */
    public function job(string $job, ?string $queue = null): Event
    {
        return $this->call(function () use ($job, $queue) {
            $instance = $this->app->make($job);
            
            if ($queue) {
                $this->app->get('queue')->push($instance, $queue);
            } else {
                $this->app->get('queue')->push($instance);
            }
        })->name($job);
    }

    /**
     * Get all scheduled events that are due.
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, function (Event $event) {
            return $event->isDue();
        });
    }

    /**
     * Get all scheduled events.
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Build the command string for a framework command.
     */
    protected function buildCommand(string $command, array $parameters = []): string
    {
        $binary = PHP_BINARY;
        $script = $this->app->basePath('Maharlika');

        $cmd = sprintf('%s %s %s', $binary, $script, $command);

        if (count($parameters)) {
            $cmd .= ' ' . $this->compileParameters($parameters);
        }

        return $cmd;
    }

    /**
     * Compile parameters for command.
     */
    protected function compileParameters(array $parameters): string
    {
        return implode(' ', array_map(function ($key, $value) {
            if (is_numeric($key)) {
                return is_numeric($value) ? $value : escapeshellarg($value);
            }

            $value = is_numeric($value) ? $value : escapeshellarg($value);

            return "--{$key}={$value}";
        }, array_keys($parameters), $parameters));
    }
}
