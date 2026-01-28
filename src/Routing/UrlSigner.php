<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Http\RequestInterface;

class UrlSigner
{
    protected string $key;
    protected UrlGenerator $urlGenerator;

    public function __construct(UrlGenerator $urlGenerator, string $key)
    {
        $this->urlGenerator = $urlGenerator;
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        $this->key = $key;
    }

    /**
     * Create a temporary signed URL that will expire.
     *
     * @param string $path
     * @param int|\DateTimeInterface|\DateInterval $expiration
     * @param array $parameters
     * @return string
     */
    public function temporarySignedRoute(string $path, $expiration, array $parameters = []): string
    {
        $expires = $this->availableAt($expiration);
        $parameters['expires'] = $expires;

        return $this->signedUrl($path, $parameters);
    }

    /**
     * Create a signed URL.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public function signedUrl(string $path, array $parameters = []): string
    {
        // Sort parameters for consistent signature
        ksort($parameters);
        
        // Build URL with parameters (without signature)
        $url = $this->urlGenerator->to($path);
        
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        
        // Generate signature from the complete URL
        $signature = $this->generateSignature($url);
        
        // Append signature to URL
        return $url . '&signature=' . $signature;
    }

    /**
     * Verify if a request has a valid signature.
     *
     * @param RequestInterface $request
     * @param bool $absolute
     * @param array $ignoreQuery
     * @return bool
     */
    public function hasValidSignature(RequestInterface $request, bool $absolute = true, array $ignoreQuery = []): bool
    {
        return $this->hasCorrectSignature($request, $absolute, $ignoreQuery);
    }

    /**
     * Verify if a request has a valid relative signature.
     *
     * @param RequestInterface $request
     * @param array $ignoreQuery
     * @return bool
     */
    public function hasValidRelativeSignature(RequestInterface $request, array $ignoreQuery = []): bool
    {
        return $this->hasCorrectSignature($request, false, $ignoreQuery);
    }

    /**
     * Determine if the signature from the given request matches the URL.
     *
     * @param RequestInterface $request
     * @param bool $absolute
     * @param array $ignoreQuery
     * @return bool
     */
    public function hasCorrectSignature(RequestInterface $request, bool $absolute = true, array $ignoreQuery = []): bool
    {
        // Get signature from query parameters
        $signature = $request->query->get('signature');
        
        if (!$signature) {
            return false;
        }

        // Check if URL has expired
        $expires = $request->query->get('expires');
        if ($expires && time() > $expires) {
            return false;
        }

        // Build URL without signature for validation
        $url = $this->buildUrlWithoutSignature($request, $absolute, $ignoreQuery);
        
        // Generate expected signature
        $expectedSignature = $this->generateSignature($url);
        
        // Compare signatures
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate a signature for a URL.
     *
     * @param string $url
     * @return string
     */
    protected function generateSignature(string $url): string
    {
        return hash_hmac('sha256', $url, $this->key);
    }

    /**
     * Build the URL without the signature parameter.
     *
     * @param RequestInterface $request
     * @param bool $absolute
     * @param array $ignoreQuery
     * @return string
     */
    protected function buildUrlWithoutSignature(RequestInterface $request, bool $absolute = true, array $ignoreQuery = []): string
    {
        // Get all query parameters
        $params = $request->query->all();
        
        // Remove signature parameter
        unset($params['signature']);
        
        // Remove ignored query parameters
        foreach ($ignoreQuery as $key) {
            unset($params[$key]);
        }

        // Build base URL
        if ($absolute) {
            $url = $request->getSchemeAndHttpHost() . $request->getPathInfo();
        } else {
            $url = $request->getPathInfo();
        }
        
        // Append sorted query parameters
        if (!empty($params)) {
            ksort($params);
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @return int
     */
    protected function availableAt($delay = 0): int
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof \DateTimeInterface
            ? $delay->getTimestamp()
            : time() + $delay;
    }

    /**
     * Parse the given date interval.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @return int|\DateTimeInterface
     */
    protected function parseDateInterval($delay)
    {
        if ($delay instanceof \DateInterval) {
            $delay = (new \DateTime())->add($delay);
        }

        return $delay;
    }
}