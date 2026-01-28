<?php


namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpPatch extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('PATCH', $path);
    }
}