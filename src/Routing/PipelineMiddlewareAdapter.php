<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Facades\Log;
use RuntimeException;

class PipelineMiddlewareAdapter
{
    private string|array $middlewareDefinition;
    private ContainerInterface $container;

    public function __construct(string|array $middlewareDefinition, ContainerInterface $container)
    {
        $this->middlewareDefinition = $middlewareDefinition;
        $this->container = $container;
    }


    public function handle(RequestInterface $request, \Closure $next): mixed
    {
        $def = $this->middlewareDefinition;

        if (is_array($def) && count($def) === 3 && is_string($def[0]) && is_string($def[1]) && is_array($def[2])) {
            [$class, $method, $params] = $def;
            $instance = $this->container->make($class);

            foreach ($params as $key => $value) {
                try {
                    $instance->$key = $value;
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        "Failed to assign middleware parameter '{$key}'",
                        0,
                        $e
                    );
                    Log::error("WARNING: Could not set property {$key}: " . $e->getMessage());
                }
            }

            return $instance->$method($request, $next);
        }

        if (is_array($def) && count($def) === 2 && is_string($def[0]) && is_string($def[1])) {
            [$class, $method] = $def;
            return $this->container->make($class)->$method($request, $next);
        }

        return $this->container->make($def)->handle($request, $next);
    }
}
