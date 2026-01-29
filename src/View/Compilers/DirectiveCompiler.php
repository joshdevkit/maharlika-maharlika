<?php

declare(strict_types=1);

namespace Maharlika\View\Compilers;

/**
 * Handles compilation of Blade directives.
 */
class DirectiveCompiler
{
    /**
     * Array of custom directives.
     *
     * @var array<string, callable>
     */
    protected array $customDirectives = [];

    /**
     * Register a custom directive.
     *
     * @param string $name The directive name (without @)
     * @param callable $handler The handler function that returns PHP code
     * @return void
     */
    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Compile @props directive.
     * This extracts props from the $attributes bag and makes them available as variables.
     */
    protected function compileProps(string $contents): string
    {
        return preg_replace_callback('/@props\s*\(\s*(\[.*?\])\s*\)/s', function ($matches) {
            $propsArray = $matches[1];

            $php = "<?php ";
            $php .= "\$__props = {$propsArray}; ";
            $php .= "foreach (\$__props as \$__propKey => \$__propDefault) { ";
            $php .= "    if (is_numeric(\$__propKey)) { ";
            $php .= "        \$__propKey = \$__propDefault; ";
            $php .= "        \$__propDefault = null; ";
            $php .= "    } ";
            $php .= "    if (isset(\$attributes) && \$attributes->has(\$__propKey)) { ";
            $php .= "        \${\$__propKey} = \$attributes->get(\$__propKey); ";
            $php .= "        \$attributes = \$attributes->except(\$__propKey); ";
            $php .= "    } elseif (!isset(\${\$__propKey})) { ";
            $php .= "        \${\$__propKey} = \$__propDefault; ";
            $php .= "    } ";
            $php .= "} ";
            $php .= "unset(\$__props, \$__propKey, \$__propDefault); ";
            $php .= "?>";

            return $php;
        }, $contents);
    }

    /**
     * Compile all directives in the given content.
     */
    public function compile(string $contents): string
    {
        $contents = $this->compileProps($contents);
        $contents = $this->compileInclude($contents);
        $contents = $this->compileFlash($contents);
        $contents = $this->compileError($contents);
        $contents = $this->compilePhp($contents);
        $contents = $this->compileCsrf($contents);
        $contents = $this->compileMethod($contents);
        $contents = $this->compileVite($contents);
        $contents = $this->compileAuth($contents);
        $contents = $this->compileOnce($contents);
        $contents = $this->compileJson($contents);
        $contents = $this->compileRoute($contents);
        $contents = $this->compileRouter($contents);
        $contents = $this->compileInertia($contents);
        $contents = $this->compileStack($contents);
        $contents = $this->compilePush($contents);
        $contents = $this->compilePrepend($contents);
        $contents = $this->compileEnv($contents);
        $contents = $this->compileProduction($contents);
        $contents = $this->compileSession($contents);
        $contents = $this->compileOld($contents);
        $contents = $this->compileClass($contents);
        $contents = $this->compileStyle($contents);
        $contents = $this->compileChecked($contents);
        $contents = $this->compileSelected($contents);
        $contents = $this->compileDisabled($contents);
        $contents = $this->compileReadonly($contents);
        $contents = $this->compileRequired($contents);
        $contents = $this->compileBreak($contents);
        $contents = $this->compileContinue($contents);

        // Gate authorization directives
        $contents = $this->compileCan($contents);
        $contents = $this->compileCannot($contents);
        $contents = $this->compileCanAny($contents);

        // Compile custom directives
        $contents = $this->compileCustomDirectives($contents);

        return $contents;
    }

    /**
     * Compile custom directives.
     */
    protected function compileCustomDirectives(string $contents): string
    {
        foreach ($this->customDirectives as $name => $handler) {
            $contents = preg_replace_callback(
                '/@' . preg_quote($name, '/') . '(?:\s*\(((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\))?/s',
                function ($matches) use ($handler) {
                    // If there's a captured expression, pass it to handler
                    // Otherwise pass null
                    $expression = isset($matches[1]) && $matches[1] !== '' ? $matches[1] : null;
                    return $handler($expression);
                },
                $contents
            );
        }

        return $contents;
    }

    /**
     * Compile comments.
     */
    public function compileComments(string $contents): string
    {
        return preg_replace('/\{\{--(.*?)--\}\}/s', '', $contents);
    }

    /**
     * Compile @include directive.
     */
    protected function compileInclude(string $contents): string
    {
        return preg_replace_callback(
            '/@include\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/s',
            function ($m) {
                $view = addslashes($m[1]);
                $data = isset($m[2]) ? $m[2] : '[]';

                return "<?php echo app('view')->render('{$view}', {$data}); ?>";
            },
            $contents
        );
    }

    /**
     * Compile @flash ... @endflash directive.
     * Now checks the session's _flash.old array properly
     */
    protected function compileFlash(string $contents): string
    {
        return preg_replace_callback('/@flash\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endflash/s', function ($m) {
            $type = addslashes($m[1]);
            $inner = $m[2];
            return "<?php "
                . "\$__flashData = app('session')->get('_flash.old', []); "
                . "if (isset(\$__flashData['{$type}'])): "
                . "\$message = \$__flashData['{$type}']; ?>\n"
                . $inner
                . "\n<?php endif; unset(\$__flashData); ?>";
        }, $contents);
    }

    /**
     * Compile @error ... @enderror directive.
     * Uses the $errors variable that should be passed to the view
     */
    protected function compileError(string $contents): string
    {
        return preg_replace_callback('/@error\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@enderror/s', function ($m) {
            $field = addslashes($m[1]);
            $inner = $m[2];
            return "<?php if (isset(\$errors) && \$errors->has('{$field}')): "
                . "\$message = \$errors->first('{$field}'); ?>\n"
                . $inner
                . "\n<?php endif; ?>";
        }, $contents);
    }

    /**
     * Compile @php ... @endphp directive and single-line @php().
     */
    protected function compilePhp(string $contents): string
    {
        // Compile single-line @php($expression)
        $contents = preg_replace('/@php\s*\(\s*(.+?)\s*\)/s', '<?php $1; ?>', $contents);

        // Compile block @php ... @endphp
        $contents = preg_replace('/@php\s*(.*?)\s*@endphp/s', '<?php $1 ?>', $contents);

        return $contents;
    }

    /**
     * Compile @csrf directive.
     */
    protected function compileCsrf(string $contents): string
    {
        return preg_replace('/@csrf/s', '<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">', $contents);
    }

    /**
     * Compile @method directive.
     */
    protected function compileMethod(string $contents): string
    {
        return preg_replace_callback('/@method\s*\(\s*[\'"](.+?)[\'"]\s*\)/s', function ($m) {
            $method = strtoupper($m[1]);
            return '<input type="hidden" name="_method" value="' . $method . '">';
        }, $contents);
    }

    /**
     * Compile @vite and @viteReactRefresh directives.
     */
    protected function compileVite(string $contents): string
    {
        $contents = preg_replace_callback('/@vite\s*\((.+?)\)/s', function ($m) {
            $args = trim($m[1]);
            return "<?php echo html(vite({$args})); ?>";
        }, $contents);

        $contents = preg_replace('/@viteReactRefresh/s', "<?php echo html(vite()->reactRefresh()); ?>", $contents);

        return $contents;
    }

    /**
     * Compile @auth / @guest directives.
     */
    protected function compileAuth(string $contents): string
    {
        $contents = preg_replace(
            '/@auth\b/',
            "<?php if (app('session')->get('auth_id')): ?>",
            $contents
        );

        $contents = preg_replace('/@endauth\b/', "<?php endif; ?>", $contents);

        $contents = preg_replace(
            '/@guest\b/',
            "<?php if (!app('session')->get('auth_id')): ?>",
            $contents
        );

        $contents = preg_replace('/@endguest\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @once ... @endonce directive.
     * Ensures blocks with the same content render only once.
     */
    protected function compileOnce(string $contents): string
    {
        return preg_replace_callback('/@once(.*?)@endonce/s', function ($matches) {
            $inner = $matches[1];

            // Generate a hash of the inner content
            $hash = md5($inner);

            return "<?php if (!isset(\$__onceRendered['$hash'])): "
                . "\$__onceRendered['$hash'] = true; ?>\n"
                . $inner
                . "\n<?php endif; ?>";
        }, $contents);
    }

    /**
     * Compile @json directive.
     */
    protected function compileJson(string $contents): string
    {
        $offset = 0;

        while (true) {
            if (!preg_match('/@json\s*\(/i', $contents, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $matchPos = $m[0][1];
            $openParenPos = $matchPos + strlen($m[0][0]) - 1;

            $closing = $this->findMatchingParen($contents, $openParenPos);
            if ($closing === -1) {
                $offset = $openParenPos + 1;
                continue;
            }

            $expression = substr($contents, $openParenPos + 1, $closing - ($openParenPos + 1));
            $replacement = "<?php echo htmlspecialchars(json_encode(" . trim($expression) . ", JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES); ?>";
            $before = substr($contents, 0, (int) $matchPos);
            $after = substr($contents, $closing + 1);
            $contents = $before . $replacement . $after;
            $offset = $matchPos + strlen($replacement);
        }

        return $contents;
    }

    /**
     * Compile @route directive (legacy action-based routing).
     */
    protected function compileRoute(string $contents): string
    {
        return preg_replace_callback(
            '/@route\s*\(\s*([^,]+?)\s*,\s*([^,)]+?)(?:\s*,\s*(.+?))?\s*\)/s',
            function ($matches) {
                $method = trim($matches[1], " \t\n\r\0\x0B'\"");
                $controller = trim($matches[2], " \t\n\r\0\x0B'\"");
                $params = isset($matches[3]) ? trim($matches[3]) : '[]';
                if (!str_ends_with($controller, 'Controller')) {
                    $controller .= 'Controller';
                }
                $action = "{$method}@{$controller}";

                return "<?php echo router('{$action}', {$params}); ?>";
            },
            $contents
        );
    }

    /**
     * Compile @router directive for named routes.
     */
    protected function compileRouter(string $contents): string
    {
        return preg_replace_callback(
            '/@router\s*\(\s*([^,)]+?)(?:\s*,\s*(.+?))?\s*\)/s',
            function ($matches) {
                $name = trim($matches[1]);
                $params = isset($matches[2]) ? trim($matches[2]) : '[]';

                return "<?php echo router({$name}, {$params}); ?>";
            },
            $contents
        );
    }

    /**
     * Compile Inertia.js directives.
     */
    protected function compileInertia(string $contents): string
    {
        // Compile @inertiaHead - renders title and meta tags
        $contents = preg_replace(
            '/@inertiaHead\b/',
            "<?php if (isset(\$page) && is_array(\$page) && isset(\$page['props']['title'])): ?>" .
                "<title><?php echo htmlspecialchars(\$page['props']['title']); ?></title>" .
                "<?php endif; ?>",
            $contents
        );

        // Compile @inertia - renders the root div with page data
        $contents = preg_replace(
            '/@inertia\b/',
            '<?php echo \'<div id="app" data-page="\' . htmlspecialchars(json_encode($page ?? []), ENT_QUOTES, \'UTF-8\') . \'"></div>\'; ?>',
            $contents
        );

        return $contents;
    }

    /**
     * Compile @stack directive.
     * Renders all content pushed to a named stack.
     */
    protected function compileStack(string $contents): string
    {
        return preg_replace_callback(
            '/@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $stack = addslashes($matches[1]);
                return "<?php if (isset(\$__stacks['{$stack}'])): "
                    . "echo implode('', \$__stacks['{$stack}']); "
                    . "endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @push ... @endpush directive.
     * Pushes content to a named stack (appends to end).
     */
    protected function compilePush(string $contents): string
    {
        return preg_replace_callback(
            '/@push\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endpush/s',
            function ($matches) {
                $stack = addslashes($matches[1]);
                $content = $matches[2];
                return "<?php \$__stacks['{$stack}'][] = <<<'__STACK_END'\n"
                    . $content
                    . "\n__STACK_END;\n?>";
            },
            $contents
        );
    }

    /**
     * Compile @prepend ... @endprepend directive.
     * Prepends content to a named stack (adds to beginning).
     */
    protected function compilePrepend(string $contents): string
    {
        return preg_replace_callback(
            '/@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endprepend/s',
            function ($matches) {
                $stack = addslashes($matches[1]);
                $content = $matches[2];
                return "<?php array_unshift(\$__stacks['{$stack}'] ?? (\$__stacks['{$stack}'] = []), <<<'__STACK_END'\n"
                    . $content
                    . "\n__STACK_END\n); ?>";
            },
            $contents
        );
    }

    /**
     * Compile @env directive.
     * Conditionally renders content based on environment.
     */
    protected function compileEnv(string $contents): string
    {
        $contents = preg_replace_callback(
            '/@env\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $env = $matches[1];
                return "<?php if (app('config')->get('app.env') === {$env}): ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endenv\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @production / @endproduction directive.
     * Conditionally renders content only in production environment.
     */
    protected function compileProduction(string $contents): string
    {
        $contents = preg_replace(
            '/@production\b/',
            "<?php if (app('config')->get('app.env') === 'production'): ?>",
            $contents
        );

        $contents = preg_replace('/@endproduction\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @session directive.
     * Check if a session value exists.
     */
    protected function compileSession(string $contents): string
    {
        $contents = preg_replace_callback(
            '/@session\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                return "<?php if (app('session')->has('{$key}')): "
                    . "\$value = app('session')->get('{$key}'); ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endsession\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @old directive.
     * Retrieves old input value with optional default.
     */
    protected function compileOld(string $contents): string
    {
        return preg_replace_callback(
            '/@old\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                $default = isset($matches[2]) ? $matches[2] : "''";
                return "<?php echo htmlspecialchars(app('session')->old('{$key}', {$default})); ?>";
            },
            $contents
        );
    }

    /**
     * Compile @class directive.
     * Conditionally compile CSS classes.
     */
    protected function compileClass(string $contents): string
    {
        return preg_replace_callback(
            '/@class\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "class=\"<?php echo \$__buildClass({$matches[1]}); ?>\"";
            },
            $contents
        );
    }

    /**
     * Compile @style directive.
     * Conditionally compile inline styles.
     */
    protected function compileStyle(string $contents): string
    {
        return preg_replace_callback(
            '/@style\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "style=\"<?php echo \$__buildStyle({$matches[1]}); ?>\"";
            },
            $contents
        );
    }

    /**
     * Compile @checked directive.
     * Conditionally add 'checked' attribute.
     */
    protected function compileChecked(string $contents): string
    {
        return preg_replace_callback(
            '/@checked\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if ({$matches[1]}): echo 'checked'; endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @selected directive.
     * Conditionally add 'selected' attribute.
     */
    protected function compileSelected(string $contents): string
    {
        return preg_replace_callback(
            '/@selected\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if ({$matches[1]}): echo 'selected'; endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @disabled directive.
     * Conditionally add 'disabled' attribute.
     */
    protected function compileDisabled(string $contents): string
    {
        return preg_replace_callback(
            '/@disabled\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if ({$matches[1]}): echo 'disabled'; endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @readonly directive.
     * Conditionally add 'readonly' attribute.
     */
    protected function compileReadonly(string $contents): string
    {
        return preg_replace_callback(
            '/@readonly\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if ({$matches[1]}): echo 'readonly'; endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @required directive.
     * Conditionally add 'required' attribute.
     */
    protected function compileRequired(string $contents): string
    {
        return preg_replace_callback(
            '/@required\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if ({$matches[1]}): echo 'required'; endif; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @break directive.
     * Break out of a loop, optionally with a condition.
     */
    protected function compileBreak(string $contents): string
    {
        return preg_replace_callback(
            '/@break\s*(?:\(\s*(.+?)\s*\))?/s',
            function ($matches) {
                $condition = isset($matches[1]) ? $matches[1] : null;
                return $condition
                    ? "<?php if ({$condition}) break; ?>"
                    : "<?php break; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @continue directive.
     * Continue to the next iteration, optionally with a condition.
     */
    protected function compileContinue(string $contents): string
    {
        return preg_replace_callback(
            '/@continue\s*(?:\(\s*(.+?)\s*\))?/s',
            function ($matches) {
                $condition = isset($matches[1]) ? $matches[1] : null;
                return $condition
                    ? "<?php if ({$condition}) continue; ?>"
                    : "<?php continue; ?>";
            },
            $contents
        );
    }

    /**
     * Compile @can directive.
     * Check if the authenticated user is authorized to perform an ability.
     */
    protected function compileCan(string $contents): string
    {
        $contents = preg_replace_callback(
            '/@can\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if (app('auth')->check() && app('auth')->user()->can({$matches[1]})): ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endcan\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @cannot (alias: @cantcan) directive.
     * Check if the authenticated user is NOT authorized to perform an ability.
     */
    protected function compileCannot(string $contents): string
    {
        $contents = preg_replace_callback(
            '/@cannot\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if (app('auth')->check() && app('auth')->user()->cannot({$matches[1]})): ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endcannot\b/', "<?php endif; ?>", $contents);

        // Support @cant as an alias
        $contents = preg_replace_callback(
            '/@cant\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if (app('auth')->check() && app('auth')->user()->cant({$matches[1]})): ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endcant\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Compile @canany directive.
     * Check if the authenticated user can perform any of the given abilities.
     */
    protected function compileCanAny(string $contents): string
    {
        $contents = preg_replace_callback(
            '/@canany\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                return "<?php if (app('auth')->check() && app('auth')->user()->canAny({$matches[1]})): ?>";
            },
            $contents
        );

        $contents = preg_replace('/@endcanany\b/', "<?php endif; ?>", $contents);

        return $contents;
    }

    /**
     * Find matching closing parenthesis.
     */
    protected function findMatchingParen(string $s, int $openPos): int
    {
        $len = strlen($s);
        if ($openPos < 0 || $openPos >= $len || $s[$openPos] !== '(') {
            return -1;
        }

        $depth = 1;
        $i = $openPos + 1;
        $inString = false;
        $stringDelim = null;

        while ($i < $len) {
            $ch = $s[$i];

            if ($inString) {
                if ($ch === $stringDelim) {
                    $backslashes = 0;
                    $j = $i - 1;
                    while ($j >= 0 && $s[$j] === '\\') {
                        $backslashes++;
                        $j--;
                    }
                    if ($backslashes % 2 === 0) {
                        $inString = false;
                        $stringDelim = null;
                    }
                }
                $i++;
                continue;
            } else {
                if ($ch === '\'' || $ch === '"') {
                    $inString = true;
                    $stringDelim = $ch;
                    $i++;
                    continue;
                }
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }

            $i++;
        }

        return -1;
    }
}
