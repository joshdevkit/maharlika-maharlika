<?php

namespace Maharlika\Routing\Attributes;

use Attribute;

/**
 * Apply middleware to a controller class or method
 * 
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public array $middlewares;

    public function __construct(string|array $middleware)
    {
        $this->middlewares = is_array($middleware) ? $middleware : [$middleware];
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}