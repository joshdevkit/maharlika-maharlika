<?php

namespace Maharlika\Support\Traits;

trait Queueable
{
    public ?string $queue = null;
    public ?string $connection = null;
    public ?int $delay = null;

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = $seconds;
        return $this;
    }

    /**
     * Dispatch the mailable to the queue
     */
    public function dispatch(): void
    {
        $job = new \Maharlika\Queue\SendEmailJob($this);

        queue()->push($job);
    }

    /**
     * Get constructor arguments for serialization
     */
    protected function getConstructorArguments(): array
    {
        $reflection = new \ReflectionClass($this);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return [];
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (property_exists($this, $name)) {
                $args[$name] = $this->$name;
            }
        }

        return $args;
    }
}
