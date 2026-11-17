<?php

namespace Tests\Feature;

use App\Auth\Controllers;
use App\Auth\RoleAssignments;
use App\Auth\Where;
use App\Enums\ControllerGrantOrigin;
use App\Enums\ControllerRating;
use App\Enums\Permission;
use App\Models\ControllerGrant;
use App\Models\Event;
use App\Models\Fir;
use App\Models\IntegrationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\VaccIdentity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeIntegration;
use Tests\Fakes\FakeRosterSync;
use Tests\TestCase;

/**
 * Who is a Controller: Administrators, the Controller Rule (S1 or above in the subdivision,
 * worked out live) and Controller Grants, from the Controller Source or by hand.
 */
class ControllersTest extends TestCase
{
    use RefreshDatabase;

    private Fir $copenhagen;

    private Fir $sweden;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null, 'integrations.registered' => [FakeIntegration::class], 'integrations.settings' => []]);
        FakeRosterSync::$roster = [];
        FakeRosterSync::$sync = null;
        FakeRosterSync::$decided = true;

        $this->copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $this->sweden = Fir::factory()->create(['icao' => 'ESAA']);
    }

    /**
     * @return array<string, array{ControllerRating, ?string, bool, bool}>
     */
    public static function members(): array
    {
        return [
            'S1 in the subdivision' => [ControllerRating::S1, 'SCA', true, true],
            'C1 in the subdivision, any case' => [ControllerRating::C1, 'sca', true, true],
            'observer in the subdivision' => [ControllerRating::OBS, 'SCA', true, false],
            'S3 elsewhere' => [ControllerRating::S3, 'GER', true, false],
            'S3 in no subdivision' => [ControllerRating::S3, null, true, false],
            'S1 in the subdivision, rule switched off' => [ControllerRating::S1, 'SCA', false, false],
        ];
    }

    #[DataProvider('members')]
    public function test_the_controller_rule_makes_rated_members_of_the_subdivision_controllers_hub_wide(ControllerRating $rating, ?string $subdivision, bool $ruleOn, bool $isController): void
    {
        $this->useControllerRule('SCA', $ruleOn);
        $member = User::factory()->rated($rating)->create(['subdivision' => $subdivision]);

        $this->assertSame($isController, app(Controllers::class)->isController($member, Where::in($this->sweden)));
        $this->assertSame($isController, app(Controllers::class)->controllersFor($this->eventIn($this->sweden))->whereKey($member->id)->exists());
    }

    public function test_the_controller_rule_follows_member_details_the_moment_they_change(): void
    {
        $this->useControllerRule('SCA');
        $member = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        $controllers = app(Controllers::class);

        $this->assertTrue($controllers->ruleApplies($member));

        $member->update(['subdivision' => 'GER']);
        $this->assertFalse($controllers->ruleApplies($member));
    }

    public function test_the_controller_rule_needs_a_subdivision_and_steps_aside_for_a_controller_source(): void
    {
        $this->useControllerRule(null);
        $member = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        $this->assertFalse(app(Controllers::class)->ruleApplies($member));

        $this->useControllerRule('SCA');
        $this->switchSourceOn();
        $this->assertFalse(app(Controllers::class)->ruleApplies($member));
    }

    public function test_the_controller_rule_keeps_deciding_until_a_source_switched_on_has_decided(): void
    {
        $this->useControllerRule('SCA');
        $member = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        FakeRosterSync::$decided = false;
        $this->switchSourceOn();

        $this->assertTrue(app(Controllers::class)->ruleApplies($member));

        FakeRosterSync::$decided = true;
        $this->assertFalse(app(Controllers::class)->ruleApplies($member));
    }

    public function test_administrators_are_controllers_everywhere(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('staff', $this->eventIn($this->sweden)));
        $this->assertTrue(app(Controllers::class)->controllersFor($this->eventIn($this->sweden))->whereKey($admin->id)->exists());
    }

    public function test_a_grant_in_one_fir_only_covers_that_firs_events_and_a_hub_wide_grant_covers_every_fir(): void
    {
        $here = User::factory()->controller(fir: $this->copenhagen)->create();
        $hubWide = User::factory()->controller()->create();
        $copenhagenEvent = $this->eventIn($this->copenhagen);
        $swedishEvent = $this->eventIn($this->sweden);

        $this->assertTrue($here->can('staff', $copenhagenEvent));
        $this->assertFalse($here->can('staff', $swedishEvent));
        $this->assertTrue($hubWide->can('staff', $swedishEvent));
        $this->assertEqualsCanonicalizing([$hubWide->id], app(Controllers::class)->controllersFor($swedishEvent)->pluck('id')->all());
    }

    public function test_no_role_lets_someone_staff_events(): void
    {
        $this->assertNotContains('book-positions', array_map(fn (Permission $permission) => $permission->value, Permission::cases()));
        $this->assertFalse(Role::query()->where('name', 'Controller')->exists());

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $shift = $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $occurrence = $event->occurrences()->sole();

        $this->actingAs(User::factory()->staff()->create())
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertForbidden();

        $this->actingAs(User::factory()->controller()->create())
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $occurrence->bookings()->count());
    }

    public function test_while_a_source_is_in_charge_signing_in_brings_the_members_source_grants_up_to_date(): void
    {
        $this->useControllerRule('SCA');
        $this->switchSourceOn();
        FakeRosterSync::$roster = [1000002];

        $this->signIn(1000001, ControllerRating::S2, 'SCA');
        $this->signIn(1000002, ControllerRating::OBS, 'GER');

        $this->assertFalse(User::query()->where('vatsim_cid', 1000001)->sole()->controllerGrants()->exists());
        $this->assertSame(ControllerGrantOrigin::Source, User::query()->where('vatsim_cid', 1000002)->sole()->controllerGrants()->sole()->origin);
    }

    public function test_a_source_owns_every_source_grant_but_never_touches_hand_grants(): void
    {
        $byHand = ControllerGrant::factory()->in($this->copenhagen)->create();
        $fromEarlierSource = ControllerGrant::factory()->fromSource()->create();
        $controllers = app(Controllers::class);

        $plan = $controllers->planSourceGrants(collect([['user' => $byHand->user, 'fir' => $this->copenhagen], ['user' => $byHand->user, 'fir' => $this->sweden]]));
        $this->assertSame([1, 1], $controllers->applySourceGrants($plan));

        $this->assertModelMissing($fromEarlierSource);
        $this->assertSame(ControllerGrantOrigin::Hand, $byHand->fresh()->origin);
        $this->assertSame(ControllerGrantOrigin::Source, $byHand->user->controllerGrants()->where('fir_id', $this->sweden->id)->sole()->origin);
    }

    public function test_switching_the_source_off_turns_its_grants_into_hand_grants_and_the_rule_takes_over(): void
    {
        Queue::fake();
        $this->useControllerRule('SCA');
        $this->switchSourceOn();
        $fromSource = ControllerGrant::factory()->fromSource()->in($this->copenhagen)->create();
        $byRule = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        $this->assertFalse(app(Controllers::class)->ruleApplies($byRule));

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/fake', [
                'enabled' => true,
                'features' => [],
                'removal_limit' => 25,
                'settings' => ['url' => 'https://fake.example', 'token' => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ControllerGrantOrigin::Hand, $fromSource->fresh()->origin);
        $this->assertTrue(app(Controllers::class)->ruleApplies($byRule));
    }

    public function test_someone_managing_users_in_a_fir_can_make_people_controllers_there_by_hand(): void
    {
        $manager = User::factory()->create();
        app(RoleAssignments::class)->give($manager, Role::create(['name' => 'User Manager', 'permissions' => [Permission::ManageUsers->value]]), $this->copenhagen);
        $visitor = User::factory()->create();

        $this->actingAs($manager)->post("/admin/users/{$visitor->id}/controller-grants", ['fir_id' => $this->copenhagen->id])->assertSessionHasNoErrors();
        $this->assertTrue($visitor->fresh()->can('staff', $this->eventIn($this->copenhagen)));

        $this->actingAs($manager)->post("/admin/users/{$visitor->id}/controller-grants", ['fir_id' => $this->copenhagen->id])
            ->assertSessionHasErrors(['controller' => "{$visitor->name} is already a Controller in EKDK."]);
        $this->actingAs($manager)->post("/admin/users/{$visitor->id}/controller-grants", ['fir_id' => $this->sweden->id])
            ->assertSessionHasErrors(['controller' => 'You can only make someone a Controller in FIRs where you manage users.']);
        $this->actingAs($manager)->post("/admin/users/{$visitor->id}/controller-grants", ['fir_id' => null])
            ->assertSessionHasErrors(['controller' => 'You can only make someone a Controller Hub-wide if you manage users across the hub.']);

        $grant = $visitor->controllerGrants()->sole();
        $this->actingAs($manager)->delete("/admin/users/{$visitor->id}/controller-grants/{$grant->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($grant);
    }

    public function test_source_grants_cannot_be_removed_by_hand(): void
    {
        $this->switchSourceOn();
        $grant = ControllerGrant::factory()->fromSource()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/users/{$grant->user_id}/controller-grants/{$grant->id}")
            ->assertSessionHasErrors(['controller' => "{$grant->user->name} is a Controller Hub-wide from Fake System roster sync, so it can’t be removed here."]);
        $this->assertModelExists($grant);
    }

    public function test_the_account_page_shows_where_someone_is_a_controller(): void
    {
        $this->useControllerRule('SCA');
        $member = User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);
        ControllerGrant::factory()->for($member)->in($this->copenhagen)->create();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/users/{$member->id}")->assertInertia(fn (Assert $page) => $page
            ->where('controller.byRule', true)
            ->where('controller.grants.0.fir.icao', 'EKDK')
            ->where('controller.grants.0.canRemove', true)
            ->where('controller.grantableIn', [null, $this->copenhagen->id, $this->sweden->id])
            ->where('user.roles', ['Controller', 'Controller · EKDK'])
        );
    }

    public function test_the_settings_page_warns_how_many_would_stop_being_controllers_without_the_rule(): void
    {
        $this->useControllerRule('SCA');
        User::factory()->rated(ControllerRating::S2)->count(2)->create(['subdivision' => 'SCA']);
        // A Controller by a grant too, so switching the rule off leaves them able to staff.
        User::factory()->controller()->create(['subdivision' => 'SCA']);
        $admin = User::factory()->admin()->create(['subdivision' => 'SCA', 'rating' => ControllerRating::C1]);

        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('controllersOnlyByRule', 2));

        $this->actingAs($admin)->post('/admin/settings', ['name' => 'Hub', 'subdivision' => 'SCA', 'auto_controller' => false])->assertSessionHasNoErrors();
        $this->assertFalse(app(Controllers::class)->ruleIsInCharge());
    }

    public function test_the_settings_page_says_which_source_decides(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('controllerSource', null));

        $this->switchSourceOn();

        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('controllerSource', 'Fake System roster sync'));
    }

    public function test_the_vatsim_sync_steps_aside_for_a_source(): void
    {
        Http::fake();
        $this->useControllerRule('SCA');
        $this->switchSourceOn();
        User::factory()->rated(ControllerRating::S2)->create(['subdivision' => 'SCA']);

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Fake System roster sync decides who is a Controller; nothing to sync.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function useControllerRule(?string $subdivision, bool $on = true): void
    {
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => $subdivision]);
        $identity->switchControllerRule($on);
    }

    private function eventIn(Fir $fir): Event
    {
        return Event::factory()->create(['fir_id' => $fir->id]);
    }

    private function switchSourceOn(): void
    {
        IntegrationSetting::query()->create([
            'key' => 'fake',
            'enabled' => true,
            'features' => ['roster'],
            'settings' => ['url' => 'https://fake.example', 'token' => 's3cret-token'],
        ]);
    }

    private function signIn(int $cid, ControllerRating $rating, string $subdivision): void
    {
        Socialite::fake('vatsim', (new SocialiteUser)
            ->setRaw([
                'cid' => (string) $cid,
                'personal' => ['name_full' => 'Kim Controller', 'email' => "{$cid}@example.com"],
                'vatsim' => ['rating' => ['id' => $rating->value], 'subdivision' => ['id' => $subdivision]],
            ])
            ->map(['id' => (string) $cid, 'name' => 'Kim Controller', 'email' => "{$cid}@example.com"]));

        $this->get('/auth/vatsim/callback?code=code&state=state')->assertRedirect();
        auth()->logout();
    }
}
