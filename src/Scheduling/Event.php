<?php

namespace Maharlika\Scheduling;

use Maharlika\Contracts\ApplicationInterface;
use Closure;
use DateTimeZone;
use RuntimeException;

class Event
{
    protected ApplicationInterface $app;
    protected string $command;
    protected ?string $expression = '* * * * *';
    protected ?DateTimeZone $timezone = null;
    protected ?string $description = null;
    protected bool $runInBackground = false;
    protected bool $withoutOverlapping = false;
    protected int $expiresAt = 1440; // 24 hours in minutes
    protected ?string $mutexName = null;
    protected array $filters = [];
    protected array $rejects = [];
    protected ?Closure $beforeCallbacks = null;
    protected ?Closure $afterCallbacks = null;
    protected ?string $output = null;
    protected bool $appendOutput = false;
    protected bool $shouldMailOutput = false;
    protected array $emailTo = [];
    protected bool $onOneServer = false;
    protected bool $runAsUser = false;
    protected ?string $user = null;

    public function __construct(ApplicationInterface $app, string $command)
    {
        $this->app = $app;
        $this->command = $command;
        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get default output file path.
     */
    protected function getDefaultOutput(): string
    {
        return $this->app->basePath('storage/logs/schedule-' . sha1($this->command) . '.log');
    }

    /**
     * Run the scheduled task.
     */
    public function run(): void
    {
        if ($this->withoutOverlapping && !$this->createMutex()) {
            return;
        }

        try {
            $this->callBeforeCallbacks();

            $this->runInBackground
                ? $this->runCommandInBackground()
                : $this->runCommandInForeground();

            $this->callAfterCallbacks();
        } finally {
            if ($this->withoutOverlapping) {
                $this->removeMutex();
            }
        }
    }

    /**
     * Run command in foreground.
     */
    protected function runCommandInForeground(): int
    {
        return $this->execute($this->buildCommand());
    }

    /**
     * Run command in background.
     */
    protected function runCommandInBackground(): void
    {
        $this->execute($this->buildCommand() . ' > /dev/null 2>&1 &');
    }

    /**
     * Build the command string.
     */
    public function buildCommand(): string
    {
        $redirect = $this->appendOutput ? ' >> ' : ' > ';

        return $this->command . $redirect . $this->output . ' 2>&1';
    }

    /**
     * Execute the command.
     */
    protected function execute(string $command): int
    {
        if ($this->user && $this->runAsUser && !windows_os()) {
            $command = "sudo -u {$this->user} {$command}";
        }

        exec($command, $output, $exitCode);

        return $exitCode;
    }

    /**
     * Check if event is due to run.
     */
    public function isDue(): bool
    {
        if (!$this->runsInEnvironment()) {
            return false;
        }

        return $this->expressionPasses() &&
               $this->filtersPass() &&
               !$this->rejectsPass();
    }

    /**
     * Check if cron expression passes.
     */
    protected function expressionPasses(): bool
    {
        $date = new \DateTime('now', $this->timezone ?: new DateTimeZone(date_default_timezone_get()));

        return CronExpression::parse($this->expression)->isDue($date);
    }

    /**
     * Check if all filters pass.
     */
    protected function filtersPass(): bool
    {
        foreach ($this->filters as $filter) {
            if (!call_user_func($filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any reject condition passes.
     */
    protected function rejectsPass(): bool
    {
        foreach ($this->rejects as $reject) {
            if (call_user_func($reject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if event runs in current environment.
     */
    protected function runsInEnvironment(): bool
    {
        return true; // Can be extended to check environments
    }

    /**
     * Create mutex to prevent overlapping.
     */
    protected function createMutex(): bool
    {
        $mutex = $this->getMutex();
        
        if ($mutex->exists()) {
            if ($mutex->isExpired($this->expiresAt)) {
                $mutex->forget();
            } else {
                return false;
            }
        }

        return $mutex->create();
    }

    /**
     * Remove mutex.
     */
    protected function removeMutex(): void
    {
        $this->getMutex()->forget();
    }

    /**
     * Get mutex instance.
     */
    protected function getMutex(): Mutex
    {
        return new Mutex($this->mutexName ?? $this->mutexName());
    }

    /**
     * Get mutex name.
     */
    protected function mutexName(): string
    {
        return 'framework-schedule-' . sha1($this->expression . $this->command);
    }

    /**
     * Call before callbacks.
     */
    protected function callBeforeCallbacks(): void
    {
        if ($this->beforeCallbacks) {
            call_user_func($this->beforeCallbacks);
        }
    }

    /**
     * Call after callbacks.
     */
    protected function callAfterCallbacks(): void
    {
        if ($this->afterCallbacks) {
            call_user_func($this->afterCallbacks);
        }
    }

    // ===== SCHEDULING METHODS =====

    /**
     * Set cron expression.
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Schedule to run every minute.
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Schedule to run every X minutes.
     */
    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyThreeMinutes(): self
    {
        return $this->cron('*/3 * * * *');
    }

    public function everyFourMinutes(): self
    {
        return $this->cron('*/4 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('0,30 * * * *');
    }

    /**
     * Schedule to run hourly.
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $offset): self
    {
        return $this->cron("{$offset} * * * *");
    }

    public function everyTwoHours(): self
    {
        return $this->cron('0 */2 * * *');
    }

    public function everyThreeHours(): self
    {
        return $this->cron('0 */3 * * *');
    }

    public function everyFourHours(): self
    {
        return $this->cron('0 */4 * * *');
    }

    public function everySixHours(): self
    {
        return $this->cron('0 */6 * * *');
    }

    /**
     * Schedule to run daily.
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} * * *");
    }

    /**
     * Schedule to run at a specific time.
     * Alias for dailyAt() for better readability.
     */
    public function at(string $time): self
    {
        return $this->dailyAt($time);
    }

    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    /**
     * Schedule to run weekly.
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $day, string $time = '0:00'): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} * * {$day}");
    }

    /**
     * Schedule to run monthly.
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $day = 1, string $time = '0:00'): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} {$day} * *");
    }

    public function twiceMonthly(int $first = 1, int $second = 16, string $time = '0:00'): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} {$first},{$second} * *");
    }

    public function lastDayOfMonth(string $time = '0:00'): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} * * *")->when(function () {
            return date('d') === date('t');
        });
    }

    /**
     * Schedule to run quarterly.
     */
    public function quarterly(): self
    {
        return $this->cron('0 0 1 */3 *');
    }

    /**
     * Schedule to run yearly.
     */
    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function yearlyOn(int $month = 1, int $day = 1, string $time = '0:00'): self
    {
        $segments = explode(':', $time);
        return $this->cron("{$segments[1]} {$segments[0]} {$day} {$month} *");
    }

    // ===== DAY OF WEEK METHODS =====

    public function weekdays(): self
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    public function weekends(): self
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    public function mondays(): self
    {
        return $this->days(1);
    }

    public function tuesdays(): self
    {
        return $this->days(2);
    }

    public function wednesdays(): self
    {
        return $this->days(3);
    }

    public function thursdays(): self
    {
        return $this->days(4);
    }

    public function fridays(): self
    {
        return $this->days(5);
    }

    public function saturdays(): self
    {
        return $this->days(6);
    }

    public function sundays(): self
    {
        return $this->days(0);
    }

    public function days($days): self
    {
        $days = is_array($days) ? $days : func_get_args();
        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Splice value into position of cron expression.
     */
    protected function spliceIntoPosition(int $position, string $value): self
    {
        $segments = explode(' ', $this->expression);
        $segments[$position - 1] = $value;
        $this->expression = implode(' ', $segments);
        return $this;
    }

    // ===== CONSTRAINT METHODS =====

    /**
     * Set timezone.
     */
    public function timezone(string|DateTimeZone $timezone): self
    {
        $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        return $this;
    }

    /**
     * Add filter constraint.
     */
    public function when(callable $callback): self
    {
        $this->filters[] = $callback;
        return $this;
    }

    /**
     * Add reject constraint.
     */
    public function skip(callable $callback): self
    {
        $this->rejects[] = $callback;
        return $this;
    }

    /**
     * Prevent overlapping executions.
     */
    public function withoutOverlapping(int $expiresAt = 1440): self
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Run on one server only.
     */
    public function onOneServer(): self
    {
        $this->onOneServer = true;
        return $this;
    }

    /**
     * Run task in background.
     */
    public function runInBackground(): self
    {
        $this->runInBackground = true;
        return $this;
    }

    // ===== OUTPUT METHODS =====

    /**
     * Send output to file.
     */
    public function sendOutputTo(string $location, bool $append = false): self
    {
        $this->output = $location;
        $this->appendOutput = $append;
        return $this;
    }

    public function appendOutputTo(string $location): self
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * Email output.
     */
    public function emailOutputTo(string|array $addresses): self
    {
        $this->shouldMailOutput = true;
        $this->emailTo = is_array($addresses) ? $addresses : [$addresses];
        return $this;
    }

    // ===== CALLBACK METHODS =====

    /**
     * Register before callback.
     */
    public function before(Closure $callback): self
    {
        $this->beforeCallbacks = $callback;
        return $this;
    }

    /**
     * Register after callback.
     */
    public function after(Closure $callback): self
    {
        $this->afterCallbacks = $callback;
        return $this;
    }

    /**
     * Register then callback (alias for after).
     */
    public function then(Closure $callback): self
    {
        return $this->after($callback);
    }

    /**
     * Ping URL before task runs.
     */
    public function pingBefore(string $url): self
    {
        return $this->before(function () use ($url) {
            (new \GuzzleHttp\Client())->get($url);
        });
    }

    /**
     * Ping URL after task runs.
     */
    public function thenPing(string $url): self
    {
        return $this->after(function () use ($url) {
            (new \GuzzleHttp\Client())->get($url);
        });
    }

    // ===== USER METHODS =====

    /**
     * Run as specific user.
     */
    public function user(string $user): self
    {
        $this->user = $user;
        $this->runAsUser = true;
        return $this;
    }

    // ===== UTILITY METHODS =====

    /**
     * Set task description.
     */
    public function name(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function description(string $description): self
    {
        return $this->name($description);
    }

    /**
     * Get summary of scheduled event.
     */
    public function getSummary(): array
    {
        return [
            'command' => $this->command,
            'expression' => $this->expression,
            'description' => $this->description,
            'timezone' => $this->timezone ? $this->timezone->getName() : null,
            'next_run' => $this->getNextRunDate(),
        ];
    }

    /**
     * Get next run date.
     */
    public function getNextRunDate(): string
    {
        $date = new \DateTime('now', $this->timezone ?: new DateTimeZone(date_default_timezone_get()));
        return CronExpression::parse($this->expression)->getNextRunDate($date)->format('Y-m-d H:i:s');
    }

    /**
     * Get expression.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}