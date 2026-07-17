# Metronome — manual smoke checklist

Pest covers the pure, Craft-free logic (`Cron::describe()`, `Task::getId()`, `Task::isDue()`, `Schedule::getTask()` matching). Everything below is Craft-coupled — the config loader, the console runner, cache locks, the queue, events, and the CP utility — and can only be verified against a real Craft install. Run through this before tagging a release, and note any gaps you hit.

Commands assume you're inside a Craft site with the plugin installed. On Jalen's Spark/Docker setup, prefix each `php craft …` with `docker compose exec php` (e.g. `docker compose exec php php craft metronome/list`).

## Install

- [ ] **Fresh install.** In a clean Craft 5 site, require the plugin from the local path or Packagist:
      `composer require jalendport/craft-metronome`
- [ ] **Plugin install.** `php craft plugin/install metronome` completes without error, and `php craft plugin/list` shows Metronome as installed.
- [ ] No settings screen appears for Metronome under **Settings → Plugins** (the plugin has `hasCpSettings = false`).

## Config loading

- [ ] Copy the template: `cp vendor/jalendport/craft-metronome/src/config.php config/metronome.php`.
- [ ] Edit `config/metronome.php` to register a handful of tasks — one of each type (see below).
- [ ] `php craft metronome/list` picks the file up and shows those tasks. Removing the file and re-running shows "No scheduled tasks are registered."

## `metronome/list`

- [ ] With tasks registered, `php craft metronome/list` prints:
  - [ ] a **Crontab** block with each task's cron expression and command;
  - [ ] a **Schedule** block with each task's human-readable schedule (from `Cron::describe()`), a **Next run** line, and a **Last run** line;
  - [ ] a "**N tasks registered.**" footer with the correct count.
- [ ] A task that has never run shows **Last run: never**.

## `metronome/run` — each task type

Register one task of each type, each with a frequency that makes it due right now (e.g. `->everyMinute()`), then run `php craft metronome/run` and confirm each executes:

- [ ] **`command`** — e.g. `$schedule->command('utils/update-search-indexes')`. Prints a ✓ line with a duration; the underlying Craft command actually ran.
- [ ] **`command` with args** — e.g. `$schedule->command('resave/entries', ['--section=news'])`. The arguments reach the command.
- [ ] **`exec`** — e.g. `$schedule->exec('echo hello')`. Runs; captured output is available (see output redirection below).
- [ ] **`call`** — a closure that writes to a file or logs a marker. The closure runs; anything it `echo`s is captured as output.
- [ ] **`job`** — e.g. `$schedule->job(\my\plugin\jobs\SyncJob::class)`. After the run, the job is **queued** (visible in **Utilities → Queue Manager** or `php craft queue/info`), not run inline.
- [ ] With nothing due, `php craft metronome/run` prints "No scheduled tasks are due." and exits `0`.
- [ ] Each successful run updates the task's **Last run** in `metronome/list` to the current time with a `success` status.

## `--task` force-run

- [ ] Give a task a name, e.g. `->name('search-reindex')`, on a schedule that is **not** currently due.
- [ ] `php craft metronome/run --task=search-reindex` runs it immediately regardless of schedule and prints its ✓ line.
- [ ] Force-running by command string also works: `php craft metronome/run --task="utils/update-search-indexes"`.
- [ ] An unknown task, e.g. `php craft metronome/run --task=nope`, prints "Task “nope” not found.", lists the available task names, and exits non-zero.

## Failure handling

- [ ] Register a task that fails, e.g. `$schedule->exec('exit 1')` or a command that errors, on an every-minute schedule.
- [ ] `php craft metronome/run` prints a ✗ line for it with the error message, and the command **exits with status 1** (`echo $?` after running confirms `1`). A second, healthy task in the same run still executes — one failure doesn't abort the rest.
- [ ] The failure is logged to **`storage/logs/metronome-*.log`** (`Failed: …` entry with the message).
- [ ] The task's **Last run** in `metronome/list` shows a `failed` status.

## Overlap prevention

- [ ] Register a slow task with `->withoutOverlapping()`, e.g. `$schedule->exec('sleep 90')->everyMinute()->withoutOverlapping()->name('slow')`.
- [ ] Start one run: `php craft metronome/run` (leave it running).
- [ ] In a second terminal, `php craft metronome/run --task=slow` while the first still holds the lock — the second run **skips** the task (no second `sleep` process; the lock key `metronome:lock:slow` exists in the cache).
- [ ] After the first run finishes, the lock is released and the task runs again on the next invocation.

## `metronome/clear-locks`

- [ ] While a `withoutOverlapping` lock is held (or after a run is killed mid-flight so a stale lock remains), `php craft metronome/clear-locks` reports "Cleared locks for N tasks." and the wedged task runs on the next `metronome/run`.

## `EVENT_DEFINE_SCHEDULE` from a module

- [ ] In a small test module, handle `Schedule::EVENT_DEFINE_SCHEDULE` in `init()` and register a task (e.g. `$event->schedule->call(fn() => Craft::info('tick'))->everyMinute()->name('module-task')`).
- [ ] `php craft metronome/list` shows `module-task` alongside any config-defined tasks.
- [ ] `php craft metronome/run` executes it, and `--task=module-task` force-runs it.

## Task events

- [ ] Register handlers for `Schedule::EVENT_BEFORE_TASK` and `Schedule::EVENT_AFTER_TASK` (e.g. log the task label from each).
- [ ] Running a due task fires **before** then **after**; the after-event carries a non-null `duration` and `success = true`.
- [ ] Running a failing task fires the after-event with `success = false` and a non-null `error`.

## Control panel utility

- [ ] Go to **Utilities → Metronome** in the CP.
- [ ] The table renders every registered task with its command, schedule, **next run**, and **last run** with a success/failure status.
- [ ] A task that has just run shows its updated last-run time and status; a never-run task shows "never".

## Uninstall

- [ ] `php craft plugin/uninstall metronome` completes cleanly with no errors.
- [ ] The **Metronome** utility disappears from the CP and the `metronome/*` console commands are gone.
- [ ] `config/metronome.php` is left untouched (it's yours, not the plugin's), and no orphaned Metronome tables or rows remain.
