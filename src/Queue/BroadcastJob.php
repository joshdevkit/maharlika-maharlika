<?php

namespace Maharlika\Queue;

use Maharlika\Contracts\Events\ShouldBroadcast;

class BroadcastJob extends Job
{

    public function __construct(
        protected readonly ShouldBroadcast $event
    ) {
        
    }

    public function handle(): void
    {
        $broadcast = app('broadcast');
        $broadcast->broadcast($this->event);
    }
}
