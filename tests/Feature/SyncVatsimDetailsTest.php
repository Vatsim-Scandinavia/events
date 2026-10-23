<?php

namespace Tests\Feature;

use App\Auth\ControllerRole;
use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Models\Role;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SyncVatsimDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null]);
        Sleep::fake();
    }

    public function test_members_rated_since_they_last_signed_in_get_controller(): void
    {
        $this->configure(automatic: true);
        $student = User::factory()->rated(ControllerRating::OBS)->create(['vatsim_cid' => 1000001, 'subdivision' => 'SCA']);
        User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000002, 'subdivision' => 'GER']);

        Http::fake(['api.vatsim.net/v2/members/1000001' => Http::response(['id' => 1000001, 'rating' => ControllerRating::S1->value, 'subdivision_id' => 'SCA'])]);

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        $this->assertSame(ControllerRating::S1, $student->fresh()->rating);
        $this->assertTrue($this->holdsController($student, AssignmentSource::Automatic));
        // Members of other subdivisions without the role aren't looked up.
        Http::assertSentCount(1);
    }

    public function test_members_who_left_the_subdivision_lose_controller(): void
    {
        $this->configure(automatic: true);
        $leaver = $this->automaticController(1000001);

        Http::fake(['api.vatsim.net/v2/members/1000001' => Http::response(['id' => 1000001, 'rating' => ControllerRating::S2->value, 'subdivision_id' => null])]);

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        $this->assertNull($leaver->fresh()->subdivision);
        $this->assertFalse($this->holdsController($leaver, AssignmentSource::Automatic));
    }

    public function test_members_whose_record_cannot_be_loaded_are_left_alone(): void
    {
        $this->configure(automatic: true);
        $unreachable = $this->automaticController(1000001);
        $leaver = $this->automaticController(1000002);

        Http::fake([
            'api.vatsim.net/v2/members/1000001' => Http::response(['detail' => 'Server error'], 500),
            'api.vatsim.net/v2/members/1000002' => Http::response(['id' => 1000002, 'rating' => ControllerRating::S2->value, 'subdivision_id' => 'GER']),
        ]);

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        $this->assertSame('SCA', $unreachable->fresh()->subdivision);
        $this->assertTrue($this->holdsController($unreachable, AssignmentSource::Automatic));
        // One failure doesn't stop the rest.
        $this->assertFalse($this->holdsController($leaver, AssignmentSource::Automatic));
    }

    public function test_nothing_is_fetched_while_the_automatic_role_is_switched_off(): void
    {
        $this->configure(automatic: false);
        User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        Http::fake();

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        Http::assertNothingSent();
    }

    private function configure(bool $automatic): void
    {
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => 'SCA']);
        $identity->setControllerRoleAutomatic($automatic);
    }

    private function automaticController(int $cid): User
    {
        $user = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => $cid, 'subdivision' => 'SCA']);
        app(ControllerRole::class)->sync($user);
        $this->assertTrue($this->holdsController($user, AssignmentSource::Automatic));

        return $user;
    }

    private function holdsController(User $user, AssignmentSource $source): bool
    {
        return $user->roleAssignments()
            ->where('role_id', Role::findByName(Roles::CONTROLLER)->id)
            ->whereNull('fir_id')
            ->where('source', $source)
            ->exists();
    }
}
