<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Enums\Permission;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ControllerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null]);
    }

    /**
     * @return array<string, array{ControllerRating, ?string, bool, bool}>
     */
    public static function signIns(): array
    {
        return [
            'S1 in the subdivision' => [ControllerRating::S1, 'SCA', true, true],
            'C1 in the subdivision, sent in lower case' => [ControllerRating::C1, 'sca', true, true],
            'an observer in the subdivision' => [ControllerRating::OBS, 'SCA', true, false],
            'S2 in another subdivision' => [ControllerRating::S2, 'GER', true, false],
            'S2 in no subdivision' => [ControllerRating::S2, null, true, false],
            'S2 in the subdivision, automatic switched off' => [ControllerRating::S2, 'SCA', false, false],
        ];
    }

    #[DataProvider('signIns')]
    public function test_signing_in_gives_controller_to_rated_members_of_the_subdivision(ControllerRating $rating, ?string $subdivision, bool $automatic, bool $isController): void
    {
        $this->configure('SCA', $automatic);

        $this->signIn(1234567, $rating, $subdivision);

        $user = User::query()->where('vatsim_cid', 1234567)->sole();
        $this->assertSame($subdivision, $user->subdivision);
        $this->assertSame($isController, $this->holdsController($user, AssignmentSource::Automatic));
        $this->assertSame($isController, $user->hasPermission(Permission::BookPositions));
    }

    public function test_leaving_the_subdivision_or_losing_the_rating_takes_controller_away(): void
    {
        $this->configure('SCA', true);

        $this->signIn(1234567, ControllerRating::S2, 'SCA');
        $user = User::query()->where('vatsim_cid', 1234567)->sole();
        $this->assertTrue($this->holdsController($user, AssignmentSource::Automatic));

        $this->signIn(1234567, ControllerRating::S2, 'GER');
        $this->assertFalse($this->holdsController($user, AssignmentSource::Automatic));

        $this->signIn(1234567, ControllerRating::S2, 'SCA');
        $this->assertTrue($this->holdsController($user, AssignmentSource::Automatic));

        $this->signIn(1234567, ControllerRating::OBS, 'SCA');
        $this->assertFalse($this->holdsController($user, AssignmentSource::Automatic));
    }

    public function test_controller_given_by_hand_is_never_taken_away(): void
    {
        $this->configure('SCA', true);
        $visitor = User::factory()->controller(ControllerRating::S3)->create(['vatsim_cid' => 1234567, 'subdivision' => 'GER']);

        $this->signIn(1234567, ControllerRating::S3, 'GER');

        $this->assertTrue($this->holdsController($visitor, AssignmentSource::Manual));
        $this->assertSame(1, $visitor->roleAssignments()->count());
    }

    public function test_switching_it_on_gives_existing_members_controller_and_switching_off_hands_it_to_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        $outsider = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'GER']);

        $this->actingAs($admin)
            ->post('/admin/settings', ['name' => 'VATSIM Scandinavia', 'subdivision' => 'sca', 'auto_controller' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->holdsController($member, AssignmentSource::Automatic));
        $this->assertFalse($this->holdsController($outsider, AssignmentSource::Automatic));

        $this->actingAs($admin)
            ->post('/admin/settings', ['name' => 'VATSIM Scandinavia', 'subdivision' => 'SCA', 'auto_controller' => false])
            ->assertSessionHasNoErrors();

        // They keep it, but it's now an admin's to take away.
        $this->assertTrue($this->holdsController($member, AssignmentSource::Manual));
    }

    public function test_changing_the_subdivision_moves_controller_to_the_new_members(): void
    {
        $admin = User::factory()->admin()->create();
        $scandinavian = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        $german = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'GER']);
        $settings = fn (string $subdivision) => $this->actingAs($admin)
            ->post('/admin/settings', ['name' => 'Hub', 'subdivision' => $subdivision, 'auto_controller' => true])
            ->assertSessionHasNoErrors();

        $settings('SCA');
        $settings('GER');

        $this->assertFalse($this->holdsController($scandinavian, AssignmentSource::Automatic));
        $this->assertTrue($this->holdsController($german, AssignmentSource::Automatic));
    }

    public function test_automatic_controller_roles_cannot_be_removed_by_hand(): void
    {
        $this->configure('SCA', true);
        $admin = User::factory()->admin()->create();
        $this->signIn(1234567, ControllerRating::S2, 'SCA');
        $user = User::query()->where('vatsim_cid', 1234567)->sole();
        $assignment = $user->roleAssignments()->sole();

        $this->actingAs($admin)->get("/admin/users/{$user->id}")->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.role', Roles::CONTROLLER)
            ->where('assignments.0.automatic', true)
            ->where('assignments.0.canRemove', false)
        );

        $this->actingAs($admin)->delete("/admin/users/{$user->id}/roles/{$assignment->id}")->assertSessionHasErrors('roles');
        $this->assertModelExists($assignment);
    }

    public function test_a_rated_controller_without_the_controller_role_cannot_book(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $shift = $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $occurrence = $event->occurrences()->sole();

        $this->actingAs(User::factory()->rated(ControllerRating::C1)->create())
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertForbidden();

        $this->actingAs(User::factory()->controller()->create())
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $occurrence->bookings()->count());
    }

    private function configure(string $subdivision, bool $automatic): void
    {
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => $subdivision]);
        $identity->setControllerRoleAutomatic($automatic);
    }

    private function signIn(int $cid, ControllerRating $rating, ?string $subdivision): void
    {
        $vatsim = ['rating' => ['id' => $rating->value, 'short' => $rating->label()]];

        if ($subdivision !== null) {
            $vatsim['subdivision'] = ['id' => $subdivision, 'name' => 'Some subdivision'];
        }

        Socialite::fake('vatsim', (new SocialiteUser)
            ->setRaw(['cid' => (string) $cid, 'personal' => ['name_full' => 'Kim Controller', 'email' => 'kim@example.com'], 'vatsim' => $vatsim])
            ->map(['id' => (string) $cid, 'name' => 'Kim Controller', 'email' => 'kim@example.com']));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect();
        auth()->logout();
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
