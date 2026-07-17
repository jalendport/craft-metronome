<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * A single scheduled task, defined through a fluent builder API.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\models;

use Closure;
use Craft;
use craft\helpers\Queue;
use craft\queue\JobInterface;
use Cron\CronExpression;
use DateTime;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * A single scheduled task.
 *
 * Tasks are plain PHP objects (not `craft\base\Model`) built fluently off the
 * {@see \jalendport\metronome\services\Schedule} registry:
 *
 * ```php
 * $schedule->command('utils/update-search-indexes')
 *     ->dailyAt('03:00')
 *     ->weekdays()
 *     ->withoutOverlapping()
 *     ->onOneServer();
 * ```
 *
 * The four task flavours — `command`, `exec`, `call`, and `job` — differ only
 * in how {@see _execute()} dispatches them; every scheduling, constraint, and
 * hook method below applies to all of them.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class Task
{
    // Private Properties
    // =========================================================================

    /**
     * @var callable[] callbacks run after the task, on success or failure
     * @since 1.0.0
     */
    private array $_afterCallbacks = [];

    /**
     * @var bool whether output is appended to (rather than overwriting) the output path
     * @since 1.0.0
     */
    private bool $_appendOutput = true;

    /**
     * @var array positional arguments for `command` and `call` tasks
     * @since 1.0.0
     */
    private array $_args = [];

    /**
     * @var callable[] callbacks run immediately before the task executes
     * @since 1.0.0
     */
    private array $_beforeCallbacks = [];

    /**
     * @var string|Closure|JobInterface the thing to run, interpreted per {@see $_type}
     * @since 1.0.0
     */
    private string|Closure|JobInterface $_command;

    /**
     * @var string|null a human-readable description, shown in listings when set
     * @since 1.0.0
     */
    private ?string $_description = null;

    /**
     * @var string[] environments (`Craft::$app->env`) the task is allowed to run in
     * @since 1.0.0
     */
    private array $_environments = [];

    /**
     * @var int minutes an overlap lock survives before it is considered stale
     * @since 1.0.0
     */
    private int $_expiresAfterMinutes = 1440;

    /**
     * @var string the cron expression governing when the task is due
     * @since 1.0.0
     */
    private string $_expression = '* * * * *';

    /**
     * @var callable[] callbacks run on failure, before the exception propagates
     * @since 1.0.0
     */
    private array $_failureCallbacks = [];

    /**
     * @var callable[] truth tests that must all pass for the task to run
     * @since 1.0.0
     */
    private array $_filters = [];

    /**
     * @var string|null an explicit name, used for locks, force-running, and listings
     * @since 1.0.0
     */
    private ?string $_name = null;

    /**
     * @var bool whether the task should run on a single server per scheduled minute
     * @since 1.0.0
     */
    private bool $_onOneServer = false;

    /**
     * @var string|null a filesystem path to write captured output to
     * @since 1.0.0
     */
    private ?string $_outputPath = null;

    /**
     * @var bool whether overlapping runs are prevented with a cache lock
     * @since 1.0.0
     */
    private bool $_preventOverlapping = false;

    /**
     * @var callable[] truth tests that skip the task when any returns true
     * @since 1.0.0
     */
    private array $_rejects = [];

    /**
     * @var callable[] callbacks run only after the task succeeds
     * @since 1.0.0
     */
    private array $_successCallbacks = [];

    /**
     * @var string|null the task's timezone, defaulting to the app timezone
     * @since 1.0.0
     */
    private ?string $_timezone = null;

    /**
     * @var string the task flavour: `command`, `exec`, `call`, or `job`
     * @since 1.0.0
     */
    private string $_type;

    // Public Methods
    // =========================================================================

    /**
     * Constructor.
     *
     * @param string $type the task flavour: `command`, `exec`, `call`, or `job`
     * @param string|Closure|JobInterface $command the thing to run
     * @param array $args positional arguments for `command` and `call` tasks
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function __construct(string $type, string|Closure|JobInterface $command, array $args = [])
    {
        $this->_type = $type;
        $this->_command = $command;
        $this->_args = $args;
    }

    // Frequencies

    /**
     * Sets the raw cron expression.
     *
     * @param string $expression a five-field cron expression
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function cron(string $expression): static
    {
        $this->_expression = $expression;
        return $this;
    }

    /**
     * Runs the task every minute.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    /**
     * Runs the task every five minutes.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Runs the task every ten minutes.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Runs the task every fifteen minutes.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Runs the task every thirty minutes.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    /**
     * Runs the task at the top of every hour.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Runs the task hourly at the given minute past the hour.
     *
     * @param int $minute the minute past the hour (0–59)
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Runs the task once a day at midnight.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Runs the task once a day at the given time.
     *
     * @param string $time a `H:i` time of day
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function dailyAt(string $time): static
    {
        [$hour, $minute] = array_pad(explode(':', $time, 2), 2, '0');
        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Runs the task twice a day at the two given hours.
     *
     * @param int $first the first hour of the day (0–23)
     * @param int $second the second hour of the day (0–23)
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function twiceDaily(int $first = 1, int $second = 13): static
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    /**
     * Runs the task once a week, at midnight on Sunday.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Runs the task weekly on the given day and time.
     *
     * @param int $day the day of the week (0 = Sunday … 6 = Saturday)
     * @param string $time a `H:i` time of day
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function weeklyOn(int $day, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time, 2), 2, '0');
        return $this->cron("{$minute} {$hour} * * {$day}");
    }

    /**
     * Runs the task once a month, at midnight on the first.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Runs the task monthly on the given day and time.
     *
     * @param int $day the day of the month (1–31)
     * @param string $time a `H:i` time of day
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function monthlyOn(int $day = 1, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time, 2), 2, '0');
        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    /**
     * Runs the task on the first of January, April, July, and October.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function quarterly(): static
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    /**
     * Runs the task once a year, at midnight on the first of January.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function yearly(): static
    {
        return $this->cron('0 0 1 1 *');
    }

    // Day constraints

    /**
     * Limits the task to the given days of the week.
     *
     * Accepts cron day-of-week values (0–7, where 0 and 7 are both Sunday), set
     * directly on the day-of-week field of the expression.
     *
     * @param int|string ...$days the days of the week
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function days(int|string ...$days): static
    {
        return $this->_spliceIntoExpression(5, implode(',', $days));
    }

    /**
     * Limits the task to weekdays (Monday–Friday).
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function weekdays(): static
    {
        return $this->_addFilter(fn(): bool => !in_array($this->_now()->format('w'), ['0', '6'], true));
    }

    /**
     * Limits the task to weekends (Saturday and Sunday).
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function weekends(): static
    {
        return $this->_addFilter(fn(): bool => in_array($this->_now()->format('w'), ['0', '6'], true));
    }

    /**
     * Limits the task to Mondays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function mondays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '1');
    }

    /**
     * Limits the task to Tuesdays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function tuesdays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '2');
    }

    /**
     * Limits the task to Wednesdays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function wednesdays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '3');
    }

    /**
     * Limits the task to Thursdays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function thursdays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '4');
    }

    /**
     * Limits the task to Fridays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function fridays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '5');
    }

    /**
     * Limits the task to Saturdays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function saturdays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '6');
    }

    /**
     * Limits the task to Sundays.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function sundays(): static
    {
        return $this->_addFilter(fn(): bool => $this->_now()->format('w') === '0');
    }

    // Time and conditional constraints

    /**
     * Limits the task to run only between the two given times of day.
     *
     * @param string $start a `H:i` start time (inclusive)
     * @param string $end a `H:i` end time (inclusive)
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function between(string $start, string $end): static
    {
        return $this->_addFilter(function() use ($start, $end): bool {
            $now = $this->_now();
            $startTime = DateTime::createFromFormat('H:i', $start, $now->getTimezone());
            $endTime = DateTime::createFromFormat('H:i', $end, $now->getTimezone());
            return $now >= $startTime && $now <= $endTime;
        });
    }

    /**
     * Prevents the task from running between the two given times of day.
     *
     * @param string $start a `H:i` start time (inclusive)
     * @param string $end a `H:i` end time (inclusive)
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function unlessBetween(string $start, string $end): static
    {
        return $this->_addFilter(function() use ($start, $end): bool {
            $now = $this->_now();
            $startTime = DateTime::createFromFormat('H:i', $start, $now->getTimezone());
            $endTime = DateTime::createFromFormat('H:i', $end, $now->getTimezone());
            return $now < $startTime || $now > $endTime;
        });
    }

    /**
     * Limits the task to environments (matched against `Craft::$app->env`).
     *
     * @param string ...$environments the allowed environments
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function environments(string ...$environments): static
    {
        $this->_environments = $environments;
        return $this;
    }

    /**
     * Adds a truth test the task must pass to run.
     *
     * @param callable $callback a callback returning whether the task may run
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function when(callable $callback): static
    {
        return $this->_addFilter($callback);
    }

    /**
     * Adds a truth test that skips the task when it returns true.
     *
     * @param callable $callback a callback returning whether to skip the task
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function skip(callable $callback): static
    {
        $this->_rejects[] = $callback;
        return $this;
    }

    /**
     * Sets the timezone the task's schedule is evaluated in.
     *
     * @param string $timezone a PHP timezone identifier
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function timezone(string $timezone): static
    {
        $this->_timezone = $timezone;
        return $this;
    }

    // Concurrency

    /**
     * Prevents the task from overlapping with a still-running instance of itself.
     *
     * @param int $expiresAfterMinutes minutes before a held lock is treated as stale
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function withoutOverlapping(int $expiresAfterMinutes = 1440): static
    {
        $this->_preventOverlapping = true;
        $this->_expiresAfterMinutes = $expiresAfterMinutes;
        return $this;
    }

    /**
     * Ensures the task runs on only one server per scheduled minute.
     *
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function onOneServer(): static
    {
        $this->_onOneServer = true;
        return $this;
    }

    // Output

    /**
     * Writes the task's captured output to a file, overwriting it each run.
     *
     * @param string $path the filesystem path to write to
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function sendOutputTo(string $path): static
    {
        $this->_outputPath = $path;
        $this->_appendOutput = false;
        return $this;
    }

    /**
     * Appends the task's captured output to a file.
     *
     * @param string $path the filesystem path to append to
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function appendOutputTo(string $path): static
    {
        $this->_outputPath = $path;
        $this->_appendOutput = true;
        return $this;
    }

    // Hooks

    /**
     * Registers a callback to run immediately before the task executes.
     *
     * @param callable $callback the callback
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function before(callable $callback): static
    {
        $this->_beforeCallbacks[] = $callback;
        return $this;
    }

    /**
     * Registers a callback to run after the task, whether it succeeds or fails.
     *
     * @param callable $callback the callback
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function after(callable $callback): static
    {
        $this->_afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * Registers a callback to run only when the task succeeds.
     *
     * @param callable $callback the callback
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function onSuccess(callable $callback): static
    {
        $this->_successCallbacks[] = $callback;
        return $this;
    }

    /**
     * Registers a callback to run when the task fails, before the error propagates.
     *
     * The callback receives the caught {@see Throwable}.
     *
     * @param callable $callback the callback
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function onFailure(callable $callback): static
    {
        $this->_failureCallbacks[] = $callback;
        return $this;
    }

    // Naming

    /**
     * Sets an explicit name for the task.
     *
     * The name identifies the task for locks, force-running, and listings.
     *
     * @param string $name the task name
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function name(string $name): static
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets a human-readable description for the task.
     *
     * @param string $description the description
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function description(string $description): static
    {
        $this->_description = $description;
        return $this;
    }

    // Accessors

    /**
     * Returns the display command: the raw command string, or a placeholder for
     * closure and job tasks.
     *
     * @return string the display command
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getDisplayCommand(): string
    {
        return match ($this->_type) {
            'call' => 'Closure',
            'job' => is_string($this->_command) ? $this->_command : $this->_command::class,
            default => (string)$this->_command,
        };
    }

    /**
     * Returns the raw command string, or null for closure and job-object tasks.
     *
     * @return string|null the command string
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getCommandString(): ?string
    {
        return is_string($this->_command) ? $this->_command : null;
    }

    /**
     * Returns the cron expression governing when the task is due.
     *
     * @return string the cron expression
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getExpression(): string
    {
        return $this->_expression;
    }

    /**
     * Returns the task's stable identifier, used to key its cache locks and
     * last-run snapshot.
     *
     * The explicit name is preferred; otherwise a hash of the expression and
     * command is used, so the identifier survives across requests.
     *
     * @return string the task identifier
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getId(): string
    {
        if ($this->_name !== null) {
            return $this->_name;
        }

        $signature = match ($this->_type) {
            'call' => 'closure',
            'job' => is_string($this->_command) ? $this->_command : $this->_command::class,
            default => (string)$this->_command,
        };

        return sha1($this->_expression . ':' . $signature);
    }

    /**
     * Returns the label shown for the task in listings: its description, then
     * its name, then its display command.
     *
     * @return string the label
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getLabel(): string
    {
        return $this->_description ?? $this->_name ?? $this->getDisplayCommand();
    }

    /**
     * Returns the task's explicit name, if one was set.
     *
     * @return string|null the name
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getName(): ?string
    {
        return $this->_name;
    }

    /**
     * Returns the next date and time the task is due, in its own timezone.
     *
     * @return DateTime the next run date
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getNextRunDate(): DateTime
    {
        return (new CronExpression($this->_expression))->getNextRunDate($this->_now());
    }

    // Evaluation

    /**
     * Returns whether the task is due to run at the given moment.
     *
     * The cron expression is matched in the task's timezone, then the
     * environment gate, filters (all must pass), and rejects (any true skips)
     * are applied in that order.
     *
     * @param DateTime $now the current moment, in any timezone
     * @return bool whether the task is due
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function isDue(DateTime $now): bool
    {
        $now = $this->_applyTimezone($now);

        if (!(new CronExpression($this->_expression))->isDue($now)) {
            return false;
        }

        if ($this->_environments !== [] && !in_array(Craft::$app->env, $this->_environments, true)) {
            return false;
        }

        foreach ($this->_filters as $filter) {
            if (!$filter()) {
                return false;
            }
        }

        foreach ($this->_rejects as $reject) {
            if ($reject()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs the task.
     *
     * Concurrency guards are acquired first: if `onOneServer` or
     * `withoutOverlapping` cannot claim their lock, the task is skipped
     * silently. Otherwise before-hooks run, the task executes, its output is
     * written, and success/after hooks fire. On failure the failure and after
     * hooks fire and the exception is re-thrown so the runner can record it.
     *
     * @return void
     * @throws Throwable if the task's execution fails
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function run(): void
    {
        if ($this->_onOneServer && !$this->_acquireServerLock()) {
            return;
        }

        if ($this->_preventOverlapping && !$this->_acquireOverlapLock()) {
            return;
        }

        try {
            foreach ($this->_beforeCallbacks as $callback) {
                $callback();
            }

            $output = $this->_execute();
            $this->_writeOutput($output);

            foreach ($this->_successCallbacks as $callback) {
                $callback();
            }
        } catch (Throwable $e) {
            foreach ($this->_failureCallbacks as $callback) {
                $callback($e);
            }

            throw $e;
        } finally {
            foreach ($this->_afterCallbacks as $callback) {
                $callback();
            }

            if ($this->_preventOverlapping) {
                $this->_releaseOverlapLock();
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Acquires the overlap-prevention lock.
     *
     * @return bool whether the lock was acquired
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _acquireOverlapLock(): bool
    {
        return Craft::$app->getCache()->add(
            'metronome:lock:' . $this->getId(),
            time(),
            $this->_expiresAfterMinutes * 60,
        );
    }

    /**
     * Acquires the single-server lock for the current scheduled minute.
     *
     * The lock is intentionally not released: its one-hour TTL is what keeps
     * other servers out for the duration of the run and lets it expire on its own.
     *
     * @return bool whether the lock was acquired
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _acquireServerLock(): bool
    {
        $minute = (int)floor(time() / 60) * 60;

        return Craft::$app->getCache()->add(
            'metronome:server-lock:' . $this->getId() . ':' . $minute,
            gethostname() ?: '1',
            3600,
        );
    }

    /**
     * Adds a filter callback and returns the task for chaining.
     *
     * @param callable $callback the filter callback
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _addFilter(callable $callback): static
    {
        $this->_filters[] = $callback;
        return $this;
    }

    /**
     * Returns the given moment converted into the task's timezone.
     *
     * @param DateTime $now the current moment
     * @return DateTime the moment in the task's timezone
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _applyTimezone(DateTime $now): DateTime
    {
        $now = clone $now;
        $now->setTimezone(new DateTimeZone($this->_timezone ?? Craft::$app->getTimeZone()));
        return $now;
    }

    /**
     * Builds the shell command for a `command` task.
     *
     * @return string the shell command
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _buildCommandString(): string
    {
        $php = PHP_BINARY;
        $command = is_string($this->_command) ? $this->_command : '';
        $shell = $php . ' ' . (string)Craft::getAlias('@root') . '/craft ' . $command;

        if ($this->_args !== []) {
            $shell .= ' ' . implode(' ', array_map('escapeshellarg', $this->_args));
        }

        return $shell;
    }

    /**
     * Executes the task and returns any captured output.
     *
     * @return string|null the captured output
     * @throws RuntimeException if a shell command exits non-zero
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _execute(): ?string
    {
        return match ($this->_type) {
            'command' => $this->_runShellCommand($this->_buildCommandString()),
            'exec' => $this->_runShellCommand(is_string($this->_command) ? $this->_command : ''),
            'call' => $this->_runCallback(),
            'job' => $this->_pushJob(),
            default => null,
        };
    }

    /**
     * Returns the current moment in the task's timezone.
     *
     * @return DateTime the current moment
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _now(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->_timezone ?? Craft::$app->getTimeZone()));
    }

    /**
     * Pushes a `job` task onto Craft's queue.
     *
     * @return null always null; jobs produce no synchronous output
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _pushJob(): null
    {
        /** @var JobInterface $job */
        $job = is_string($this->_command) ? new $this->_command() : $this->_command;
        Queue::push($job);
        return null;
    }

    /**
     * Releases the overlap-prevention lock.
     *
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _releaseOverlapLock(): void
    {
        Craft::$app->getCache()->delete('metronome:lock:' . $this->getId());
    }

    /**
     * Runs a `call` task, capturing anything it echoes as output.
     *
     * @return string|null the captured output
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _runCallback(): ?string
    {
        /** @var Closure $callback */
        $callback = $this->_command;

        ob_start();
        $callback(...$this->_args);
        return ob_get_clean() ?: null;
    }

    /**
     * Runs a shell command, returning its output and throwing on a non-zero exit.
     *
     * The throw is deliberate: it lets a command failure propagate out of
     * {@see run()} so the runner records the task as failed and fires
     * `EVENT_AFTER_TASK` with the error, rather than reporting a false success.
     * The captured output is embedded in the exception so the detail survives
     * into logs and monitoring.
     *
     * @param string $command the shell command
     * @return string|null the captured output
     * @throws RuntimeException if the command exits non-zero
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _runShellCommand(string $command): ?string
    {
        $output = [];
        $exitCode = 0;

        exec($command . ' 2>&1', $output, $exitCode);

        $outputStr = implode(PHP_EOL, $output) ?: null;

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Command exited with status {$exitCode}: {$command}"
                . ($outputStr !== null ? PHP_EOL . $outputStr : ''),
            );
        }

        return $outputStr;
    }

    /**
     * Sets the given cron field on the expression and returns the task.
     *
     * @param int $position the one-based field position (1 = minute … 5 = day of week)
     * @param string $value the field value
     * @return static
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _spliceIntoExpression(int $position, string $value): static
    {
        $segments = explode(' ', $this->_expression);
        $segments[$position - 1] = $value;
        $this->_expression = implode(' ', $segments);
        return $this;
    }

    /**
     * Writes captured output to the task's output path, if one is configured.
     *
     * This is user-directed I/O: the path comes from `sendOutputTo()` /
     * `appendOutputTo()`, analogous to shell redirection. All of Metronome's
     * own state (locks, last-run) lives in the cache, never on disk.
     *
     * @param string|null $output the captured output
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _writeOutput(?string $output): void
    {
        if ($this->_outputPath === null || $output === null) {
            return;
        }

        $flags = $this->_appendOutput ? FILE_APPEND : 0;
        file_put_contents($this->_outputPath, $output . PHP_EOL, $flags);
    }
}
