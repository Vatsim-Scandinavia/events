# VATSIM Event Hub

An event hub for a single VATSIM vACC. Pilots find upcoming events, their airports, routes and briefings; controllers book shifts or show interest in staffing; Event Staff plan positions, shifts and rosters.

## Features

- **Events** with banners, airports, suggested routes and booking/briefing links. Events can repeat weekly, every other week or monthly, and single dates or whole events can be cancelled and restored.
- **Two ways to staff an event**, chosen per event:
  - *Pre-defined shifts*: staff lay out shifts per position on a timeline, and rated controllers book them first come, first served.
  - *Open interest*: controllers say which positions they want and when they're available, and staff build and publish a roster from that.
- **Roles and permissions**: Admin and Event Staff are built in, and admins can create more roles. Nobody can hand out more than they can do themselves.
- **Live network stats** from the VATSIM data feed on the home page.

All times are UTC.

## Stack

Laravel 13 (PHP 8.3+) with Inertia v3, React 19, TypeScript, Tailwind CSS 4 and shadcn/ui. Roles use spatie/laravel-permission. SQLite works out of the box; MySQL and PostgreSQL work too.

## Getting started

```bash
composer run setup
```

This installs dependencies, creates `.env` from `.env.example`, generates the app key, runs the migrations and builds the frontend. Then:

1. The vACC's name, division, FIRs, website and logo are set by an admin under **Admin → Settings** once the hub is running. To lock any of these details instead, so they can't be changed in the app, set them in `.env`:

   ```env
   VACC_NAME="Example vACC"
   VACC_DIVISION="Example Division"
   VACC_FIRS=EKDK,ESAA
   VACC_WEBSITE=https://example.org
   ```

   Keys and secrets for VATSIM and other services are only ever set in `.env`.

2. Set up sign-in with [VATSIM Connect](https://auth.vatsim.net). Create an OAuth client there, or in the sandbox at `https://auth-dev.vatsim.net` for local development, with the redirect URL `APP_URL/auth/vatsim/callback` (for example `http://localhost:8000/auth/vatsim/callback`), and add its details:

   ```env
   VATSIM_CONNECT_CLIENT_ID=your-client-id
   VATSIM_CONNECT_SECRET=your-client-secret
   # Optional: defaults to /auth/vatsim/callback on APP_URL.
   VATSIM_CONNECT_REDIRECT=
   # Optional: defaults to https://auth.vatsim.net. Use https://auth-dev.vatsim.net with a sandbox client.
   VATSIM_CONNECT_BASE_URL=
   ```

   The client must be allowed the `full_name`, `vatsim_details` and `email` scopes. People are matched by CID, and their name, email and controller rating are refreshed each time they sign in.

   To skip VATSIM Connect locally, turn on development sign-in instead, **on your own machine only**:

   ```env
   AUTH_DEV_LOGIN=true
   ```

   `/login` then lets you sign in as any user. It is always off when `APP_ENV=production`.

3. Add example events and users (an admin, Event Staff, controllers of several ratings and an observer):

   ```bash
   php artisan db:seed
   ```

4. Start the app, the Vite dev server, the queue worker and log tailing together:

   ```bash
   composer run dev
   ```

To make someone an admin once they have signed in:

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

Backend code lives in `app/`, with staffing rules in `app/Staffing`. Pages are in `resources/js/pages`, shared components in `resources/js/components`.

## Deploying

Run `php artisan schedule:run` every minute so repeating events keep getting new dates (`events:extend-repeats` runs daily). On a public server set at least `APP_ENV=production`, `APP_DEBUG=false`, an `https://` `APP_URL`, `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`, and leave `AUTH_DEV_LOGIN` off. `.env.example` lists these as well.
