<?php

namespace Maharlika\Providers;

use Maharlika\Contracts\Container\ContainerInterface;
use Maharlika\Contracts\ServiceProviderInterface;
use Maharlika\Encryption\Encrypter;

class EncryptionServiceProvider implements ServiceProviderInterface
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function register(): void
    {
        $this->container->singleton(Encrypter::class, function ($c) {
            $config = $c->get('config');
            
            $key = $config->get('app.key');
            
            if (empty($key)) {
                throw new \RuntimeException(
                    'No application encryption key has been specified. ' .
                    'Please set the APP_KEY environment variable or run: php dev key:generate'
                );
            }

            // If key is base64 encoded, decode it
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            $cipher = $config->get('app.cipher', 'AES-256-CBC');

            return new Encrypter($key, $cipher);
        });

        $this->container->singleton('encrypter', function ($c) {
            return $c->get(Encrypter::class);
        });
    }

    public function boot(): void
    {
        // Nothing to boot
    }
}