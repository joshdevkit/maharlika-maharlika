<?php

namespace Maharlika\Routing;

use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Http\Response;

class Redirector
{
    protected UrlGenerator $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }


    /**
     * Create a redirect response to the previous URL (back).
     *
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    public function back(int $status = 302, array $headers = []): ResponseInterface
    {
        $url = $this->url->previous() ?? $this->url->to('/'); // Fallback to home if no previous
        return $this->createRedirect($url, $status, $headers);
    }

    /**
     * Create a redirect response to the current URL (refresh).
     *
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    public function refresh(int $status = 302, array $headers = []): ResponseInterface
    {
        $url = $this->url->current();
        return $this->createRedirect($url, $status, $headers);
    }

    /**
     * Create a redirect response with data to flash to the session.
     *
     * @param string $path
     * @param array $with
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    public function to(string $path, array $with = [], int $status = 302, array $headers = []): ResponseInterface
    {
        if (!empty($with) && app()->has('session')) {
            $session = app('session');
            foreach ($with as $key => $value) {
                $session->flash($key, $value);
            }
        }

        return $this->createRedirect($path, $status, $headers);
    }



    /**
     * Helper to create the redirect response.
     *
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return ResponseInterface
     */
    protected function createRedirect(string $url, int $status = 302, array $headers = []): ResponseInterface
    {
        $headers['Location'] = $url;
        return new Response('', $status, $headers);
    }
}
