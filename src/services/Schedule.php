<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * The schedule: task registry, due-matching, and runner rolled into one component.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\services;

use Closure;
use Craft;
use craft\queue\JobInterface;
use DateTime;
use DateTimeZone;
use jalendport\metronome\events\DefineScheduleEvent;
use jalendport\metronome\events\TaskEvent;
use jalendport\metronome\Metronome;
use jalendport\metronome\models\Task;
use Throwable;
use yii\base\Component;

/**
 * The schedule component.
 *
 * Metronome keeps a single schedule that plays three roles: the registry tasks
 * are defined against (`command()`, `exec()`, `call()`, `job()`), the matcher
 * that decides which are due, and the runner that executes them and records
 * their results. The registry is loaded lazily and exactly once — from
 * `config/metronome.php` and then any {@see EVENT_DEFINE_SCHEDULE} handlers —
 * the first time it is read.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class Schedule extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event DefineScheduleEvent The event fired once when the schedule is
     * first loaded, letting plugins and modules register their own tasks.
     * @since 1.0.0
     */
    public const EVENT_DEFINE_SCHEDULE = 'defineSchedule';

    /**
     * @event TaskEvent The event fired before each task runs.
     * @since 1.0.0
     */
    public const EVENT_BEFORE_TASK = 'beforeTask';

    /**
     * @event TaskEvent The event fired after each task runs, on success or failure.
     * @since 1.0.0
     */
    public const EVENT_AFTER_TASK = 'afterTask';

    // Private Properties
    // =========================================================================

    /**
     * @var bool whether the schedule has been loaded from config and events
     * @since 1.0.0
     */
    private bool $_loaded = false;

    /**
     * @var Task[] the registered tasks
     * @since 1.0.0
     */
    private array $_tasks = [];

    // Public Methods
    // =========================================================================

    // Registration

    /**
     * Schedules a Craft console command.
     *
     * @param string $command the command, e.g. `utils/update-search-indexes`
     * @param array $args positional arguments appended to the command
     * @return Task the new task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function command(string $command, array $args = []): Task
    {
        return $this->_add(new Task('command', $command, $args));
    }

    /**
     * Schedules a raw shell command.
     *
     * @param string $command the shell command
     * @return Task the new task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function exec(string $command): Task
    {
        return $this->_add(new Task('exec', $command));
    }

    /**
     * Schedules a PHP callable.
     *
     * @param callable $callback the callable to run
     * @param array $args positional arguments passed to the callable
     * @return Task the new task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function call(callable $callback, array $args = []): Task
    {
        return $this->_add(new Task('call', Closure::fromCallable($callback), $args));
    }

    /**
     * Schedules a queue job.
     *
     * @param JobInterface|string $job a job instance or job class name
     * @return Task the new task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function job(JobInterface|string $job): Task
    {
        return $this->_add(new Task('job', $job));
    }

    // Querying

    /**
     * Returns all registered tasks, loading the schedule first if needed.
     *
     * @return Task[] the registered tasks
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getTasks(): array
    {
        $this->_load();
        return $this->_tasks;
    }

    /**
     * Returns the tasks that are due to run now.
     *
     * @return Task[] the due tasks
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getDueTasks(): array
    {
        $now = new DateTime('now', new DateTimeZone(Craft::$app->getTimeZone()));

        return array_values(array_filter(
            $this->getTasks(),
            static fn(Task $task): bool => $task->isDue($now),
        ));
    }

    /**
     * Returns the task matching the given identifier, or null if none matches.
     *
     * Tasks are matched by explicit name first, then by command string, so a
     * `--task` value can reference either.
     *
     * @param string $name a task name or command string
     * @return Task|null the matching task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getTask(string $name): ?Task
    {
        $tasks = $this->getTasks();

        foreach ($tasks as $task) {
            if ($task->getName() === $name) {
                return $task;
            }
        }

        foreach ($tasks as $task) {
            if ($task->getCommandString() === $name) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Returns the last-run snapshot for the given task, or null if it has never run.
     *
     * @param Task $task the task
     * @return array|null the snapshot: `date`, `duration`, `success`, `error`
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getLastRun(Task $task): ?array
    {
        $value = Craft::$app->getCache()->get('metronome:last-run:' . $task->getId());
        return $value === false ? null : $value;
    }

    // Running

    /**
     * Runs every due task and returns a result record for each.
     *
     * A failing task never aborts the rest; each is timed, snapshotted, logged,
     * and wrapped in before/after events independently.
     *
     * @return array[] one result record per task run
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function runDueTasks(): array
    {
        $results = [];

        foreach ($this->getDueTasks() as $task) {
            $results[] = $this->runTask($task);
        }

        return $results;
    }

    /**
     * Runs a single task immediately, regardless of its schedule, and returns
     * its result record.
     *
     * @param Task $task the task to run
     * @return array the result record: `name`, `success`, `duration`, `error`
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function runTask(Task $task): array
    {
        $name = $task->getLabel();

        if ($this->hasEventHandlers(self::EVENT_BEFORE_TASK)) {
            $this->trigger(self::EVENT_BEFORE_TASK, new TaskEvent(['task' => $task]));
        }

        $startTime = microtime(true);
        $success = true;
        $error = null;

        try {
            Metronome::info("Running: {$name}");
            $task->run();
            Metronome::info("Completed: {$name}");
        } catch (Throwable $e) {
            $success = false;
            $error = $e;
            Metronome::error("Failed: {$name} — {$e->getMessage()}");
        }

        $duration = microtime(true) - $startTime;

        $this->_recordLastRun($task, $duration, $success, $error);

        if ($this->hasEventHandlers(self::EVENT_AFTER_TASK)) {
            $this->trigger(self::EVENT_AFTER_TASK, new TaskEvent([
                'task' => $task,
                'duration' => $duration,
                'success' => $success,
                'error' => $error,
            ]));
        }

        return [
            'name' => $name,
            'success' => $success,
            'duration' => $duration,
            'error' => $error?->getMessage(),
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers a task and returns it for fluent chaining.
     *
     * @param Task $task the task to register
     * @return Task the same task
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _add(Task $task): Task
    {
        $this->_tasks[] = $task;
        return $task;
    }

    /**
     * Loads the schedule once, from `config/metronome.php` and then any
     * {@see EVENT_DEFINE_SCHEDULE} handlers.
     *
     * The loaded flag is set before running the definitions so a task that
     * reads the schedule during registration can't trigger a reload.
     *
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _load(): void
    {
        if ($this->_loaded) {
            return;
        }

        $this->_loaded = true;

        $configPath = Craft::getAlias('@config/metronome.php');

        if (is_string($configPath) && file_exists($configPath)) {
            $definition = require $configPath;

            if (is_callable($definition)) {
                $definition($this);
            }
        }

        if ($this->hasEventHandlers(self::EVENT_DEFINE_SCHEDULE)) {
            $this->trigger(self::EVENT_DEFINE_SCHEDULE, new DefineScheduleEvent(['schedule' => $this]));
        }
    }

    /**
     * Records a task's last-run snapshot in the cache, with no expiry.
     *
     * @param Task $task the task
     * @param float $duration the run duration in seconds
     * @param bool $success whether the task succeeded
     * @param Throwable|null $error the error the task threw, if any
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _recordLastRun(Task $task, float $duration, bool $success, ?Throwable $error): void
    {
        Craft::$app->getCache()->set('metronome:last-run:' . $task->getId(), [
            'date' => (new DateTime('now', new DateTimeZone(Craft::$app->getTimeZone())))->format(DateTime::ATOM),
            'duration' => $duration,
            'success' => $success,
            'error' => $error?->getMessage(),
        ]);
    }
}
