<?php

namespace Maharlika\Broadcasting\Broadcasters;

interface Broadcaster
{
    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth(mixed $request): mixed;

    /**
     * Return the valid authentication response.
     */
    public function validAuthenticationResponse(mixed $request, mixed $result): mixed;

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, string $event, array $payload = []): void;
}
