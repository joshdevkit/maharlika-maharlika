<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Http\RequestInterface;
use Maharlika\Contracts\Session\SessionInterface;

class UrlGenerator
{
    protected Router $router;
    protected RequestInterface $request;
    protected ?SessionInterface $session;
    protected ?UrlSigner $signer = null;

    public function __construct(Router $router, RequestInterface $request, ?SessionInterface $session = null)
    {
        $this->router = $router;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * Set the URL signer instance.
     *
     * @param UrlSigner $signer
     * @return void
     */
    public function setSigner(UrlSigner $signer): void
    {
        $this->signer = $signer;
    }

    /**
     * Get or create the URL signer.
     *
     * @return UrlSigner
     */
    protected function getSigner(): UrlSigner
    {
        if (!$this->signer) {
            $key = $_ENV['APP_KEY'] ?? 'default-key';
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }
            
            $this->signer = new UrlSigner($this, $key);
        }

        return $this->signer;
    }

    /**
     * Create a signed URL.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public function signedRoute(string $path, array $parameters = []): string
    {
        return $this->getSigner()->signedUrl($path, $parameters);
    }

    /**
     * Create a temporary signed URL.
     *
     * @param string $path
     * @param int|\DateTimeInterface|\DateInterval $expiration
     * @param array $parameters
     * @return string
     */
    public function temporarySignedRoute(string $path, $expiration, array $parameters = []): string
    {
        return $this->getSigner()->temporarySignedRoute($path, $expiration, $parameters);
    }

    /**
     * Verify if the request has a valid signature.
     *
     * @param RequestInterface $request
     * @param bool $absolute
     * @param array $ignoreQuery
     * @return bool
     */
    public function hasValidSignature(RequestInterface $request, bool $absolute = true, array $ignoreQuery = []): bool
    {
        return $this->getSigner()->hasValidSignature($request, $absolute, $ignoreQuery);
    }

    /**
     * Verify if the request has a valid relative signature.
     *
     * @param RequestInterface $request
     * @param array $ignoreQuery
     * @return bool
     */
    public function hasValidRelativeSignature(RequestInterface $request, array $ignoreQuery = []): bool
    {
        return $this->getSigner()->hasValidRelativeSignature($request, $ignoreQuery);
    }

    /**
     * Generate a URL to a given path.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public function to(string $path, array $parameters = []): string
    {
        $baseUrl = $this->request->getSchemeAndHttpHost();
        $url = $baseUrl . '/' . ltrim($path, '/');

        // Append query parameters if provided
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Get the current URL.
     *
     * @return string
     */
    public function current(): string
    {
        return $this->request->getSchemeAndHttpHost() . $this->request->getUri();
    }

    /**
     * Get the previous URL (from session or referer header).
     *
     * @return string|null
     */
    public function previous(): ?string
    {
        // Check session first (if available)
        if ($this->session && $this->session->has('previous_url')) {
            return $this->session->get('previous_url');
        }

        // Fallback to HTTP_REFERER header
        return $this->request->getHeader('referer');
    }

    /**
     * Generate a secure (HTTPS) URL to a given path.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public function secure(string $path, array $parameters = []): string
    {
        $secureUrl = 'https://' . $this->request->getServer('HTTP_HOST', 'localhost');
        $url = $secureUrl . '/' . ltrim($path, '/');

        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }
}