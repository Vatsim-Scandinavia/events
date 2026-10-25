# Roles are our own model, not spatie/laravel-permission

Roles are held per FIR (see ADR 0001), which the package's per-user role tables can't express, so every permission check already ran through our own role assignments and the package only stored a copy of the `Permission` enum. We dropped it: a role is a plain model whose permissions are a list of `Permission` enum values, and the built-in roles are kept in place by `php artisan roles:sync` on every deploy rather than by migrations.

## Considered Options

- **Keeping the package for its role and permission tables only.** Rejected: two tables, a config file and a cache to clear by hand, all mirroring an enum that is already the source of truth.
- **Spatie's teams feature for FIR scoping.** Rejected: a team is fixed per request, while one person can hold different roles in several FIRs and across the Hub at once.

## Consequences

A new permission is a new `Permission` case; the next deploy's `roles:sync` gives it to Administrator. No migration is needed.
