<?php 

namespace Maharlika\Validation\Rules;

use Maharlika\Validation\Rule;

class Password extends Rule
{
    protected int $min = 8;
    protected bool $requireLetters = false;
    protected bool $requireMixedCase = false;
    protected bool $requireNumbers = false;
    protected bool $requireSymbols = false;
    protected bool $uncompromised = false;

    public static function min(int $length): self
    {
        $instance = new static();
        $instance->min = $length;
        return $instance;
    }

    public static function defaults(): self
    {
        return static::min(8);
    }

    public function letters(): self
    {
        $this->requireLetters = true;
        return $this;
    }

    public function mixedCase(): self
    {
        $this->requireMixedCase = true;
        return $this;
    }

    public function numbers(): self
    {
        $this->requireNumbers = true;
        return $this;
    }

    public function symbols(): self
    {
        $this->requireSymbols = true;
        return $this;
    }

    public function uncompromised(int $threshold = 0): self
    {
        $this->uncompromised = true;
        return $this;
    }

    public function passes(string $field, $value, array $data): bool
    {
        if ($value === null || $value === '') return true;

        $password = (string)$value;

        if (strlen($password) < $this->min) {
            return false;
        }

        if ($this->requireLetters && !preg_match('/[a-zA-Z]/', $password)) {
            return false;
        }

        if ($this->requireMixedCase && (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password))) {
            return false;
        }

        if ($this->requireNumbers && !preg_match('/[0-9]/', $password)) {
            return false;
        }

        if ($this->requireSymbols && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    public function message(string $field): string
    {
        if ($this->message) {
            return $this->message;
        }

        $requirements = [];
        
        $requirements[] = "at least {$this->min} characters";
        
        if ($this->requireLetters) {
            $requirements[] = "contain letters";
        }
        
        if ($this->requireMixedCase) {
            $requirements[] = "contain both uppercase and lowercase letters";
        }
        
        if ($this->requireNumbers) {
            $requirements[] = "contain numbers";
        }
        
        if ($this->requireSymbols) {
            $requirements[] = "contain symbols";
        }

        return "The $field must be " . implode(', ', $requirements) . ".";
    }
}
