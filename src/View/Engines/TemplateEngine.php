<?php

declare(strict_types=1);

namespace Maharlika\View\Engines;

use Maharlika\Contracts\View\EngineInterface;
use Maharlika\View\Compilers\ComponentCompiler;
use Maharlika\View\Compilers\DirectiveCompiler;
use Maharlika\View\Compilers\EchoCompiler;
use Maharlika\View\Compilers\ControlStructureCompiler;
use Maharlika\View\Compilers\LayoutCompiler;
use Maharlika\View\ComponentResolver;
use Maharlika\View\TemplateEvaluator;
use Maharlika\Facades\Log;

class TemplateEngine implements EngineInterface
{
    protected ComponentCompiler $componentCompiler;
    protected DirectiveCompiler $directiveCompiler;
    protected EchoCompiler $echoCompiler;
    protected ControlStructureCompiler $controlStructureCompiler;
    protected LayoutCompiler $layoutCompiler;
    protected TemplateEvaluator $evaluator;

    public function __construct(string $cachePath, ?ComponentResolver $resolver = null)
    {
        // Log::debug("TemplateEngine constructor", [
        //     'has_resolver' => $resolver !== null,
        //     'resolver_namespaces' => $resolver ? $resolver->getNamespaces() : []
        // ]);
        
        $this->componentCompiler = new ComponentCompiler($resolver);
        $this->directiveCompiler = new DirectiveCompiler();
        $this->echoCompiler = new EchoCompiler();
        $this->controlStructureCompiler = new ControlStructureCompiler();
        $this->layoutCompiler = new LayoutCompiler();
        $this->evaluator = new TemplateEvaluator();
        
        // Log::debug("ComponentCompiler created with resolver");
    }

    /**
     * Register a custom directive with the compiler.
     *
     * @param string $name The directive name (without @)
     * @param callable $handler The handler function that returns PHP code
     * @return void
     */
    public function directive(string $name, callable $handler): void
    {
        $this->directiveCompiler->directive($name, $handler);
    }

    /**
     * Render a view file using cache when available.
     */
    public function render(string $path, array $data = []): string
    {
        $source = file_get_contents($path);
        $compiled = $this->compile($source);
        return $this->evaluator->evaluate($compiled, $data, $this);
    }

    /**
     * Compile template source into PHP code.
     */
    public function compile(string $contents): string
    {
        // Remove comments FIRST
        $contents = $this->directiveCompiler->compileComments($contents);

        // CRITICAL: Compile components BEFORE storing escaped syntax
        // This allows components to see raw {{ }} in attributes
        // Log::debug("Starting component compilation");
        $contents = $this->componentCompiler->compile($contents);
        // Log::debug("Component compilation complete");

        // NOW store escaped syntax (protects @{{ for JS frameworks)
        $contents = $this->echoCompiler->storeEscapedSyntax($contents);

        // Compile layout directives (extends, section, yield)
        $contents = $this->layoutCompiler->compile($contents);

        // Compile all other directives
        $contents = $this->directiveCompiler->compile($contents);

        // Compile control structures
        $contents = $this->controlStructureCompiler->compile($contents);

        // Compile echo statements {{ }} and {!! !!} AFTER components
        $contents = $this->echoCompiler->compile($contents);

        // Restore escaped syntax
        $contents = $this->echoCompiler->restoreEscapedSyntax($contents);
        
        return $contents;
    }
}