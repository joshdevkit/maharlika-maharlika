<?php

namespace Maharlika\Contracts\Http;

/**
 * Interface ResponseInterface
 *
 * Defines the contract for building and sending HTTP responses.
 * Implementations should allow fluent modification of status codes,
 * headers, and response content, and must be capable of outputting
 * the final response to the client.
 */
interface ResponseInterface
{
    /**
     * Set the HTTP status code for the response.
     *
     * @param int $code HTTP status code (e.g., 200, 404, 500)
     * @return $this Fluent response instance
     */
    public function setStatusCode(int $code): self;

    /**
     * Get the currently assigned HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int;

    /**
     * Set or replace a header on the response.
     *
     * @param string $name  Header name (e.g., "Content-Type")
     * @param string $value Header value
     * @return $this Fluent response instance
     */
    public function setHeader(string $name, string $value): self;

    /**
     * Get all the headers array
     */
    public function getHeaders(): array;

    /**
     * Alias for setHeader — adds or replaces a single header.
     *
     * @param string $name  Header name
     * @param string $value Header value
     * @return $this Fluent response instance
     */
    public function header(string $name, string $value): self;


    /**
     * Check if a specific header exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader(string $name): bool;

    /**
     * Set the response body content.
     *
     * Implementations may serialize arrays/objects
     * (e.g., to JSON) or accept view objects.
     *
     * @param mixed $content String, array, object, or view instance
     * @return $this Fluent response instance
     */
    public function setContent(mixed $content): self;

    /**
     * Get the rendered response content as a string.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * Send the HTTP headers and response content to the client.
     *
     * Implementations must handle:
     *   - HTTP status line
     *   - all headers
     *   - echoing the content body
     *   - graceful handling if headers are already sent
     *
     * @return void
     */
    public function send(): void;
}
