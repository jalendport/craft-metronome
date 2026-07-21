<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Control panel utility that lists the scheduled tasks.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\utilities;

use Craft;
use craft\base\Utility;
use jalendport\metronome\helpers\Cron;
use jalendport\metronome\Metronome;

/**
 * The Metronome control panel utility.
 *
 * A read-only view of every registered task: its schedule, next run, and last
 * run with status. All rendering lives in `metronome/_utility.twig`.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class Schedule extends Utility
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'metronome';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('metronome', 'Metronome');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'calendar-check';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $schedule = Metronome::$plugin->getSchedule();

        $tasks = [];

        foreach ($schedule->getTasks() as $task) {
            $tasks[] = [
                'name' => $task->getLabel(),
                'command' => $task->getDisplayCommand(),
                'expression' => $task->getExpression(),
                'schedule' => Cron::describe($task->getExpression()),
                'nextRun' => $task->getNextRunDate()->format('Y-m-d H:i'),
                'lastRun' => $schedule->getLastRun($task),
            ];
        }

        return Craft::$app->getView()->renderTemplate('metronome/_utility.twig', [
            'tasks' => $tasks,
        ]);
    }
}
