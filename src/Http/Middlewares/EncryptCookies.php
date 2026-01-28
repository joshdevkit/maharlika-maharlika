<?php

namespace Maharlika\Http\Middlewares;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Encryption\Encrypter;
use Closure;

/**
 * EncryptCookies Middleware
 * 
 * Automatically encrypts outgoing cookies and decrypts incoming cookies.
 * This provides security for sensitive data stored in cookies.
 * 
 * How it works:
 * 1. Incoming Request: Decrypts all cookies (except those in $except array)
 * 2. Process Request: Controller/app uses plain cookie values
 * 3. Outgoing Response: Encrypts all Set-Cookie headers
 */
class EncryptCookies
{
    /**
     * Cookies that should not be encrypted
     * 
     * @var array
     */
    protected array $except = [
        // Add cookie names that should remain unencrypted
        // Example: 'analytics_id', 'tracking_token'
    ];

    protected Encrypter $encrypter;

    public function __construct()
    {
        $this->encrypter = app(Encrypter::class);
    }

    /**
     * Handle an incoming request
     */
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        // Decrypt incoming cookies
        $this->decryptCookies($request);

        // Process the request
        $response = $next($request);

        // Encrypt outgoing cookies in the response
        $this->encryptCookies($response);

        return $response;
    }

    /**
     * Decrypt the cookies on the request
     */
    protected function decryptCookies(RequestInterface $request): void
    {
        $cookies = $request->cookies();

        foreach ($cookies as $name => $value) {
            if ($this->shouldEncrypt($name)) {
                try {
                    // Decrypt the cookie value
                    $decrypted = $this->encrypter->decrypt($value);
                    $_COOKIE[$name] = $decrypted;
                } catch (\Exception $e) {
                    // If decryption fails, remove the cookie
                    unset($_COOKIE[$name]);
                }
            }
        }
    }

    /**
     * Encrypt the cookies in the response
     */
    protected function encryptCookies(ResponseInterface $response): void
    {
        $headers = $response->getHeaders();

        foreach ($headers as $name => $value) {
            // Check if this is a Set-Cookie header
            if (strtolower($name) === 'set-cookie') {
                $cookies = is_array($value) ? $value : [$value];
                $encrypted = [];

                foreach ($cookies as $cookie) {
                    $encrypted[] = $this->encryptCookie($cookie);
                }

                // Update the header with encrypted cookies
                if (count($encrypted) === 1) {
                    $response->setHeader($name, $encrypted[0]);
                } else {
                    // Multiple Set-Cookie headers
                    foreach ($encrypted as $encryptedCookie) {
                        header("Set-Cookie: {$encryptedCookie}", false);
                    }
                }
            }
        }
    }

    /**
     * Encrypt a single cookie string
     */
    protected function encryptCookie(string $cookie): string
    {
        // Parse cookie: "name=value; Path=/; HttpOnly"
        $parts = explode(';', $cookie);
        $nameValue = array_shift($parts);
        
        if (strpos($nameValue, '=') === false) {
            return $cookie;
        }

        [$name, $value] = explode('=', $nameValue, 2);
        $name = trim($name);
        $value = trim($value);

        // Check if this cookie should be encrypted
        if (!$this->shouldEncrypt($name)) {
            return $cookie;
        }

        // Encrypt the value
        try {
            $encryptedValue = $this->encrypter->encrypt($value);
            $encryptedValue = urlencode($encryptedValue);
            
            // Rebuild cookie string
            return $name . '=' . $encryptedValue . (empty($parts) ? '' : '; ' . implode('; ', $parts));
        } catch (\Exception $e) {
            // If encryption fails, return original
            return $cookie;
        }
    }

    /**
     * Determine if a cookie should be encrypted
     */
    protected function shouldEncrypt(string $name): bool
    {
        return !in_array($name, $this->except, true);
    }

    /**
     * Add cookie names that should not be encrypted
     */
    public function except(array $cookies): self
    {
        $this->except = array_merge($this->except, $cookies);
        return $this;
    }

    /**
     * Get the list of cookies that should not be encrypted
     */
    public function getExcept(): array
    {
        return $this->except;
    }
}