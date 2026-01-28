<?php

namespace Maharlika\Routing\Attributes;

use Attribute;

/**
 * Shorthand for GuestMiddleware
 * 
 * Usage:
 * #[Guest]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Guest
{
    public function __construct() {}
}