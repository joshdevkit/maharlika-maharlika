<?php

namespace Maharlika\Database\Traits;

use Maharlika\Support\Carbon;

trait HasTimestamps
{
    const CREATED_AT = 'created_at';
    
    const UPDATED_AT = 'updated_at';

    protected function updateTimestamps(): void
    {
        $now = Carbon::now(config('app.timezone'))->format($this->dateFormat);

        if (!$this->exists && defined('static::CREATED_AT') && static::CREATED_AT) {
            $this->setAttribute(static::CREATED_AT, $now);
        }

        if (defined('static::UPDATED_AT') && static::UPDATED_AT) {
            $this->setAttribute(static::UPDATED_AT, $now);
        }
    }


    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestampString(): string
    {
        return date($this->dateFormat);
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): string
    {
        return static::UPDATED_AT;
    }
}
