<?php

namespace Maharlika\Broadcasting\Broadcasters;

class NullBroadcaster implements Broadcaster
{
    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth(mixed $request): mixed
    {
        return true;
    }

    /**
     * Return the valid authentication response.
     */
    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        return ['auth' => ''];
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        // Do nothing
    }
}
