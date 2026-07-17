<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * The plugin's settings model.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\models;

use Closure;
use craft\base\Model;

/**
 * Metronome settings.
 *
 * Metronome has no control panel settings page; this model exists so Craft's
 * standard `config/metronome.php` plugin-config mechanism can deliver the
 * schedule definition. The file is merged into this model at boot, which also
 * makes it multi-environment aware for free.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var Closure|null the schedule definition — a callback that receives the
     * {@see \jalendport\metronome\services\Schedule} component and registers
     * tasks against it
     * @since 1.0.0
     */
    public ?Closure $schedule = null;
}
