<?php

declare(strict_types=1);

namespace Maharlika\View\Compilers;

/**
 * Handles compilation of echo statements and escaped syntax.
 */
class EchoCompiler
{
    /**
     * Compile echo statements in the given content.
     */
    public function compile(string $contents): string
    {
        // Raw echos {!! !!}
        $contents = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', function ($m) {
            return '<?php echo (string)(' . $m[1] . '); ?>';
        }, $contents);

        // Escaped echos {{ }} - now with Htmlable support
        $contents = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function ($m) {
            $expression = $m[1];
            $hash = '__bladeEcho_' . md5($expression);
            
            $php = '<?php ';
            $php .= '$' . $hash . ' = ' . $expression . '; ';
            $php .= 'echo ($' . $hash . ' instanceof \\Maharlika\\Contracts\\Support\\Htmlable) ? $' . $hash . '->toHtml() : e($' . $hash . '); ';
            $php .= 'unset($' . $hash . '); ';
            $php .= '?>';
            
            return $php;
        }, $contents);

        return $contents;
    }

    /**
     * Store escaped Blade syntax (@{{, @{!!, @@) in placeholders.
     */
    public function storeEscapedSyntax(string $contents): string
    {
        // Store @{{ ... }} as a complete placeholder (for Alpine.js and Vue.js)
        $contents = preg_replace_callback('/@\{\{(.+?)\}\}/s', function ($m) {
            $hash = md5(uniqid('', true));
            return '___ESCAPED_ECHO_' . $hash . '_START___' . $m[1] . '___ESCAPED_ECHO_' . $hash . '_END___';
        }, $contents);

        // Store @{!! ... !!} as a complete placeholder
        $contents = preg_replace_callback('/@\{!!(.+?)!!\}/s', function ($m) {
            $hash = md5(uniqid('', true));
            return '___ESCAPED_RAW_' . $hash . '_START___' . $m[1] . '___ESCAPED_RAW_' . $hash . '_END___';
        }, $contents);

        // Store @@ as placeholder (literal @ symbol)
        $contents = str_replace('@@', '___ESCAPED_AT___', $contents);

        return $contents;
    }

    /**
     * Restore escaped Blade syntax from placeholders.
     */
    public function restoreEscapedSyntax(string $contents): string
    {
        // Restore @{{ ... }} to {{ ... }}
        $contents = preg_replace_callback('/___ESCAPED_ECHO_[a-f0-9]+_START___(.+?)___ESCAPED_ECHO_[a-f0-9]+_END___/s', function ($m) {
            return '{{' . $m[1] . '}}';
        }, $contents);

        // Restore @{!! ... !!} to {!! ... !!}
        $contents = preg_replace_callback('/___ESCAPED_RAW_[a-f0-9]+_START___(.+?)___ESCAPED_RAW_[a-f0-9]+_END___/s', function ($m) {
            return '{!!' . $m[1] . '!!}';
        }, $contents);

        // Restore @@ to @
        $contents = str_replace('___ESCAPED_AT___', '@', $contents);

        return $contents;
    }
}