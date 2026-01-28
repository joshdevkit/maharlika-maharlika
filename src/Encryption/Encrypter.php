<?php

namespace Maharlika\Encryption;

use RuntimeException;

/**
 * Encrypter
 * 
 * Provides AES-256-CBC encryption for sensitive data.
 * Uses HMAC for authentication to prevent tampering.
 */
class Encrypter
{
    /**
     * The encryption key
     */
    protected string $key;

    /**
     * The cipher algorithm
     */
    protected string $cipher;

    /**
     * Available cipher methods
     */
    protected static array $supportedCiphers = [
        'AES-128-CBC' => ['size' => 16, 'aead' => false],
        'AES-256-CBC' => ['size' => 32, 'aead' => false],
        'AES-128-GCM' => ['size' => 16, 'aead' => true],
        'AES-256-GCM' => ['size' => 32, 'aead' => true],
    ];

    public function __construct(string $key, string $cipher = 'AES-256-CBC')
    {
        $this->key = $key;
        $this->cipher = strtoupper($cipher);

        if (!$this->supported($this->key, $this->cipher)) {
            $ciphers = implode(', ', array_keys(static::$supportedCiphers));
            throw new RuntimeException(
                "Unsupported cipher or incorrect key length. Supported ciphers: {$ciphers}"
            );
        }
    }

    /**
     * Determine if the given key and cipher combination is valid
     */
    public static function supported(string $key, string $cipher): bool
    {
        if (!isset(static::$supportedCiphers[$cipher])) {
            return false;
        }

        return mb_strlen($key, '8bit') === static::$supportedCiphers[$cipher]['size'];
    }

    /**
     * Generate a random encryption key for the given cipher
     */
    public static function generateKey(string $cipher = 'AES-256-CBC'): string
    {
        $cipher = strtoupper($cipher);
        
        if (!isset(static::$supportedCiphers[$cipher])) {
            throw new RuntimeException("Unsupported cipher: {$cipher}");
        }

        return random_bytes(static::$supportedCiphers[$cipher]['size']);
    }

    /**
     * Encrypt the given value
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $value = $serialize ? serialize($value) : $value;

        $encrypted = openssl_encrypt(
            $value,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        // Create authentication tag using HMAC
        $mac = $this->hash($iv, $encrypted);

        // Combine IV, encrypted value, and MAC
        $json = json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted),
            'mac' => $mac,
        ], JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Encrypt a string without serialization
     */
    public function encryptString(string $value): string
    {
        return $this->encrypt($value, false);
    }

    /**
     * Decrypt the given value
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);
        $decrypted = openssl_decrypt(
            base64_decode($payload['value']),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Decrypt the given string without unserialization
     */
    public function decryptString(string $payload): string
    {
        return $this->decrypt($payload, false);
    }

    /**
     * Get the JSON payload from the encrypted string
     */
    protected function getJsonPayload(string $payload): array
    {
        $payload = json_decode(base64_decode($payload), true);

        if (!$this->validPayload($payload)) {
            throw new RuntimeException('The payload is invalid.');
        }

        if (!$this->validMac($payload)) {
            throw new RuntimeException('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the payload is valid
     */
    protected function validPayload(mixed $payload): bool
    {
        return is_array($payload) 
            && isset($payload['iv'], $payload['value'], $payload['mac'])
            && strlen(base64_decode($payload['iv'], true)) === openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Verify the MAC is valid
     */
    protected function validMac(array $payload): bool
    {
        return hash_equals(
            $this->hash(base64_decode($payload['iv']), base64_decode($payload['value'])),
            $payload['mac']
        );
    }

    /**
     * Create a MAC for the given value
     */
    protected function hash(string $iv, string $value): string
    {
        return hash_hmac('sha256', $iv . $value, $this->key);
    }

    /**
     * Get the encryption key
     */
    public function getKey(): string
    {
        return $this->key;
    }
}