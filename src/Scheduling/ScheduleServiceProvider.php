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
        $schedulerPath = app()->basePath('app/Schedules/TaskScheduler.php');

        if (file_exists($schedulerPath)) {
            $result = require $schedulerPath;
            
            // If it returns a closure, call it with the schedule
            if ($result instanceof \Closure) {
                $result($schedule);
            }
        }
    }
}