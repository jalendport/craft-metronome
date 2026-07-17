<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Console command that lists the registered scheduled tasks.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\console\controllers;

use Craft;
use craft\console\Controller;
use jalendport\base\controllers\ConsoleControllerTrait;
use jalendport\metronome\helpers\Cron;
use jalendport\metronome\Metronome;
use jalendport\metronome\models\Task;
use jalendport\metronome\services\Schedule;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Lists the registered scheduled tasks.
 *
 * Shows the crontab-style expressions first, then a table of each task's
 * human-readable schedule, next run, and last run with its status.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class ListController extends Controller
{
    use ConsoleControllerTrait;

    // Public Methods
    // =========================================================================

    /**
     * Lists every registered scheduled task.
     *
     * @return int the exit code
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function actionIndex(): int
    {
        $schedule = Metronome::$plugin->getSchedule();
        $tasks = $schedule->getTasks();

        if ($tasks === []) {
            $this->writeLine(Craft::t('metronome', 'No scheduled tasks are registered.'));
            return ExitCode::OK;
        }

        $this->_writeCrontab($tasks);
        $this->_writeSchedule($schedule, $tasks);

        $this->writeLine('');
        $this->writeLine('  ' . Craft::t('metronome', '{count, plural, =1{1 task} other{# tasks}} registered.', [
            'count' => count($tasks),
        ]));
        $this->writeLine('');

        return ExitCode::OK;
    }

    // Private Methods
    // =========================================================================

    /**
     * Writes the crontab-style view: each task's expression and command.
     *
     * @param Task[] $tasks the registered tasks
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _writeCrontab(array $tasks): void
    {
        $this->writeLine('');
        $this->stdout('  ' . Craft::t('metronome', 'Crontab') . PHP_EOL, Console::BOLD);
        $this->writeLine('  ' . str_repeat('-', 72));

        foreach ($tasks as $task) {
            $expression = str_pad($task->getExpression(), 20);
            $this->writeLine("  {$expression} {$task->getDisplayCommand()}");
        }
    }

    /**
     * Writes the schedule view: each task's label, description, next run, and
     * last run with its status.
     *
     * @param Schedule $schedule the schedule service
     * @param Task[] $tasks the registered tasks
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _writeSchedule(Schedule $schedule, array $tasks): void
    {
        $this->writeLine('');
        $this->stdout('  ' . Craft::t('metronome', 'Schedule') . PHP_EOL, Console::BOLD);
        $this->writeLine('  ' . str_repeat('-', 72));

        foreach ($tasks as $task) {
            $this->stdout('  ' . $task->getLabel() . PHP_EOL, Console::BOLD);
            $this->writeLine('    ' . Cron::describe($task->getExpression()));
            $this->writeLine('    ' . Craft::t('metronome', 'Next run: {date}', [
                'date' => $task->getNextRunDate()->format('Y-m-d H:i'),
            ]));
            $this->_writeLastRun($schedule->getLastRun($task));
        }
    }

    /**
     * Writes a task's last-run line from its snapshot.
     *
     * @param array|null $lastRun the last-run snapshot, or null if never run
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _writeLastRun(?array $lastRun): void
    {
        if ($lastRun === null) {
            $this->writeLine('    ' . Craft::t('metronome', 'Last run: never'));
            return;
        }

        $status = $lastRun['success']
            ? Craft::t('metronome', 'success')
            : Craft::t('metronome', 'failed');

        $this->stdout('    ' . Craft::t('metronome', 'Last run: {date} ({status})', [
            'date' => $lastRun['date'],
            'status' => $status,
        ]) . PHP_EOL, $lastRun['success'] ? Console::FG_GREEN : Console::FG_RED);
    }
}
