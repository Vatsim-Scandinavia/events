# Events

Laravel 13 with the official React starter kit: Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, and pnpm. Includes OAuth authentication, a dashboard, and account settings.

## Requirements

- PHP 8.4+ and Composer
- Node.js 22.12+ and pnpm 11.24.0
- SQLite PHP extension

## Setup

```sh
composer run setup
```

This installs dependencies, creates `.env`, generates the app key, runs SQLite migrations, and builds frontend assets. The initial workspace is already set up.

## Development

```sh
composer run dev
```

Open http://localhost:8000. This starts Laravel, the queue listener, logs, and Vite together.

Alternatively, run `php artisan serve` and `pnpm dev` in separate terminals.

## Checks

```sh
composer test
pnpm build
pnpm types:check
pnpm check
```

Routes live in `routes/web.php`; React pages live in `resources/js/pages`; Tailwind styles start in `resources/css/app.css`.

Dependencies are locked in `composer.lock` and `pnpm-lock.yaml`. Use pnpm for frontend dependencies.

## OAuth authentication

Set `OAUTH_PROVIDER=vatsim` for VATSIM Connect or `OAUTH_PROVIDER=handover` for Handover. Only the selected provider is available. Configure its client ID, client secret, and redirect URI in `.env` using the variables in `.env.example`. Handover also requires `HANDOVER_BASE_URL` (your deployment URL). VATSIM Connect defaults to `https://auth.vatsim.net`; `VATSIM_BASE_URL` can point to its sandbox.

Register `${APP_URL}/auth/callback` as the OAuth callback URL with the selected provider and allow the `full_name` and `email` scopes. Run `php artisan config:clear` after changing configuration. Keep credentials in `.env`, not source control.

Accounts are created on first sign-in and matched by VATSIM CID. Names and email addresses refresh from the provider at login. Existing accounts without a CID are not automatically linked by email. There are no local password, registration, passkey, two-factor, or email-verification flows; identity and authentication are managed by the provider. Profile settings show provider-managed identity; appearance settings remain editable.
