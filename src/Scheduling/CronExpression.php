<?php

namespace Maharlika\Scheduling;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

class CronExpression
{
    protected array $segments;

    protected const MINUTE = 0;
    protected const HOUR = 1;
    protected const DAY = 2;
    protected const MONTH = 3;
    protected const WEEKDAY = 4;

    protected static array $months = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4,
        'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8,
        'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    protected static array $weekdays = [
        'SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3,
        'THU' => 4, 'FRI' => 5, 'SAT' => 6,
    ];

    public function __construct(string $expression)
    {
        $this->segments = $this->parseExpression($expression);
    }

    /**
     * Create instance from expression string.
     */
    public static function parse(string $expression): self
    {
        return new static($expression);
    }

    /**
     * Parse cron expression into segments.
     */
    protected function parseExpression(string $expression): array
    {
        $segments = preg_split('/\s+/', trim($expression));

        if (count($segments) !== 5) {
            throw new InvalidArgumentException(
                "Invalid cron expression: {$expression}. Must have 5 segments."
            );
        }

        return $segments;
    }

    /**
     * Check if expression is due to run.
     */
    public function isDue(?DateTime $date = null): bool
    {
        $date = $date ?: new DateTime('now');

        return $this->matchesMinute($date) &&
               $this->matchesHour($date) &&
               $this->matchesDay($date) &&
               $this->matchesMonth($date) &&
               $this->matchesWeekday($date);
    }

    /**
     * Get next run date.
     */
    public function getNextRunDate(?DateTime $date = null): DateTime
    {
        $date = $date ? clone $date : new DateTime('now');
        $date->setTime((int)$date->format('H'), (int)$date->format('i'), 0);
        
        // Start from next minute
        $date->modify('+1 minute');

        // Find next valid time (max 2 years ahead to prevent infinite loop)
        $maxAttempts = 60 * 24 * 365 * 2; // 2 years worth of minutes
        $attempts = 0;

        while (!$this->isDue($date) && $attempts < $maxAttempts) {
            $date->modify('+1 minute');
            $attempts++;
        }

        if ($attempts >= $maxAttempts) {
            throw new \RuntimeException('Could not calculate next run date within 2 years');
        }

        return $date;
    }

    /**
     * Check if minute matches.
     */
    protected function matchesMinute(DateTime $date): bool
    {
        return $this->matchesSegment(
            self::MINUTE,
            (int)$date->format('i'),
            0,
            59
        );
    }

    /**
     * Check if hour matches.
     */
    protected function matchesHour(DateTime $date): bool
    {
        return $this->matchesSegment(
            self::HOUR,
            (int)$date->format('H'),
            0,
            23
        );
    }

    /**
     * Check if day matches.
     */
    protected function matchesDay(DateTime $date): bool
    {
        // If weekday is specified and not *, skip day check
        if ($this->segments[self::WEEKDAY] !== '*') {
            return true;
        }

        return $this->matchesSegment(
            self::DAY,
            (int)$date->format('d'),
            1,
            31
        );
    }

    /**
     * Check if month matches.
     */
    protected function matchesMonth(DateTime $date): bool
    {
        return $this->matchesSegment(
            self::MONTH,
            (int)$date->format('m'),
            1,
            12
        );
    }

    /**
     * Check if weekday matches.
     */
    protected function matchesWeekday(DateTime $date): bool
    {
        // If day is specified and not *, skip weekday check
        if ($this->segments[self::DAY] !== '*') {
            return true;
        }

        return $this->matchesSegment(
            self::WEEKDAY,
            (int)$date->format('w'),
            0,
            6
        );
    }

    /**
     * Check if a segment matches.
     */
    protected function matchesSegment(int $position, int $value, int $min, int $max): bool
    {
        $segment = $this->segments[$position];

        // Wildcard
        if ($segment === '*') {
            return true;
        }

        // List (e.g., 1,3,5)
        if (str_contains($segment, ',')) {
            $values = explode(',', $segment);
            return in_array($value, array_map('intval', $values));
        }

        // Range (e.g., 1-5)
        if (str_contains($segment, '-') && !str_contains($segment, '/')) {
            [$start, $end] = explode('-', $segment);
            return $value >= (int)$start && $value <= (int)$end;
        }

        // Step (e.g., */5 or 0-30/5)
        if (str_contains($segment, '/')) {
            return $this->matchesStep($segment, $value, $min, $max);
        }

        // Exact match
        return (int)$segment === $value;
    }

    /**
     * Check if step value matches.
     */
    protected function matchesStep(string $segment, int $value, int $min, int $max): bool
    {
        [$range, $step] = explode('/', $segment);

        if ($range === '*') {
            $start = $min;
            $end = $max;
        } elseif (str_contains($range, '-')) {
            [$start, $end] = explode('-', $range);
        } else {
            $start = (int)$range;
            $end = $max;
        }

        $step = (int)$step;

        // Check if value is within range and matches step
        if ($value < $start || $value > $end) {
            return false;
        }

        return ($value - $start) % $step === 0;
    }

    /**
     * Get human-readable description.
     */
    public function getDescription(): string
    {
        $expression = implode(' ', $this->segments);

        // Common patterns
        $patterns = [
            '* * * * *' => 'every minute',
            '*/5 * * * *' => 'every 5 minutes',
            '0 * * * *' => 'every hour',
            '0 0 * * *' => 'daily at midnight',
            '0 0 * * 0' => 'weekly on Sunday',
            '0 0 1 * *' => 'monthly on the 1st',
            '0 0 1 1 *' => 'yearly on January 1st',
        ];

        if (isset($patterns[$expression])) {
            return $patterns[$expression];
        }

        return $expression;
    }
}