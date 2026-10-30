# VATSIM Event Hub

An event hub for a single VATSIM vACC. Pilots find upcoming events, their airports, routes and briefings; controllers book shifts or show interest in staffing; Event Staff plan positions, shifts and rosters.

The words used here and in the app (Occurrence, Shift Booking, Staffing Interest and so on) are defined in [GLOSSARY.md](GLOSSARY.md).

## Features

- **Events** with banners, airports, suggested routes, a slot link and a briefing link. Events can repeat weekly, every other week or monthly, and single dates (Occurrences) or whole events can be cancelled and restored.
- **Drafts and publishing**: a new event starts as a Draft, seen only by staff who can view or manage events in its FIR. Publishing it shows it to everyone and, with the Discord Integration's Event announcements switched on, announces it once in the events channel. If Discord can't be reached, that's logged and the announcement isn't retried.
- **Two ways to staff an event**, chosen per event:
  - *Pre-defined shifts*: staff lay out shifts per position on a timeline, and controllers make Shift Bookings first come, first served.
  - *Open interest*: controllers show Staffing Interest in the positions they want, with their availability, and staff build and publish a roster of Roster Entries from that.
- **Roles per FIR**: see [Roles](#roles).
- **Live network stats** from the VATSIM data feed on the home page.

Times are shown in UTC unless someone picks another time zone in their settings. Staff enter an event's times in a time zone of their choice, which also keeps a repeating event at the same local time across daylight saving changes.

## Roles

| Role | Can |
| --- | --- |
| Administrator | Everything. Always held across the whole hub, and the only role that can change the vACC's settings, FIRs, event types and role definitions, or give someone Administrator. |
| Event Staff | Create, edit, publish and cancel events, and plan their staffing. |
| vACC Staff | See every event and its staffing, including Drafts, without changing anything. |
| Controller | Book shifts, show Staffing Interest and be put on rosters. |
| Pilot | Not a role: anyone who has signed in without one. |

Every role except Administrator can be held across the hub or in a single FIR, where it only covers that FIR's events. Administrators can change what the built-in roles allow and create more roles. People who manage users in a FIR can only give roles there, and only ones that allow nothing beyond what they can do there themselves.

**Who can book shifts**: anyone holding the Controller role, or another role that allows staffing events. When *Give the Controller role automatically* is switched on under **Admin → Settings**, members rated S1 or above in the vACC's subdivision get it when they sign in and lose it when they leave or lose their rating. Visiting controllers can be given it by hand, across the hub or in one FIR, and the automatic rules never take a role given by hand away.

## Stack

Laravel 13 (PHP 8.3+) with Inertia v3, React 19, TypeScript, Tailwind CSS 4 and shadcn/ui. Roles and their permissions are the app's own; see [ADR 0002](docs/adr/0002-own-roles-instead-of-spatie-permission.md). SQLite works out of the box; MySQL and PostgreSQL work too.

## Getting started

```bash
composer run setup
```

This installs dependencies, creates `.env` from `.env.example`, generates the app key, runs the migrations and builds the frontend. Then:

1. The vACC's name, division, subdivision, website and logo are set by an Administrator under **Admin → Settings** once the hub is running. To lock any of these but the logo instead, so they can't be changed in the app, set them in `.env`:

   ```env
   VACC_NAME="Example vACC"
   VACC_DIVISION="Example Division"
   VACC_SUBDIVISION=SCA
   VACC_WEBSITE=https://example.org
   ```

   FIRs are added under **Admin → FIRs**. For the Scandinavian FIRs on a fresh database, run `php artisan db:seed --class=FirSeeder`.

   Keys and secrets for VATSIM sign-in are only ever set in `.env`. Integrations, such as Control Center and Discord, are set up under **Admin → Integrations**, or locked from `.env`.

2. Set up sign-in with [VATSIM Connect](https://auth.vatsim.net). Create an OAuth client there, or in the sandbox at `https://auth-dev.vatsim.net`, with the redirect URL `APP_URL/auth/vatsim/callback` (for example `http://127.0.0.1:8000/auth/vatsim/callback`), and add its details:

   ```env
   VATSIM_CONNECT_CLIENT_ID=your-client-id
   VATSIM_CONNECT_SECRET=your-client-secret
   # Optional: defaults to /auth/vatsim/callback on APP_URL.
   VATSIM_CONNECT_REDIRECT=
   # Optional: defaults to https://auth.vatsim.net. Use https://auth-dev.vatsim.net with a sandbox client.
   VATSIM_CONNECT_BASE_URL=
   ```

   The client must be allowed the `full_name`, `vatsim_details` and `email` scopes. People are matched by CID, and their name, email, rating and subdivision are refreshed each time they sign in.

   VATSIM Scandinavia can sign in through [Handover](https://github.com/Vatsim-Scandinavia/handover) instead. Ask for a Handover OAuth client with the redirect URL `APP_URL/auth/handover/callback`, and add:

   ```env
   AUTH_PROVIDER=handover
   HANDOVER_CLIENT_ID=your-client-id
   HANDOVER_SECRET=your-client-secret
   # Optional: defaults to /auth/handover/callback on APP_URL.
   HANDOVER_REDIRECT=
   # Optional: defaults to https://handover.vatsim-scandinavia.org.
   HANDOVER_BASE_URL=
   ```

   Handover vouches for the same CIDs, so existing accounts carry over when you switch. Ratings and subdivisions are still refreshed from the VATSIM API each day.

   To skip VATSIM Connect, turn on development sign-in instead. It only works with `APP_ENV=local`:

   ```env
   AUTH_DEV_LOGIN=true
   ```

   `/login` then lets you sign in as any user.

3. Add example events and users (an Administrator, Event Staff, controllers of several ratings and an observer):

   ```bash
   php artisan db:seed
   ```

4. Start the app, the Vite dev server and log tailing together:

   ```bash
   composer run dev
   ```

To make someone an Administrator once they have signed in:

```bash
php artisan users:make-admin <vatsim-cid>
```

## Development

| Task | Command |
| --- | --- |
| Run the tests | `composer test` |
| Format PHP | `vendor/bin/pint` |
| Lint and format TypeScript | `npm run lint` and `npm run format` |
| Type-check TypeScript | `npm run typecheck` |
| Build the frontend | `npm run build` |

Backend code lives in `app/`, with staffing rules in `app/Staffing`. Pages are in `resources/js/pages`, shared components in `resources/js/components`. CI runs the tests, type check, lint and formatting on every push and pull request to `dev`.

## Deploying

The hub ships as a Docker image, published as `ghcr.io/vatsim-scandinavia/events:dev` on every push to `dev`. [compose.yaml](compose.yaml) runs it with a scheduler, a queue worker and one storage volume for uploads, logs and SQLite; its comments cover the settings. Starting the container migrates the database, creates the built-in roles (`php artisan roles:sync`) and caches the configuration.

**Background jobs.** These run through Laravel's scheduler (`php artisan schedule:work` in the Compose setup, or `php artisan schedule:run` every minute from cron):

- `events:extend-repeats` (daily) adds new dates to events that repeat indefinitely.
- `users:sync-vatsim` (daily) keeps the automatic Controller role up to date between sign-ins. While that role is switched on, it refreshes the rating and subdivision of everyone it could affect (people in the subdivision, or holding the automatic role) from the VATSIM API. Everyone else is refreshed when they sign in. Set `VATSIM_SYNC=false` where people sign in with sandbox accounts, which the live API doesn't know.
- `model:prune` (daily) forgets Integration sync runs older than 90 days.
- The syncs of Integrations switched on under Admin → Integrations, such as Control Center's roster (hourly), positions (daily) and booking push (hourly sweep).

**Queue worker.** Integration syncs, "Sync now" and pushing bookings to Control Center are queued, so something must run `php artisan queue:work`. Without a worker they wait in the `jobs` table and never run. The Compose setup has a `queue` service for this. It runs `queue:work --max-time=3600`, so it stops after an hour and Docker starts it again. Run it as a long-lived process rather than from cron: jobs then run within seconds, and retries after Control Center outages keep their delays. If cron is all you have, `php artisan queue:work --stop-when-empty --max-time=50` every minute works, with up to a minute's delay. The image's code is loaded when the worker starts, so restart it (or replace its container) after deploying a new image.

**Discord.** Event announcements are a feature of the Discord Integration, switched on under Admin → Integrations with the webhook of the channel to announce in. Setting `DISCORD_EVENTS_WEBHOOK` locks that webhook. Installs that already had it set are switched on when they upgrade, so they keep announcing.

**Settings.** On a public server set at least `APP_ENV=production` (or `staging`), `APP_DEBUG=false`, an `https://` `APP_URL`, `LOG_STACK=daily`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`. Put the hub behind a reverse proxy that handles HTTPS and name it in `TRUSTED_PROXIES`. Requests for any host but `APP_URL`'s are refused. `.env.example` lists all of these.

**First Administrator.** Sign in once through VATSIM, then run `php artisan users:make-admin <vatsim-cid>` (with Compose: `docker compose exec app php artisan users:make-admin <vatsim-cid>`).

### Playground

For people to try the hub out, `ghcr.io/vatsim-scandinavia/events:playground` (built with `--target playground`) is the same app with sample events and dev sign-in, so anyone can sign in as Hub Admin, Event Staff, the sample controllers or a plain member without a VATSIM account.

- **Each start resets it to the sample data.** Restart the container for a clean slate. With `RESET_PLAYGROUND=false` it keeps what people did, and only seeds on its first start.
- **Run it with [compose.yaml](compose.yaml)**, with every `image:` line changed to `:playground`, the queue worker's included. It needs an `APP_KEY`, an `https://` `APP_URL` and `SESSION_SECURE_COOKIE=true`. Leave `APP_ENV`, `APP_DEBUG` and `AUTH_DEV_LOGIN` out of its `.env`: the image sets them, and dev sign-in only works with `APP_ENV=local`. Leave out `DISCORD_EVENTS_WEBHOOK` too, or test events get announced.
- **Anyone who reaches it can sign in as an Administrator,** and it doesn't refuse other hosts, so put a password in front of it at the reverse proxy. Never give it real data.
