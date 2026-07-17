<?php

require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';

use jalendport\base\testing\Factory;
use jalendport\metronome\models\Task;
use yii\caching\ArrayCache;

function metronomeTask(string $command = 'php -v'): Task
{
    return new Task('exec', $command);
}

function metronomeCallTask(callable $callback): Task
{
    return new Task('call', Closure::fromCallable($callback));
}

function metronomeCraftStub(string $env = 'production', string $timezone = 'UTC', ?ArrayCache $cache = null): ArrayCache
{
    $cache ??= Factory::cache();

    Craft::$app = new class($env, $timezone, $cache) {
        public function __construct(
            public string $env,
            private string $timezone,
            private ArrayCache $cache,
        ) {
        }

        public function getTimeZone(): string
        {
            return $this->timezone;
        }

        public function getCache(): ArrayCache
        {
            return $this->cache;
        }
    };

    return $cache;
}

afterEach(function(): void {
    Craft::$app = null;
    Craft::setAlias('@config', null);
});
