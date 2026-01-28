<?php

declare(strict_types=1);

namespace Maharlika\View\Compilers;

/**
 * Handles compilation of control structures (if, foreach, for, while, etc.).
 */
class ControlStructureCompiler
{
    /**
     * Compile control structures in the given content.
     */
    public function compile(string $contents): string
    {
        $contents = $this->compileOpeningStructures($contents);
        $contents = $this->compileForelse($contents);
        $contents = $this->compileClosingStructures($contents);

        return $contents;
    }

    /**
     * Compile opening control structures.
     */
    protected function compileOpeningStructures(string $contents): string
    {
        $directives = [
            'if'      => 'if',
            'elseif'  => 'elseif',
            'foreach' => 'foreach',
            'for'     => 'for',
            'while'   => 'while',
            'isset'   => 'if (isset',
            'empty'   => 'if (empty',
            'unless'  => 'if (!',
        ];

        $offset = 0;

        while (true) {
            if (!preg_match('/@(' . implode('|', array_keys($directives)) . ')\s*\(/i', $contents, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $directive = strtolower($m[1][0]);
            $matchPos = $m[0][1];
            $openParenPos = $matchPos + strlen($m[0][0]) - 1;

            $closing = $this->findMatchingParen($contents, $openParenPos);
            if ($closing === -1) {
                $offset = $openParenPos + 1;
                continue;
            }

            $inner = substr($contents, $openParenPos + 1, $closing - ($openParenPos + 1));
            $phpPrefix = $directives[$directive];

            if ($directive === 'isset') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>";
            } elseif ($directive === 'empty') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>";
            } elseif ($directive === 'unless') {
                $replacement = "<?php if (!(" . trim($inner) . ")): ?>";
            } else {
                $replacement = "<?php {$phpPrefix} (" . trim($inner) . "): ?>";
            }

            $before = substr($contents, 0, (int) $matchPos);
            $after = substr($contents, $closing + 1);
            $contents = $before . $replacement . $after;
            $offset = $matchPos + strlen($replacement);
        }

        return $contents;
    }

    /**
     * Compile @forelse directive.
     */
    protected function compileForelse(string $contents): string
    {
        $contents = preg_replace_callback('/@forelse\s*\((.+?)\s+as\s+(.+?)\)/s', function ($m) {
            $collection = trim($m[1]);
            $iteration  = trim($m[2]);
            return "<?php if (count({$collection}) > 0): foreach ({$collection} as {$iteration}): ?>";
        }, $contents);

        $contents = preg_replace('/@empty\b/', '<?php endforeach; else: ?>', $contents);

        return $contents;
    }

    /**
     * Compile closing control structures.
     */
    protected function compileClosingStructures(string $contents): string
    {
        $closers = [
            '/@else\b/'       => '<?php else: ?>',
            '/@endif\b/'      => '<?php endif; ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@endfor\b/'     => '<?php endfor; ?>',
            '/@endwhile\b/'   => '<?php endwhile; ?>',
            '/@endisset\b/'   => '<?php endif; ?>',
            '/@endempty\b/'   => '<?php endif; ?>',
            '/@endunless\b/'  => '<?php endif; ?>',
            '/@endforelse\b/' => '<?php endif; ?>',
        ];

        foreach ($closers as $pattern => $replace) {
            $contents = preg_replace($pattern, $replace, $contents);
        }

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