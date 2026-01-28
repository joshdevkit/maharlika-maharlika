<?php

namespace Maharlika\Database\Traits;

trait HasEvents
{
    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool $halt
     * @return mixed
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        static::fireEvent($event, $this);

        return true;
    }

    /**
     * Register a saving model event callback.
     */
    protected static function saving(callable $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event callback.
     */
    protected static function saved(callable $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event callback.
     */
    protected static function updating(callable $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event callback.
     */
    protected static function updated(callable $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a created model event callback.
     */
    protected static function created(callable $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a deleting model event callback.
     */
    protected static function deleting(callable $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event callback.
     */
    protected static function deleted(callable $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Register a retrieved model event callback.
     */
    protected static function retrieved(callable $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }
}