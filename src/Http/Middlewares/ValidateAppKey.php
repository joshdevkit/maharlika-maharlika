<?php

declare(strict_types=1);

namespace Maharlika\Http\Middlewares;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Framework\AppKeyValidator;
use Maharlika\Config\Repository as Config;

class ValidateAppKey
{
    protected AppKeyValidator $validator;
    protected Config $config;

    public function __construct(AppKeyValidator $validator, Config $config)
    {
        $this->validator = $validator;
        $this->config = $config;
    }

    /**
     * Handle the request and validate app key.
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // Only validate in production or when explicitly enabled
        $shouldValidate = $this->config->get('app.env') === 'production'
            || $this->config->get('app.validate_key', true);

        if ($shouldValidate) {
            // This will throw RuntimeException if invalid
            // Exception handler will catch it and display a nice error page
            $this->validator->validate();
        }

        return $next($request);
    }
}
