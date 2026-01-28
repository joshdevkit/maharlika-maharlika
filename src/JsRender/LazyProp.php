<?php

namespace Maharlika\JsRender;

/**
 * Lazy Prop - Only evaluated when explicitly requested
 */
class LazyProp
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function resolve()
    {
        return call_user_func($this->callback);
    }
}