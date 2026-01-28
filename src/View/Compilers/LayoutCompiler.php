<?php

declare(strict_types=1);

namespace Maharlika\View\Compilers;

/**
 * Handles compilation of layout directives (extends, section, yield, renderBody).
 */
class LayoutCompiler
{
    /**
     * Compile layout directives in the given content.
     */
    public function compile(string $contents): string
    {
        $contents = $this->compileExtends($contents);
        $contents = $this->compileSection($contents);
        $contents = $this->compileEndSection($contents);
        $contents = $this->compileYield($contents);
        $contents = $this->compileRenderBody($contents);

        return $contents;
    }

    /**
     * Compile @extends directive.
     */
    protected function compileExtends(string $contents): string
    {
        return preg_replace_callback('/@extends\s*\([\'"](.+?)[\'"]\)/s', function ($m) {
            return "<?php \$__extends = '" . addslashes($m[1]) . "'; ?>";
        }, $contents);
    }

    /**
     * Compile @section directive (inline or block).
     */
    protected function compileSection(string $contents): string
    {
        return preg_replace_callback('/@section\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $value = $m[2] ?? null;
            if ($value !== null) {
                return "<?php \$__sections['{$section}'] = {$value}; ?>";
            }
            return "<?php ob_start(); \$__currentSection = '{$section}'; ?>";
        }, $contents);
    }

    /**
     * Compile @endsection directive.
     */
    protected function compileEndSection(string $contents): string
    {
        return preg_replace('/@endsection/', "<?php if(isset(\$__currentSection)) { \$__sections[\$__currentSection] = ob_get_clean(); unset(\$__currentSection); } ?>", $contents);
    }

    /**
     * Compile @yield directive.
     */
    protected function compileYield(string $contents): string
    {
        return preg_replace_callback('/@yield\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $default = $m[2] ?? "''";
            return "<?php echo \$__sections['{$section}'] ?? ({$default}); ?>";
        }, $contents);
    }

    /**
     * Compile @renderBody directive.
     */
    protected function compileRenderBody(string $contents): string
    {
        return preg_replace_callback('/@renderBody\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $default = $m[2] ?? "''";
            return "<?php echo \$__sections['{$section}'] ?? ({$default}); ?>";
        }, $contents);
    }
}