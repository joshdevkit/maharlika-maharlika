<?php

namespace Maharlika\Validation;

use Exception;

class ValidationException extends Exception
{
    protected $validator;
    protected $errors;
    protected $errorBag;

    public function __construct($validator, string $message = 'Validation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validator = $validator;
        $this->errors = $validator->errors();
        $this->errorBag = $validator->getErrorBag() ?? 'default';
    }

    public function errors()
    {
        return $this->errors;
    }

    public function validator()
    {
        return $this->validator;
    }

    public function getErrorBag()
    {
        return $this->errorBag;
    }

    public function setErrorBag(string $errorBag): void
    {
        $this->errorBag = $errorBag;
    }
}
