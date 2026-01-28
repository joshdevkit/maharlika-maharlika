<?php

namespace Maharlika\Facades;

/**
 * Hash Facade
 * 
 * Provides password hashing and verification functionality using Bcrypt or Argon2.
 * Also includes utility methods for SHA-256, SHA-1, MD5, HMAC, and timing-safe comparisons.
 * 
 * @method static string make(string $value, array $options = []) Hash the given value using the default driver (bcrypt/argon2)
 * @method static bool check(string $value, string $hashedValue, array $options = []) Verify a plain value against a hash
 * @method static bool needsRehash(string $hashedValue, array $options = []) Check if a hash needs to be rehashed with current options
 * @method static array info(string $hashedValue) Get algorithm information about a hashed value
 * @method static \Maharlika\Contracts\Hashing\HasherContract driver(string|null $driver = null) Get a specific hash driver instance (bcrypt, argon, argon2, argon2id)
 * @method static bool isHashed(string $value) Determine if a given string is already hashed
 * @method static string getDefaultDriver() Get the name of the default hash driver
 * @method static void setDefaultDriver(string $driver) Set the default hash driver
 * 
 * Simple Hashing Methods (Non-Password):
 * @method static string sha256(string $value) Generate a SHA-256 hash of the given value
 * @method static string sha1(string $value) Generate a SHA-1 hash of the given value
 * @method static string md5(string $value) Generate an MD5 hash of the given value
 * @method static string hash(string $algorithm, string $value) Generate a hash using the specified algorithm
 * @method static bool equals(string $known, string $user) Timing-safe string comparison to prevent timing attacks
 * @method static string hmac(string $algorithm, string $data, string $key) Generate an HMAC hash with the given algorithm and key
 * 
 * Bcrypt Specific Methods:
 * @method static \Maharlika\Hashing\BcryptHasher setRounds(int $rounds) Set the default bcrypt work factor (cost)
 * 
 * @see \Maharlika\Hashing\HashManager
 * @see \Maharlika\Hashing\BcryptHasher
 * @see \Maharlika\Hashing\ArgonHasher
 */
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hash';
    }
}