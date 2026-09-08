<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AirportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinators_create_normalized_airports_without_a_city_and_can_look_them_up(): void
    {
        $user = $this->member();
        $this->actingAs($user)->postJson(route('airports.store'), ['icao' => ' ekch ', 'name' => 'Copenhagen Airport', 'country' => 'Denmark', 'id' => 12345])
            ->assertCreated()->assertJsonPath('airport.icao', 'EKCH')->assertJsonMissingPath('airport.city');
        $airport = Airport::firstOrFail();

        $this->assertDatabaseHas('airports', ['icao' => 'EKCH', 'name' => 'Copenhagen Airport', 'country' => 'Denmark']);
        $this->assertDatabaseMissing('airports', ['id' => 12345]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'airport', 'subject_id' => $airport->id, 'event' => 'created']);
        $this->getJson(route('airports.index', ['icao' => 'EKCH']))->assertJsonPath('airport.id', $airport->id)->assertJsonMissingPath('airport.city');
        $this->getJson(route('airports.index', ['icao' => 'ZZZZ']))->assertJsonPath('airport', null);
        $this->get(route('airports.index', ['search' => 'copenhagen']))->assertInertia(fn (Assert $page) => $page->has('airports.data', 1)->where('airports.data.0.icao', 'EKCH')->missing('airports.data.0.city'));
    }

    public function test_duplicate_airports_are_rejected_after_normalization(): void
    {
        $airport = Airport::factory()->create(['icao' => 'EKCH']);
        $this->actingAs($this->member())->postJson(route('airports.store'), ['icao' => ' ekch ', 'name' => 'Duplicate', 'country' => 'Country'])
            ->assertJsonValidationErrors(['icao' => 'This airport already exists. Look up its ICAO code to select it.']);

        $this->assertDatabaseCount('airports', 1);
        $this->assertSame($airport->name, $airport->fresh()->name);
    }

    #[TestWith(['EKC'])]
    #[TestWith(['EK1H'])]
    #[TestWith(['<svg'])]
    public function test_invalid_icao_codes_are_rejected(string $icao): void
    {
        $this->actingAs($this->member())->postJson(route('airports.store'), ['icao' => $icao, 'name' => 'Airport', 'country' => 'Country'])
            ->assertJsonValidationErrors(['icao' => 'The ICAO code must be exactly 4 letters (A-Z).']);
        $this->assertDatabaseCount('airports', 0);
    }

    public function test_airport_details_are_required_and_staff_cannot_create(): void
    {
        $this->actingAs($this->member())->postJson(route('airports.store'), [])->assertJsonValidationErrors(['icao', 'name', 'country']);
        $this->actingAs($this->member(RoleName::VaccStaff))->postJson(route('airports.store'), ['icao' => 'EKCH', 'name' => 'Airport', 'country' => 'Country'])->assertForbidden();
        $this->get(route('airports.index'))->assertInertia(fn (Assert $page) => $page->where('can_create', false));
        $this->assertDatabaseCount('airports', 0);
    }

    public function test_guests_and_unprivileged_users_cannot_access_the_directory(): void
    {
        $this->get(route('airports.index'))->assertRedirectToRoute('login');
        $this->postJson(route('airports.store'), [])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get(route('airports.index'))->assertForbidden();
        $this->postJson(route('airports.store'), [])->assertForbidden();
        $this->assertDatabaseCount('airports', 0);
    }

    private function member(RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, Team::factory()->create());

        return $user;
    }
}
