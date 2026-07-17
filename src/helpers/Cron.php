<?php
/**
 * Metronome plugin for Craft CMS 5.x
 *
 * Renders cron expressions as human-readable schedule descriptions.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\metronome\helpers;

use InvalidArgumentException;

/**
 * Cron helper.
 *
 * Turns a five-field cron expression into an English description such as
 * "Every 5 minutes" or "At 3:00 AM, on Monday". Kept free of any Craft
 * dependency so it stays trivially unit-testable; the descriptions are
 * therefore not run through Craft's translation layer.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 1.0.0
 */
class Cron
{
    // Static Methods
    // =========================================================================

    /**
     * Returns a human-readable description of a cron expression.
     *
     * @param string $expression a five-field cron expression
     * @return string the description
     * @throws InvalidArgumentException if the expression does not have five fields
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    public static function describe(string $expression): string
    {
        $fields = preg_split('/\s+/', trim($expression));

        if ($fields === false || count($fields) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression: {$expression}");
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $fields;

        $parts = [];

        if ($minute === '*' && $hour === '*') {
            $parts[] = 'every minute';
        } elseif (str_contains($minute, '/') && $hour === '*') {
            $step = explode('/', $minute)[1];
            $parts[] = "every {$step} minutes";
        } else {
            $minutes = self::expandField($minute, 0, 59);
            $hours = self::expandField($hour, 0, 23);

            if (count($hours) === 24 && count($minutes) === 1) {
                $parts[] = 'hourly at :' . str_pad((string)$minutes[0], 2, '0', STR_PAD_LEFT);
            } else {
                $times = [];

                foreach ($hours as $h) {
                    foreach ($minutes as $m) {
                        $times[] = self::formatTime($h, $m);
                    }
                }

                $parts[] = 'at ' . self::joinList($times);
            }
        }

        if ($dayOfWeek !== '*') {
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $days = array_map(static fn(int $d): string => $dayNames[$d % 7], self::expandField($dayOfWeek, 0, 6));
            $parts[] = 'on ' . self::joinList($days);
        }

        if ($dayOfMonth !== '*') {
            $days = array_map(static fn(int $d): string => self::ordinal($d), self::expandField($dayOfMonth, 1, 31));
            $parts[] = 'on the ' . self::joinList($days);
        }

        if ($month !== '*') {
            $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            $months = array_map(static fn(int $m): string => $monthNames[$m - 1], self::expandField($month, 1, 12));
            $parts[] = 'in ' . self::joinList($months);
        }

        return ucfirst(implode(', ', $parts));
    }

    /**
     * Expands a cron field into the sorted, unique list of integers it matches.
     *
     * @param string $field a single cron field
     * @param int $min the lowest legal value for the field
     * @param int $max the highest legal value for the field
     * @return int[] the matching values
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private static function expandField(string $field, int $min, int $max): array
    {
        $values = [];

        foreach (explode(',', $field) as $segment) {
            $segment = trim($segment);

            $step = null;
            if (str_contains($segment, '/')) {
                [$segment, $stepPart] = explode('/', $segment, 2);
                $step = (int)$stepPart;
            }

            if ($segment === '*') {
                $rangeMin = $min;
                $rangeMax = $max;
            } elseif (str_contains($segment, '-')) {
                [$rangeMin, $rangeMax] = explode('-', $segment, 2);
                $rangeMin = (int)$rangeMin;
                $rangeMax = (int)$rangeMax;
            } else {
                $values[] = (int)$segment;
                continue;
            }

            for ($i = $rangeMin; $i <= $rangeMax; $i++) {
                if ($step === null || ($i - $rangeMin) % $step === 0) {
                    $values[] = $i;
                }
            }
        }

        sort($values);
        return array_values(array_unique($values));
    }

    /**
     * Formats an hour and minute as a 12-hour clock time.
     *
     * @param int $hour the hour (0–23)
     * @param int $minute the minute (0–59)
     * @return string the formatted time, e.g. `3:05 PM`
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private static function formatTime(int $hour, int $minute): string
    {
        $period = $hour >= 12 ? 'PM' : 'AM';
        $h = $hour % 12 ?: 12;
        $m = str_pad((string)$minute, 2, '0', STR_PAD_LEFT);
        return "{$h}:{$m} {$period}";
    }

    /**
     * Joins a list into readable prose with an Oxford comma.
     *
     * @param string[] $items the items
     * @return string the joined list
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private static function joinList(array $items): string
    {
        if (count($items) <= 2) {
            return implode(' and ', $items);
        }

        $last = array_pop($items);
        return implode(', ', $items) . ', and ' . $last;
    }

    /**
     * Returns an integer with its ordinal suffix.
     *
     * @param int $n the integer
     * @return string the ordinal, e.g. `21st`
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 1.0.0
     */
    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n . $suffix;
    }
}
