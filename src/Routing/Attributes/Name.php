<?php

namespace Maharlika\Routing\Attributes;

/**
 * Assigns a dot notation name to a route for reverse routing.
 * 
 * Example: #[Name('route.name')]
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Name
{
    public function __construct(
        public mixed $name
    ) {}
}