<?php
/**
 * Metronome — Spark Craft Lab seed hook
 *
 * Metronome has no CP settings screen; its schedule is defined entirely by a
 * `config/metronome.php` file the site owner drops in (see src/config.php).
 * This hook writes that file straight into the lab instance's config
 * directory so every mint gets a representative, mixed schedule to inspect
 * at /lab-test and exercise with `metronome/run` — one task of each
 * registrable type, one of which is due every minute.
 *
 * @link https://github.com/jalendport/spark-craft-lab
 */

return function (): void {
    $configPath = Craft::getAlias('@config') . '/metronome.php';

    $config = <<<'PHP'
<?php

use jalendport\metronome\services\Schedule;

return [
    'schedule' => static function (Schedule $schedule): void {
        $schedule->call(static fn() => null)
            ->everyMinute()
            ->name('lab-heartbeat')
            ->description('Lab heartbeat (call)');

        $schedule->exec('echo "metronome lab tick"')
            ->everyMinute()
            ->name('lab-echo')
            ->description('Lab echo (exec)');

        $schedule->command('utils/update-search-indexes')
            ->daily()
            ->name('lab-daily-reindex')
            ->description('Lab daily reindex (command)');
    },
];

PHP;

    file_put_contents($configPath, $config);
};
