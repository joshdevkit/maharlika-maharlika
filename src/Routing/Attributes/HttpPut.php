<?php

namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPut extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('PUT', $path);
    }
}
