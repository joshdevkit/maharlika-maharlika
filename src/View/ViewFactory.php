<?php

namespace Maharlika\View;

use Maharlika\Contracts\View\ViewFactoryInterface;
use Maharlika\Contracts\View\ViewInterface;
use Maharlika\Contracts\View\ViewFinderInterface;
use Maharlika\Contracts\View\EngineInterface;

class ViewFactory implements ViewFactoryInterface
{
    protected ViewFinderInterface $finder;
    protected EngineInterface $engine;
    protected array $shared = [];
    protected array $composers = [];

    public function __construct(ViewFinderInterface $finder, EngineInterface $engine)
    {
        $this->finder = $finder;
        $this->engine = $engine;
    }

    public function make(string $view, array $data = []): ViewInterface
    {
        $path = $this->finder->find($view);
        
        // Merge shared data
        $data = array_merge($this->shared, $data);

        // Call view composers
        $data = $this->callComposer($view, $data);

        return new View($this->engine, $path, $data);
    }

    public function render(string $view, array $data = []): string
    {
        return $this->make($view, $data)->render();
    }

    public function exists(string $view): bool
    {
        return $this->finder->exists($view);
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function composer(string $view, callable $callback): void
    {
        $this->composers[$view] = $callback;
    }

    protected function callComposer(string $view, array $data): array
    {
        if (isset($this->composers[$view])) {
            $result = call_user_func($this->composers[$view], $data);
            
            if (is_array($result)) {
                $data = array_merge($data, $result);
            }
        }

        // Call wildcard composers
        if (isset($this->composers['*'])) {
            $result = call_user_func($this->composers['*'], $data);
            
            if (is_array($result)) {
                $data = array_merge($data, $result);
            }
        }

        return $data;
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->finder->addNamespace($namespace, $path);
    }

    public function getFinder(): ViewFinderInterface
    {
        return $this->finder;
    }

    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    /**
     * Get all shared data
     * 
     * @return array
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Get a specific shared value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }
}