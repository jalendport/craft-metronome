<?php

use jalendport\metronome\helpers\Cron;

test('it describes cron expressions', function(string $expression, string $description): void {
    expect(Cron::describe($expression))->toBe($description);
})->with([
    'every minute' => ['* * * * *', 'Every minute'],
    'every n minutes' => ['*/5 * * * *', 'Every 5 minutes'],
    'hourly at top of hour' => ['0 * * * *', 'Hourly at :00'],
    'hourly at minute' => ['15 * * * *', 'Hourly at :15'],
    'daily at midnight' => ['0 0 * * *', 'At 12:00 AM'],
    'daily at time' => ['30 14 * * *', 'At 2:30 PM'],
    'day of week' => ['0 0 * * 1', 'At 12:00 AM, on Monday'],
    'alt sunday' => ['0 0 * * 7', 'At 12:00 AM, on Sunday'],
    'day of month' => ['0 0 1 * *', 'At 12:00 AM, on the 1st'],
    'month' => ['0 0 * 1 *', 'At 12:00 AM, in January'],
    'hour range and weekday range' => ['0 9-11 * * 1-3', 'At 9:00 AM, 10:00 AM, and 11:00 AM, on Monday, Tuesday, and Wednesday'],
    'minute step with fixed hour' => ['*/20 9 * * *', 'At 9:00 AM, 9:20 AM, and 9:40 AM'],
    'hour list' => ['0 9,17 * * *', 'At 9:00 AM and 5:00 PM'],
    'combined fields' => ['13 6 2 3 4', 'At 6:13 AM, on Thursday, on the 2nd, in March'],
]);

test('it rejects invalid cron expressions', function(): void {
    Cron::describe('* * *');
})->throws(InvalidArgumentException::class, 'Invalid cron expression: * * *');
