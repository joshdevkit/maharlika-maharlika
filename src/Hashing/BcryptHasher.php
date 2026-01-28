<?php

namespace Maharlika\Hashing;

use Maharlika\Contracts\Hashing\HasherContract;
use RuntimeException;

class BcryptHasher implements HasherContract
{
    /**
     * The default cost factor.
     *
     * @var int
     */
    protected int $rounds = 12;

    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected bool $verifyAlgorithm = false;

    /**
     * Create a new hasher instance.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->rounds = $options['rounds'] ?? $this->rounds;
        $this->verifyAlgorithm = $options['verify'] ?? $this->verifyAlgorithm;
    }

    /**
     * Hash the given value.
     *
     * @param string $value
     * @param array $options
     * @return string
     *
     * @throws RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     *
     * @throws RuntimeException
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if ($this->verifyAlgorithm && !$this->isHashed($hashedValue)) {
            throw new RuntimeException('This password does not use the Bcrypt algorithm.');
        }

        return password_verify($value, $hashedValue);
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
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    /**
     * Get information about the given hashed value.
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Set the default password work factor.
     *
     * @param int $rounds
     * @return $this
     */
    public function setRounds(int $rounds): static
    {
        $this->rounds = $rounds;
        return $this;
    }

    /**
     * Extract the cost value from the options array.
     *
     * @param array $options
     * @return int
     */
    protected function cost(array $options = []): int
    {
        return $options['rounds'] ?? $this->rounds;
    }

    /**
     * Verify that the given hash was created using Bcrypt.
     *
     * @param string $hashedValue
     * @return bool
     */
    protected function isHashed(string $hashedValue): bool
    {
        $info = $this->info($hashedValue);
        return $info['algoName'] === 'bcrypt';
    }

    // ============================================
    // SIMPLE HASHING METHODS (NON-PASSWORD)
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
}