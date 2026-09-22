<?php

use jalendport\metronome\models\Task;

function metronomeCommandString(Task $task): string
{
    return (fn(): string => $this->_buildCommandString())->call($task);
}

test('command tasks quote the PHP binary and craft script path', function(): void {
    Craft::setAlias('@root', '/Users/name/Library/Application Support/Herd/project');

    $shell = metronomeCommandString(new Task('command', 'resave/entries'));

    expect($shell)->toBe(
        escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg('/Users/name/Library/Application Support/Herd/project/craft')
        . ' resave/entries',
    );
});

test('command tasks append escaped arguments', function(): void {
    Craft::setAlias('@root', '/app');

    $shell = metronomeCommandString(new Task('command', 'resave/entries', ['--section=news', 'two words']));

    expect($shell)->toEndWith(" resave/entries '--section=news' 'two words'");
});
