<?php

namespace Maharlika\Facades;

/**
 * JsRender Facade
 * 
 * @method static \Maharlika\Contracts\Http\ResponseInterface render(string $component, array $props = [])
 * @method static self share(string|array $key, mixed $value = null)
 * @method static array getShared()
 * @method static self setRootView(string $view)
 * @method static self|string version(?string $version = null)
 * @method static \Maharlika\JsRender\LazyProp lazy(callable $callback)
 * @method static \Maharlika\JsRender\AlwaysProp always(mixed $value)
 * @method static \Maharlika\Contracts\Http\ResponseInterface back()
 * @method static \Maharlika\Contracts\Http\ResponseInterface location(string $url)
 * 
 * @see \Maharlika\JsRender\MountSpaPhp
 */
class Spa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'jsrender';
    }
}