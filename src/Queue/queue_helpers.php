<?php

if (!function_exists('queue')) {
    /**
     * Get queue instance
     */
    function queue(): \Maharlika\Queue\Queue
    {
        return app('queue');
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue
     */
    function dispatch(\Maharlika\Queue\Job $job): void
    {
        queue()->push($job);
    }
}
