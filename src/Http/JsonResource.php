<?php

namespace Maharlika\Http;

use Maharlika\Contracts\Http\ResponseInterface;
use Maharlika\Support\Paginator;

class JsonResource
{
    protected static bool $wrap = true;
    protected static string $wrapKey = 'data';
    protected mixed $resource;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Disable wrapping for all JSON resources
     */
    public static function withoutWrapping(): void
    {
        static::$wrap = false;
    }

    /**
     * Enable wrapping for all JSON resources
     */
    public static function withWrapping(string $key = 'data'): void
    {
        static::$wrap = true;
        static::$wrapKey = $key;
    }

    /**
     * Transform the resource into an array
     */
    public function toArray(): array
    {
        if ($this->resource instanceof Paginator) {
            return $this->resource->toArray();
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        return (array) $this->resource;
    }

    /**
     * Create an HTTP response
     */
    public function toResponse(): ResponseInterface
    {
        $data = $this->toArray();

        // If wrapping is disabled, return pure array (no wrapping at all)
        // If wrapping is enabled, wrap non-paginated data only
        if (static::$wrap && !$this->isPaginatedData($data)) {
            $data = [static::$wrapKey => $data];
        }

        return new Response(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Check if data is paginated
     */
    protected function isPaginatedData(array $data): bool
    {
        return isset($data['data']) 
            && isset($data['current_page']) 
            && isset($data['total']);
    }

    /**
     * Create a new resource collection
     */
    public static function collection(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * Convert resource to JSON string
     */
    public function toJson(): string
    {
        $data = $this->toArray();

        // Paginated data already has its own structure with 'data' key
        // Only wrap non-paginated data if wrapping is enabled
        if (!$this->isPaginatedData($data) && static::$wrap) {
            $data = [static::$wrapKey => $data];
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Magic method to convert to string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}