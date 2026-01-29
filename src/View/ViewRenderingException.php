<?php

declare(strict_types=1);

namespace Maharlika\View;

use Exception;
use Throwable;
use ReflectionProperty;

class ViewRenderingException extends Exception
{
    protected string $viewPath;
    protected array $viewData;

    public function __construct(
        string $viewPath = '',
        array $viewData = [],
        ?Throwable $previous = null
    ) {
        $this->viewPath = $viewPath;
        $this->viewData = $viewData;

        $message = $this->buildMessage($previous);

        parent::__construct($message, 0, $previous);
        
        // Use reflection to override the file and line properties
        if ($this->viewPath) {
            $this->overrideFileAndLine($previous);
        }
    }

    /**
     * Override the file and line properties using reflection
     */
    protected function overrideFileAndLine(?Throwable $previous): void
    {
        try {
            // Override file property
            $fileReflection = new ReflectionProperty(Exception::class, 'file');
            $fileReflection->setValue($this, $this->viewPath);
            
            // Override line property
            if ($previous) {
                $lineReflection = new ReflectionProperty(Exception::class, 'line');
                $lineReflection->setValue($this, $this->mapLineNumber($previous));
            }
        } catch (\Exception $e) {
            // If reflection fails, just continue without overriding
        }
    }

    protected function buildMessage(?Throwable $previous): string
    {
        $message = "";

        if ($previous) {
            $message .= "[Error] {$previous->getMessage()} \n\n";
        }

        if (!empty($this->viewData)) {
            $keys = array_map(fn($key) => "[$key]", array_keys($this->viewData));
            $message .= "\n\nAvailable Data " . implode(', ', $keys);
        }

        return $message;
    }

    /**
     * Map compiled file line number to approximate source line
     */
    protected function mapLineNumber(Throwable $previous): int
    {
        // Read the compiled file to look for line markers or do smart mapping
        $compiledFile = $previous->getFile();
        $compiledLine = $previous->getLine();
        
        if (!file_exists($compiledFile) || !file_exists($this->viewPath)) {
            return 0;
        }
        
        // Try to find context in the original view file
        $errorMessage = $previous->getMessage();
        
        // Search for the variable/property/function in the error
        $searchTerm = $this->extractSearchTerm($errorMessage);
        
        if ($searchTerm) {
            $viewLines = file($this->viewPath);
            foreach ($viewLines as $lineNum => $line) {
                if (stripos($line, $searchTerm) !== false) {
                    return $lineNum + 1;
                }
            }
        }
        
        // Fallback: rough estimate based on file sizes
        $viewLineCount = count(file($this->viewPath));
        $compiledLineCount = count(file($compiledFile));
        
        if ($compiledLineCount > 0) {
            $ratio = $viewLineCount / $compiledLineCount;
            return max(1, (int)($compiledLine * $ratio));
        }
        
        return 0;
    }

    /**
     * Extract search term from error message
     */
    protected function extractSearchTerm(string $errorMessage): ?string
    {
        // Property access: property "name"
        if (preg_match('/property ["\'](\w+)["\']/', $errorMessage, $matches)) {
            return '->' . $matches[1];
        }
        
        // Undefined variable: variable $name
        if (preg_match('/variable [\$](\w+)/', $errorMessage, $matches)) {
            return '$' . $matches[1];
        }
        
        // Function call: function name()
        if (preg_match('/function (\w+)\(\)/', $errorMessage, $matches)) {
            return $matches[1] . '(';
        }
        
        return null;
    }

    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }
}