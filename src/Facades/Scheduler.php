<?php

namespace Maharlika\Facades;

/**
 * @method static call(callable $callback, array $parameters = [])
 * @method static command(string $command, array $parameters = [])
 * @method static exec(string $command, array $parameters = [])
 * @method static job(string $job, ?string $queue = null)
 * @method static array dueEvents()
 * @method static array events()
 *
 * Scheduling Methods (chainable with call/command/exec/job):
 * @method static cron(string $expression)
 * @method static everyMinute()
 * @method static everyTwoMinutes()
 * @method static everyThreeMinutes()
 * @method static everyFourMinutes()
 * @method static everyFiveMinutes()
 * @method static everyTenMinutes()
 * @method static everyFifteenMinutes()
 * @method static everyThirtyMinutes()
 * @method static hourly()
 * @method static hourlyAt(int $offset)
 * @method static everyTwoHours()
 * @method static everyThreeHours()
 * @method static everyFourHours()
 * @method static everySixHours()
 * @method static daily()
 * @method static dailyAt(string $time)
 * @method static at(string $time)
 * @method static twiceDaily(int $first = 1, int $second = 13)
 * @method static weekly()
 * @method static weeklyOn(int $day, string $time = '0:00')
 * @method static monthly()
 * @method static monthlyOn(int $day = 1, string $time = '0:00')
 * @method static twiceMonthly(int $first = 1, int $second = 16, string $time = '0:00')
 * @method static lastDayOfMonth(string $time = '0:00')
 * @method static quarterly()
 * @method static yearly()
 * @method static yearlyOn(int $month = 1, int $day = 1, string $time = '0:00')
 *
 * Day of Week Methods:
 * @method static weekdays()
 * @method static weekends()
 * @method static mondays()
 * @method static tuesdays()
 * @method static wednesdays()
 * @method static thursdays()
 * @method static fridays()
 * @method static saturdays()
 * @method static sundays()
 * @method static days(int|array $days)
 *
 * Constraint Methods:
 * @method static timezone(string|\DateTimeZone $timezone)
 * @method static when(callable $callback)
 * @method static skip(callable $callback)
 * @method static withoutOverlapping(int $expiresAt = 1440)
 * @method static onOneServer()
 * @method static runInBackground()
 *
 * Output Methods:
 * @method static sendOutputTo(string $location, bool $append = false)
 * @method static appendOutputTo(string $location)
 * @method static emailOutputTo(string|array $addresses)
 *
 * Callback Methods:
 * @method static before(\Closure $callback)
 * @method static after(\Closure $callback)
 * @method static then(\Closure $callback)
 * @method static pingBefore(string $url)
 * @method static thenPing(string $url)
 *
 * Utility Methods:
 * @method static name(string $description)
 * @method static description(string $description)
 * @method static user(string $user)
 *
 * @see \Maharlika\Scheduling\Schedule
 */
class Scheduler extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'schedule';
    }
}