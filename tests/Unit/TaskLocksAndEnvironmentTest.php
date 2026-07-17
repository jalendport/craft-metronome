<?php

test('environment gates use the Craft app env', function(): void {
    metronomeCraftStub('production');

    $now = new DateTime('2026-07-17 12:15:00', new DateTimeZone('UTC'));

    expect(metronomeTask()->everyMinute()->timezone('UTC')->environments('production')->isDue($now))->toBeTrue()
        ->and(metronomeTask()->everyMinute()->timezone('UTC')->environments('staging')->isDue($now))->toBeFalse();
});

test('withoutOverlapping skips when the overlap lock already exists', function(): void {
    $cache = metronomeCraftStub();
    $runs = 0;
    $task = metronomeCallTask(function() use (&$runs): void {
        $runs++;
    })->name('locked-task')->withoutOverlapping();

    $cache->add('metronome:lock:locked-task', time(), 3600);

    $task->run();

    expect($runs)->toBe(0)
        ->and($cache->get('metronome:lock:locked-task'))->not->toBeFalse();
});

test('withoutOverlapping releases the overlap lock after a run', function(): void {
    $cache = metronomeCraftStub();
    $runs = 0;
    $task = metronomeCallTask(function() use (&$runs): void {
        $runs++;
    })->name('releasing-task')->withoutOverlapping();

    $task->run();

    expect($runs)->toBe(1)
        ->and($cache->get('metronome:lock:releasing-task'))->toBeFalse();
});

test('withoutOverlapping releases the overlap lock after a failure', function(): void {
    $cache = metronomeCraftStub();
    $task = metronomeCallTask(function(): void {
        throw new RuntimeException('failed');
    })->name('failing-task')->withoutOverlapping();

    expect(fn() => $task->run())->toThrow(RuntimeException::class, 'failed')
        ->and($cache->get('metronome:lock:failing-task'))->toBeFalse();
});

test('onOneServer skips the second run in the same minute', function(): void {
    metronomeCraftStub();
    $runs = 0;
    $task = metronomeCallTask(function() use (&$runs): void {
        $runs++;
    })->name('single-server-task')->onOneServer();

    $task->run();
    $task->run();

    expect($runs)->toBe(1);
});
