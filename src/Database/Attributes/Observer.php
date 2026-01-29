<?php

namespace Maharlika\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Observer
{
    public function __construct(
        public string|array $observers
    ) {
        // Allow single observer or array of observers
        $this->observers = is_array($observers) ? $observers : [$observers];
    }

    public function getObservers(): array
    {
        return $this->observers;
    }
}