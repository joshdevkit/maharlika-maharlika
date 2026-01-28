<?php

namespace Maharlika\Contracts\View;

interface ViewInterface
{
    /**
     * Render the view.
     * Parameters are optional - uses stored data if not provided.
     */
    public function render(): string;
    /**
     * Supports both single key-value and array of data.
     * 
     * @param string|array $key
     * @param mixed $value
     * @return self
     */
    public function with(string $key, mixed $value): self;
    

      /**
     * Add multiple data items to the view.
     */
    public function withData(array $data): self;

    
    /**
     * Get all data assigned to the view
     */
    public function getData(): array;

    /**
     * Get the view path
     */
    public function getPath(): string;
}