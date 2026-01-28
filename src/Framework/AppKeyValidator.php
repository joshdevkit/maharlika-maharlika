<?php

declare(strict_types=1);

namespace Maharlika\Framework;

use Maharlika\Config\Repository as Config;
use RuntimeException;

class AppKeyValidator
{
    /**
     * Validate the application key from configuration.
     *
     * @throws RuntimeException
     */
    public function validate(): void
    {
        $key = config('app.key');
        $cipher = config('app.cipher', 'AES-256-CBC');
        if (empty($key)) {
            throw new RuntimeException(
                'No application key has been specified. ' .
                    'Please run: php maharlika key:generate'
            );
        }

        $parsedKey = $this->parseKey($key);

        if ($this->keyIsInvalid($parsedKey, $cipher)) {
            throw new RuntimeException(
                'The application key is invalid. ' .
                    'The key must be ' . $this->getRequiredKeyLength($cipher) . ' bytes long. ' .
                    'Please run: php maharlika key:generate'
            );
        }
    }

    /**
     * Validate a specific key.
     *
     * @param string|null $key
     * @param string $cipher
     * @throws RuntimeException
     */
    public function validateKey(?string $key, string $cipher = 'AES-256-CBC'): void
    {
        if (empty($key)) {
            throw new RuntimeException(
                'No application key has been specified.'
            );
        }

        $parsedKey = $this->parseKey($key);

        if ($this->keyIsInvalid($parsedKey, $cipher)) {
            throw new RuntimeException(
                'The application key is invalid. ' .
                    'The key must be ' . $this->getRequiredKeyLength($cipher) . ' bytes long.'
            );
        }
    }

    /**
     * Parse the encryption key.
     */
    protected function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }

    /**
     * Check if the key is invalid for the given cipher.
     */
    protected function keyIsInvalid(string $key, string $cipher): bool
    {
        return mb_strlen($key, '8bit') !== $this->getRequiredKeyLength($cipher);
    }

    /**
     * Get the required key length for the cipher.
     */
    protected function getRequiredKeyLength(string $cipher): int
    {
        return match (strtoupper($cipher)) {
            'AES-128-CBC' => 16,
            'AES-256-CBC' => 32,
            default => throw new RuntimeException("Unsupported cipher: {$cipher}")
        };
    }

    /**
     * Check if the configured key is valid without throwing exception.
     */
    public function isValid(): bool
    {
        try {
            $this->validate();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Check if a specific key is valid without throwing exception.
     */
    public function isKeyValid(?string $key, string $cipher = 'AES-256-CBC'): bool
    {
        try {
            $this->validateKey($key, $cipher);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
