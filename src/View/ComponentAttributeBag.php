<?php

declare(strict_types=1);

namespace Maharlika\View;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

/**
 * Manages component attributes with Laravel-like API.
 */
class ComponentAttributeBag implements ArrayAccess, IteratorAggregate
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get a specific attribute value.
     */
    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if an attribute exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get all attributes except the specified ones.
     */
    public function except(...$keys): self
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        return new static(array_diff_key($this->attributes, array_flip($keys)));
    }

    /**
     * Get only the specified attributes.
     */
    public function only(...$keys): self
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        return new static(array_intersect_key($this->attributes, array_flip($keys)));
    }

    /**
     * Merge additional attributes into the bag.
     */
    public function merge(array $attributes = [], bool $escape = true): self
    {
        $merged = array_merge($this->attributes, $attributes);
        
        // Handle class merging specially
        if (isset($this->attributes['class']) && isset($attributes['class'])) {
            $merged['class'] = trim($this->attributes['class'] . ' ' . $attributes['class']);
        }
        
        return new static($merged);
    }

    /**
     * Conditionally include classes based on boolean values.
     * 
     */
    public function class($classList): self
    {
        $classes = [];
        
        // Handle array input
        if (is_array($classList)) {
            foreach ($classList as $key => $value) {
                // If key is numeric, value is the class name
                if (is_numeric($key)) {
                    if ($value) {
                        $classes[] = $value;
                    }
                } else {
                    // Key is the class name, value is the condition
                    if ($value) {
                        $classes[] = $key;
                    }
                }
            }
        } else {
            // Single class string
            $classes[] = $classList;
        }
        
        // Merge with existing classes
        $existingClasses = $this->attributes['class'] ?? '';
        $newClasses = implode(' ', array_filter($classes));
        
        $attributes = $this->attributes;
        $attributes['class'] = trim($existingClasses . ' ' . $newClasses);
        
        return new static($attributes);
    }

    /**
     * Conditionally merge attributes.
     */
    public function mergeIf(bool $condition, array $attributes = []): self
    {
        return $condition ? $this->merge($attributes) : $this;
    }

    /**
     * Get the attributes as a string for rendering.
     * Returns an HtmlString so it won't be escaped when using {{ }}
     */
    public function toHtml(): string
    {
        $html = [];
        
        foreach ($this->attributes as $key => $value) {
            // Skip null, false, empty string
            if ($value === null || $value === false || $value === '') {
                continue;
            }
            
            // Boolean attributes (checked, disabled, readonly, etc.)
            if ($value === true) {
                $html[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                continue;
            }
            
            // Regular attributes
            $html[] = sprintf(
                '%s="%s"',
                htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
            );
        }
        
        return implode(' ', $html);
    }

    /**
     * Convert to string (alias for toHtml).
     * Returns raw HTML that's safe to output.
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Get the raw attributes array.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if the bag is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->attributes);
    }

    /**
     * Check if the bag is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Filter attributes using a callback.
     */
    public function filter(callable $callback): self
    {
        return new static(array_filter($this->attributes, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Get attributes that start with a specific prefix.
     */
    public function whereStartsWith(string $prefix): self
    {
        return new static(array_filter(
            $this->attributes,
            fn($value, $key) => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Get the first attribute value.
     */
    public function first(): mixed
    {
        return reset($this->attributes) ?: null;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    // IteratorAggregate implementation
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->attributes);
    }
}