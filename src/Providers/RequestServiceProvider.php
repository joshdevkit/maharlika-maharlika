<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\Session\SessionInterface;
use Maharlika\Http\Request;
use Maharlika\Validation\ValidationException;
use Maharlika\Validation\ValidationFactory;

class RequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
       //
    }   

    public function boot(): void
    {
        /**
         * Add's the macro validate to the controller request cycle
         * 
         */
        Request::macro('validate', function (array $rules, array $messages = [], array $customAttributes = []) {
            $validator = app(ValidationFactory::class)->make(
                $this->all(),
                $rules,
                $messages,
                $customAttributes
            );

            /**
             * Let the exception handler process validation failures for consistent
             * error responses (JSON for API requests, redirects for web requests).
             * 
             * @throws \Maharlika\Validation\ValidationException
             */
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            return $validator->validated();
        });


        /**
         * add the validate with bag instance to the app session
         */
        Request::macro('validateWithBag', function (string $bag, array $rules) {
            try {
                return $this->validate($rules);
            } catch (ValidationException $e) {
                app(SessionInterface::class)->put("errors.$bag", $e->errors());
                throw $e;
            }
        });

         /**
         * Determine if the request has a valid signature.
         */
        Request::macro('hasValidSignature', function (bool $absolute = true) {
            return app('url')->hasValidSignature($this, $absolute);
        });

        /**
         * Determine if the request has a valid signature for a relative URL.
         */
        Request::macro('hasValidRelativeSignature', function () {
            return app('url')->hasCorrectSignature($this);
        });

        /**
         * Determine if the request has a valid signature while ignoring certain query parameters.
         */
        Request::macro('hasValidSignatureWhileIgnoring', function ($ignoreQuery = [], $absolute = true) {
            return app('url')->hasValidSignature($this, $absolute, is_array($ignoreQuery) ? $ignoreQuery : func_get_args());
        });

        /**
         * Determine if the request has a valid relative signature while ignoring certain query parameters.
         */
        Request::macro('hasValidRelativeSignatureWhileIgnoring', function ($ignoreQuery = []) {
            return app('url')->hasCorrectSignature($this, is_array($ignoreQuery) ? $ignoreQuery : func_get_args());
        });
    }
}
