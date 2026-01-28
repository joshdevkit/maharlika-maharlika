<?php

declare(strict_types=1);

namespace Maharlika\View;

use Maharlika\Contracts\View\ViewInterface;
use Maharlika\Contracts\View\EngineInterface;

class View implements ViewInterface
{
    protected EngineInterface $engine;
    protected string $path;
    protected array $data = [];

    public function __construct(EngineInterface $engine, string $path, array $data = [])
    {
        $this->engine = $engine;
        $this->path = $path;
        $this->data = $data;
    }

    /**
     * Render the view.
     * Parameters are optional - uses stored data if not provided.
     */
    public function render(array $data = []): string
    {
        // Merge any additional data passed to render() with stored data
        $mergedData = array_merge($this->data, $data);
        
        return $this->engine->render($this->path, $mergedData);
    }

    /**
     * Supports both single key-value and array of data.
     * 
     * @param string|array $key
     * @param mixed $value
     * @return self
     */
    public function with(string|array $key, mixed $value = null): self
    {
        // If $key is an array, merge it with existing data
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            // Otherwise, add single key-value pair
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Add multiple data items to the view.
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get all data assigned to the view.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the view path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Convert the view to a string (renders it).
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            throw new ViewRenderingException(
                viewPath: $this->path,
                viewData: $this->data,
                previous: $e
            );
        }
    }
}