<?php

namespace Maharlika\Validation;

use Maharlika\Contracts\Validation\ValidatorInterface;
use Maharlika\Support\Traits\Macroable;

class Validation implements ValidatorInterface
{
    use Macroable;
    
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $customAttributes;
    protected array $errors = [];
    protected string $errorBag = 'default';
    protected array $afterCallbacks = [];

    protected array $mappedRules = [
        // Original rules
        'required' => \Maharlika\Validation\Rules\Required::class,
        'required_with' => \Maharlika\Validation\Rules\RequiredWith::class,
        'required_without' => \Maharlika\Validation\Rules\RequiredWithout::class,
        'email' => \Maharlika\Validation\Rules\Email::class,
        'min' => \Maharlika\Validation\Rules\Min::class,
        'max' => \Maharlika\Validation\Rules\Max::class,
        'confirmed' => \Maharlika\Validation\Rules\Confirmed::class,
        'unique' => \Maharlika\Validation\Rules\Unique::class,
        'exists' => \Maharlika\Validation\Rules\Exists::class,
        'in' => \Maharlika\Validation\Rules\In::class,
        'string' => \Maharlika\Validation\Rules\StringRule::class,
        'integer' => \Maharlika\Validation\Rules\IntegerRule::class,
        'numeric' => \Maharlika\Validation\Rules\NumericRule::class,
        'array' => \Maharlika\Validation\Rules\ArrayRule::class,
        'boolean' => \Maharlika\Validation\Rules\BooleanRule::class,
        'same' => \Maharlika\Validation\Rules\Same::class,
        'different' => \Maharlika\Validation\Rules\Different::class,
        'not_in' => \Maharlika\Validation\Rules\NotIn::class,
        'date' => \Maharlika\Validation\Rules\DateRule::class,
        'after' => \Maharlika\Validation\Rules\After::class,
        'before' => \Maharlika\Validation\Rules\Before::class,
        'json' => \Maharlika\Validation\Rules\JsonRule::class,
        'url' => \Maharlika\Validation\Rules\UrlRule::class,
        'regex' => \Maharlika\Validation\Rules\Regex::class,
        'file' => \Maharlika\Validation\Rules\FileRule::class,
        'image' => \Maharlika\Validation\Rules\ImageRule::class,
        
       
        'alpha' => \Maharlika\Validation\Rules\Alpha::class,
        'alpha_num' => \Maharlika\Validation\Rules\AlphaNum::class,
        'alpha_dash' => \Maharlika\Validation\Rules\AlphaDash::class,
        'between' => \Maharlika\Validation\Rules\Between::class,
        'size' => \Maharlika\Validation\Rules\Size::class,
        'starts_with' => \Maharlika\Validation\Rules\StartsWith::class,
        'ends_with' => \Maharlika\Validation\Rules\EndsWith::class,
        'ip' => \Maharlika\Validation\Rules\Ip::class,
        'ipv4' => \Maharlika\Validation\Rules\Ipv4::class,
        'ipv6' => \Maharlika\Validation\Rules\Ipv6::class,
        'lowercase' => \Maharlika\Validation\Rules\Lowercase::class,
        'uppercase' => \Maharlika\Validation\Rules\Uppercase::class,
        'timezone' => \Maharlika\Validation\Rules\Timezone::class,
        'uuid' => \Maharlika\Validation\Rules\Uuid::class,
        'digits' => \Maharlika\Validation\Rules\Digits::class,
        'digits_between' => \Maharlika\Validation\Rules\DigitsBetween::class,
        'active_url' => \Maharlika\Validation\Rules\ActiveUrl::class,
        'accepted' => \Maharlika\Validation\Rules\Accepted::class,
        'declined' => \Maharlika\Validation\Rules\Declined::class,
    ];

    public function __construct(array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->customAttributes = $customAttributes;
    }

    /**
     * Add an after validation callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * Run the after validation callbacks.
     *
     * @return void
     */
    protected function runAfterCallbacks(): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $callback($this);
        }
    }

    public function fails(): bool
    {
        $this->errors = [];

        // Run standard validation rules
        foreach ($this->rules as $field => $fieldRules) {
            if (is_array($fieldRules)) {
                $this->validateWithRuleObjects($field, $fieldRules);
            } else {
                $this->validateWithStringRules($field, $fieldRules);
            }
        }

        // Run after callbacks
        $this->runAfterCallbacks();

        return !empty($this->errors);
    }

    /**
     * Determine if the validation passes.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    protected function validateWithRuleObjects(string $field, array $rules): void
    {
        $value = $this->data[$field] ?? null;
        $isNullable = false;
        $isSometimes = false;

        foreach ($rules as $rule) {
            if ($rule === 'nullable' || (is_string($rule) && $rule === 'nullable')) {
                $isNullable = true;
            }
            if ($rule === 'sometimes' || (is_string($rule) && $rule === 'sometimes')) {
                $isSometimes = true;
            }
        }

        if ($isNullable && ($value === null || $value === '')) {
            return;
        }

        if ($isSometimes && !isset($this->data[$field])) {
            return;
        }

        foreach ($rules as $rule) {
            if ($rule === 'nullable' || $rule === 'sometimes') {
                continue;
            }

            if (is_string($rule)) {
                $ruleObject = $this->createRuleFromString($rule);
                if ($ruleObject && !$ruleObject->passes($field, $value, $this->data)) {
                    $ruleName = $this->extractRuleName($rule);
                    $message = $this->getMessage($field, $ruleName, $ruleObject, $rule);
                    $this->addError($field, $message, $value);
                }
            } elseif ($rule instanceof Rule) {
                if (!$rule->passes($field, $value, $this->data)) {
                    $ruleName = $this->getRuleName($rule);
                    $message = $this->getMessage($field, $ruleName, $rule);
                    $this->addError($field, $message, $value);
                }
            }
        }
    }

    protected function validateWithStringRules(string $field, string $ruleString): void
    {
        $rules = explode('|', $ruleString);
        $ruleObjects = [];

        foreach ($rules as $rule) {
            $ruleObjects[] = $this->createRuleFromString($rule) ?? $rule;
        }

        $this->validateWithRuleObjects($field, $ruleObjects);
    }

    /**
     * Get the error message for a field and rule, with placeholder replacement
     */
    protected function getMessage(string $field, string $ruleName, Rule $ruleObject, ?string $originalRule = null): string
    {
        // Check for custom message
        $customMessage = $this->messages["$field.$ruleName"] ?? null;

        if ($customMessage) {
            return $this->replacePlaceholders($customMessage, $field, $ruleObject, $originalRule);
        }

        // Use default message from rule
        return $this->replacePlaceholders($ruleObject->message($field), $field, $ruleObject, $originalRule);
    }

    /**
     * Replace placeholders in error messages
     */
    protected function replacePlaceholders(string $message, string $field, Rule $ruleObject, ?string $originalRule = null): string
    {
        // Replace :attribute with field name
        $attribute = $this->customAttributes[$field] ?? $field;
        $message = str_replace(':attribute', $attribute, $message);
        $message = str_replace(':field', $attribute, $message);

        // Get parameters from the rule
        $parameters = $this->getRuleParameters($ruleObject, $originalRule);

        // Replace parameter placeholders
        foreach ($parameters as $key => $value) {
            $message = str_replace(":{$key}", (string)$value, $message);
        }

        return $message;
    }

    /**
     * Extract parameters from rule object
     */
    protected function getRuleParameters(Rule $ruleObject, ?string $originalRule = null): array
    {
        $parameters = [];

        // Extract parameters based on rule type
        if ($ruleObject instanceof \Maharlika\Validation\Rules\Min) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('min');
            $parameters['min'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Max) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('max');
            $parameters['max'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Same) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('otherField');
            $parameters['other'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Different) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('otherField');
            $parameters['other'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Between) {
            $reflection = new \ReflectionClass($ruleObject);
            $minProp = $reflection->getProperty('min');
            $maxProp = $reflection->getProperty('max');
            $parameters['min'] = $minProp->getValue($ruleObject);
            $parameters['max'] = $maxProp->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Size) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('size');
            $parameters['size'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\Digits) {
            $reflection = new \ReflectionClass($ruleObject);
            $property = $reflection->getProperty('length');
            $parameters['length'] = $property->getValue($ruleObject);
        } elseif ($ruleObject instanceof \Maharlika\Validation\Rules\DigitsBetween) {
            $reflection = new \ReflectionClass($ruleObject);
            $minProp = $reflection->getProperty('min');
            $maxProp = $reflection->getProperty('max');
            $parameters['min'] = $minProp->getValue($ruleObject);
            $parameters['max'] = $maxProp->getValue($ruleObject);
        }

        // If we have the original rule string, extract parameters from it
        if ($originalRule && str_contains($originalRule, ':')) {
            [, $paramString] = explode(':', $originalRule, 2);
            $values = explode(',', $paramString);

            // Map common parameter names
            $paramNames = ['min', 'max', 'size', 'other', 'value', 'length'];
            foreach ($values as $index => $value) {
                $paramName = $paramNames[$index] ?? "param{$index}";
                $parameters[$paramName] = $value;
            }
        }

        return $parameters;
    }

    protected function createRuleFromString(string $rule): ?Rule
    {
        if ($rule === 'nullable' || $rule === 'sometimes') {
            return null;
        }

        $ruleName = $rule;
        $parameters = [];

        if (str_contains($rule, ':')) {
            [$ruleName, $paramString] = explode(':', $rule, 2);
            $parameters = explode(',', $paramString);
        }

        $ruleClass = $this->mappedRules[strtolower($ruleName)] ?? null;

        if (!$ruleClass) {
            throw new \InvalidArgumentException("Validation rule '{$ruleName}' does not exist.");
        }

        if (strtolower($ruleName) === 'unique') {
            return $this->createUniqueRule($parameters);
        }

        if (strtolower($ruleName) === 'required_with') {
            return new $ruleClass(...$parameters);
        }

        return !empty($parameters) ? new $ruleClass(...$parameters) : new $ruleClass();
    }

    protected function createUniqueRule(array $parameters): Rule
    {
        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? null;
        $ignoreValue = $parameters[2] ?? null;
        $ignoreColumn = $parameters[3] ?? null;

        $ruleClass = $this->mappedRules['unique'];
        $uniqueRule = new $ruleClass($table, $column ?? '');

        if ($ignoreValue !== null) {
            $uniqueRule->ignore($ignoreValue, $ignoreColumn);
        }

        return $uniqueRule;
    }

    protected function extractRuleName(string $rule): string
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2)[0];
        }
        return $rule;
    }

    protected function getRuleName(Rule $rule): string
    {
        $className = get_class($rule);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        if (str_ends_with($shortName, 'Rule')) {
            $shortName = substr($shortName, 0, -4);
        }

        return strtolower($shortName);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (str_ends_with($field, '_confirmation')) {
                continue;
            }

            if (str_contains($field, '.')) {
                $value = $this->getNestedValue($this->data, $field);
            } else {
                $value = $this->data[$field] ?? null;
            }

            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $value;
            }
        }

        return $validated;
    }

    protected function getNestedValue(array $data, string $key)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    public function setErrorBag(string $errorBag): void
    {
        $this->errorBag = $errorBag;
    }

    public function getErrorBag(): string
    {
        return $this->errorBag;
    }

    public function addError(string $field, string $message, $value = null): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
