<?php

namespace Maharlika\Routing\Traits;

use Maharlika\Routing\ControllerMiddlewareOptions;

trait HasMiddleware
{
    /**
     * The middleware registered on the controller.
     */
    protected array $middleware = [];

    /**
     * Register middleware on the controller.
     *
     * @param array|string $middleware
     * @param array $options
     * @return ControllerMiddlewareOptions
     */
    protected function middleware(array|string $middleware, array $options = []): ControllerMiddlewareOptions
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middlewares as $m) {
            $this->middleware[] = [
                'middleware' => $m,
                'options' => $options,
            ];
        }

        $lastIndex = array_key_last($this->middleware);

        if ($lastIndex !== null) {
            return new ControllerMiddlewareOptions($this->middleware[$lastIndex]);
        }

        $this->middleware[] = ['middleware' => '', 'options' => []];
        $newIndex = array_key_last($this->middleware);
        return new ControllerMiddlewareOptions($this->middleware[$newIndex]);
    }

    /**
     * Get the middleware assigned to the controller.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}