<?php

namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class AuthRoute
{
    public function __construct(
        public ?string $type = 'login' 
    ) {}
}
