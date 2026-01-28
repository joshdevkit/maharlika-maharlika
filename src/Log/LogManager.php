<?php

namespace Maharlika\Log;

use Maharlika\Contracts\Container\ContainerInterface;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

class LogManager implements LoggerInterface
{
    protected ContainerInterface $container;
    protected array $channels = [];
    protected string $defaultChannel = 'stack';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->defaultChannel = config('logging.default', 'stack');
    }

    /**
     * Get a log channel instance
     */
    public function channel(?string $channel = null): LoggerInterface
    {
        $channel = $channel ?? $this->defaultChannel;

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = $this->createChannel($channel);
        }

        return $this->channels[$channel];
    }

    /**
     * Create a new log channel
     */
    protected function createChannel(string $channel): MonologLogger
    {
        $config = config("logging.channels.{$channel}", []);
        
        if (empty($config)) {
            $config = $this->getDefaultConfig($channel);
        }

        $logger = new MonologLogger($channel);
        $handler = $this->createHandler($config);
        
        if ($handler) {
            $logger->pushHandler($handler);
        }

        return $logger;
    }

    /**
     * Create a log handler based on configuration
     */
    protected function createHandler(array $config)
    {
        $driver = $config['driver'] ?? 'single';
        $level = $this->parseLevel($config['level'] ?? 'debug');

        switch ($driver) {
            case 'single':
                return $this->createSingleHandler($config, $level);
            
            case 'daily':
                return $this->createDailyHandler($config, $level);
            
            case 'stack':
                return $this->createStackHandler($config);
            
            default:
                return $this->createSingleHandler($config, $level);
        }
    }

    /**
     * Create a single file handler
     */
    protected function createSingleHandler(array $config, int $level): StreamHandler
    {
        $path = $config['path'] ?? storage_path('logs/app.log');
        
        $handler = new StreamHandler($path, $level);
        $handler->setFormatter($this->getFormatter());
        
        return $handler;
    }

    /**
     * Create a daily rotating file handler
     */
    protected function createDailyHandler(array $config, int $level): RotatingFileHandler
    {
        $path = $config['path'] ?? storage_path('logs/app.log');
        $days = $config['days'] ?? 14;
        
        $handler = new RotatingFileHandler($path, $days, $level);
        $handler->setFormatter($this->getFormatter());
        
        return $handler;
    }

    /**
     * Create a stack handler (multiple channels)
     */
    protected function createStackHandler(array $config)
    {
        $channels = $config['channels'] ?? ['daily'];
        $mainChannel = $channels[0] ?? 'daily';
        
        return $this->createChannel($mainChannel)->getHandlers()[0] ?? null;
    }

    /**
     * Get the log formatter
     */
    protected function getFormatter(): LineFormatter
    {
        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $dateFormat = "Y-m-d H:i:s";
        
        $formatter = new LineFormatter($format, $dateFormat, true, true);
        $formatter->includeStacktraces();
        
        return $formatter;
    }

    /**
     * Parse log level string to Monolog constant
     */
    protected function parseLevel(string $level): int
    {
        return constant(MonologLogger::class . '::' . strtoupper($level));
    }

    /**
     * Get default configuration for a channel
     */
    protected function getDefaultConfig(string $channel): array
    {
        return [
            'driver' => 'daily',
            'path' => storage_path("logs/{$channel}.log"),
            'level' => 'debug',
            'days' => 14,
        ];
    }

    /**
     * System is unusable
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }

    /**
     * Interesting events
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    /**
     * Magic method to forward calls to the default channel
     */
    public function __call(string $method, array $parameters)
    {
        return $this->channel()->$method(...$parameters);
    }
}