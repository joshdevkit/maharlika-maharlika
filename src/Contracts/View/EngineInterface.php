<?php

namespace Maharlika\Contracts\View;

interface EngineInterface
{
    /**
     * Render the view at the given path.
     *
     * @param string $path
     * @param array $data
     * @return string
     */
    public function render(string $path, array $data = []): string;

    /**
     * Register a custom directive.
     *
     * @param string $name
     * @param callable $handler
     * @return void
     */
    public function directive(string $name, callable $handler): void;
}