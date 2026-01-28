<?php

namespace Maharlika\Providers;
use Maharlika\Exceptions\ProductionErrorRenderer;
use Spatie\Ignition\Ignition;

class ErrorHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $debug = config('app.debug');
        if ($debug) {
            $this->registerIgnition();
        } else {
            set_exception_handler(function (\Throwable $e) {
                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
                    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
                    exit(1);
                }
                ProductionErrorRenderer::render();
            });
        }
    }

    protected function registerIgnition(): void
    {
        try {
            $ignition = Ignition::make('flare');
            $ignition->register();
        } catch (\Throwable $e) {
            // If Ignition fails to initialize, fall back to basic error handling
            // This prevents the application from crashing if Ignition has issues
        }
    }
}