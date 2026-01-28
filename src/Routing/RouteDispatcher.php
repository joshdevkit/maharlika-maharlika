<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Exceptions\HttpErrorRenderer;
use Maharlika\Http\Response;
use Maharlika\Pipeline\Pipeline;
use Maharlika\Support\Arr;

class RouteDispatcher
{
    private ContainerInterface $container;
    private MiddlewareResolver $middlewareResolver;
    private RouteExecutor $routeExecutor;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->middlewareResolver = new MiddlewareResolver();
        $this->routeExecutor = new RouteExecutor($container);
    }

    public function dispatch(?array $route, RequestInterface $request): ResponseInterface
    {
        if (preg_match('#//+#', $request->getUri())) {
            return HttpErrorRenderer::render(400, 'Bad Request: Malformed URI', '');
        }

        if ($route === null) {
            return HttpErrorRenderer::render(404, '', $request->getPath());
        }

        $method = $this->resolveMethod($request);

        if (isset($route['type'])) {
            if ($route['type'] === 'method_not_allowed') {
                if (Arr::contains($route['allowed_methods'], $method)) {
                    unset($route['type']);
                } else {
                    return $this->methodNotAllowedResponse(
                        $method,
                        $route['path'],
                        $route['allowed_methods']
                    );
                }
            } else {
                return $this->handleSpecialRoute($route);
            }
        }

        // Add route parameters to request attributes
        if (!empty($route['params'])) {
            foreach ($route['params'] as $key => $value) {
                $request->attributes->set($key, $value);
            }
        }

        return $this->executeRoute($route, $request);
    }

    private function resolveMethod(RequestInterface $request): string
    {
        if ($request->method() === 'POST') {
            $spoofed = Arr::get($request->all(), '_method');
            if ($spoofed) {
                return strtoupper($spoofed);
            }
        }

        return $request->method();
    }


    private function handleSpecialRoute(array $route): ResponseInterface
    {
        if ($route['type'] === 'method_not_allowed') {
            return $this->methodNotAllowedResponse(
                $route['method'],
                $route['path'],
                $route['allowed_methods']
            );
        }

        if ($route['type'] === 'not_found') {
            return HttpErrorRenderer::render(404, '', $route['path']);
        }

        return HttpErrorRenderer::render(500, 'Unknown route type', '');
    }

    private function methodNotAllowedResponse(
        string $requestedMethod,
        string $path,
        array $allowedMethods
    ): ResponseInterface {
        $allowedMethodsStr = implode(', ', $allowedMethods);

        $message = sprintf(
            'Method %s not allowed  %s. Allowed methods: %s',
            $requestedMethod,
            $path,
            $allowedMethodsStr
        );
        $response = HttpErrorRenderer::render(405, $message, $path);

        if ($response instanceof Response) {
            $response->header('Allow', $allowedMethodsStr);
        }

        return $response;
    }

    /**
     * Execute route through Pipeline
     * Response conversion is handled automatically by Pipeline
     */
    private function executeRoute(array $route, RequestInterface $request): ResponseInterface
    {
        $action = $route['action'];

        // Handle Closure routes
        if ($action instanceof \Closure) {
            return (new Pipeline($this->container))
                ->send($request)
                ->through([])
                ->then(fn($req) => $this->routeExecutor->execute(
                    $action,
                    $route['params'] ?? [],
                    $req
                ));
        }

        // Handle Controller routes
        [$controller, $method] = $action;
        $middlewares = $this->middlewareResolver->resolve($controller, $method);

        $pipelineMiddlewares = array_map(
            fn($middleware) => new PipelineMiddlewareAdapter($middleware, $this->container),
            $middlewares
        );

        return (new Pipeline($this->container))
            ->send($request)
            ->through($pipelineMiddlewares)
            ->then(fn($req) => $this->routeExecutor->execute(
                $route['action'],
                $route['params'] ?? [],
                $req
            ));
    }
}
