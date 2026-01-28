<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\JsonResource;
use Maharlika\Http\Response;
use Maharlika\Http\Server;
use Maharlika\Pagination\Paginator;

class RouteExecutor
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function execute(mixed $action, array $params, RequestInterface $request): mixed
    {
        if ($action instanceof \Closure) {
            $result = $this->executeClosure($action, $params, $request);
        } elseif (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $result = $this->executeControllerMethod($controller, $method, $params, $request);
        } elseif (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            $result = $this->executeControllerMethod($controller, $method, $params, $request);
        } elseif (is_callable($action)) {
            $result = call_user_func_array($action, $params);
        } else {
            throw new \RuntimeException("Invalid route action");
        }

        return $this->wrapResponse($result, $request);
    }

    private function executeClosure(\Closure $closure, array $params, RequestInterface $request): mixed
    {
        $reflection = new \ReflectionFunction($closure);
        $boundParams = $this->bindParameters($reflection, $params, $request);
        return $closure(...$boundParams);
    }

    private function executeControllerMethod(
        string|object $controller,
        string $method,
        array $params,
        RequestInterface $request
    ): mixed {
        if (is_string($controller)) {
            $controller = $this->container->make($controller);
        }

        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($request);
        }

        $reflection = new \ReflectionMethod($controller, $method);
        $boundParams = $this->bindParameters($reflection, $params, $request);

        return $controller->$method(...$boundParams);
    }

    private function bindParameters(
        \ReflectionFunctionAbstract $reflection,
        array $routeParams,
        RequestInterface $request
    ): array {
        $binder = new ParameterBinder($request, $this->container); 
        return $binder->bindParameters($reflection, $routeParams);
    }

    /**
     * Wrap the response appropriately
     */
    private function wrapResponse(mixed $result, RequestInterface $request): ResponseInterface
    {
        // Already a Response
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // Check if it's an API request
        $isApiRequest = $this->isApiRequest($request);

        // Handle arrays and objects for API requests
        if ($isApiRequest && (is_array($result) || is_object($result))) {
            // If it's a Paginator, convert to array first
            if ($result instanceof Paginator) {
                return (new JsonResource($result))->toResponse();
            }

            // If it has a toArray method, use it
            if (is_object($result) && method_exists($result, 'toArray')) {
                return (new JsonResource($result))->toResponse();
            }

            // For plain arrays
            if (is_array($result)) {
                return (new JsonResource($result))->toResponse();
            }
        }

        // Handle string responses
        if (is_string($result)) {
            $contentType = $isApiRequest ? Server::getJsonPrefix() : Server::textHtml();
            return new Response($result, 200, ['Content-Type' => $contentType]);
        }

        // Handle null
        if ($result === null) {
            return new Response('', 204);
        }

        // Default: convert to string
        return new Response((string) $result);
    }

    /**
     * Check if the request is an API request
     */
    private function isApiRequest(RequestInterface $request): bool
    {
        $path = $request->getPath();
        
        if (str_starts_with($path, Server::apiPrefix())) {
            return true;
        }

        $accept = $request->header('Accept');
        if ($accept && str_contains($accept, Server::getJsonPrefix())) {
            return true;
        }

        return false;
    }
}