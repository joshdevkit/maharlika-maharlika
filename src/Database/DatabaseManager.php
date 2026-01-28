<?php

namespace Maharlika\Database;

use Maharlika\Database\Query\Builder;
use Maharlika\Contracts\Database\ConnectionInterface;
use Maharlika\Contracts\Database\ConnectionResolverInterface;
use Maharlika\Database\FluentORM\Model;
use Maharlika\Exceptions\DatabaseException;

class DatabaseManager implements ConnectionResolverInterface
{
    protected array $connections = [];
    protected array $runtimeConfigs = [];
    protected string $defaultConnection;

    public function __construct(protected array $config = [])
    {
        $this->config = $config;
        $this->defaultConnection = $config['default'] ?? 'mysql';
    }

    /**
     * Add a connection at runtime
     *
     * @param array $config Connection configuration
     * @param string $name Connection name
     * @return void
     */
    public function addConnection(array $config, string $name): void
    {
        // Store the config for lazy loading
        $this->runtimeConfigs[$name] = $config;

        // Remove existing connection if any (will be recreated on next use)
        if (isset($this->connections[$name])) {
            unset($this->connections[$name]);
        }
    }


    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    protected function makeConnection(string $name): ConnectionInterface
    {
        // First check runtime configs (takes priority)
        if (isset($this->runtimeConfigs[$name])) {
            $config = $this->runtimeConfigs[$name];
            return new Connection($config, $name);
        }

        // Then check config file
        if (isset($this->config['connections'][$name])) {
            $config = $this->config['connections'][$name];
            return new Connection($config, $name);
        }

        throw new DatabaseException("Database connection [{$name}] not configured.");
    }

    /**
     * Check if a connection configuration exists
     *
     * @param string $name
     * @return bool
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->runtimeConfigs[$name])
            || isset($this->config['connections'][$name]);
    }

    /**
     * Remove a connection configuration
     *
     * @param string $name
     * @return void
     */
    public function removeConnection(string $name): void
    {
        unset($this->runtimeConfigs[$name]);
        unset($this->connections[$name]);
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    public function purge(?string $name = null): void
    {
        $name = $name ?? $this->defaultConnection;
        unset($this->connections[$name]);
    }

    public function disconnect(?string $name = null): void
    {
        $this->purge($name);
    }

    public function reconnect(?string $name = null): ConnectionInterface
    {
        $this->disconnect($name);
        return $this->connection($name);
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get all available connection names (both config and runtime)
     *
     * @return array
     */
    public function getAvailableConnections(): array
    {
        $configConnections = array_keys($this->config['connections'] ?? []);
        $runtimeConnections = array_keys($this->runtimeConfigs);

        return array_unique(array_merge($configConnections, $runtimeConnections));
    }


    public function table(string $table, ?Model $model = null): Builder
    {
        return (new Builder(
            $this->connection($model ? $model->getConnectionName() : null),
            $model
        ))->table($table);
    }

    /**
     * Enable query logging on a connection
     */
    public function enableQueryLog(?string $name = null): void
    {
        $this->connection($name)->enableQueryLog();
    }

    /**
     * Disable query logging on a connection
     */
    public function disableQueryLog(?string $name = null): void
    {
        $this->connection($name)->disableQueryLog();
    }

    /**
     * Get query log from a connection
     */
    public function getQueryLog(?string $name = null): array
    {
        return $this->connection($name)->getQueryLog();
    }

    /**
     * Flush query log from a connection
     */
    public function flushQueryLog(?string $name = null): void
    {
        $this->connection($name)->flushQueryLog();
    }

    /**
     * Enable query logging on all active connections
     */
    public function enableQueryLogOnAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->enableQueryLog();
        }
    }

    /**
     * Disable query logging on all active connections
     */
    public function disableQueryLogOnAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disableQueryLog();
        }
    }

    /**
     * Get query logs from all active connections
     */
    public function getAllQueryLogs(): array
    {
        $logs = [];
        
        foreach ($this->connections as $name => $connection) {
            $logs[$name] = $connection->getQueryLog();
        }
        
        return $logs;
    }

    /**
     * Flush query logs from all active connections
     */
    public function flushAllQueryLogs(): void
    {
        foreach ($this->connections as $connection) {
            $connection->flushQueryLog();
        }
    }

    public function __call(string $method, array $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}