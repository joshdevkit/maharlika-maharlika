<?php

namespace Maharlika\Queue;

interface ShouldQueue
{
    public function handle(): void;
}
