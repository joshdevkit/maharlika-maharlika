<?php

namespace Maharlika\Http;

use Maharlika\Exceptions\UnauthorizedException;

abstract class FormRequest extends Request
{
    /**
     * The container instance.
     *
     * @var \Maharlika\Contracts\Container\ContainerInterface
     */
    protected $container;

    /**
     * The validator instance.
     *
     * @var \Maharlika\Contracts\Validation\ValidatorInterface
     */
    protected $validator;

    /**
     * The validated data.
     *
     * @var array
     */
    protected $validatedData = [];

    /**
     * Define the validation rules for the request.
     * Child classes must implement this method.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Define custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Define custom attribute names for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array
     */
    public function after(): array
    {
        return [];
    }

    /**
     * Determine if the user is authorized to make this request.
     * Can be overridden by child classes.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resolve and perform validation.
     *
     * @throws \Maharlika\Exceptions\UnauthorizedException
     * @throws \Maharlika\Validation\ValidationException
     * @return void
     */
    public function validateResolved()
    {
        if (! $this->authorize()) {
            throw new UnauthorizedException();
        }

        $this->validator = $this->getValidatorInstance();

        if ($this->validator->fails()) {
            $this->failedValidation($this->validator);
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Maharlika\Contracts\Validation\ValidatorInterface  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        //
    }

    /**
     * Create and return the validator instance.
     * 
     * This method constructs the validator with the request data, rules, messages,
     * and custom attributes. It also applies any "after" validation callbacks defined
     * in the child class.
     * 
     * The "after" callbacks are executed after all standard validation rules have been
     * evaluated, allowing for complex cross-field validation, database checks, or any
     * custom validation logic that depends on multiple fields passing their initial rules.
     * 
     * @return \Maharlika\Contracts\Validation\ValidatorInterface The configured validator instance
     */
    protected function getValidatorInstance()
    {
        $validator = app('validator')->make(
            $this->all(),
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );

        // Allow custom validator configuration
        $this->withValidator($validator);
        
        // Apply after validation hooks if defined
        // These callbacks execute after standard rules pass, allowing for
        // complex validation logic like credential checks or cross-field validation
        $afterCallbacks = $this->after();
        if (!empty($afterCallbacks)) {
            foreach ($afterCallbacks as $callback) {
                $validator->after($callback);
            }
        }

        return $validator;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  mixed  $validator
     * @throws \Maharlika\Validation\ValidationException
     * @return void
     */
    protected function failedValidation($validator)
    {
        throw new \Maharlika\Validation\ValidationException($validator);
    }

    /**
     * Retrieve only the validated data.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validator->validated();
    }
}
