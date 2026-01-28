<?php

namespace Maharlika\Broadcasting\Broadcasters;

use Maharlika\Broadcasting\ChannelAuthorizer;

class RedisBroadcaster implements Broadcaster
{
    protected mixed $redis;
    protected ChannelAuthorizer $authorizer;

    public function __construct(mixed $redis, ChannelAuthorizer $authorizer)
    {
        $this->redis = $redis;
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
        // For Redis, we'll implement WebSocket authentication later
        return ['auth' => hash_hmac('sha256', $request->input('socket_id'), config('app.key'))];
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        $payload = json_encode([
            'event' => $event,
            'data' => $payload,
            'socket' => null,
        ]);

        foreach ($channels as $channel) {
            $this->redis->publish((string) $channel, $payload);
        }
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
}
