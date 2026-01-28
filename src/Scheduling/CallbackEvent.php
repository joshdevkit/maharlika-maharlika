<?php

namespace Maharlika\Scheduling;

use Maharlika\Contracts\ApplicationInterface;
use Closure;

class CallbackEvent extends Event
{
    protected $callback;
    protected array $parameters;

    public function __construct(ApplicationInterface $app, callable|string $callback, array $parameters = [])
    {
        $this->callback = $callback;
        $this->parameters = $parameters;

        parent::__construct($app, $this->buildCommand());
    }

    /**
     * Run the callback event.
     */
    public function run(): void
    {
        if ($this->withoutOverlapping && !$this->createMutex()) {
            return;
        }

        try {
            $this->callBeforeCallbacks();

            $response = $this->runCallback();

            $this->callAfterCallbacks();

            // Store output if specified
            if ($this->output && $response) {
                $this->storeOutput($response);
            }
        } finally {
            if ($this->withoutOverlapping) {
                $this->removeMutex();
            }
        }
    }

    /**
     * Run the callback.
     */
    protected function runCallback()
    {
        if (is_string($this->callback)) {
            // If callback is a string, resolve from container
            $callback = $this->app->make($this->callback);
            return $callback(...$this->parameters);
        }

        return call_user_func_array($this->callback, $this->parameters);
    }

    /**
     * Store output to file.
     */
    protected function storeOutput($output): void
    {
        $mode = $this->appendOutput ? 'a' : 'w';
        
        if ($handle = fopen($this->output, $mode)) {
            fwrite($handle, print_r($output, true) . PHP_EOL);
            fclose($handle);
        }
    }

    /**
     * Build command description.
     */
    public function buildCommand(): string
    {
        if (is_string($this->callback)) {
            return $this->callback;
        }

        if ($this->callback instanceof Closure) {
            $reflection = new \ReflectionFunction($this->callback);
            return 'Closure at ' . $reflection->getFileName() . ':' . $reflection->getStartLine();
        }

        if (is_array($this->callback)) {
            $class = is_string($this->callback[0]) ? $this->callback[0] : get_class($this->callback[0]);
            return $class . '@' . $this->callback[1];
        }

        return 'Callback';
    }

    /**
     * Get summary with callback info.
     */
    public function getSummary(): array
    {
        $summary = parent::getSummary();
        $summary['type'] = 'callback';
        return $summary;
    }
}
