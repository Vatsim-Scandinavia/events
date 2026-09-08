<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(fn (User $user): ?bool => $user->isAdministrator() ? true : null);

        Gate::define(PermissionName::ManageRoles->value, fn (User $user): bool => false);
        Gate::define(PermissionName::ManageFirs->value, fn (User $user): bool => false);

        foreach ([PermissionName::ViewEvents, PermissionName::ManageEvents] as $permission) {
            Gate::define($permission->value, fn (User $user, ?Team $team = null): bool => $team !== null && $user->hasPermissionInTeam($permission, $team));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
