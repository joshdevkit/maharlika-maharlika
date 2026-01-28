<?php

namespace Maharlika\Routing;

class ControllerMiddlewareOptions
{
    protected array $middleware;

    public function __construct(array &$middleware)
    {
        $this->middleware = &$middleware;
    }

    /**
     * Set the methods the middleware should apply to.
     */
    public function only(string|array $methods): self
    {
        $this->middleware['options']['only'] = is_array($methods) ? $methods : [$methods];
        return $this;
    }

    /**
     * Set the methods the middleware should exclude.
     */
    public function except(string|array $methods): self
    {
        $this->middleware['options']['except'] = is_array($methods) ? $methods : [$methods];
        return $this;
    }

    /**
     * Set middleware parameters.
     */
    public function with(array $parameters): self
    {
        $this->middleware['options']['parameters'] = $parameters;
        return $this;
    }
}