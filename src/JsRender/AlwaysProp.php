<?php

namespace Maharlika\JsRender;

class AlwaysProp
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function resolve()
    {
        // If value is a callable, call it
        if (is_callable($this->value)) {
            return call_user_func($this->value);
        }
        
        return $this->value;
    }
}