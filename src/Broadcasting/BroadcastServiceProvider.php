<?php

namespace Maharlika\Broadcasting;

use Maharlika\Http\Response;
use Maharlika\Providers\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('broadcast', function ($app) {
            $config = $app->get('config')->get('broadcasting', []);
            return new BroadcastManager($app, $config);
        });

        $this->app->singleton(ChannelManager::class, function ($app) {
            return new ChannelManager($app);
        });

        $this->app->singleton(ChannelAuthorizer::class, function ($app) {
            return new ChannelAuthorizer($app->make(ChannelManager::class));
        });

        $this->app->singleton(ChannelDiscovery::class, function ($app) {
            $discovery = new ChannelDiscovery($app->make(ChannelManager::class));

            $config = $app->get('config')->get('broadcasting', []);
            $channelPath = $config['channels']['path'] ?? $app->basePath('app/Channels');
            $channelNamespace = $config['channels']['namespace'] ?? 'App\\Channels';

            $discovery->addChannelNamespace($channelNamespace, $channelPath);

            return $discovery;
        });

        $this->app->alias('broadcast', BroadcastManager::class);
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        // Discover channels
        $discovery = $this->app->make(ChannelDiscovery::class);
        $discovery->discover();

        // Register broadcast auth route
        $this->registerBroadcastAuthRoute();
    }

    /**
     * Register the broadcast authentication route
     */
    protected function registerBroadcastAuthRoute(): void
    {
        $router = $this->app->get('router');
        $config = $this->app->get('config')->get('broadcasting', []);
        $authEndpoint = $config['auth_endpoint'] ?? '/broadcasting/auth';

        $app = $this->app;

        $router->post($authEndpoint, function () use ($app) {
            $request = $app->get('request');

            // Handle both form data and JSON payloads
            $channelName = null;
            $socketId = null;

            // Try to get from POST data
            if (method_exists($request, 'input')) {
                $channelName = $request->input('channel_name');
                $socketId = $request->input('socket_id');
            } elseif (method_exists($request, 'get')) {
                $channelName = $request->get('channel_name');
                $socketId = $request->get('socket_id');
            }

            // Fallback to direct POST access
            if (!$channelName) {
                $channelName = $_POST['channel_name'] ?? null;
            }
            if (!$socketId) {
                $socketId = $_POST['socket_id'] ?? null;
            }

            // Try JSON if still empty
            if ((!$channelName || !$socketId) && !empty(file_get_contents('php://input'))) {
                $jsonData = json_decode(file_get_contents('php://input'), true);
                if (is_array($jsonData)) {
                    $channelName = $channelName ?? $jsonData['channel_name'] ?? null;
                    $socketId = $socketId ?? $jsonData['socket_id'] ?? null;
                }
            }

            // Check if user is authenticated
            $user = null;
            if (method_exists($request, 'user')) {
                $user = $request->user();
            }

            if (!$user && function_exists('auth')) {
                $user = auth()->user();
            }


            if (!$channelName) {
                return new Response(json_encode(['error' => 'Channel name is required']), 400, [
                    'Content-Type' => 'application/json'
                ]);
            }

            if (!$socketId) {
                return new Response(json_encode(['error' => 'Socket ID is required']), 400, [
                    'Content-Type' => 'application/json'
                ]);
            }

            if (!$user) {
                return new Response(json_encode(['error' => 'Unauthenticated']), 403, [
                    'Content-Type' => 'application/json'
                ]);
            }

            $broadcaster = $app->get('broadcast')->connection();

            try {
                $result = $broadcaster->auth($request);

                if ($result === false || $result === null) {
                    return new Response(json_encode(['error' => 'Unauthorized']), 403, [
                        'Content-Type' => 'application/json'
                    ]);
                }

                // Get the auth response - this should be an array with 'auth' key
                $authResponse = $broadcaster->validAuthenticationResponse($request, $result);

                // The response should already be an array like ['auth' => 'signature']
                // Don't double-encode it
                if (is_string($authResponse)) {
                    // If it's already JSON string, decode it first
                    $authResponse = json_decode($authResponse, true);
                }

                return new Response(json_encode($authResponse), 200, [
                    'Content-Type' => 'application/json'
                ]);
            } catch (\Exception $e) {
                return new Response(json_encode([
                    'error' => $e->getMessage(),
                ]), 500, [
                    'Content-Type' => 'application/json'
                ]);
            }
        })->name('broadcasting.auth');
    }
}
