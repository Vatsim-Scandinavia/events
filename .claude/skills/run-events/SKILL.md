---
name: run-events
description: Run, start, serve, screenshot or click through the Event Hub (Laravel + Inertia React) in a headless browser on a fresh seeded database, sign in as any seeded user, and drive the event form, staffing planner, bookings, interest and rosters. Use to confirm a change works in the real app, not just in tests.
---

# Run the Event Hub

The app is served by PHP's built-in server on `http://127.0.0.1:8123`, using a throwaway SQLite database. It is driven by `driver.mjs`, which runs Playwright in headless Chromium. Paths below are relative to the repo root. Everything the skill installs or writes goes under `~/.cache/run-events/`: Playwright, unpacked system libraries, `dev.sqlite`, `serve.log` and `shots/`. The repo's `package.json` and `database/database.sqlite` are never touched.

## Prerequisites (once per machine)

```bash
.claude/skills/run-events/setup.sh
```

This installs Playwright 1.49.1 and Chromium under `~/.cache/run-events`. There is no sudo here, so any `.so` Chromium can't find (on Ubuntu: libnss3, libnspr4, libasound2t64) is fetched with `apt-get download` and unpacked with `dpkg -x` into `~/.cache/run-events/libs`. The driver points Chromium at them itself. PHP 8.5, Composer dependencies (`vendor/`) and `node_modules` must already be installed.

## Serve

```bash
.claude/skills/run-events/serve.sh        # fresh: new DB, migrate:fresh, db:seed (runs roles:sync), serve
.claude/skills/run-events/serve.sh keep   # restart on the same DB; needed after any PHP change
.claude/skills/run-events/serve.sh stop
```

A fresh start takes about 30s. Before serving, the script runs `npm run build` if anything in `resources/` or `vite.config.js` is newer than `public/build`. It also sets `AUTH_DEV_LOGIN=true` and `DEBUGBAR_ENABLED=false` for the server only, without editing `.env`.

## Drive (agent path)

```bash
node .claude/skills/run-events/driver.mjs shot /events --as "Hub Admin"
node .claude/skills/run-events/driver.mjs run .claude/skills/run-events/flows/staffing.mjs
```

- `shot <path>` screenshots one page to `~/.cache/run-events/shots/<slug>.png`.
- `--out <name>` chooses the file name.
- `--as` signs in first.
- After every run the driver prints console errors, page errors and 5xx responses, or `no problems`, and exits 1 if there were any. **Read the screenshot** after a run.

For anything interactive, write a flow file. A flow default-exports `async (app) => {}`, and `app` has:

| | |
|---|---|
| `app.page` | Playwright page. It is replaced on every `signIn`, so always read it as `app.page` |
| `app.go(path)` | Navigate and wait until Inertia settles |
| `app.signIn(name)` | Fresh browser context, then the dev login as that seeded user |
| `app.shot(name)` | Full-page screenshot into `shots/` |
| `app.dragTo(source, dest, {x, y})` | HTML5 drag-and-drop, dropped at a point given as fractions of `dest`'s size |
| `app.check(label, value, ok)` | Prints `ok`/`FAIL`; a `FAIL` makes the run exit 1 |

`flows/staffing.mjs` is the worked example, and it passes on a fresh database. As Hub Admin it publishes events 4 and 2, then shortens a shift with Shift+← and saves it. As Tower Controller it books a shift and shows interest. As Hub Admin it drags that controller onto the roster and publishes the roster. Finally, as Tower Controller, it checks the roster appears on the event page. Copy it to start a new flow. Flows that publish or book need `serve.sh` (fresh) first.

**Seeded users** (dev login names): Hub Admin, Event Staff, Copenhagen Event Staff, vACC Director, Tower Controller (S2), Approach Controller (S3), Centre Controller (C1), Test User.

**Seeded events:** 7 events, **all Drafts**, so guests and controllers see none until a flow publishes one.
- `/events/4`, Copenhagen Fly-in: pre-defined shifts.
- `/events/2`, Scandinavian Night Ops: open interest. The window opens 21 days before the event, so it is open now.
- The staffing planner for an event is at `/events/{id}/staffing`.

## Direct invocation

```bash
DB_DATABASE=$HOME/.cache/run-events/dev.sqlite php artisan tinker --execute 'echo App\Models\EventOccurrence::query()->whereNotNull("roster_published_at")->count(), PHP_EOL;'
php artisan test --compact     # 271 tests, about 2 minutes on /mnt/c
```

## Run (human path)

`composer run dev` serves on the port in `.env` with Vite hot reload. Sign in at `/login` once `AUTH_DEV_LOGIN=true` is set in `.env`. This is of no use headless.

## Gotchas

- **The app is slow on `/mnt/c` (WSL).** `php artisan serve` runs without opcache and re-reads the framework from the Windows drive on every request: about 6s per request, and 36s for the first. `serve.sh` runs the same `server.php` with `opcache.enable_cli=1 opcache.validate_timestamps=0`, which brings requests down to about 0.35s. The trade-off is that **PHP edits aren't picked up until `serve.sh keep`.** A rebuilt frontend is picked up straight away.
- **Sign-in fails silently if you navigate too soon.** The dev login POST is slow on a cold server, and navigating before its redirect lands leaves you browsing as a guest. `signIn` waits for the URL to leave `/login`.
- **Switching users needs a fresh context.** `/login` is guests-only, so `signIn` opens a new browser context. Locators made before the switch belong to the closed page and fail with "Target page, context or browser has been closed".
- **The roster board uses HTML5 drag-and-drop.** Playwright's `dragTo` doesn't trigger it, so use `app.dragTo`, which dispatches `dragstart`/`dragover`/`drop` with a real `DataTransfer` and pointer coordinates. The drop time comes from `x`: 0.02 means the start of the timeline.
- **Rostering needs interest first.** The roster board only lists controllers who have shown interest. Sign in as the controller and do that first.
- **The planner timeline uses pointer events, not HTML5 drag.** Draw shifts with `page.mouse.down/move/up` along a `[data-timeline-row]`, and use focus plus arrow keys on `[data-timeline-item]`. Its `aria-label` reads like `Open, 16:00–18:00z`, which is useful for assertions. Every seeded row is already full of shifts, so shorten one before drawing a new one, or the drag is refused as overlapping.
- **A stale `public/hot` breaks every page.** Vite leaves `public/hot` behind when killed, and pages then try to load assets from a dead `:5173`. `serve.sh` removes the file when nothing answers there.
- **The save bar covers part of full-page screenshots.** The planner's sticky "All changes saved" bar sits over the first timeline rows in `fullPage` shots. It's a screenshot artefact, not a layout bug.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `error while loading shared libraries: libnss3.so` | Rerun `setup.sh`. If you launch Chromium yourself instead of through the driver, add `~/.cache/run-events/libs/root/usr/lib/x86_64-linux-gnu` to `LD_LIBRARY_PATH` |
| `Host system is missing dependencies` stack trace from `playwright install` | Harmless; `setup.sh` sets `PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS=1` |
| `page.goto: Timeout 30000ms` on the first request | Server was started without opcache (e.g. `php artisan serve`); use `serve.sh` |
| `Failed opening required '.../index.php'` from `php -S` | `server.php` resolves `public/` from the working directory; start it from `public/` as `serve.sh` does |
| Screenshot shows the guest header and "No events are scheduled" | Not signed in (see the sign-in gotcha), or the events are still Drafts |
| `locator.dispatchEvent: Timeout` on `li[draggable=true]` | No controller has shown interest in that event yet |
