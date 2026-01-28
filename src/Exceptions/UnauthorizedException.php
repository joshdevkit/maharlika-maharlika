<?php

namespace Maharlika\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    protected int $status;

    public function __construct(
        string $message = "This action is unauthorized.",
        int $status = 403
    ) {
        parent::__construct($message, $status);
        $this->status = $status;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }
}
