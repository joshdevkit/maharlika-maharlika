<?php

namespace Maharlika\Pipeline;

use Closure;
use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Exceptions\PipelineException;
use Maharlika\Http\Response;
use Throwable;

class Pipeline
{
    protected ContainerInterface $container;
    protected mixed $passable;
    protected array $pipes = [];
    protected string $method = 'handle';
    protected array $parameters = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function send(mixed $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    public function through(array|string $pipes): static
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    public function pipe(Closure $pipe): static
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    public function via(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function with(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback
     * Automatically converts result to ResponseInterface if needed
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    public function thenReturn(): mixed
    {
        return $this->then(fn($passable) => $passable);
    }

    /**
     * Prepare the final destination with automatic response conversion
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function ($passable) use ($destination) {
            try {
                $result = $destination($passable);
                
                if ($passable instanceof \Maharlika\Contracts\Http\RequestInterface) {
                    return $this->toResponse($result);
                }
                
                return $result;
            } catch (Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Build the middleware onion layers
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    if (is_callable($pipe)) {
                        $result = $pipe($passable, $stack, ...$this->parameters);
                    } elseif (is_object($pipe)) {
                        $method = $this->method;

                        if (!method_exists($pipe, $method)) {
                            throw new PipelineException(
                                "Method [{$method}] does not exist on pipe [" . get_class($pipe) . "]"
                            );
                        }

                        $result = $pipe->{$method}($passable, $stack, ...$this->parameters);
                    } elseif (is_string($pipe)) {
                        [$name, $pipeParameters] = $this->parsePipeString($pipe);
                        $parameters = array_merge($this->parameters, $pipeParameters);
                        $pipeInstance = $this->container->make($name);
                        $method = $this->method;

                        if (!method_exists($pipeInstance, $method)) {
                            throw new PipelineException(
                                "Method [{$method}] does not exist on pipe [{$name}]"
                            );
                        }

                        $result = $pipeInstance->{$method}($passable, $stack, ...$parameters);
                    } else {
                        throw new PipelineException("Invalid pipe type: " . gettype($pipe));
                    }

                    // Auto-convert to Response if working with HTTP
                    if ($passable instanceof \Maharlika\Contracts\Http\RequestInterface) {
                        return $this->toResponse($result);
                    }

                    return $result;
                } catch (Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    protected function parsePipeString(string $pipe): array
    {
        if (!str_contains($pipe, ':')) {
            return [$pipe, []];
        }

        [$name, $parameters] = explode(':', $pipe, 2);
        $parameters = array_map('trim', explode(',', $parameters));

        return [$name, $parameters];
    }

    /**
     * Convert any value to ResponseInterface
     * SINGLE SOURCE OF TRUTH for response conversion
     */
    protected function toResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof \Maharlika\Contracts\View\ViewInterface) {
            return new Response($result);
        }

        if ($result instanceof \Maharlika\View\View) {
            return new Response($result);
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return new Response((string) $result);
    }

    protected function handleException(mixed $passable, Throwable $e): mixed
    {
        throw $e;
    }

    public function getPipes(): array
    {
        return $this->pipes;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public static function create(ContainerInterface $container): static
    {
        return new static($container);
    }
}