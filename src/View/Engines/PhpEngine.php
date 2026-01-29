<?php

namespace Maharlika\View\Engines;

use Maharlika\Contracts\View\EngineInterface;

class PhpEngine implements EngineInterface
{
    public function render(string $path, array $data = []): string
    {
        return $this->evaluatePath($path, $data);
    }

    protected function evaluatePath(string $path, array $data): string
    {
        ob_start();

        extract($data, EXTR_SKIP);

        try {
            include $path;
        } catch (\Throwable $e) {
            $this->handleException($e);
        }

        return ltrim(ob_get_clean());
    }

    protected function handleException(\Throwable $e): void
    {
        ob_get_clean();
        throw $e;
    }

    /**
     * Register a custom directive.
     * PhpEngine doesn't support directives, so this is a no-op.
     *
     * @param string $name
     * @param callable $handler
     * @return void
     */
    public function directive(string $name, callable $handler): void
    {
        // PhpEngine doesn't support custom directives
        // This method exists only to satisfy the EngineInterface
    }
}