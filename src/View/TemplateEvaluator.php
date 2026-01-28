<?php

declare(strict_types=1);

namespace Maharlika\View;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Support\ErrorBag;
use Maharlika\View\Engines\TemplateEngine;

class TemplateEvaluator
{
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

        // CRITICAL: Extract data variables into template scope
        // EXTR_SKIP ensures we don't overwrite the helper variables above
        extract($data, EXTR_SKIP);

        // CRITICAL FIX: If there's a component in the data, we need to evaluate 
        // the template in the context of that component so $this refers to it
        if (isset($data['component']) && is_object($data['component'])) {
            // Use a closure to bind the component as $this
            $evaluator = function() use ($compiled, $data, $__buildClass, $__buildStyle, $__sections, $__currentSection, $__extends, $errors) {
                // Extract all data into this scope
                extract($data, EXTR_SKIP);
                
                // Write compiled template to a temporary file
                $tempFile = sys_get_temp_dir() . '/blade_' . md5($compiled . uniqid('', true)) . '.php';
                file_put_contents($tempFile, $compiled);

                ob_start();

                try {
                    include $tempFile;
                } catch (\Throwable $e) {
                    ob_get_clean();
                    @unlink($tempFile);
                    throw $e;
                }

                @unlink($tempFile);
                return ob_get_clean();
            };
            
            // Bind the closure to the component instance so $this works
            $boundEvaluator = \Closure::bind($evaluator, $data['component'], get_class($data['component']));
            $output = ltrim($boundEvaluator());
            
        } else {
            // No component, evaluate normally
            // Write compiled template to a temporary file
            $tempFile = sys_get_temp_dir() . '/blade_' . md5($compiled . uniqid('', true)) . '.php';
            file_put_contents($tempFile, $compiled);

            ob_start();

            try {
                include $tempFile;
            } catch (\Throwable $e) {
                ob_get_clean();
                @unlink($tempFile);
                throw $e;
            }

            @unlink($tempFile);
            $output = ltrim(ob_get_clean());
        }

        // Handle template inheritance
        if (isset($__extends) && $__extends) {
            $parentPath = app('view')->getFinder()->find($__extends);
            $parentSource = file_get_contents($parentPath);
            $parentCompiled = $engine->compile($parentSource);

            $parentTempFile = sys_get_temp_dir() . '/blade_' . md5($parentCompiled . uniqid('', true)) . '.php';
            file_put_contents($parentTempFile, $parentCompiled);

            ob_start();
            include $parentTempFile;
            @unlink($parentTempFile);

            $output = ltrim(ob_get_clean());
        }

        if (config('session.driver') === 'file' && $session->has('errors')) {
            $session->forget('errors');
        }

        return $output;
    }
}