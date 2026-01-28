<?php

/**
 * Application Task Scheduler
 * 
 * Define all scheduled tasks here. The scheduler will automatically
 * run these tasks at the specified intervals.
 * 
 * @var \Maharlika\Scheduling\Schedule $schedule
 */

use Maharlika\Scheduling\Schedule;

return function (Schedule $schedule) {
    
    // ============================================
    // COMMAND SCHEDULING EXAMPLES
    // ============================================
    
    // Run a custom console command every minute
    // $schedule->command('email:send')
    //     ->everyMinute()
    //     ->description('Send pending emails');
    
    // Run queue worker hourly
    // $schedule->command('queue:work')
    //     ->hourly()
    //     ->description('Process queued jobs');
    
    
    // ============================================
    // CLOSURE SCHEDULING EXAMPLES
    // ============================================
    
    // Run a closure every 5 minutes
    $schedule->call(function () {
        // Clean up old temporary files
        $tempDir = __DIR__ . '/../storage/temp';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            $now = time();
            foreach ($files as $file) {
                if (is_file($file) && $now - filemtime($file) >= 3600) {
                    unlink($file);
                }
            }
        }
    })->everyFiveMinutes()
      ->description('Clean temporary files');
    
    // Run database cleanup daily at 2am
    $schedule->call(function () {
        // Clean up old logs
        app('db')->table('logs')
            ->where('created_at', '<', date('Y-m-d', strtotime('-30 days')))
            ->delete();
    })->dailyAt('2:00')
      ->description('Clean old log entries');
    
    
    // ============================================
    // SHELL COMMAND EXAMPLES
    // ============================================
    
    // Run shell command daily
    // $schedule->exec('php /path/to/script.php')
    //     ->daily()
    //     ->description('Run external PHP script');
    
    // Backup database weekly on Sunday at 3am
    // $schedule->exec('mysqldump -u user -p database > backup.sql')
    //     ->weeklyOn(0, '3:00')
    //     ->description('Backup database');
    
    
    // ============================================
    // QUEUE JOB EXAMPLES
    // ============================================
    
    // Push job to queue every 10 minutes
    // $schedule->job(App\Jobs\SendNewsletters::class)
    //     ->everyTenMinutes()
    //     ->description('Queue newsletter job');
    
    
    // ============================================
    // ADVANCED SCHEDULING OPTIONS
    // ============================================
    
    // Prevent overlapping executions
    $schedule->call(function () {
        // Long running task
        sleep(120); // Simulating 2 minute task
    })->everyMinute()
      ->withoutOverlapping()
      ->description('Task with overlap prevention');
    
    // Run only on weekdays
    $schedule->call(function () {
        // Business day task
    })->dailyAt('9:00')
      ->weekdays()
      ->description('Weekday morning task');
    
    // Run only on weekends
    $schedule->call(function () {
        // Weekend task
    })->dailyAt('10:00')
      ->weekends()
      ->description('Weekend task');
    
    // Custom timezone
    $schedule->call(function () {
        // Task with specific timezone
    })->dailyAt('12:00')
      ->timezone('America/New_York')
      ->description('Task in EST timezone');
    
    // Conditional execution
    $schedule->call(function () {
        // Task logic
    })->daily()
      ->when(function () {
          // Only run if condition is true
          return date('d') === '01'; // First day of month
      })
      ->description('First day of month task');
    
    // Skip execution based on condition
    $schedule->call(function () {
        // Task logic
    })->daily()
      ->skip(function () {
          // Skip if condition is true
          return app('db')->table('maintenance')->where('active', true)->exists();
      })
      ->description('Skip during maintenance');
    
    // Before and after callbacks
    $schedule->call(function () {
        // Main task
    })->hourly()
      ->before(function () {
          // Run before the task
          app('log')->info('Task starting');
      })
      ->after(function () {
          // Run after the task
          app('log')->info('Task completed');
      })
      ->description('Task with callbacks');
    
    // Send output to file
    $schedule->call(function () {
        echo "Task output\n";
        return ['status' => 'success'];
    })->daily()
      ->sendOutputTo(storage_path('logs/scheduled-task.log'))
      ->description('Task with file output');
    
    // Append output instead of overwriting
    $schedule->call(function () {
        echo date('Y-m-d H:i:s') . " - Task executed\n";
    })->everyMinute()
      ->appendOutputTo(storage_path('logs/task-history.log'))
      ->description('Task with appended output');
    
    // Run task in background
    // $schedule->exec('php long-running-script.php')
    //     ->hourly()
    //     ->runInBackground()
    //     ->description('Background task');
    
    // Multiple time specifications
    $schedule->call(function () {
        // Task runs twice daily
    })->twiceDaily(8, 20) // 8am and 8pm
      ->description('Twice daily task');
    
    $schedule->call(function () {
        // Quarterly task
    })->quarterly()
      ->description('Quarterly report');
    
    $schedule->call(function () {
        // Last day of month
    })->lastDayOfMonth('23:59')
      ->description('End of month task');
};
