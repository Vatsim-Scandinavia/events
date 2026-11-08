<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/profile')->assertRedirect('/auth/vatsim');
        $this->put('/profile', ['timezone' => 'Europe/Copenhagen'])->assertRedirect('/auth/vatsim');
    }

    public function test_the_page_shows_zulu_until_a_time_zone_is_picked(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('settings.timezone', 'UTC')
            ->has('timezones')
        );

        $this->actingAs(User::factory()->withTimezone('Europe/Copenhagen')->create())->get('/profile')
            ->assertInertia(fn (Assert $page) => $page->where('settings.timezone', 'Europe/Copenhagen'));
    }

    public function test_the_header_shows_rating_and_subdivision_instead_of_roles(): void
    {
        $user = User::factory()->staff()->vaccStaff()->rated(ControllerRating::C1)->create(['vatsim_cid' => 1000009, 'subdivision' => 'SCA']);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.cid', 1000009)
            ->where('auth.user.rating', 'C1')
            ->where('auth.user.subdivision', 'SCA')
            ->missing('auth.user.roles')
        );
    }

    public function test_the_profile_shows_vatsim_details_and_roles_grouped_by_where_they_are_held(): void
    {
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $polaris = Fir::factory()->create(['icao' => 'ENOR', 'name' => 'ENOR']);
        $user = User::factory()->rated(ControllerRating::S2)->create(['name' => 'Jane Doe', 'vatsim_cid' => 1000009, 'subdivision' => 'SCA']);
        $user->assignRole(Roles::VACC_STAFF);
        $user->assignRole(Roles::EVENT_STAFF, $polaris);
        $user->assignRole(Roles::EVENT_STAFF, $copenhagen);
        $user->assignRole(Roles::CONTROLLER, $copenhagen, AssignmentSource::Automatic);

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('details', ['name' => 'Jane Doe', 'cid' => 1000009, 'rating' => 'S2', 'subdivision' => 'SCA'])
            ->where('roleGroups', [
                ['scope' => 'Across the Hub', 'roles' => [['name' => 'vACC Staff', 'automatic' => false]]],
                ['scope' => 'EKDK Copenhagen', 'roles' => [['name' => 'Controller', 'automatic' => true], ['name' => 'Event Staff', 'automatic' => false]]],
                // A FIR named by its ICAO isn't named twice.
                ['scope' => 'ENOR', 'roles' => [['name' => 'Event Staff', 'automatic' => false]]],
            ])
        );
    }

    public function test_a_pilot_has_no_roles_to_list(): void
    {
        $this->actingAs(User::factory()->create(['subdivision' => null]))->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('details.rating', 'OBS')
            ->where('details.subdivision', null)
            ->where('roleGroups', [])
        );
    }

    public function test_the_old_settings_address_leads_to_the_profile_without_losing_a_save(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertStatus(307)->assertRedirect('/profile');
        $this->put('/settings', ['timezone' => 'Europe/Copenhagen'])->assertStatus(307)->assertRedirect('/profile');
    }

    public function test_a_user_can_pick_a_time_zone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/profile', ['timezone' => 'Europe/Copenhagen'])
            ->assertRedirect('/profile');

        $this->assertSame('Europe/Copenhagen', $user->fresh()->timezone);
    }

    public function test_picking_utc_goes_back_to_the_default(): void
    {
        $user = User::factory()->withTimezone('Europe/Copenhagen')->create();

        $this->actingAs($user)->put('/profile', ['timezone' => 'UTC'])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->timezone);
    }

    public function test_a_time_zone_must_come_from_the_list(): void
    {
        $user = User::factory()->withTimezone('Europe/Copenhagen')->create();

        $this->actingAs($user)->put('/profile', ['timezone' => 'Mars/Olympus_Mons'])
            ->assertSessionHasErrors(['timezone' => 'Pick a time zone from the list.']);

        $this->assertSame('Europe/Copenhagen', $user->fresh()->timezone);
    }
}
