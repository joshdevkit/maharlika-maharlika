<?php


namespace Maharlika\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpDelete extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('DELETE', $path);
    }
}
