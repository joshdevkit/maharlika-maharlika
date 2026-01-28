<?php

namespace Maharlika\Database\Traits;

trait GuardsAttributes
{
    protected static $unguarded = false;

    /**
     * Get the fillable attributes for the model.
     */
    protected function getFillableAttributes(): array
    {
        if (!empty($this->fillable)) {
            return $this->fillable;
        }

        return [];
    }

    /**
     * Determine if the model is totally guarded.
     *
     * @return bool
     */
    public function totallyGuarded()
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
    }


    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        // If globally unguarded, everything is fillable
        if (static::isUnguarded()) {
            return true;
        }

        $fillable = $this->getFillableAttributes();

        // If using $fillable, check if key is in it
        if (!empty($fillable)) {
            // First check if it's directly in fillable
            if (in_array($key, $fillable)) {
                return true;
            }

            // Not in fillable, but check if it's a date cast (Laravel behavior)
            if (
                method_exists($this, 'hasCast') &&
                $this->hasCast($key, ['date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime'])
            ) {
                return true;
            }

            // Not fillable and not a date cast
            return false;
        }

        // No fillable defined, check casts
        if (
            method_exists($this, 'hasCast') &&
            $this->hasCast($key, ['date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime'])
        ) {
            return true;
        }

        // If $guarded is ['*'], nothing else is fillable by default
        if (in_array('*', $this->guarded)) {
            return false;
        }

        // Otherwise, fillable if not in $guarded
        return !in_array($key, $this->guarded);
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return !$this->isFillable($key);
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     */
    public function forceFill(array $attributes): self
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Run the given callable while being unguarded.
     */
    public static function unguarded(callable $callback): mixed
    {
        $previous = static::$unguarded ?? false;
        static::$unguarded = true;

        try {
            return $callback();
        } finally {
            static::$unguarded = $previous;
        }
    }

    /**
     * Determine if the current state is "unguarded".
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded ?? false;
    }

    /**
     * Disable all mass assignment restrictions.
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillableFromArray(array $attributes)
    {
        if (count($this->getFillable()) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }

    /**
     * Get the fillable attributes for the model.
     */
    public function getFillable()
    {
        return $this->fillable;
    }

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the fillable attributes for the model.
     */
    public function fillable(array $fillable): self
    {
        $this->fillable = $fillable;
        return $this;
    }

    /**
     * Set the guarded attributes for the model.
     */
    public function guard(array $guarded): self
    {
        $this->guarded = $guarded;
        return $this;
    }

    /**
     * Merge new fillable attributes with existing fillable attributes on the model.
     */
    public function mergeFillable(array $fillable): self
    {
        $this->fillable = array_merge($this->fillable ?? [], $fillable);
        return $this;
    }

    /**
     * Merge new guarded attributes with existing guarded attributes on the model.
     */
    public function mergeGuarded(array $guarded): self
    {
        $this->guarded = array_merge($this->guarded, $guarded);
        return $this;
    }
}
