<?php

declare(strict_types=1);

namespace Maharlika\View;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Support\ErrorBag;
use Maharlika\View\Engines\TemplateEngine;

class TemplateEvaluator
{
    protected string $cachePath;

    public function __construct(?string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? app()->basePath('storage/framework/cache/views');

        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function evaluate(string $compiled, array $data, TemplateEngine $engine): string
    {
        $__sections = [];
        $__currentSection = null;
        $__extends = null;

        // Use the SessionInterface from container
        $session = app(SessionInterface::class);

        // Get errors from session
        $errors = $session->get('errors') ?? new ErrorBag();

        // Convert array to ErrorBag if needed
        if (is_array($errors)) {
            $errors = new ErrorBag($errors);
        }

        ${'errors'} = $errors;

        // Define helper functions for @class and @style directives
        $__buildClass = function ($classes) {
            if (is_string($classes)) {
                return $classes;
            }

            $classList = [];
            foreach ($classes as $class => $condition) {
                if (is_numeric($class)) {
                    $classList[] = $condition;
                } elseif ($condition) {
                    $classList[] = $class;
                }
            }
            return implode(' ', $classList);
        };

        $__buildStyle = function ($styles) {
            if (is_string($styles)) {
                return $styles;
            }

            $styleList = [];
            foreach ($styles as $style => $condition) {
                if (is_numeric($style)) {
                    $styleList[] = $condition;
                } elseif ($condition) {
                    $styleList[] = $style;
                }
            }
            return implode(' ', $styleList);
        };

        extract($data, EXTR_SKIP);

        // Write compiled template to cache file ONCE, before the component evaluation
        $cacheFile = $this->getCacheFilePath($compiled);
        file_put_contents($cacheFile, $compiled);

        // the template in the context of that component so $this refers to it
        if (isset($data['component']) && is_object($data['component'])) {
            // Use a closure to bind the component as $this
            $evaluator = function () use ($cacheFile, $data, $__buildClass, $__buildStyle, $__sections, $__currentSection, $__extends, $errors) {
                // Extract all data into this scope
                extract($data, EXTR_SKIP);

                ob_start();

                try {
                    include $cacheFile;
                } catch (\Throwable $e) {
                    ob_get_clean();
                    throw $e;
                }

                return ob_get_clean();
            };

            // Bind the closure to the component instance so $this works
            $boundEvaluator = \Closure::bind($evaluator, $data['component'], get_class($data['component']));
            $output = ltrim($boundEvaluator());
        } else {
            // No component, evaluate normally
            ob_start();

            try {
                include $cacheFile;
            } catch (\Throwable $e) {
                ob_get_clean();
                throw $e;
            }

            $output = ltrim(ob_get_clean());
        }

        // Handle template inheritance
        if (isset($__extends) && $__extends) {
            $parentPath = app('view')->getFinder()->find($__extends);
            $parentSource = file_get_contents($parentPath);
            $parentCompiled = $engine->compile($parentSource);

            $parentCacheFile = $this->getCacheFilePath($parentCompiled);
            file_put_contents($parentCacheFile, $parentCompiled);

            ob_start();
            include $parentCacheFile;

            $output = ltrim(ob_get_clean());
        }

        if (config('session.driver') === 'file' && $session->has('errors')) {
            $session->forget('errors');
        }

        return $output;
    }

    /**
     * Get cache file path for compiled template
     */
    protected function getCacheFilePath(string $compiled): string
    {
        $hash = md5($compiled);
        return $this->cachePath . DIRECTORY_SEPARATOR . 'cache_' . $hash . '.php';
    }

    /**
     * Clear all cached compiled views
     */
    public function clearCache(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        $files = glob($this->cachePath . '/cache_*.php');

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Get the cache directory path
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }
}
