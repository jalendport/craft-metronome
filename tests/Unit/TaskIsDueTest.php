<?php

test('it matches cron expressions against an injected date', function(): void {
    $now = new DateTime('2026-07-17 12:15:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->cron('15 12 * * *')->timezone('UTC')->isDue($now))->toBeTrue()
        ->and(metronomeTask()->cron('16 12 * * *')->timezone('UTC')->isDue($now))->toBeFalse();
});

test('it evaluates cron expressions in the task timezone', function(): void {
    $now = new DateTime('2026-07-17 04:30:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->dailyAt('00:30')->timezone('America/New_York')->isDue($now))->toBeTrue()
        ->and(metronomeTask()->dailyAt('04:30')->timezone('America/New_York')->isDue($now))->toBeFalse();
});

test('when filters must pass', function(): void {
    $now = new DateTime('2026-07-17 12:15:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->everyMinute()->timezone('UTC')->when(fn() => true)->isDue($now))->toBeTrue()
        ->and(metronomeTask()->everyMinute()->timezone('UTC')->when(fn() => false)->isDue($now))->toBeFalse();
});

test('skip filters reject due tasks', function(): void {
    $now = new DateTime('2026-07-17 12:15:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->everyMinute()->timezone('UTC')->skip(fn() => false)->isDue($now))->toBeTrue()
        ->and(metronomeTask()->everyMinute()->timezone('UTC')->skip(fn() => true)->isDue($now))->toBeFalse();
});

test('between filters allow all-day windows', function(): void {
    $now = new DateTime('now', new DateTimeZone('UTC'));

    expect(metronomeTask()->everyMinute()->timezone('UTC')->between('00:00', '23:59')->isDue($now))->toBeTrue();
});

test('unlessBetween filters reject all-day windows', function(): void {
    $now = new DateTime('now', new DateTimeZone('UTC'));

    expect(metronomeTask()->everyMinute()->timezone('UTC')->unlessBetween('00:00', '23:59')->isDue($now))->toBeFalse();
});

test('weekday and weekend filters follow the current UTC day', function(): void {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $isWeekend = in_array($now->format('w'), ['0', '6'], true);

    expect(metronomeTask()->everyMinute()->timezone('UTC')->weekends()->isDue($now))->toBe($isWeekend)
        ->and(metronomeTask()->everyMinute()->timezone('UTC')->weekdays()->isDue($now))->toBe(!$isWeekend);
});

test('named day filters follow the current UTC day', function(): void {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $methods = [
        '0' => 'sundays',
        '1' => 'mondays',
        '2' => 'tuesdays',
        '3' => 'wednesdays',
        '4' => 'thursdays',
        '5' => 'fridays',
        '6' => 'saturdays',
    ];
    $currentMethod = $methods[$now->format('w')];
    $otherMethod = $methods[(string)(((int)$now->format('w') + 1) % 7)];

    expect(metronomeTask()->everyMinute()->timezone('UTC')->{$currentMethod}()->isDue($now))->toBeTrue()
        ->and(metronomeTask()->everyMinute()->timezone('UTC')->{$otherMethod}()->isDue($now))->toBeFalse();
});

test('days sets cron weekday matching', function(): void {
    $friday = new DateTime('2026-07-17 12:00:00', new DateTimeZone('UTC'));
    $saturday = new DateTime('2026-07-18 12:00:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->hourly()->days(5)->timezone('UTC')->isDue($friday))->toBeTrue()
        ->and(metronomeTask()->hourly()->days(5)->timezone('UTC')->isDue($saturday))->toBeFalse();
});
