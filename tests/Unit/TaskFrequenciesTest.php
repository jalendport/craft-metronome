<?php

test('frequency methods set the cron expression', function(string $method, array $arguments, string $expression): void {
    $task = metronomeTask();

    $task->{$method}(...$arguments);

    expect($task->getExpression())->toBe($expression);
})->with([
    'every minute' => ['everyMinute', [], '* * * * *'],
    'every five minutes' => ['everyFiveMinutes', [], '*/5 * * * *'],
    'every ten minutes' => ['everyTenMinutes', [], '*/10 * * * *'],
    'every fifteen minutes' => ['everyFifteenMinutes', [], '*/15 * * * *'],
    'every thirty minutes' => ['everyThirtyMinutes', [], '*/30 * * * *'],
    'hourly' => ['hourly', [], '0 * * * *'],
    'hourly at' => ['hourlyAt', [17], '17 * * * *'],
    'daily' => ['daily', [], '0 0 * * *'],
    'daily at' => ['dailyAt', ['13:45'], '45 13 * * *'],
    'twice daily' => ['twiceDaily', [2, 14], '0 2,14 * * *'],
    'weekly' => ['weekly', [], '0 0 * * 0'],
    'weekly on' => ['weeklyOn', [2, '8:30'], '30 8 * * 2'],
    'monthly' => ['monthly', [], '0 0 1 * *'],
    'monthly on' => ['monthlyOn', [15, '16:20'], '20 16 15 * *'],
    'quarterly' => ['quarterly', [], '0 0 1 1,4,7,10 *'],
    'yearly' => ['yearly', [], '0 0 1 1 *'],
    'raw cron' => ['cron', ['*/7 9-17 * * 1-5'], '*/7 9-17 * * 1-5'],
]);

test('days sets the day of week field on the cron expression', function(): void {
    expect(metronomeTask()->hourly()->days(1, 3, 5)->getExpression())->toBe('0 * * * 1,3,5');
});
