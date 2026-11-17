<?php

namespace Database\Factories;

use App\Auth\RoleAssignments;
use App\Auth\Roles;
use App\Enums\ControllerRating;
use App\Models\ControllerGrant;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
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
        ];
    }

    /**
     * A rated controller made a Controller by hand, Hub-wide or only in the given FIR, so able to
     * staff events.
     */
    public function controller(ControllerRating $rating = ControllerRating::S2, ?Fir $fir = null): static
    {
        return $this->rated($rating)->afterCreating(fn (User $user) => ControllerGrant::factory()->for($user)->state(['fir_id' => $fir?->id])->create());
    }

    /**
     * Someone with a controller rating who isn't a Controller (yet), so can't staff events.
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
        return $this->afterCreating(fn (User $user) => app(RoleAssignments::class)->give($user, $role, $fir));
    }
}
