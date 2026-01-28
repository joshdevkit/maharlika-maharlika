<?php

if (!function_exists('broadcast')) {
    /**
     * Broadcast an event
     */
    function broadcast(\Maharlika\Contracts\Events\ShouldBroadcast $event): \Maharlika\Broadcasting\PendingBroadcast
    {
        return app('broadcast')->event($event);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event
     */
    function event(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}
