<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Event fired before and after a scheduled task runs.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\events;

use jalendport\metronome\models\Task;
use Throwable;
use yii\base\Event;

/**
 * Event fired before and after a scheduled task runs.
 *
 * The `duration`, `success`, and `error` properties are only meaningful on the
 * after-task event; on the before-task event they carry their defaults.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class TaskEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var Task the task being run
     * @since 1.0.0
     */
    public Task $task;

    /**
     * @var float|null the run duration in seconds (after-task only)
     * @since 1.0.0
     */
    public ?float $duration = null;

    /**
     * @var bool whether the task succeeded (after-task only)
     * @since 1.0.0
     */
    public bool $success = true;

    /**
     * @var Throwable|null the error the task threw, if it failed (after-task only)
     * @since 1.0.0
     */
    public ?Throwable $error = null;
}
