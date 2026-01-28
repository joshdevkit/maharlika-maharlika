<?php

declare(strict_types=1);

namespace Maharlika\View\Compilers;

use Maharlika\View\ComponentResolver;

/**
 * Compiles component tags into PHP code.
 * Supports both self-closing and paired component tags with slots.
 */
class ComponentCompiler
{
    protected ComponentResolver $resolver;
    protected int $componentCounter = 0;

    public function __construct(?ComponentResolver $resolver = null)
    {
        $this->resolver = $resolver ?? $this->createDefaultResolver();
    }

    /**
     * Create default component resolver with standard namespaces.
     */
    protected function createDefaultResolver(): ComponentResolver
    {
        $resolver = new ComponentResolver();
        
        // Priority order: App components first, then Maharlika components
        $resolver->addNamespace('App\\View\\Components', base_path('app/View/Components'));
        $resolver->addNamespace('Maharlika\\Mail\\Components', base_path('Maharlika/Mail/Components'));
        $resolver->addNamespace('Maharlika\\Pagination\\Components', base_path('Maharlika/Pagination/Components'));
        
        return $resolver;
    }

    /**
     * Set a custom component resolver.
     */
    public function setResolver(ComponentResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Compile component tags in the given content.
     */
    public function compile(string $contents): string
    {
        // \Maharlika\Facades\Log::debug("ComponentCompiler: Starting compilation", [
        //     'has_resolver' => $this->resolver !== null,
        //     'content_length' => strlen($contents)
        // ]);
        
        // 1. Sanitize content to remove non-breaking spaces and other problematic characters
        $contents = $this->sanitizeContent($contents);

        // 2. Compile @props directive (crucial for component templates)
        $contents = $this->compileProps($contents);

        // Compile slots first (they're inside components)
        $contents = $this->compileSlots($contents);

        // Convert $attributes->... expressions to raw output before component compilation
        $contents = $this->convertAttributesToRaw($contents);

        // Compile PAIRED components first (they take precedence)
        $this->componentCounter = 0;
        // \Maharlika\Facades\Log::debug("ComponentCompiler: Compiling paired components");
        $contents = $this->compilePairedComponents($contents);

        // Then compile any remaining self-closing components
        $this->componentCounter = 0;
        // \Maharlika\Facades\Log::debug("ComponentCompiler: Compiling self-closing components");
        $contents = $this->compileSelfClosingComponents($contents);

        // \Maharlika\Facades\Log::debug("ComponentCompiler: Compilation complete");
        
        return $contents;
    }

    /**
     * Sanitizes content by replacing non-breaking spaces (U+00A0) and zero-width spaces
     * with standard spaces to prevent PHP syntax errors.
     */
    protected function sanitizeContent(string $contents): string
    {
        return str_replace(["\xC2\xA0", "\u{200B}", "\t"], ' ', $contents);
    }

    /**
     * Compiles the @props directive found in component views.
     */
    protected function compileProps(string $contents): string
    {
        $pattern = '/@props\s*\(\s*\[(.*?)\]\s*\)/s';

        return preg_replace_callback($pattern, function ($matches) {
            $props_array_content = trim($matches[1]);

            $php = "<?php \$__prop_defaults = [{$props_array_content}]; ";
            $php .= "foreach (\$__prop_defaults as \$__key => \$__default) { ";
            $php .= "  \$\$__key = \$\$__key ?? (\$attributes[\$__key] ?? \$__default); ";
            $php .= "  unset(\$attributes[\$__key]); ";
            $php .= "} ?>";

            return $php;
        }, $contents) ?: $contents;
    }

    /**
     * Convert {{ $attributes->... }} chains (like ->class or ->merge) to {!! $attributes->... !!}
     * for raw HTML attribute output, supporting complex chaining.
     */
    protected function convertAttributesToRaw(string $contents): string
    {
        return preg_replace_callback('/\{\{\s*(\$attributes\s*->.*?)\s*\}\}/s', function ($matches) {
            return '{!! ' . $matches[1] . ' !!}';
        }, $contents) ?: $contents;
    }

    /**
     * Check if a value is a dynamic expression that should be evaluated at runtime
     */
    protected function isDynamicExpression(string $value): bool
    {
        $value = trim($value);

        // Check for function calls like old(), request(), etc.
        if (preg_match('/^[a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*\s*\(/', $value)) {
            return true;
        }

        // Check for variable access
        if (preg_match('/^\$/', $value)) {
            return true;
        }

        // Check for array access on variables or functions
        if (preg_match('/[\$a-zA-Z_][a-zA-Z0-9_]*\s*\[/', $value)) {
            return true;
        }

        // Check for object property/method access
        if (preg_match('/->/', $value)) {
            return true;
        }

        // Check for static method calls
        if (preg_match('/::/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Builds the constructor arguments array from provided attributes, handling reflection.
     */
    protected function buildConstructorArgs(string $class, array $attributes): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return [];
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $attributes)) {
                $value = $attributes[$name]['value'];
                $isDynamic = $attributes[$name]['dynamic'];

                if ($value === null) {
                    $args[] = 'null';
                } elseif ($isDynamic && is_string($value) && $this->isDynamicExpression($value)) {
                    // For dynamic expressions (function calls, variables), skip in constructor
                    // They'll be set via bindPropertiesAndAttributes
                    if ($param->isDefaultValueAvailable()) {
                        $default = $param->getDefaultValue();
                        $args[] = $this->exportValue($default);
                    } else {
                        // Use type-appropriate default
                        $args[] = $this->getDefaultForType($param);
                    }
                } elseif ($isDynamic) {
                    // Dynamic but not a function/variable - evaluate it
                    $cleanValue = trim($value);
                    if (!empty($cleanValue)) {
                        $args[] = $cleanValue;
                    }
                } elseif (is_array($value)) {
                    $args[] = var_export($value, true);
                } elseif (is_string($value)) {
                    $args[] = var_export($value, true);
                } elseif (is_bool($value)) {
                    $args[] = $value ? 'true' : 'false';
                } else {
                    $args[] = var_export($value, true);
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $args[] = $this->exportValue($default);
            } else {
                throw new \RuntimeException("Required parameter '{$name}' missing for component {$class}");
            }
        }

        return $args;
    }

    /**
     * Export a value to PHP code
     */
    protected function exportValue($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (is_array($value)) {
            if (empty($value)) {
                return '[]';
            } else {
                return var_export($value, true);
            }
        } elseif (is_string($value)) {
            return var_export($value, true);
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            return var_export($value, true);
        }
    }

    /**
     * Get default value for a parameter based on its type
     */
    protected function getDefaultForType(\ReflectionParameter $param): string
    {
        $type = $param->getType();

        if (!$type || $type->allowsNull()) {
            return 'null';
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'array' => '[]',
            'string' => "''",
            'int' => '0',
            'float' => '0.0',
            'bool' => 'false',
            default => 'null'
        };
    }

    /**
     * Compile paired component tags (opening and closing together).
     * FIXED: Added : to the character class to support pagination::page-link
     */
    protected function compilePairedComponents(string $contents): string
    {
        // CHANGED: [a-zA-Z0-9\-\.:]+ now includes colon
        $pattern = '/<\s*x-([a-zA-Z0-9\-\.\:]+)\s*((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)>\s*(.*?)\s*<\/\s*x-\1\s*>/s';

        $result = preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = trim($matches[2]);
            $slotContent = $matches[3];
            $attributes = $this->extractComponentAttributes($attributes);

            // Recursively compile the slot content first
            $slotContent = $this->compileSlots($slotContent);
            $slotContent = $this->convertAttributesToRaw($slotContent);
            $slotContent = $this->compilePairedComponents($slotContent);
            $slotContent = $this->compileSelfClosingComponents($slotContent);

            $id = $this->componentCounter++;
            $class = $this->resolveComponentClass($component);
            
            if (!$class) {
                throw new \RuntimeException("Component class not found for: x-{$component}");
            }

            $attributesData = $this->parseAttributes($attributes);

            $instanceVar = '__component_' . $id;
            $constructorArgs = $this->buildConstructorArgs($class, $attributesData);
            $constructorArgsString = implode(', ', $constructorArgs);

            $attributesArray = $this->buildAttributesArray($attributesData);

            $php = "<?php ";
            $php .= "\${$instanceVar} = new \\{$class}({$constructorArgsString});";
            $php .= "\${$instanceVar}->bindPropertiesAndAttributes({$attributesArray}); ";
            $php .= "ob_start(); ?>";
            $php .= $slotContent;
            $php .= "<?php \$__slotContent = trim(ob_get_clean()); ";
            $php .= "\$__slots = get_defined_vars(); ";
            $php .= "foreach (\$__slots as \$__slotName => \$__slotValue) { ";
            $php .= "  if (strpos(\$__slotName, '__slot_') === 0) { ";
            $php .= "    \${$instanceVar}->setNamedSlot(substr(\$__slotName, 7), \$__slotValue); ";
            $php .= "    unset(\${\$__slotName}); ";
            $php .= "  } ";
            $php .= "} ";
            $php .= "\${$instanceVar}->setSlot(\$__slotContent); ";
            $php .= "echo \${$instanceVar}->resolveComponent(); ";
            $php .= "unset(\${$instanceVar}, \$__slotContent); ";
            $php .= "?>";

            return $php;
        }, $contents);

        return $result !== null ? $result : $contents;
    }

    protected function extractComponentAttributes(string $attrString): string
    {
        return trim($attrString);
    }

    /**
     * Compile slot tags.
     */
    protected function compileSlots(string $contents): string
    {
        // Named slots: <x-slot:name>...</x-slot:name>
        $contents = preg_replace_callback(
            '/<\s*x-slot:([a-zA-Z0-9\-_]+)\s*([^>]*)>(.*?)<\/\s*x-slot:\1\s*>/s',
            function ($matches) {
                $name = $matches[1];
                $content = $matches[3];
                $slotVar = '__slot_' . $name;
                return "<?php ob_start(); ?>{$content}<?php \${$slotVar} = ob_get_clean(); ?>";
            },
            $contents
        ) ?: $contents;

        // Default slot: <x-slot>...</x-slot>
        $contents = preg_replace_callback(
            '/<\s*x-slot\s*([^>]*)>(.*?)<\/\s*x-slot\s*>/s',
            function ($matches) {
                $attributes = trim($matches[1]);
                $content = $matches[2];

                if (preg_match('/name\s*=\s*["\']([^"\']+)["\']/', $attributes, $nameMatch)) {
                    $name = $nameMatch[1];
                    $slotVar = '__slot_' . $name;
                    return "<?php ob_start(); ?>{$content}<?php \${$slotVar} = ob_get_clean(); ?>";
                }

                return "<?php ob_start(); ?>{$content}<?php \$__slot_default = ob_get_clean(); ?>";
            },
            $contents
        ) ?: $contents;

        return $contents;
    }

    /**
     * Compile self-closing component tags.
     * FIXED: Added : to the character class to support pagination::page-link
     */
    protected function compileSelfClosingComponents(string $contents): string
    {
        // CHANGED: [a-zA-Z0-9\-\.\:]+ now includes colon
        $pattern = '/<\s*x-([a-zA-Z0-9\-\.\:]+)\s*((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)\s*\/>/s';

        $result = preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = isset($matches[2]) ? trim($matches[2]) : '';

            return $this->compileComponentTag($component, $attributes, true);
        }, $contents);

        return $result !== null ? $result : $contents;
    }

    /**
     * Compile a single component tag into PHP code.
     */
    protected function compileComponentTag(string $component, string $attributesString, bool $selfClosing): string
    {
        $class = $this->resolveComponentClass($component);
        
        if (!$class) {
            throw new \RuntimeException("Component class not found for: x-{$component}");
        }

        $attributes = $this->parseAttributes($attributesString);

        $instanceVar = '__component_' . $this->componentCounter++;

        if ($selfClosing) {
            $php = "<?php ";

            $constructorArgs = $this->buildConstructorArgs($class, $attributes);
            $constructorArgsString = implode(', ', $constructorArgs);

            $attributesArray = $this->buildAttributesArray($attributes);

            $php .= "\${$instanceVar} = new \\{$class}({$constructorArgsString}); ";
            $php .= "\${$instanceVar}->bindPropertiesAndAttributes({$attributesArray}); ";
            $php .= "\${$instanceVar}->setSlot(''); ";
            $php .= "echo \${$instanceVar}->resolveComponent(); ";
            $php .= "unset(\${$instanceVar}); ";
            $php .= "?>";

            return $php;
        }

        $attributesArray = $this->buildAttributesArray($attributes);
        return "<?php \${$instanceVar} = new \\{$class}({$attributesArray}); ob_start(); ?>";
    }

    /**
     * Resolve component name to fully qualified class name.
     */
    protected function resolveComponentClass(string $component): ?string
    {
        // \Maharlika\Facades\Log::debug("ComponentCompiler: Resolving component", [
        //     'component_tag' => $component,
        //     'has_resolver' => $this->resolver !== null
        // ]);
        
        $resolved = $this->resolver->resolve($component);
        
        // \Maharlika\Facades\Log::debug("ComponentCompiler: Resolution result", [
        //     'component' => $component,
        //     'resolved_class' => $resolved
        // ]);
        
        return $resolved;
    }

    /**
     * Compile the @router directive into a PHP expression.
     */
    protected function compileRouterValue(string $value): string
    {
        if (preg_match(
            '/^@router\s*\(\s*([^,)]+?)(?:\s*,\s*(.+?))?\s*\)$/s',
            $value,
            $matches
        )) {
            $name = trim($matches[1]);
            $params = isset($matches[2]) && !empty(trim($matches[2])) ? trim($matches[2]) : '[]';

            return "router({$name}, {$params})";
        }

        return $value;
    }

    /**
     * Parse attributes string into array.
     */
    protected function parseAttributes(string $attributesString): array
    {
        $attributes = [];

        $attributesString = trim($attributesString);
        if ($attributesString === '') {
            return $attributes;
        }

        $tokens = $this->tokenizeAttributes($attributesString);

        foreach ($tokens as $token) {
            $name = null;
            $rawValue = true;

            $eqPos = strpos($token, '=');
            if ($eqPos !== false) {
                $name = trim(substr($token, 0, $eqPos));
                $rawValue = trim(substr($token, $eqPos + 1));

                if ((str_starts_with($rawValue, '"') && str_ends_with($rawValue, '"')) ||
                    (str_starts_with($rawValue, "'") && str_ends_with($rawValue, "'"))
                ) {
                    $rawValue = substr($rawValue, 1, -1);
                }
            } else {
                $name = trim($token);
                $rawValue = true;
            }

            if ($name === '' || $name === null) {
                continue;
            }

            $isDynamic = false;
            $value = $rawValue;

            // Event attributes (like @click)
            if (strpos($name, '@') === 0) {
                $event = substr($name, 1);
                $name = 'on' . $event;
                $isDynamic = true;
                $value = $rawValue;
            }
            // :dynamic attribute
            elseif (strpos($name, ':') === 0) {
                $name = substr($name, 1);
                $isDynamic = true;

                if (is_string($rawValue) && preg_match('/^\s*@router\s*\(/', $rawValue)) {
                    $value = $this->compileRouterValue($rawValue);
                } elseif (is_string($rawValue) && preg_match('/^\s*@route\s*\(/', $rawValue)) {
                    $value = $this->compileRouteValue($rawValue);
                } elseif (is_string($rawValue) && preg_match('/^<\?php\s+echo\s+e\((.*?)\);\s*\?>$/s', $rawValue, $echoMatch)) {
                    $value = trim($echoMatch[1]);
                } else {
                    $value = $rawValue;
                }
            } elseif (is_string($rawValue) && (preg_match('/\{\{.+?\}\}/s', $rawValue) || preg_match('/\{!!.+?!!\}/s', $rawValue))) {
                $isDynamic = true;
                if (preg_match('/\{\{\s*(.+?)\s*\}\}/s', $rawValue, $m)) {
                    $value = $m[1];
                } elseif (preg_match('/\{!!\s*(.+?)\s*!!\}/s', $rawValue, $m)) {
                    $value = $m[1];
                } else {
                    $value = $rawValue;
                }
            } else {
                if ($rawValue === true) {
                    $value = true;
                } elseif (is_string($rawValue) && ($rawValue === 'true' || $rawValue === 'false' || $rawValue === 'null' || is_numeric($rawValue))) {
                    $value = $rawValue;
                } else {
                    $value = $rawValue;
                }
            }

            $attributes[$name] = [
                'dynamic' => (bool) $isDynamic,
                'value' => $value,
            ];
        }

        return $attributes;
    }

    /**
     * Tokenize attributes string.
     */
    function tokenizeAttributes(string $input): array
    {
        $tokens = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $depth = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $c = $input[$i];

            if ($c === '\\' && $i + 1 < strlen($input)) {
                $current .= $c . $input[$i + 1];
                $i++;
                continue;
            }

            if (($c === '"' || $c === "'") && $depth === 0) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $c;
                } elseif ($quoteChar === $c) {
                    $inQuote = false;
                    $quoteChar = '';
                }
                $current .= $c;
                continue;
            }

            if (!$inQuote) {
                if ($c === '(' || $c === '[' || $c === '{') {
                    $depth++;
                } elseif ($c === ')' || $c === ']' || $c === '}') {
                    $depth--;
                }
            }

            if (!$inQuote && $depth === 0 && $c === ' ') {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $c;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Compile the @route directive into a PHP expression.
     */
    protected function compileRouteValue(string $value): string
    {
        if (preg_match(
            '/^@route\s*\(\s*(.+?)\s*,\s*(.+?)\s*(?:,\s*(.+?)\s*)?\)$/s',
            $value,
            $matches
        )) {
            $method = trim($matches[1], " \t\n\r\0\x0B'\"");
            $controller = trim($matches[2], " \t\n\r\0\x0B'\"");
            $params = isset($matches[3]) && !empty(trim($matches[3])) ? trim($matches[3]) : '[]';

            $method = trim($method, "'\"");
            $controller = trim($controller, "'\"");

            if (!str_ends_with($controller, 'Controller')) {
                $controller .= 'Controller';
            }
            $action = "{$method}@{$controller}";

            return "router('{$action}', {$params})";
        }

        return $value;
    }

    /**
     * Build PHP array string for attributes.
     */
    protected function buildAttributesArray(array $attributes): string
    {
        if (empty($attributes)) {
            return '[]';
        }

        $parts = [];
        foreach ($attributes as $name => $data) {
            if ($name === '') {
                continue;
            }

            $key = var_export($name, true);
            $value = $data['value'];
            $isDynamic = $data['dynamic'];

            if ($value === null) {
                $parts[] = "{$key} => null";
            } elseif ($isDynamic) {
                if (is_array($value)) {
                    $parts[] = "{$key} => " . var_export($value, true);
                } else {
                    $parts[] = "{$key} => {$value}";
                }
            } elseif (is_bool($value)) {
                $valStr = $value ? 'true' : 'false';
                $parts[] = "{$key} => {$valStr}";
            } else {
                $valStr = var_export($value, true);
                $parts[] = "{$key} => {$valStr}";
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }
}