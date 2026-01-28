<?php

namespace Maharlika\Routing\Attributes;

use Attribute;

/**
 * Shorthand for AuthMiddleware
 * 
 * #[Auth]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authenticated
{
    public function __construct(
        public ?string $guard = null
    ) {}
}
