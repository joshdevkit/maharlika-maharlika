<?php

namespace Maharlika\Facades;

use Maharlika\View\ViewFactory;

/**
 * ---------------------------------------------------------------
 * Facade: View
 * ---------------------------------------------------------------
 * 
 * The View Facade provides a static interface to the underlying
 * view factory or view engine bound in the service container.
 * 
 * This allows developers to render templates or pass data to views
 * without directly resolving the view service from the container.
 * 
 * 
 * @method static \Maharlika\Contracts\View\ViewInterface make(string $view, array $data = [])
 * @method static bool exists(string $view)
 * @method static string render(string $view, array $data = [])
 * @method static void directive(string $name, callable $handler)
 * @method static void share(string $key, mixed $value)
 * @see \Maharlika\View\ViewFactory
 * @see \Maharlika\Contracts\View\ViewInterface
 */
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return ViewFactory::class;
    }
}