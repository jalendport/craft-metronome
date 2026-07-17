<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Console command that runs due scheduled tasks.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\console\controllers;

use Craft;
use craft\console\Controller;
use jalendport\base\controllers\ConsoleControllerTrait;
use jalendport\metronome\Metronome;
use jalendport\metronome\services\Schedule;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Runs due scheduled tasks.
 *
 * Wire a single crontab entry to this command and let Metronome decide what is
 * due:
 *
 * ```
 * * * * * * php /path/to/craft metronome/run >> /dev/null 2>&1
 * ```
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class RunController extends Controller
{
    use ConsoleControllerTrait;

    // Public Properties
    // =========================================================================

    /**
     * @var string|null a task name or command to force-run immediately,
     * regardless of its schedule
     * @since 1.0.0
     */
    public ?string $task = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['task']);
    }

    /**
     * @inheritdoc
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['t' => 'task']);
    }

    /**
     * Runs all due scheduled tasks, or a single task when `--task` is given.
     *
     * @return int the exit code
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function actionIndex(): int
    {
        $schedule = Metronome::$plugin->getSchedule();

        if ($this->task !== null) {
            return $this->_runForced($schedule);
        }

        $results = $schedule->runDueTasks();

        if ($results === []) {
            $this->writeLine(Craft::t('metronome', 'No scheduled tasks are due.'));
            return ExitCode::OK;
        }

        foreach ($results as $result) {
            $this->_writeResult($result);
        }

        $failed = count(array_filter($results, static fn(array $r): bool => !$r['success']));

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    // Private Methods
    // =========================================================================

    /**
     * Force-runs the task named by `--task`, listing the available tasks when
     * no match is found.
     *
     * @param Schedule $schedule the schedule service
     * @return int the exit code
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _runForced(Schedule $schedule): int
    {
        $task = $schedule->getTask((string)$this->task);

        if ($task === null) {
            $this->writeError(Craft::t('metronome', 'Task “{name}” not found.', ['name' => $this->task]));
            $this->_listAvailableTasks($schedule);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $result = $schedule->runTask($task);
        $this->_writeResult($result);

        return $result['success'] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Lists the names and commands of every registered task.
     *
     * @param Schedule $schedule the schedule service
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _listAvailableTasks(Schedule $schedule): void
    {
        $tasks = $schedule->getTasks();

        if ($tasks === []) {
            return;
        }

        $this->writeLine('');
        $this->writeLine(Craft::t('metronome', 'Available tasks:'));

        foreach ($tasks as $task) {
            $this->writeLine('  ' . ($task->getName() ?? $task->getDisplayCommand()));
        }
    }

    /**
     * Writes a single task result as a ✓/✗ line with its duration.
     *
     * @param array $result a result record from the schedule runner
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _writeResult(array $result): void
    {
        $milliseconds = number_format($result['duration'] * 1000);

        if ($result['success']) {
            $this->stdout('  ✓ ', Console::FG_GREEN);
            $this->stdout("{$result['name']} ({$milliseconds}ms)" . PHP_EOL);
            return;
        }

        $this->stderr('  ✗ ', Console::FG_RED);
        $this->stderr("{$result['name']}: {$result['error']}" . PHP_EOL);
    }
}
