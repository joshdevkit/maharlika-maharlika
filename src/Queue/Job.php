<?php

namespace Maharlika\Queue;

abstract class Job implements ShouldQueue
{

    public ?string $queue = null;


    public ?string $connection = null;



    public ?int $delay = null;



    public int $tries = 1;



    public int $timeout = 60;



    abstract public function handle(): void;
}
