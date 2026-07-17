<p align="center"><img src="src/icon.svg" alt="Metronome" width="80" height="80"></p>

<h1 align="center">Metronome</h1>

<p align="center"><em>A Laravel-style task scheduler for Craft CMS. One crontab entry, everything else in PHP.</em></p>

Stop scattering cron lines across your server. Metronome gives you a single crontab entry that fires every minute — everything else lives in a fluent PHP schedule you keep in version control, right next to the rest of your project config.

## Features

- **Fluent PHP schedules.** Define when everything runs in one readable file: `->dailyAt('03:00')->weekdays()->withoutOverlapping()`.
- **Every kind of task.** Schedule Craft console commands, raw shell commands, PHP closures, and queue jobs — all with the same builder.
- **Overlap prevention and single-server locks.** `withoutOverlapping()` skips a run while the last one is still going; `onOneServer()` makes sure a task fires once across a load-balanced fleet. Both use Craft's cache, no files on disk.
- **Environment gating.** `->environments('production')` keeps a task off staging and local without a single `if` block.
- **Before/after events.** Hook `EVENT_BEFORE_TASK` and `EVENT_AFTER_TASK` for integrations like Sentry Crons check-ins — you get the task, its duration, and its success or error.
- **Control panel utility.** A read-only Utilities screen shows every task, its human-readable schedule, and its next and last run.
- **Console commands.** `metronome/run` executes what's due, `metronome/list` shows the whole schedule, and `metronome/clear-locks` frees a wedged lock.

## Installation

### Requirements

This plugin requires **Craft CMS 5.0.0 or later** and **PHP 8.2 or later**.

### Plugin Store

Log into your control panel and click on "Plugin Store". Search for "Metronome", then click "Install".

### Composer

Open your terminal, go to your Craft project, and run:

```bash
composer require jalendport/craft-metronome && php craft plugin/install metronome
```

## Usage

### The single crontab entry

Metronome runs off one cron line. Add this to your server's crontab and let Metronome decide what's actually due each time it fires:

```bash
* * * * * php /path/to/craft metronome/run >> /dev/null 2>&1
```

Running every minute is recommended — it's the finest resolution the scheduler can act on — but any cadence works. Metronome only runs the tasks whose schedule matches the moment it wakes up.

### Defining tasks

Your schedule lives in `config/metronome.php` — a standard plugin config file whose `schedule` key holds a closure receiving the `Schedule` registry. Call one of the four registration methods to add a task, then chain the fluent methods below to shape when and how it runs.

```php
<?php

use jalendport\metronome\services\Schedule;

return [
    'schedule' => static function(Schedule $schedule): void {
        // A Craft console command. Pass extra arguments as the second array.
        $schedule->command('utils/update-search-indexes')
            ->daily();

        $schedule->command('resave/entries', ['--section=news'])
            ->hourly();

        // A raw shell command.
        $schedule->exec('/usr/bin/backup.sh')
            ->dailyAt('02:30');

        // A PHP callable. Anything it echoes is captured as the task's output.
        $schedule->call(function() {
            // ...
        })->everyFiveMinutes();

        // A queue job — pass a job instance or a job class name.
        $schedule->job(\my\plugin\jobs\SyncJob::class)
            ->hourly();
    },
];
```

| Task type | Method | Runs |
| --- | --- | --- |
| Console command | `command(string $command, array $args = [])` | A Craft console command, shelled out as `php craft <command>`. A non-zero exit is treated as a failure. |
| Shell command | `exec(string $command)` | Any shell command. A non-zero exit is treated as a failure. |
| Closure | `call(callable $callback, array $args = [])` | A PHP callable. Arguments are spread into it positionally; anything it echoes becomes the task's output. |
| Queue job | `job(JobInterface|string $job)` | Pushes a job onto Craft's queue — pass an instance or a class name. |

### Fluent API reference

Every method below returns the task, so you can chain as many as you need.

#### Frequencies

| Method | Runs |
| --- | --- |
| `cron('*/5 9-17 * * *')` | On any raw five-field cron expression. |
| `everyMinute()` | Every minute. |
| `everyFiveMinutes()` | Every 5 minutes. |
| `everyTenMinutes()` | Every 10 minutes. |
| `everyFifteenMinutes()` | Every 15 minutes. |
| `everyThirtyMinutes()` | Every 30 minutes. |
| `hourly()` | At the top of every hour. |
| `hourlyAt(15)` | Every hour, at 15 minutes past. |
| `daily()` | Every day at midnight. |
| `dailyAt('13:00')` | Every day at the given time. |
| `twiceDaily(1, 13)` | Every day at the two given hours. |
| `weekly()` | Every Sunday at midnight. |
| `weeklyOn(1, '8:00')` | Weekly on the given day (0 = Sunday) and time. |
| `monthly()` | On the first of every month at midnight. |
| `monthlyOn(15, '15:00')` | Monthly on the given day and time. |
| `quarterly()` | On the first of January, April, July, and October. |
| `yearly()` | On the first of January. |

#### Day constraints

| Method | Limits the task to |
| --- | --- |
| `weekdays()` | Monday through Friday. |
| `weekends()` | Saturday and Sunday. |
| `mondays()` … `sundays()` | A single named weekday. |
| `days(1, 3, 5)` | The given cron day-of-week values (0–7, where 0 and 7 are both Sunday). |

#### Time and conditional constraints

| Method | Effect |
| --- | --- |
| `between('9:00', '17:00')` | Only runs between the two times of day. |
| `unlessBetween('0:00', '6:00')` | Never runs between the two times of day. |
| `when(fn() => …)` | Only runs when the callback returns `true`. |
| `skip(fn() => …)` | Skips the run when the callback returns `true`. |
| `timezone('America/New_York')` | Evaluates the schedule in the given timezone (defaults to your app timezone). |
| `environments('production', 'staging')` | Only runs when `Craft::$app->env` matches. |

#### Concurrency

| Method | Effect |
| --- | --- |
| `withoutOverlapping()` | Skips the run if a previous run is still holding the lock. |
| `withoutOverlapping(30)` | Same, treating a lock older than 30 minutes as stale. |
| `onOneServer()` | Runs on just one server per scheduled minute. |

#### Output

| Method | Effect |
| --- | --- |
| `sendOutputTo('/path/to/log')` | Writes the task's captured output to a file, overwriting it each run. |
| `appendOutputTo('/path/to/log')` | Appends the task's captured output to a file. |

#### Hooks

| Method | Runs |
| --- | --- |
| `before(fn() => …)` | Immediately before the task executes. |
| `after(fn() => …)` | After the task, whether it succeeded or failed. |
| `onSuccess(fn() => …)` | Only when the task succeeds. |
| `onFailure(fn(\Throwable $e) => …)` | Only when the task fails, before the error propagates. |

#### Naming

| Method | Effect |
| --- | --- |
| `name('search-reindex')` | Sets a stable name — used for locks, force-running with `--task`, and listings. |
| `description('Rebuild the search indexes')` | Sets a human-readable label shown in listings and the utility. |

### Console commands

**`metronome/run`** — runs every task that's due right now. This is the command your crontab calls. Each task prints a ✓ or ✗ line with its duration, and the command exits with status `1` if any task failed.

```
$ php craft metronome/run
  ✓ utils/update-search-indexes (1,240ms)
  ✗ resave/entries: Command exited with status 1: …
```

**`metronome/run --task="…"`** — force-runs a single task immediately, ignoring its schedule. Match by name or by command string. Handy for testing a task without waiting for its window.

```
$ php craft metronome/run --task=search-reindex
  ✓ Rebuild the search indexes (1,240ms)
```

**`metronome/list`** — shows the whole schedule: a crontab-style view, then each task's human-readable schedule with its next and last run.

```
$ php craft metronome/list

  Crontab
  ------------------------------------------------------------------------
  0 0 * * *            utils/update-search-indexes
  */5 * * * *          Closure

  Schedule
  ------------------------------------------------------------------------
  utils/update-search-indexes
    At 12:00 AM
    Next run: 2026-07-18 00:00
    Last run: 2026-07-17 00:00 (success)

  2 tasks registered.
```

**`metronome/clear-locks`** — deletes the overlap and single-server locks held by your registered tasks. Reach for it when a task is wedged behind a lock left by a crashed run.

```
$ php craft metronome/clear-locks
Cleared locks for 2 tasks.
```

### Control panel utility

Metronome adds a **Metronome** screen under **Utilities**. It's a read-only table of every registered task — its command, schedule, next run, and last run with a success or failure status — so you can confirm your schedule at a glance without opening a terminal.

## Configuration

### The config file

Metronome has no control panel settings; your entire configuration is the schedule file. Copy the plugin's template to your project's `config/` folder to get started:

```bash
cp vendor/jalendport/craft-metronome/src/config.php config/metronome.php
```

The template is fully commented with an example of every task type and fluent chain. Because it's a standard plugin config file, it's [multi-environment aware](https://craftcms.com/docs/5.x/configure.html#multi-environment-configs) — nest the `schedule` key under environment keys to run a different schedule per environment, or keep one schedule and gate individual tasks with `environments()`.

### Registering tasks from a plugin or module

Config isn't the only place tasks come from. Any plugin or module can add its own tasks by handling `EVENT_DEFINE_SCHEDULE`, which fires once when the schedule is first loaded. This is the right home for tasks that ship *with* a plugin rather than living in a site's config.

```php
use Craft;
use jalendport\metronome\events\DefineScheduleEvent;
use jalendport\metronome\services\Schedule;
use yii\base\Event;

Event::on(
    Schedule::class,
    Schedule::EVENT_DEFINE_SCHEDULE,
    function(DefineScheduleEvent $event) {
        $event->schedule
            ->job(\my\plugin\jobs\SyncJob::class)
            ->hourly()
            ->name('my-plugin-sync');
    },
);
```

Put this in your plugin or module's `init()` method. Tasks registered this way behave exactly like ones defined in config, and show up in `metronome/list` and the utility alongside them.

### Task events

Metronome fires `EVENT_BEFORE_TASK` before each task runs and `EVENT_AFTER_TASK` after — on both success and failure. The event carries the `Task`, and on the after-event its `duration`, `success`, and `error`. Together they're everything you need to report to an external monitor.

Here's a [Sentry Crons](https://docs.sentry.io/product/crons/) check-in, using the task's ID as the monitor slug:

```php
use Craft;
use jalendport\metronome\events\TaskEvent;
use jalendport\metronome\services\Schedule;
use yii\base\Event;

Event::on(
    Schedule::class,
    Schedule::EVENT_BEFORE_TASK,
    function(TaskEvent $event) {
        $checkInId = \Sentry\captureCheckIn(
            slug: $event->task->getId(),
            status: \Sentry\CheckInStatus::inProgress(),
        );

        // Stash the check-in ID so the after-event can close it out.
        Craft::$app->getCache()->set('sentry:checkin:' . $event->task->getId(), $checkInId, 3600);
    },
);

Event::on(
    Schedule::class,
    Schedule::EVENT_AFTER_TASK,
    function(TaskEvent $event) {
        \Sentry\captureCheckIn(
            slug: $event->task->getId(),
            status: $event->success ? \Sentry\CheckInStatus::ok() : \Sentry\CheckInStatus::error(),
            checkInId: Craft::$app->getCache()->get('sentry:checkin:' . $event->task->getId()) ?: null,
            duration: $event->duration,
        );
    },
);
```

Because a failed console or shell command exits non-zero and propagates out of the task, `EVENT_AFTER_TASK` reports it as a failure rather than a silent success — so your monitor sees the error, not a missed check-in.

### A note on output redirection

`sendOutputTo()` and `appendOutputTo()` write to the path you give them with plain file I/O. Everything else Metronome tracks — its locks and last-run snapshots — lives in Craft's cache, never on disk. If you use the output methods on an ephemeral host (where the filesystem is wiped between deploys or requests), point them at a persistent, writable location or those files won't survive.

## Support

Found a bug or need help? Open an [issue](https://github.com/jalendport/craft-metronome/issues).

<hr>

<p align="center">Made by <a href="https://jalendport.com">Jalen Davenport</a></p>
