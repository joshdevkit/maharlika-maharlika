<?php

if (!function_exists('jsrender')) {
    /**
     * Create a JsRender response
     * 
     * @param string $component
     * @param array $props
     * @return \Maharlika\Contracts\Http\ResponseInterface
     */
    function jsrender(string $component, array $props = [])
    {
        return app('jsrender')->render($component, $props);
    }
}

if (!function_exists('jsrender_share')) {
    /**
     * Share data with all JsRender responses
     * 
     * @param string|array $key
     * @param mixed $value
     * @return \Maharlika\JsRender\MountSpaPhp
     */
    function jsrender_share(string|array $key, mixed $value = null)
    {
        return app('jsrender')->share($key, $value);
    }
}

if (!function_exists('jsrender_lazy')) {
    /**
     * Create a lazy prop
     * 
     * @param callable $callback
     * @return \Maharlika\JsRender\LazyProp
     */
    function jsrender_lazy(callable $callback)
    {
        return app('jsrender')->lazy($callback);
    }
}

if (!function_exists('jsrender_always')) {
    /**
     * Create an always prop
     * 
     * @param mixed $value
     * @return \Maharlika\JsRender\AlwaysProp
     */
    function jsrender_always(mixed $value)
    {
        return app('jsrender')->always($value);
    }
}

if (!function_exists('jsrender_location')) {
    /**
     * Create a JsRender redirect
     * 
     * @param string $url
     * @return \Maharlika\Contracts\Http\ResponseInterface
     */
    function jsrender_location(string $url)
    {
        return app('jsrender')->location($url);
    }
}