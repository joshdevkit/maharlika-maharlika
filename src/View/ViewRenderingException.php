<?php

declare(strict_types=1);

namespace Maharlika\View;

use Exception;
use Throwable;

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
    }

    protected function buildMessage(?Throwable $previous): string
    {
        $message = "";

        // if ($this->viewPath) {
        //     $message .= " for view: '{$this->viewPath}'";
        // }

        if ($previous) {
            $message .= "[Error] {$previous->getMessage()}";
            // $message .= "\nIn file: {$previous->getFile()}";
            // $message .= "\nOn line: {$previous->getLine()}";
        }

        if (!empty($this->viewData)) {
            $message .= "\n\nAvailable view data keys: " . implode(', ', array_keys($this->viewData));
        }

        return $message;
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