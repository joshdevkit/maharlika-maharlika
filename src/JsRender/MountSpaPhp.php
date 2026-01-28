<?php

namespace Maharlika\JsRender;

use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\Response;
use Maharlika\Http\JsonResponse;

class MountSpaPhp
{
    protected string $rootView = 'spa'; //spa.blade.php
    protected array $sharedProps = [];
    protected array $version = [];
    protected ?string $assetsVersion = null;

    /**
     * Create a JsRender response
     * 
     * @param string $component The JavaScript component name (e.g., 'Dashboard/Index')
     * @param array $props Data to pass to the component
     * @return ResponseInterface
     */
    public function render(string $component, array $props = []): ResponseInterface
    {
        // IMPORTANT: Resolve shared props FIRST, then merge with component props
        $resolvedSharedProps = $this->resolveSharedProps();
        $props = array_merge($resolvedSharedProps, $props);

        $page = [
            'component' => $component,
            'props' => $props,
            'url' => request()->getUri(),
            'version' => $this->getAssetVersion(),
        ];

        $request = request();

        // Check if this is a JsRender request
        if ($request->header('X-JsRender')) {
            return $this->createJsRenderResponse($page);
        }

        // Check if this is an Inertia request
        if ($this->isInertiaRequest()) {
            return $this->createInertiaResponse($page);
        }

        // First load - return full HTML with embedded page data
        return $this->createInitialResponse($page);
    }

    /**
     * Resolve shared props (LazyProp and AlwaysProp)
     */
    protected function resolveSharedProps(): array
    {
        $resolved = [];

        foreach ($this->sharedProps as $key => $value) {
            if ($value instanceof LazyProp || $value instanceof AlwaysProp) {
                $resolved[$key] = $value->resolve();
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveArrayProps($value);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Resolve props in arrays recursively
     */
    protected function resolveArrayProps(array $props): array
    {
        $resolved = [];

        foreach ($props as $key => $value) {
            if ($value instanceof LazyProp || $value instanceof AlwaysProp) {
                $resolved[$key] = $value->resolve();
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveArrayProps($value);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Share data with all JsRender responses
     * 
     * @param string|array $key
     * @param mixed $value
     * @return self
     */
    public function share(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            $this->sharedProps[$key] = $value;
        }

        return $this;
    }

    /**
     * Get all shared data
     * 
     * @return array
     */
    public function getShared(): array
    {
        return $this->sharedProps;
    }

    /**
     * Set the root view template
     * 
     * @param string $view
     * @return self
     */
    public function setRootView(string $view): self
    {
        $this->rootView = $view;
        return $this;
    }

    /**
     * Set or get the asset version for cache busting
     * 
     * @param string|null $version
     * @return self|string
     */
    public function version(?string $version = null): self|string
    {
        if ($version === null) {
            return $this->assetsVersion ?? '1';
        }

        $this->assetsVersion = $version;
        return $this;
    }

    /**
     * Create a lazy prop that's only evaluated when needed
     * 
     * @param callable $callback
     * @return LazyProp
     */
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create an always included prop (even on partial reloads)
     * 
     * @param mixed $value
     * @return AlwaysProp
     */
    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Redirect back with JsRender
     * 
     * @return ResponseInterface
     */
    public function back(): ResponseInterface
    {
        return $this->location(request()->header('Referer') ?? '/');
    }

    /**
     * Create a JsRender redirect response
     * 
     * @param string $url
     * @return ResponseInterface
     */
    public function location(string $url): ResponseInterface
    {
        if (request()->header('X-Inertia')) {
            return response('', 409)
                ->withHeader('X-Inertia-Location', $url);
        }

        return redirect($url);
    }

    /**
     * Get the asset version for cache busting
     * 
     * @return string
     */
    protected function getAssetVersion(): string
    {
        if ($this->assetsVersion) {
            return $this->assetsVersion;
        }

        // Try to get from manifest or use timestamp
        $manifestPath = base_path('public/build/manifest.json');

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            return md5(json_encode($manifest));
        }

        return (string) filemtime(base_path('public'));
    }

    /**
     * Create a JsRender JSON response for client-side navigation
     * 
     * @param array $page
     * @return JsonResponse
     */
    protected function createJsRenderResponse(array $page)
    {
        // Handle partial reloads
        if ($only = request()->header('X-JsRender-Partial-Data')) {
            $only = explode(',', $only);
            $page['props'] = $this->filterProps($page['props'], $only);
        }

        return response()->json($page)
            ->withHeader('X-JsRender', 'true')
            ->withHeader('Vary', 'Accept');
    }

    /**
     * Create the initial full HTML response
     * 
     * @param array $page
     * @return Response
     */
    protected function createInitialResponse(array $page): Response
    {
        // IMPORTANT: Share the page data with the view factory
        // This makes it accessible to @inertia and @inertiaHead directives
        if (function_exists('view')) {
            view()->share('page', $page);
        }

        // Also set it on the request for middleware/other components
        if (function_exists('request')) {
            request()->attributes->set('inertia.page', $page);
        }

        // Initial page load - return full HTML
        return Response::view($this->rootView, ['page' => $page]);
    }
    /**
     * Check if the current request is an Inertia request
     */
    protected function isInertiaRequest(): bool
    {
        return request()->header('X-Inertia') === 'true';
    }

    /**
     * Create an Inertia JSON response
     */
    protected function createInertiaResponse(array $page): Response
    {
        return Response::json($page)
            ->header('X-Inertia', 'true')
            ->header('Vary', 'X-Inertia');
    }

    /**
     * Filter props for partial reloads
     * 
     * @param array $props
     * @param array $only
     * @return array
     */
    protected function filterProps(array $props, array $only): array
    {
        $filtered = [];

        foreach ($props as $key => $value) {
            // Always include AlwaysProp (but they should already be resolved)
            if (in_array($key, ['auth', 'flash', 'errors', 'csrf'])) {
                $filtered[$key] = $value;
                continue;
            }

            // Include if requested
            if (in_array($key, $only)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
