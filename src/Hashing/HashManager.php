<?php

namespace Maharlika\Hashing;

use Maharlika\Contracts\Hashing\HasherContract;
use InvalidArgumentException;

class HashManager implements HasherContract
{
    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * The configuration array.
     *
     * @var array
     */
    protected array $config;

    /**
     * The default driver name.
     *
     * @var string
     */
    protected string $defaultDriver = 'bcrypt';

    /**
     * Create a new Hash manager instance.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->defaultDriver = $config['driver'] ?? 'bcrypt';
    }

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return HasherContract
     */
    public function driver(?string $driver = null): HasherContract
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return HasherContract
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver): HasherContract
    {
        $config = $this->config[$driver] ?? [];

        return match ($driver) {
            'bcrypt' => new BcryptHasher($config),
            'argon', 'argon2', 'argon2id' => new ArgonHasher($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported."),
        };
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return $this->driver()->info($hashedValue);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver name.
     *
     * @param string $driver
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Determine if a given string is already hashed.
     *
     * @param string $value
     * @return bool
     */
    public function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }

    // ============================================
    // SIMPLE HASHING METHODS (DELEGATED TO BCRYPT HASHER)
    // ============================================

    /**
     * Generate a SHA-256 hash of the given value.
     *
     * @param string $value
     * @return string
     */
    public function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Generate a SHA-1 hash of the given value.
     *
     * @param string $value
     * @return string
     */
    public function sha1(string $value): string
    {
        return hash('sha1', $value);
    }

    /**
     * Generate an MD5 hash of the given value.
     *
     * @param string $value
     * @return string
     */
    public function md5(string $value): string
    {
        return hash('md5', $value);
    }

    /**
     * Generate a hash using the specified algorithm.
     *
     * @param string $algorithm
     * @param string $value
     * @return string
     */
    public function hash(string $algorithm, string $value): string
    {
        return hash($algorithm, $value);
    }

    /**
     * Timing-safe string comparison.
     *
     * @param string $known
     * @param string $user
     * @return bool
     */
    public function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate an HMAC hash.
     *
     * @param string $algorithm
     * @param string $data
     * @param string $key
     * @return string
     */
    public function hmac(string $algorithm, string $data, string $key): string
    {
        return hash_hmac($algorithm, $data, $key);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}