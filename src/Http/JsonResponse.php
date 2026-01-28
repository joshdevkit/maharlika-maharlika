<?php

namespace Maharlika\Http;

use Maharlika\Contracts\Http\ResponseInterface;

/**
 * JsonResponse
 * 
 * A specialized HTTP response for JSON data. This class handles
 * JSON encoding, appropriate headers, and status codes while
 * implementing the ResponseInterface contract.
 * 
 * Features:
 * - Automatic JSON encoding with error handling
 * - Fluent interface for building responses
 * - Proper Content-Type headers
 * - Support for custom JSON encoding options
 * - UTF-8 encoding by default
 * 
 * @package Maharlika\Http
 */
class JsonResponse implements ResponseInterface
{
    /**
     * HTTP status code
     */
    protected int $statusCode = 200;

    /**
     * Response headers
     */
    protected array $headers = [];

    /**
     * Data to be JSON encoded
     */
    protected mixed $data;

    /**
     * JSON encoding options
     */
    protected int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Create a new JSON response
     * 
     * @param mixed $data Data to be JSON encoded
     * @param int $status HTTP status code
     * @param array $headers Additional headers
     */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $status;
        
        // Set default JSON content type
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $headers);
    }

    /**
     * Set the HTTP status code for the response.
     * 
     * @param int $code HTTP status code (e.g., 200, 404, 500)
     * @return $this
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get the currently assigned HTTP status code.
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set or replace a header on the response.
     * 
     * @param string $name Header name (e.g., "Content-Type")
     * @param string $value Header value
     * @return $this
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get all headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if a specific header exists
     * 
     * @param string $name Header name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Get a specific header value
     * 
     * @param string $name Header name
     * @param mixed $default Default value if header doesn't exist
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Alias for setHeader — adds or replaces a single header.
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return $this
     */
    public function header(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Fluent method to add a header (alias for withHeader)
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return $this
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->setHeader($name, $value);
    }

    /**
     * Set the response body content (data to be JSON encoded).
     * 
     * @param mixed $content Data to encode as JSON
     * @return $this
     */
    public function setContent(mixed $content): self
    {
        $this->data = $content;
        return $this;
    }

    /**
     * Set the data to be JSON encoded (alias for setContent)
     * 
     * @param mixed $data
     * @return $this
     */
    public function setData(mixed $data): self
    {
        return $this->setContent($data);
    }

    /**
     * Get the raw data before JSON encoding
     * 
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the rendered response content as a JSON string.
     * 
     * @return string
     * @throws \JsonException If JSON encoding fails
     */
    public function getContent(): string
    {
        try {
            $json = json_encode(
                $this->data, 
                $this->encodingOptions | JSON_THROW_ON_ERROR
            );
            
            return $json;
        } catch (\JsonException $e) {
            // Log the error if logger is available
            if (function_exists('logger')) {
                logger()->error('JSON encoding failed', [
                    'error' => $e->getMessage(),
                    'data' => $this->data,
                ]);
            }
            
            // Return error response
            return json_encode([
                'error' => 'Failed to encode response data',
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Internal server error',
            ], JSON_THROW_ON_ERROR);
        }
    }

    /**
     * Set JSON encoding options
     * 
     * @param int $options JSON encoding options (e.g., JSON_PRETTY_PRINT)
     * @return $this
     */
    public function setEncodingOptions(int $options): self
    {
        $this->encodingOptions = $options;
        return $this;
    }

    /**
     * Add JSON encoding option(s)
     * 
     * @param int $option JSON encoding option to add
     * @return $this
     */
    public function addEncodingOption(int $option): self
    {
        $this->encodingOptions |= $option;
        return $this;
    }

    /**
     * Enable pretty printing for the JSON output
     * 
     * @return $this
     */
    public function prettyPrint(): self
    {
        return $this->addEncodingOption(JSON_PRETTY_PRINT);
    }

    /**
     * Send the HTTP headers and response content to the client.
     * 
     * @return void
     */
    public function send(): void
    {
        // Don't send if headers already sent (prevents errors)
        if (headers_sent()) {
            echo $this->getContent();
            return;
        }

        // Send status code
        http_response_code($this->statusCode);

        // Send all headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send content
        echo $this->getContent();
    }

    /**
     * Convert the response to a string (returns JSON)
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->getContent();
    }

    /**
     * Static factory method for quick JSON responses
     * 
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return static
     */
    public static function create(mixed $data = null, int $status = 200, array $headers = []): static
    {
        return new static($data, $status, $headers);
    }

    /**
     * Create a success response (200 OK)
     * 
     * @param mixed $data
     * @param string $message
     * @return static
     */
    public static function success(mixed $data = null, string $message = 'Success'): static
    {
        return new static([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    /**
     * Create an error response
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param mixed $errors Additional error details
     * @return static
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): static
    {
        $data = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $data['errors'] = $errors;
        }

        return new static($data, $status);
    }

    /**
     * Create a validation error response (422)
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return static
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Create a not found response (404)
     * 
     * @param string $message
     * @return static
     */
    public static function notFound(string $message = 'Resource not found'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    /**
     * Create an unauthorized response (401)
     * 
     * @param string $message
     * @return static
     */
    public static function unauthorized(string $message = 'Unauthorized'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
        ], 401);
    }

    /**
     * Create a forbidden response (403)
     * 
     * @param string $message
     * @return static
     */
    public static function forbidden(string $message = 'Forbidden'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Create a server error response (500)
     * 
     * @param string $message
     * @return static
     */
    public static function serverError(string $message = 'Internal server error'): static
    {
        return new static([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}