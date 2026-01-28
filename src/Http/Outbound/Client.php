<?php

namespace Maharlika\Http\Outbound;


class Client
{
    protected array $options = [];
    protected array $headers = [];
    protected array $queryParameters = [];
    protected int $timeout = 30;
    protected ?string $baseUrl = null;
    protected bool $verifySSL = true;
    protected ?string $proxy = null;

    /**
     * Create a new HTTP client instance.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        if (isset($options['base_url'])) {
            $this->baseUrl = rtrim($options['base_url'], '/');
        }

        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        }

        if (isset($options['verify'])) {
            $this->verifySSL = $options['verify'];
        }

        if (isset($options['proxy'])) {
            $this->proxy = $options['proxy'];
        }

        if (isset($options['headers'])) {
            $this->headers = $options['headers'];
        }
    }

    /**
     * Create a new HTTP client instance.
     */
    public static function make(array $options = []): static
    {
        return new static($options);
    }

    /**
     * Set base URL for requests.
     */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Set request timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set headers for the request.
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add a single header.
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add query parameters to the request.
     */
    public function withQueryParameters(array $parameters): static
    {
        $this->queryParameters = array_merge($this->queryParameters, $parameters);
        return $this;
    }

    /**
     * Add a single query parameter.
     */
    public function withQueryParameter(string $key, mixed $value): static
    {
        $this->queryParameters[$key] = $value;
        return $this;
    }

    /**
     * Set bearer token authorization.
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Set basic authentication.
     */
    public function withBasicAuth(string $username, string $password): static
    {
        return $this->withHeader('Authorization', 'Basic ' . base64_encode("{$username}:{$password}"));
    }

    /**
     * Accept JSON responses.
     */
    public function acceptJson(): static
    {
        return $this->withHeader('Accept', 'application/json');
    }

    /**
     * Set Content-Type to JSON.
     */
    public function asJson(): static
    {
        return $this->withHeader('Content-Type', 'application/json');
    }

    /**
     * Set Content-Type to form data.
     */
    public function asForm(): static
    {
        return $this->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    /**
     * Set Content-Type to multipart form data.
     */
    public function asMultipart(): static
    {
        return $this->withHeader('Content-Type', 'multipart/form-data');
        return $this;
    }

    /**
     * Disable SSL verification.
     */
    public function withoutVerifying(): static
    {
        $this->verifySSL = false;
        return $this;
    }

    /**
     * Set proxy.
     */
    public function withProxy(string $proxy): static
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * Send a GET request.
     */
    public function get(string $url, array $query = []): Response
    {
        // Merge stored query parameters with provided ones
        $query = array_merge($this->queryParameters, $query);

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->send('GET', $url);
    }

    /**
     * Send a POST request.
     */
    public function post(string $url, array $data = []): Response
    {
        $url = $this->appendQueryParameters($url);

        return $this->send('POST', $url, [
            'body' => $data
        ]);
    }

    /**
     * Send a PUT request.
     */
    public function put(string $url, array $data = []): Response
    {
        $url = $this->appendQueryParameters($url);

        return $this->send('PUT', $url, [
            'body' => $data
        ]);
    }

    /**
     * Send a PATCH request.
     */
    public function patch(string $url, array $data = []): Response
    {
        $url = $this->appendQueryParameters($url);

        return $this->send('PATCH', $url, [
            'body' => $data
        ]);
    }

    /**
     * Send a DELETE request.
     */
    public function delete(string $url, array $data = []): Response
    {
        $url = $this->appendQueryParameters($url);

        return $this->send('DELETE', $url, [
            'body' => $data
        ]);
    }

    /**
     * Send a HEAD request.
     */
    public function head(string $url): Response
    {
        $url = $this->appendQueryParameters($url);

        return $this->send('HEAD', $url);
    }

    /**
     * Append query parameters to URL.
     */
    protected function appendQueryParameters(string $url): string
    {
        if (!empty($this->queryParameters)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($this->queryParameters);
        }

        return $url;
    }

    /**
     * Send an HTTP request.
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        $url = $this->buildUrl($url);

        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        // Return response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Include headers in output
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);

        // SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);

        // Proxy
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        // Headers
        $headers = $this->buildHeaders();
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Body
        if (isset($options['body']) && !empty($options['body'])) {
            $body = $this->prepareBody($options['body']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Execute request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            throw new \RuntimeException("cURL error [{$errno}]: {$error}");
        }

        // Get info
        $info = curl_getinfo($ch);

        // Parse response
        return $this->parseResponse($response, $info);
    }

    /**
     * Build full URL.
     */
    protected function buildUrl(string $url): string
    {
        if ($this->baseUrl && !$this->isAbsoluteUrl($url)) {
            return $this->baseUrl . '/' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Check if URL is absolute.
     */
    protected function isAbsoluteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//', $url) === 1;
    }

    /**
     * Build headers array.
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return $headers;
    }

    /**
     * Prepare request body.
     */
    protected function prepareBody(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }

        $contentType = $this->headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            return json_encode($body);
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return http_build_query($body);
        }

        // Default to JSON for arrays
        if (is_array($body)) {
            return json_encode($body);
        }

        return (string) $body;
    }

    /**
     * Parse cURL response.
     */
    protected function parseResponse(string $response, array $info): Response
    {
        $headerSize = $info['header_size'];
        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Parse headers
        $headers = [];
        $headerLines = explode("\r\n", trim($headerString));

        foreach ($headerLines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return new Response(
            $body,
            $info['http_code'],
            $headers,
            $info
        );
    }

    /**
     * Create a new pool of requests.
     */
    public function pool(callable $callback): array
    {
        $pool = new Pool($this);
        $callback($pool);
        return $pool->send();
    }
}
