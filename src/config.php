<?php
/**
 * Metronome schedule template
 *
 * Don't edit this file — copy it to your project's config/ folder as
 * metronome.php, then define your schedule inside the callback below.
 *
 * Metronome runs on a single crontab entry. Add this line to your server's
 * crontab (running every minute is recommended; any cadence works), and let
 * Metronome decide what's actually due each time it fires:
 *
 *     * * * * * php /path/to/craft metronome/run >> /dev/null 2>&1
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\metronome\services\Schedule;

return static function(Schedule $schedule): void {
    // A Craft console command. Pass extra arguments as the second array.
    $schedule->command('utils/update-search-indexes')
        ->daily();

    // $schedule->command('resave/entries', ['--section=news'])
    //     ->hourly();

    // A raw shell command.
    // $schedule->exec('/usr/bin/backup.sh')
    //     ->dailyAt('02:30');

    // A PHP callable. Anything it echoes is captured as the task's output.
    // $schedule->call(function() {
    //     // ...
    // })->everyFiveMinutes();

    // A queue job — pass a job instance or a job class name.
    // $schedule->job(\my\plugin\jobs\SyncJob::class)
    //     ->hourly();

    // Frequencies:
    //   ->everyMinute()  ->everyFiveMinutes()  ->everyTenMinutes()
    //   ->everyFifteenMinutes()  ->everyThirtyMinutes()
    //   ->hourly()  ->hourlyAt(15)
    //   ->daily()  ->dailyAt('13:00')  ->twiceDaily(1, 13)
    //   ->weekly()  ->weeklyOn(1, '8:00')
    //   ->monthly()  ->monthlyOn(15, '15:00')
    //   ->quarterly()  ->yearly()
    //   ->cron('*/5 9-17 * * *')   // any raw expression

    // Day constraints:
    //   ->weekdays()  ->weekends()
    //   ->mondays()  ->tuesdays()  …  ->sundays()
    //   ->days(1, 3, 5)            // cron day-of-week values

    // Time and conditional constraints:
    //   ->between('9:00', '17:00')      ->unlessBetween('0:00', '6:00')
    //   ->when(fn() => /* ... */ true)  ->skip(fn() => /* ... */ false)
    //   ->timezone('America/New_York')
    //   ->environments('production', 'staging')

    // Concurrency:
    //   ->withoutOverlapping()      // skip if a previous run is still going
    //   ->withoutOverlapping(30)    // …treating a 30-minute-old lock as stale
    //   ->onOneServer()             // run on just one server per scheduled minute

    // Output and hooks:
    //   ->sendOutputTo('/path/to/log')     ->appendOutputTo('/path/to/log')
    //   ->before(fn() => /* ... */ null)   ->after(fn() => /* ... */ null)
    //   ->onSuccess(fn() => /* ... */ null)
    //   ->onFailure(fn(\Throwable $e) => /* ... */ null)

    // Naming (used for locks, force-running via --task, and listings):
    //   ->name('search-reindex')
    //   ->description('Rebuild the search indexes')
};
