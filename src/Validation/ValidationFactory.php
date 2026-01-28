<?php

namespace Maharlika\Validation;

use Maharlika\Contracts\Validation\ValidatorInterface;

class ValidationFactory
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function make(array $data, array $rules, array $messages = [], array $customAttributes = []): ValidatorInterface
    {
        return new Validation($data, $rules, $messages, $customAttributes);
    }
}
