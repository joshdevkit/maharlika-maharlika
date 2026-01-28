<?php

declare(strict_types=1);

namespace Maharlika\View;

use Maharlika\View\ComponentAttributeBag;
use Maharlika\Support\HtmlString;
use Exception;

/**
 * Base class for all view components (for class-based components).
 */
abstract class Component
{
    protected HtmlString $slot;
    protected array $namedSlots = [];
    protected ComponentAttributeBag $attributes;

    public function __construct(array $attributes = [])
    {
        $this->slot = new HtmlString('');

        // Extract known properties from attributes
        $this->extractProperties($attributes);

        // Remaining attributes go into the attribute bag
        $this->attributes = new ComponentAttributeBag($attributes);
    }

    /**
     * Extract component properties from attributes array.
     */
    protected function extractProperties(array &$attributes): void
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Skip special properties
            if (in_array($propertyName, ['slot', 'namedSlots', 'attributes'])) {
                continue;
            }

            // Set property value if it exists in attributes
            if (array_key_exists($propertyName, $attributes)) {
                $value = $attributes[$propertyName];

                try {
                    // Handle type casting based on property type
                    $type = $property->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $typeName = $type->getName();

                        // Only cast if the value is not already the correct type
                        if ($typeName === 'bool' && !is_bool($value)) {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        } elseif ($typeName === 'int' && !is_int($value)) {
                            $value = (int) $value;
                        } elseif ($typeName === 'float' && !is_float($value)) {
                            $value = (float) $value;
                        } elseif ($typeName === 'string' && !is_string($value)) {
                            // Only convert to string if it's not an array or object
                            if (!is_array($value) && !is_object($value)) {
                                $value = (string) $value;
                            } else {
                                // Skip this property if type mismatch (array/object can't be string)
                                throw new Exception("Component " . get_class($this) . ": Skipping property '{$propertyName}' - cannot convert " . gettype($value) . " to string");
                                continue;
                            }
                        } elseif ($typeName === 'array' && !is_array($value)) {
                            // Try to convert to array if possible
                            $value = is_string($value) ? [$value] : (array) $value;
                        }
                    }

                    $this->{$propertyName} = $value;

                    // Remove from attributes array so it doesn't appear in $attributes bag
                    unset($attributes[$propertyName]);
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        "Error setting property '{$propertyName}' on component " . get_class($this) .
                            ": " . $e->getMessage() . " (value type: " . gettype($value) . ")",
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Set the slot content.
     */
    public function setSlot(string $content): void
    {
        $this->slot = new HtmlString($content);
    }

    /**
     * Set a named slot.
     */
    public function setNamedSlot(string $name, string $content): void
    {
        $this->namedSlots[$name] = new HtmlString($content);
    }

    /**
     * Get a named slot.
     */
    protected function getNamedSlot(string $name, string $default = ''): HtmlString
    {
        return $this->namedSlots[$name] ?? new HtmlString($default);
    }

    /**
     * Resolve and render the component.
     */
    public function resolveComponent(): string
    {
        $result = $this->render();

        if (is_object($result) && method_exists($result, 'render')) {
            $view = $result->with($this->data());
            return $view->render();
        }

        // If render() returns a string (view path)
        if (is_string($result)) {
            // Check if it's a view path or already rendered content
            if (strpos($result, '<') === false && strpos($result, "\n") === false) {
                // Looks like a view path, render it with data through the view system
                $viewInstance = app('view')->make($result, $this->data());
                return $viewInstance->render();
            }
            // Already rendered content
            return $result;
        }

        return '';
    }

    /**
     * Get additional data for the component.
     * This method makes component properties and methods available in the view.
     * 
     */
    protected function data(): array
    {
        $data = [
            'slot' => $this->slot,
            'attributes' => $this->attributes,
        ];

        // Add named slots
        foreach ($this->namedSlots as $name => $content) {
            $data[$name] = $content;
        }

        // Add all public properties directly as variables
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Skip special properties that are already added
            if (in_array($propertyName, ['slot', 'namedSlots', 'attributes'])) {
                continue;
            }

            $data[$propertyName] = $this->{$propertyName};
        }
        
        $data['component'] = $this;

        return $data;
    }

    /**
     * Binds component properties from attributes and sets up attribute bag.
     * This method MUST be called by the component resolver after instantiation.
     */
    public function bindPropertiesAndAttributes(array $attributes = []): void
    {
        // Extract known properties from attributes
        $this->extractProperties($attributes);

        // Remaining attributes go into the attribute bag
        $this->attributes = new ComponentAttributeBag($attributes);
    }

    /**
     * Get the view / contents that represent the component.
     */
    abstract public function render();
}