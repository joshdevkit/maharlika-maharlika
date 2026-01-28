<?php 

namespace Maharlika\Http\Concerns;

trait InteractsWithContentTypes
{
    /**
     * Determine if the request expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isXmlHttpRequest() || $this->headers->get('Accept') === 'application/json';
    }

    /**
     * Determine if the request is sending JSON data.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return str_contains($this->headers->get('Content-Type', ''), 'application/json');
    }

    /**
     * Determine if the current request is asking for JSON.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        if ($this->expectsJson()) {
            return true;
        }

        if ($this->isJson()) {
            return true;
        }

        if (str_starts_with($this->getPath(), '/api')) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether a request accepts JSON.
     *
     * @return bool
     */
    public function acceptsJson(): bool
    {
        return $this->accepts('application/json');
    }

    /**
     * Determines whether a request accepts HTML.
     *
     * @return bool
     */
    public function acceptsHtml(): bool
    {
        return $this->accepts('text/html');
    }

    /**
     * Determine if the given content type is accepted.
     *
     * @param  string  $contentType
     * @return bool
     */
    public function accepts(string $contentType): bool
    {
        $acceptable = $this->getAcceptableContentTypes();

        if (count($acceptable) === 0) {
            return true;
        }

        foreach ($acceptable as $type) {
            if ($type === $contentType || str_ends_with($type, '/*') && str_starts_with($contentType, rtrim($type, '/*'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the request is sending form data.
     *
     * @return bool
     */
    public function isForm(): bool
    {
        return in_array(
            $this->headers->get('Content-Type'),
            ['application/x-www-form-urlencoded', 'multipart/form-data']
        );
    }
}
