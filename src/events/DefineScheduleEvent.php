<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Event that lets plugins and modules register their own scheduled tasks.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\events;

use jalendport\metronome\services\Schedule;
use yii\base\Event;

/**
 * Event fired once when the schedule is first loaded, so plugins and modules
 * can register their own tasks against the shared {@see Schedule} registry.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class DefineScheduleEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var Schedule the schedule registry to add tasks to
     * @since 1.0.0
     */
    public Schedule $schedule;
}
