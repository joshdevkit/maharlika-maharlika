<?php

namespace Maharlika\Broadcasting;

class ChannelMatcher
{
    /**
     * Match a channel name against registered patterns
     */
    public function match(string $channelName, array $patterns): ?array
    {
        // Remove channel prefixes for matching
        $cleanChannelName = $this->removeChannelPrefix($channelName);

        foreach ($patterns as $pattern => $callback) {
            if ($this->matchPattern($pattern, $cleanChannelName, $params)) {
                return [
                    'pattern' => $pattern,
                    'callback' => $callback,
                    'params' => $params,
                    'original_channel' => $channelName,
                ];
            }
        }

        return null;
    }

    /**
     * Remove channel prefix (private-, presence-)
     */
    protected function removeChannelPrefix(string $channelName): string
    {
        if (str_starts_with($channelName, 'private-')) {
            return substr($channelName, 8);
        }

        if (str_starts_with($channelName, 'presence-')) {
            return substr($channelName, 9);
        }

        return $channelName;
    }

    /**
     * Match a pattern against a channel name
     */
    protected function matchPattern(string $pattern, string $channelName, &$params = []): bool
    {
        $params = [];

        // Exact match
        if ($pattern === $channelName) {
            return true;
        }

        // Convert pattern to regex
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn($m) => '(?P<' . $m[1] . '>[^.]+)',
            $pattern
        );

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $channelName, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }

        return false;
    }

    /**
     * Check if channel is private
     */
    public function isPrivateChannel(string $channelName): bool
    {
        return str_starts_with($channelName, 'private-');
    }

    /**
     * Check if channel is presence
     */
    public function isPresenceChannel(string $channelName): bool
    {
        return str_starts_with($channelName, 'presence-');
    }

    /**
     * Check if channel is public
     */
    public function isPublicChannel(string $channelName): bool
    {
        return !$this->isPrivateChannel($channelName) && !$this->isPresenceChannel($channelName);
    }
}
