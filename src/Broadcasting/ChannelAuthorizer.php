<?php

namespace Maharlika\Broadcasting;

use Maharlika\Contracts\Http\RequestInterface;
use Exception;

class ChannelAuthorizer
{
    protected ChannelManager $manager;

    public function __construct(ChannelManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Authorize a channel subscription request
     */
    public function authorize(RequestInterface $request, string $channelName): mixed
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return false;
        }

        $match = $this->manager->find($channelName);

        if (!$match) {
            // No authorization callback found - allow public channels
            // For private channels without explicit auth, deny
            if (str_starts_with($channelName, 'private-') || str_starts_with($channelName, 'presence-')) {
                return false;
            }
            return true;
        }

        return $this->manager->authorize($user, $channelName, $match['params']);
    }

    /**
     * Get authenticated user from request
     */
    protected function getAuthenticatedUser(RequestInterface $request): mixed
    {
        // Try multiple methods to get the user

        // Method 1: From request user() method
        if (method_exists($request, 'user')) {
            $user = $request->user();
            if ($user) {
                return $user;
            }
        }

        // Method 2: From Auth facade
        if (class_exists(\Maharlika\Facades\Auth::class)) {
            $user = \Maharlika\Facades\Auth::user();
            if ($user) {
                return $user;
            }
        }

        // Method 3: From auth service
        if (function_exists('auth')) {
            $user = auth()->user();
            if ($user) {
                return $user;
            }
        }

        // Method 4: From session
        try {
            $session = app('session');
            $userId = $session->get('auth_id');
            if ($userId) {
                // Try to load user model
                $model = config('auth.model');
                if (class_exists($model)) {
                    /**
                     * Underlying authenticated model
                     */
                    return $model::find($userId);
                }
            }
        } catch (\Exception $e) {
           throw new Exception($e->getMessage());
        }

        return null;
    }
}
