<?php

namespace Maharlika\Session;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Contracts\Session\SessionHandlerInterface;
use Maharlika\Session\Handlers\FileSessionHandler;
use Maharlika\Session\Handlers\DatabaseSessionHandler;
use Maharlika\Providers\ServiceProvider;
use Maharlika\Session\Handlers\NullSessionHandler;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Session Handler
        $this->app->singleton(SessionHandlerInterface::class, function ($c) {
            // Skip session handler creation entirely in CLI mode
            if (php_sapi_name() === 'cli') {
                return new NullSessionHandler();
            }

            $config = $c->get('config');
            $driver = $config->get('session.driver', 'file');
            return match ($driver) {
                'database' => $this->createDatabaseHandler($c, $config),
                'file' => $this->createFileHandler($config),
                default => throw new \InvalidArgumentException("Unsupported session driver: {$driver}"),
            };
        });

        // Register Session
        $this->app->singleton(SessionInterface::class, function ($c) {
            // Skip session creation entirely in CLI mode
            if (php_sapi_name() === 'cli') {
                return new NullSession();
            }

            $config = $c->get('config');
            $sessionConfig = $config->get('session', []);
            $handler = $c->get(SessionHandlerInterface::class);

            return new Session($handler, $sessionConfig);
        });

        $this->app->singleton('session', function ($c) {
            return $c->get(SessionInterface::class);
        });
    }

    public function boot(): void
    {
        // Skip completely if running CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        /** @var Session $session */
        $session = $this->app->get('session');

        $session->start();

        $session->ageFlashData();

        if (!$session->has('_token')) {
            $session->put('_token', bin2hex(random_bytes(32)));
        }

        $this->regenerateSessionPeriodically($session);

        // Save session at the END of the request
        register_shutdown_function(function () use ($session) {
            if ($session->isStarted()) {
                $session->save();
            }
        });
    }

    /**
     * Create file session handler
     */
    protected function createFileHandler($config): FileSessionHandler
    {
        $savePath = $config->get('session.save_path', storage_path('framework/sessions'));
        return new FileSessionHandler($savePath);
    }

    /**
     * Create database session handler
     */
    protected function createDatabaseHandler($container, $config): DatabaseSessionHandler
    {
        $db = $container->get('db');
        $table = $config->get('session.table', 'sessions');
        $lifetime = $config->get('session.lifetime', 120) * 60; // Convert to seconds
        return new DatabaseSessionHandler($db, $table, $lifetime);
    }

    /**
     * Regenerate session ID periodically to prevent session fixation
     */
    protected function regenerateSessionPeriodically(SessionInterface $session): void
    {
        $lastRegeneration = $session->get('_last_regeneration');
        $regenerationInterval = 3600; // 1 Hour

        // Set initial regeneration time if not set
        if ($lastRegeneration === null) {
            $session->put('_last_regeneration', time());
            return;
        }

        // Only regenerate if interval has passed
        if (time() - $lastRegeneration > $regenerationInterval) {
            $session->regenerate(true);
            $session->put('_last_regeneration', time());
        }
    }
}
