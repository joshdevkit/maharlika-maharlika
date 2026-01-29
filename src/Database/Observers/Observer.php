<?php

namespace Maharlika\Database\Observers;

use Maharlika\Database\FluentORM\Model;

abstract class Observer
{
    /**
     * Handle the Model "retrieved" event.
     */
    public function retrieved(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "creating" event.
     */
    public function creating(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "updating" event.
     */
    public function updating(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "saving" event.
     */
    public function saving(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "saved" event.
     */
    public function saved(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "deleting" event.
     */
    public function deleting(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "restoring" event.
     */
    public function restoring(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        //
    }

    /**
     * Handle the Model "replicating" event.
     */
    public function replicating(Model $model): void
    {
        //
    }
}