<?php

declare(strict_types=1);

namespace Maharlika\Support\Traits;

use Maharlika\Database\Model;
use Maharlika\Database\Collection;

trait SerializesModels
{
    use SerializesCollections;

    /**
     * Prepare the instance for serialization.
     */
    public function __serialize(): array
    {
        $properties = [];

        $reflectionClass = new \ReflectionClass($this);

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($this);

            $properties[$name] = $this->getSerializedPropertyValue($value);
        }

        return $properties;
    }

    /**
     * Restore the model after serialization.
     */
    public function __unserialize(array $data): void
    {
        $reflectionClass = new \ReflectionClass($this);

        foreach ($data as $name => $value) {
            $restoredValue = $this->getRestoredPropertyValue($value);

            // Check if property exists and get its type
            if ($reflectionClass->hasProperty($name)) {
                $property = $reflectionClass->getProperty($name);
                $type = $property->getType();

                // If the property doesn't allow null and we got null, throw a better error
                if ($restoredValue === null && $type && !$type->allowsNull()) {
                    // Check if it's a model that failed to restore
                    if ($this->isSerializedModel($value)) {
                        throw new \RuntimeException(
                            "Failed to restore model for property '{$name}'. " .
                            "The {$value['class']} with ID {$value['key']} was not found in the database. " .
                            "The record may have been deleted since the job was queued."
                        );
                    }

                    throw new \RuntimeException(
                        "Cannot assign null to property {$property->class}::\${$name} of type {$type}. " .
                        "The property does not allow null values."
                    );
                }
            }

            $this->$name = $restoredValue;
        }
    }

    /**
     * Get the property value prepared for serialization.
     */
    protected function getSerializedPropertyValue(mixed $value): mixed
    {
        // Serialize Model instances
        if ($value instanceof Model) {
            return $this->serializeModel($value);
        }

        // Serialize Collections
        if ($value instanceof Collection) {
            return $this->serializeCollection($value);
        }

        // Serialize arrays (might contain models)
        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->getSerializedPropertyValue($item);
            }, $value);
        }

        return $value;
    }

    /**
     * Get the restored property value after unserialization.
     */
    protected function getRestoredPropertyValue(mixed $value): mixed
    {
        // Restore Model instances
        if ($this->isSerializedModel($value)) {
            return $this->restoreModel($value);
        }

        // Restore Collections
        if ($this->isSerializedCollection($value)) {
            return $this->restoreCollection($value);
        }

        // Restore arrays (might contain models)
        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->getRestoredPropertyValue($item);
            }, $value);
        }

        return $value;
    }

    /**
     * Serialize a model for storage.
     */
    protected function serializeModel(Model $model): array
    {
        return [
            '__is_model__' => true,
            'class' => get_class($model),
            'key' => $model->getKey(),
            'key_name' => $model->getKeyName(),
            'connection' => $model->getConnectionName(),
        ];
    }

    /**
     * Restore a model from serialized data.
     */
    protected function restoreModel(array $data): ?Model
    {
        $modelClass = $data['class'];

        // Create a new instance
        $model = new $modelClass;

        // Set connection if specified
        if (!empty($data['connection'])) {
            $model->setConnection($data['connection']);
        }

        // Fetch the model from database
        $restoredModel = $modelClass::query()->find($data['key']);

        // If model was not found, return null (will be handled in __unserialize)
        return $restoredModel;
    }

    /**
     * Check if data represents a serialized model.
     */
    protected function isSerializedModel(mixed $value): bool
    {
        return is_array($value)
            && isset($value['__is_model__'])
            && $value['__is_model__'] === true;
    }

    /**
     * Serialize a value (implementation for SerializesCollections).
     */
    protected function serializeValue(mixed $value): mixed
    {
        return $this->getSerializedPropertyValue($value);
    }

    /**
     * Restore a value (implementation for SerializesCollections).
     */
    protected function restoreValue(mixed $value): mixed
    {
        return $this->getRestoredPropertyValue($value);
    }
}