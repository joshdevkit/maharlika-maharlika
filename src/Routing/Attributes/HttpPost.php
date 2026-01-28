<?php

namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPost extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('POST', $path);
    }
}