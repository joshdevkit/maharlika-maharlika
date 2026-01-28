<?php

namespace Maharlika\Broadcasting\Broadcasters;

use Maharlika\Broadcasting\ChannelAuthorizer;
use Pusher\Pusher;

class PusherBroadcaster implements Broadcaster
{
    protected Pusher $pusher;
    protected ChannelAuthorizer $authorizer;

    public function __construct(Pusher $pusher, ChannelAuthorizer $authorizer)
    {
        $this->pusher = $pusher;
        $this->authorizer = $authorizer;
    }

    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth(mixed $request): mixed
    {
        $channelName = $this->getChannelName($request);

        if ($this->isGuardedChannel($channelName)) {
            return $this->authorizer->authorize($request, $channelName);
        }

        return true;
    }

    /**
     * Return the valid authentication response.
     */
    public function validAuthenticationResponse(mixed $request, mixed $result): mixed
    {
        $channelName = $this->getChannelName($request);
        $socketId = $request->input('socket_id');

        if ($this->isPresenceChannel($channelName)) {
            // For presence channels, result should be an array with user data
            // Extract user ID from the result
            $userId = is_array($result) ? ($result['id'] ?? null) : null;

            if (!$userId) {
                throw new \InvalidArgumentException(
                    'Presence channel authorization must return an array with an "id" key'
                );
            }

            return $this->pusher->authorizePresenceChannel(
                $channelName,
                $socketId,
                (string) $userId,
                $result
            );
        }

        return $this->pusher->authorizeChannel($channelName, $socketId);
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $channels = array_map(function ($channel) {
            return (string) $channel;
        }, $channels);

        $this->pusher->trigger($channels, $event, $payload);
    }

    /**
     * Get channel name from request
     */
    protected function getChannelName(mixed $request): string
    {
        return $request->input('channel_name', '');
    }

    /**
     * Check if channel is guarded (private or presence)
     */
    protected function isGuardedChannel(string $channel): bool
    {
        return str_starts_with($channel, 'private-') || str_starts_with($channel, 'presence-');
    }

    /**
     * Check if channel is a presence channel
     */
    protected function isPresenceChannel(string $channel): bool
    {
        return str_starts_with($channel, 'presence-');
    }
}
