<?php

namespace Maharlika\Database\Traits;

trait HasAttributes
{
    public function setAttribute(string $key, mixed $value): void
    {
        // Check for mutator first
        if ($this->hasSetMutator($key)) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
            $this->$method($value);
            return;
        }

        if ($this->shouldCastOnSet($key)) {
            // For array/json/collection casts
            if ($this->hasCast($key, ['array', 'json', 'collection'])) {
                // If value is already an array, store it as-is (don't cast)
                // It will be JSON encoded when saving to database
                if (is_array($value)) {
                    $this->attributes[$key] = $value;
                    return;
                }
            }
            
            // For object cast
            if ($this->hasCast($key, 'object')) {
                // If value is already an object, store it as-is
                if (is_object($value)) {
                    $this->attributes[$key] = $value;
                    return;
                }
            }
            
            // Only cast if not already in correct format
            $value = $this->castAttribute($key, $value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Remove an attribute from the model.
     */
    public function removeAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            // Check for accessor
            if ($this->hasGetMutator($key)) {
                return $this->mutateAttribute($key);
            }

            // Check for appended attribute
            if (in_array($key, $this->appends)) {
                return $this->mutateAttribute($key);
            }

            return null;
        }

        $value = $this->attributes[$key];

        // Check for accessor
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // Check if value is already in the target format before casting
        if ($this->shouldCastOnGet($key)) {
            // For array/json/collection casts, check if already an array
            if ($this->hasCast($key, ['array', 'json', 'collection'])) {
                if (is_array($value)) {
                    return $value; // Already in correct format, don't re-cast
                }
            }
            
            // For object cast, check if already an object
            if ($this->hasCast($key, 'object')) {
                if (is_object($value)) {
                    return $value; // Already in correct format, don't re-cast
                }
            }
            
            // For datetime casts, check if already a Carbon instance
            if ($this->hasCast($key, ['date', 'datetime'])) {
                if ($value instanceof \Maharlika\Support\Carbon) {
                    return $value; // Already in correct format, don't re-cast
                }
            }
            
            // Only cast if not already in the correct format
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes)
            || $this->hasGetMutator($key)
            || in_array($key, $this->appends);
    }

    protected function hasGetMutator(string $key): bool
    {
        $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return method_exists($this, $method);
    }

    protected function hasSetMutator(string $key): bool
    {
        $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return method_exists($this, $method);
    }

    protected function mutateAttribute(string $key, mixed $value = null): mixed
    {
        $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        return $this->$method($value);
    }

    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key)
    {
        return isset($this->attributes[$key]);
    }
}