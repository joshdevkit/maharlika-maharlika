<?php

namespace Maharlika\Contracts\View;

/**
 * Interface ViewFactoryInterface
 *
 * Defines the contract for the View Factory responsible for
 * creating, resolving, and managing view instances.
 *
 * Implementations of this interface should handle:
 * - View instantiation and rendering
 * - View existence checks
 * - Shared/global view data
 * - View composers (callbacks bound to views)
 * - View namespace registration
 *
 * This interface allows the view system to remain decoupled
 * from any specific rendering engine or filesystem structure.
 *
 * @package Maharlika\Contracts\View
 */
interface ViewFactoryInterface
{
    /**
     * Create a new view instance.
     *
     * @param string $view  The view name or identifier.
     * @param array  $data  Data to be passed to the view.
     *
     * @return ViewInterface
     */
    public function make(string $view, array $data = []): ViewInterface;

    /**
     * Render a view base on given view with data
     * @return string
     */
    public function render(string $view, array $data = []): string;

    
    /**
     * Determine if the given view exists.
     *
     * @param string $view  The view name or identifier.
     *
     * @return bool
     */
    public function exists(string $view): bool;

    /**
     * Share a piece of data with all views.
     *
     * @param string $key    The variable name.
     * @param mixed  $value  The value to be shared.
     *
     * @return void
     */
    public function share(string $key, mixed $value): void;

    /**
     * Register a view composer callback.
     *
     * The callback will be executed when the specified view
     * is being rendered.
     *
     * @param string   $view      The view name or wildcard.
     * @param callable $callback  The composer callback.
     *
     * @return void
     */
    public function composer(string $view, callable $callback): void;

    /**
     * Register a view namespace.
     *
     * Namespaces allow grouping views under a logical prefix.
     *
     * @param string $namespace  The namespace identifier.
     * @param string $path       The filesystem path to the views.
     *
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void;
}
