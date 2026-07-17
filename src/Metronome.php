<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * A Laravel-style task scheduler for Craft CMS. One crontab entry, everything else in PHP.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use jalendport\base\Plugin;
use jalendport\metronome\services\Schedule as ScheduleService;
use jalendport\metronome\utilities\Schedule as ScheduleUtility;
use yii\base\Event;

/**
 * Metronome plugin.
 *
 * The main class is deliberately lean: component registration lives in the
 * static {@see config()} method, and {@see init()} is a table of contents of
 * private `_registerXxx()` methods. Console controllers under
 * `console\controllers` are wired up automatically by Craft, so there is no
 * console `controllerNamespace` bookkeeping here.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 *
 * @property-read ScheduleService $schedule
 */
class Metronome extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Metronome the plugin instance
     * @since 1.0.0
     */
    public static Metronome $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var bool whether the plugin has a settings page in the control panel
     * @since 1.0.0
     */
    public bool $hasCpSettings = false;

    /**
     * @var string the plugin's schema version
     * @since 1.0.0
     */
    public string $schemaVersion = '1.0.0';

    // Static Methods
    // =========================================================================

    /**
     * Registers the plugin's components per the Craft 5 plugin spec.
     *
     * @return array the component configuration
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public static function config(): array
    {
        return [
            'components' => [
                'schedule' => ScheduleService::class,
            ],
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerUtilities();
    }

    /**
     * Returns the schedule service — Metronome's task registry and runner.
     *
     * @return ScheduleService the schedule service
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public function getSchedule(): ScheduleService
    {
        return $this->get('schedule');
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the plugin's control panel utilities.
     *
     * @return void
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private function _registerUtilities(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = ScheduleUtility::class;
            },
        );
    }
}
