<?php

namespace Tests\Feature\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FirScopedUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Fir $copenhagen;

    private Fir $sweden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $this->sweden = Fir::factory()->create(['icao' => 'ESAA', 'name' => 'Sweden']);
    }

    /**
     * Anna leads EKDK: she manages its users, events and staffing, and nothing anywhere else.
     */
    private function anna(): User
    {
        $anna = User::factory()->create(['name' => 'Anna']);
        $anna->assignRole(
            Role::create(['name' => 'FIR Lead'])->givePermissionTo([Permission::ManageUsers->value, Permission::ManageEvents->value, Permission::ManageStaffing->value]),
            $this->copenhagen,
        );

        return $anna;
    }

    public function test_someone_who_manages_users_in_a_fir_can_find_everyone_including_newcomers(): void
    {
        $anna = $this->anna();
        User::factory()->create(['name' => 'Newcomer Nils', 'vatsim_cid' => 1234567]);
        User::factory()->staff($this->sweden)->create(['name' => 'Swedish Sara']);

        $this->actingAs($anna)->get('/admin/users?search=Newcomer')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Newcomer Nils')
        );

        $this->actingAs($anna)->get('/admin/users?search=1234567')->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
        $this->actingAs($anna)->get('/admin/users?search=Sara')->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
    }

    public function test_they_can_give_roles_in_their_fir_only(): void
    {
        $anna = $this->anna();
        $newcomer = User::factory()->create();
        $staff = Role::findByName(Roles::EVENT_STAFF);

        $this->actingAs($anna)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => $this->copenhagen->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->copenhagen->id, $newcomer->roleAssignments()->sole()->fir_id);
        $this->assertTrue($newcomer->fresh()->can('manage-events', $this->copenhagen));
        $this->assertFalse($newcomer->fresh()->can('manage-events', $this->sweden));

        $this->actingAs($anna)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => $this->sweden->id])
            ->assertSessionHasErrors(['roles' => 'You can only give or take away roles in FIRs where you manage users.']);
        $this->actingAs($anna)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => null])
            ->assertSessionHasErrors(['roles' => 'You can only give or take away roles across the hub if you manage users across the hub.']);

        $this->assertSame(1, $newcomer->roleAssignments()->count());
    }

    public function test_they_can_only_give_roles_that_allow_nothing_beyond_what_they_can_do_in_that_fir(): void
    {
        $lead = User::factory()->create();
        $lead->assignRole(Role::create(['name' => 'Events Lead'])->givePermissionTo([Permission::ManageUsers->value, Permission::ManageEvents->value]), $this->copenhagen);
        $newcomer = User::factory()->create();

        // Event Staff also manages staffing, which this lead can't do in EKDK.
        $this->actingAs($lead)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => Role::findByName(Roles::EVENT_STAFF)->id, 'fir_id' => $this->copenhagen->id])
            ->assertSessionHasErrors(['roles' => 'You can’t give or take away Event Staff, because it allows more than you can do yourself.']);

        $this->actingAs($lead)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => Role::findByName(Roles::CONTROLLER)->id, 'fir_id' => $this->copenhagen->id])
            ->assertSessionHasErrors('roles');

        $this->assertSame(0, $newcomer->roleAssignments()->count());
    }

    public function test_they_can_take_away_roles_in_their_fir_only(): void
    {
        $anna = $this->anna();
        $kim = User::factory()->create();
        $inCopenhagen = $kim->assignRole(Roles::EVENT_STAFF, $this->copenhagen);
        $inSweden = $kim->assignRole(Roles::EVENT_STAFF, $this->sweden);
        $acrossTheHub = $kim->assignRole(Roles::VACC_STAFF);

        $this->actingAs($anna)->delete("/admin/users/{$kim->id}/roles/{$inSweden->id}")->assertSessionHasErrors('roles');
        $this->actingAs($anna)->delete("/admin/users/{$kim->id}/roles/{$acrossTheHub->id}")->assertSessionHasErrors('roles');
        $this->assertModelExists($inSweden);
        $this->assertModelExists($acrossTheHub);

        $this->actingAs($anna)->delete("/admin/users/{$kim->id}/roles/{$inCopenhagen->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($inCopenhagen);
    }

    public function test_the_user_page_only_offers_what_they_may_change(): void
    {
        $anna = $this->anna();
        $kim = User::factory()->create();
        $kim->assignRole(Roles::VACC_STAFF);
        $kim->assignRole(Roles::EVENT_STAFF, $this->copenhagen);
        $kim->assignRole(Roles::EVENT_STAFF, $this->sweden);

        $this->actingAs($anna)->get("/admin/users/{$kim->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Edit')
            ->where('firs', [['id' => $this->copenhagen->id, 'icao' => 'EKDK', 'name' => 'Copenhagen']])
            ->where('assignments.0.role', Roles::VACC_STAFF)
            ->where('assignments.0.canRemove', false)
            ->where('assignments.1.fir.icao', 'EKDK')
            ->where('assignments.1.canRemove', true)
            ->where('assignments.2.fir.icao', 'ESAA')
            ->where('assignments.2.canRemove', false)
            ->where('roles', fn ($roles) => collect($roles)->mapWithKeys(fn ($role) => [$role['name'] => $role['grantableIn']])->all() === [
                Roles::ADMINISTRATOR => [],
                Roles::CONTROLLER => [],
                Roles::EVENT_STAFF => [$this->copenhagen->id],
                'FIR Lead' => [$this->copenhagen->id],
                Roles::VACC_STAFF => [],
            ])
        );
    }

    public function test_someone_who_manages_users_across_the_hub_can_give_roles_anywhere_their_permissions_reach(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'User Manager'])->givePermissionTo(Permission::ManageUsers->value));
        $manager->assignRole(Roles::EVENT_STAFF, $this->copenhagen);
        $newcomer = User::factory()->create();
        $staff = Role::findByName(Roles::EVENT_STAFF);

        // Managing events and staffing in EKDK, they can make someone Event Staff there, but not elsewhere.
        $this->actingAs($manager)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => $this->copenhagen->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => $this->sweden->id])
            ->assertSessionHasErrors('roles');
        $this->actingAs($manager)->post("/admin/users/{$newcomer->id}/roles", ['role_id' => $staff->id, 'fir_id' => null])
            ->assertSessionHasErrors('roles');

        $this->actingAs($manager)->get("/admin/users/{$newcomer->id}")->assertInertia(fn (Assert $page) => $page
            ->has('firs', 2)
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'User Manager')['grantableIn'] === [null, $this->copenhagen->id, $this->sweden->id])
        );
    }
}
