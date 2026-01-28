<?php

declare(strict_types=1);

namespace Maharlika\Support\Traits;

use Maharlika\Database\Collection;

trait SerializesCollections
{
    /**
     * Serialize a collection for storage.
     */
    protected function serializeCollection(Collection $collection): array
    {
        return [
            '__is_collection__' => true,
            'class' => get_class($collection),
            'items' => $collection->map(function ($item) {
                return $this->serializeValue($item);
            })->all(),
        ];
    }

    /**
     * Restore a collection from serialized data.
     */
    protected function restoreCollection(array $data): Collection
    {
        $collectionClass = $data['class'];

        $items = array_map(function ($item) {
            return $this->restoreValue($item);
        }, $data['items']);

        return new $collectionClass($items);
    }

    /**
     * Check if data represents a serialized collection.
     */
    protected function isSerializedCollection(mixed $value): bool
    {
        return is_array($value)
            && isset($value['__is_collection__'])
            && $value['__is_collection__'] === true;
    }

    /**
     * Serialize a value (to be implemented by using trait).
     */
    abstract protected function serializeValue(mixed $value): mixed;

    /**
     * Restore a value (to be implemented by using trait).
     */
    abstract protected function restoreValue(mixed $value): mixed;
}
