<?php

namespace Maharlika\Routing;

use Maharlika\Routing\Attributes\Route;
use Maharlika\Routing\Attributes\HttpGet;
use Maharlika\Routing\Attributes\HttpPost;
use Maharlika\Routing\Attributes\HttpPut;
use Maharlika\Routing\Attributes\HttpDelete;
use Maharlika\Routing\Attributes\HttpPatch;

class RouteAttributeResolver
{
    public function resolveFromMethod(\ReflectionMethod $method): array
    {
        $routes = [];
        $attributes = $method->getAttributes();

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($this->isRouteAttribute($instance)) {
                $routes[] = [
                    'method' => $this->getHttpMethod($instance),
                    'path' => $instance->path ?? $this->generatePathFromMethodName($method->getName()),
                ];
            }
        }

        return $routes;
    }

    private function isRouteAttribute(object $attribute): bool
    {
        return $attribute instanceof Route ||
            $attribute instanceof HttpGet ||
            $attribute instanceof HttpPost ||
            $attribute instanceof HttpPut ||
            $attribute instanceof HttpDelete ||
            $attribute instanceof HttpPatch;
    }

    private function getHttpMethod(object $attribute): string
    {
        if (isset($attribute->method)) {
            return strtoupper($attribute->method);
        }

        return match (true) {
            $attribute instanceof HttpGet => 'GET',
            $attribute instanceof HttpPost => 'POST',
            $attribute instanceof HttpPut => 'PUT',
            $attribute instanceof HttpDelete => 'DELETE',
            $attribute instanceof HttpPatch => 'PATCH',
            default => 'GET',
        };
    }

    private function generatePathFromMethodName(string $method): string
    {
        return '/' . strtolower(preg_replace('/[A-Z]/', '-$0', $method));
    }
}
