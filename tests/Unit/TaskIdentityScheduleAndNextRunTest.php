<?php

use jalendport\metronome\services\Schedule;

test('task ids prefer explicit names', function(): void {
    expect(metronomeTask('php -v')->hourly()->name('named-task')->getId())->toBe('named-task');
});

test('task ids fall back to a stable sha1 of expression and command signature', function(): void {
    $task = metronomeTask('php -v')->hourly();

    expect($task->getId())->toBe(sha1('0 * * * *:php -v'));
});

test('schedule getTask matches explicit names before command strings', function(): void {
    Craft::setAlias('@config', sys_get_temp_dir() . '/craft-metronome-empty-config-' . uniqid());
    $schedule = new Schedule();
    $named = $schedule->command('same-command')->name('explicit-name');
    $command = $schedule->command('command-only');
    $shadowingName = $schedule->command('shadow-source')->name('fallback-command');

    expect($schedule->getTask('explicit-name'))->toBe($named)
        ->and($schedule->getTask('fallback-command'))->toBe($shadowingName)
        ->and($schedule->getTask('command-only'))->toBe($command)
        ->and($schedule->getTask('same-command'))->toBe($named)
        ->and($schedule->getTask('shadow-source'))->toBe($shadowingName)
        ->and($schedule->getTask('missing'))->toBeNull();
});

test('next run dates are returned in the task timezone', function(): void {
    $hourly = metronomeTask()->hourly()->timezone('UTC')->getNextRunDate();
    $daily = metronomeTask()->dailyAt('13:45')->timezone('UTC')->getNextRunDate();

    expect($hourly->getTimezone()->getName())->toBe('UTC')
        ->and($hourly->format('i'))->toBe('00')
        ->and($daily->getTimezone()->getName())->toBe('UTC')
        ->and($daily->format('H:i'))->toBe('13:45');
});
