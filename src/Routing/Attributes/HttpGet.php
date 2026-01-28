<?php

namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpGet extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('GET', $path);
    }
}