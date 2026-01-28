<?php

namespace Maharlika\Contracts\View;

interface EngineInterface
{
    public function render(string $path, array $data = []): string;
}