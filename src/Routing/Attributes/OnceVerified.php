<?php

namespace Maharlika\Routing\Attributes;

use Attribute;

/**
 * Indicates that the route should only be accessible
 * to users who have verified their email addresses.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnceVerified
{
    public string $redirectTo;

    public function __construct(string $redirectTo = '/')
    {
        $this->redirectTo = $redirectTo;
    }
}
