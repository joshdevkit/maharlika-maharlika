<?php

namespace Maharlika\Scheduling;

use Maharlika\Providers\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton('schedule', function ($container) {
            return new Schedule($container);
        });

        $this->app->singleton(ScheduleRunner::class, function ($container) {
            return new ScheduleRunner($container);
        });
    }

    public function boot(): void
    {
        // Load user-defined schedule
        $this->loadSchedule();
    }

    protected function loadSchedule(): void
    {
        $schedule = $this->app->get('schedule');

        // Try multiple possible locations for schedule definition
        $schedulerPaths = [
            app()->basePath('app/Schedules/TaskScheduler.php'),
        ];

        foreach ($schedulerPaths as $path) {
            if (file_exists($path)) {
                $result = require $path;
                
                // If it returns a closure, call it with the schedule
                if ($result instanceof \Closure) {
                    $result($schedule);
                }
                
                // Only load the first found file
                return;
            }
        }
    }
}
