<?php

namespace Maharlika\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class BroadcastChannel
{
    public function __construct(
        public readonly string $channel
    ) {}
}
