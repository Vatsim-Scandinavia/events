<?php

namespace Database\Factories;

use App\Auth\Roles;
use App\Enums\ControllerRating;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vatsim_cid' => fake()->unique()->numberBetween(800000, 1999999),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'rating' => ControllerRating::OBS,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A rated controller with the Controller role, so eligible to staff events.
     */
    public function controller(ControllerRating $rating = ControllerRating::S2): static
    {
        return $this->rated($rating)->withRole(Roles::CONTROLLER);
    }

    /**
     * Someone with a controller rating but no role (yet), who can't staff events.
     */
    public function rated(ControllerRating $rating = ControllerRating::S2): static
    {
        return $this->state(['rating' => $rating]);
    }

    /**
     * A member of Event Staff across the hub, or only in the given FIR, who is also a rated
     * controller.
     */
    public function staff(?Fir $fir = null): static
    {
        return $this->rated(ControllerRating::C1)->withRole(Roles::EVENT_STAFF, $fir);
    }

    /**
     * Someone in vACC Staff (a director or board member), across the hub or in one FIR.
     */
    public function vaccStaff(?Fir $fir = null): static
    {
        return $this->withRole(Roles::VACC_STAFF, $fir);
    }

    /**
     * An Administrator, who may do everything.
     */
    public function admin(): static
    {
        return $this->rated(ControllerRating::C1)->withRole(Roles::ADMINISTRATOR);
    }

    /**
     * Someone who sees event times in the given IANA time zone.
     */
    public function withTimezone(string $timezone): static
    {
        return $this->state(['timezone' => $timezone]);
    }

    /**
     * Someone holding the role across the hub, or only in the given FIR.
     */
    public function withRole(string $role, ?Fir $fir = null): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role, $fir));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
