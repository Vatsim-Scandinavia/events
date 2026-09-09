# Events

Laravel 13 application using the official React starter kit, with React 19,
Inertia 3, TypeScript, Tailwind CSS 4, and shadcn/ui. Frontend dependencies are
managed with pnpm.

## Requirements

- PHP 8.4 or later, with Laravel's required extensions and PDO SQLite
- Composer 2
- Node.js 24 or later
- pnpm 11.25.0 (pinned in `package.json`)

## Local setup

From the project directory, run:

```sh
composer setup
composer run dev
```

`composer setup` installs the locked PHP and frontend dependencies, creates
`.env` when missing, generates an application key only if none is configured,
creates the SQLite database, runs migrations and seeders, and builds the
frontend. Rerunning setup preserves the existing key and encrypted data.

Open [http://localhost:8000](http://localhost:8000). The development command runs
the Laravel server, queue listener, and Vite together using the project's locked
`concurrently` dependency. Press Ctrl+C to stop development processes.

SQLite is configured by default at `database/database.sqlite`. Email is written
to `storage/logs/laravel.log` with the local `log` mail driver.

## Sign-in and account settings

Sign-in uses VATSIM Connect or the optional VATSCA Handover provider. Accounts
are created or refreshed from the provider's verified VATSIM CID on sign-in.
Profile details are read-only and refreshed at each sign-in; users can also
choose light, dark, or system appearance in settings. Local registration,
password login and resets, email verification, two-factor authentication, and
passkey routes are disabled; authentication is managed by the selected provider.

For local VATSIM Connect sign-in, configure `.env` with `VATSIM_ENABLED=true`,
`VATSIM_CLIENT_ID`, and `VATSIM_CLIENT_SECRET`. Use sandbox credentials with
`VATSIM_BASE_URL=https://auth-dev.vatsim.net` during development. Set `APP_URL`
to your development origin and register the exact `VATSIM_REDIRECT_URI` with
the provider; the default is `${APP_URL}/auth/vatsim/callback`.

To enable Handover, set `HANDOVER_ENABLED=true`, `HANDOVER_CLIENT_ID`, and
`HANDOVER_CLIENT_SECRET`. Set `HANDOVER_BASE_URL` to the provider instance and
register the exact `HANDOVER_REDIRECT_URI`, which defaults to
`${APP_URL}/auth/handover/callback`. A sign-in button appears only when its
provider is enabled and its credentials, base URL, and redirect URI are set.

## Frontend development

```sh
pnpm run dev
pnpm run build
pnpm run build:ssr
```

React pages live in `resources/js/pages`, layouts in `resources/js/layouts`, and
shadcn/ui components in `resources/js/components/ui`. Tailwind and theme tokens
are configured in `resources/css/app.css`. Import aliases use `@/` for
`resources/js`.

To add a shadcn/ui component:

```sh
pnpm dlx shadcn@latest add @shadcn/textarea
```

If pnpm's temporary CLI cache reports missing modules on Windows, use its
hoisted linker for that command:

```sh
pnpm --config.node-linker=hoisted --config.enable-global-virtual-store=false dlx shadcn@latest add @shadcn/textarea
```

The registry and paths are configured in `components.json`. Wayfinder generates
typed Laravel routes during Vite development and builds. To generate them
separately, run:

```sh
php artisan wayfinder:generate --with-form
```

## Checks

```sh
composer ci:check
```

This runs frontend formatting/lint checks, TypeScript checking, PHP formatting,
PHPStan, and the Laravel test suite. Run `pnpm run check:fix` or `composer lint`
to apply formatting fixes. Tests use an in-memory SQLite database.

GitHub Actions runs setup and these checks on pushes to `main` and `dev`, and
on pull requests. Commit both `composer.lock` and `pnpm-lock.yaml` for
reproducible dependency installation.
