<?php

namespace Maharlika\Database\Traits;

trait TracksChanges
{
    /**
     * The changed model attributes.
     *
     * @var array
     */
    protected $changes = [];

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
    protected $wasRecentlyCreated = false;

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param string|array|null $attributes
     * @return bool
     */
    public function wasChanged(string|array|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getChanges(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed.
     *
     * @param array $changes
     * @param array|string|null $attributes
     * @return bool
     */
    protected function hasChanges(array $changes, array|string|null $attributes = null): bool
    {
        // If no specific attributes were provided, check if any changes exist
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Check if any of the specified attributes were changed
        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getChanges(): array
    {
        return $this->changes ?? [];
    }

    /**
     * Determine if the model was recently created.
     *
     * @return bool
     */
    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated ?? false;
    }

    /**
     * Mark the model as recently created.
     *
     * @param bool $value
     * @return static
     */
    public function setWasRecentlyCreated(bool $value): static
    {
        $this->wasRecentlyCreated = $value;

        return $this;
    }

    /**
     * Finish save processing (track changes and mark as not recently created).
     *
     * @param array $options
     * @return void
     */
    protected function finishSave(array $options = []): void
    {
        // Sync changes
        $this->syncChanges();

        // Fire the saved event if events trait is present
        if (method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent('saved', false);
        }
    }
}