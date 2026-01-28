<?php

namespace Maharlika\Database\Query;

class QueryLog
{
    public function __construct(
        public readonly string $query,
        public readonly array $bindings,
        public readonly float $time,
        public readonly ?string $connection = null
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'bindings' => $this->bindings,
            'time' => $this->time,
            'connection' => $this->connection,
        ];
    }
}