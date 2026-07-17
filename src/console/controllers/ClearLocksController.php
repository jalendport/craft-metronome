<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Console command that clears stale scheduler locks.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\console\controllers;

use Craft;
use craft\console\Controller;
use jalendport\base\controllers\ConsoleControllerTrait;
use jalendport\metronome\Metronome;
use yii\console\ExitCode;

/**
 * Clears the overlap and single-server locks held by registered tasks.
 *
 * Useful when a task is wedged behind an overlap lock left by a crashed run.
 * The cache exposes no wildcard delete, so this iterates the registered tasks
 * and deletes each one's known lock keys. Single-server locks are keyed by the
 * scheduled minute; only the current minute's key is deterministic here, and
 * any others expire on their own within the hour.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class ClearLocksController extends Controller
{
    use ConsoleControllerTrait;

    // Public Methods
    // =========================================================================

    /**
     * Clears the locks held by every registered task.
     *
     * @return int the exit code
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function actionIndex(): int
    {
        $tasks = Metronome::$plugin->getSchedule()->getTasks();

        if ($tasks === []) {
            $this->writeLine(Craft::t('metronome', 'No scheduled tasks are registered.'));
            return ExitCode::OK;
        }

        $cache = Craft::$app->getCache();
        $minute = (int)floor(time() / 60) * 60;

        foreach ($tasks as $task) {
            $id = $task->getId();
            $cache->delete('metronome:lock:' . $id);
            $cache->delete('metronome:server-lock:' . $id . ':' . $minute);
        }

        $this->writeSuccess(Craft::t('metronome', 'Cleared locks for {count, plural, =1{1 task} other{# tasks}}.', [
            'count' => count($tasks),
        ]));

        return ExitCode::OK;
    }
}
