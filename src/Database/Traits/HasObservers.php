<?php

namespace Maharlika\Database\Traits;

use Maharlika\Database\Attributes\Observer as ObserverAttribute;
use ReflectionClass;

trait HasObservers
{
    /**
     * Registered observers for this model
     */
    protected static array $observers = [];

    /**
     * Boot the HasObservers trait
     */
    protected static function bootHasObservers(): void
    {
        static::registerObserversFromAttribute();
    }

    /**
     * Register observers defined via the Observer attribute
     */
    protected static function registerObserversFromAttribute(): void
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(ObserverAttribute::class);

        foreach ($attributes as $attribute) {
            /** @var ObserverAttribute $observer */
            $observerAttribute = $attribute->newInstance();
            
            foreach ($observerAttribute->getObservers() as $observerClass) {
                static::observe($observerClass);
            }
        }
    }

    /**
     * Register an observer with the model
     */
    public static function observe(string|object $class): void
    {
        $observer = is_string($class) ? app($class) : $class;
        $className = get_class($observer);

        // Store observer instance
        if (!isset(static::$observers[static::class])) {
            static::$observers[static::class] = [];
        }

        static::$observers[static::class][$className] = $observer;

        // Register event listeners for each observable event
        $events = (new static)->getObservableEvents();

        foreach ($events as $event) {
            if (method_exists($observer, $event)) {
                static::registerModelEvent($event, function ($model) use ($observer, $event) {
                    return $observer->{$event}($model);
                });
            }
        }
    }

    /**
     * Get all registered observers for this model
     */
    public static function getObservers(): array
    {
        return static::$observers[static::class] ?? [];
    }

    /**
     * Remove all observers from the model
     */
    public static function flushObservers(): void
    {
        unset(static::$observers[static::class]);
    }
}