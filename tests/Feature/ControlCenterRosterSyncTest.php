<?php

namespace Tests\Feature;

use App\Auth\Controllers;
use App\Enums\ControllerGrantOrigin;
use App\Enums\ControllerRating;
use App\Enums\IntegrationRunStatus;
use App\Integrations\ControlCenter\Member;
use App\Integrations\RunSync;
use App\Models\ControllerGrant;
use App\Models\Event;
use App\Models\Fir;
use App\Models\IntegrationRun;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsInThroughTheProvider;
use Tests\TestCase;

class ControlCenterRosterSyncTest extends TestCase
{
    use RefreshDatabase;
    use SignsInThroughTheProvider;

    private const string USERS_URL = 'cc.example/api/v1/users*';

    private Fir $denmark;

    private Fir $norway;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null, 'integrations.settings.control-center' => []]);
        $this->denmark = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $this->norway = Fir::factory()->create(['icao' => 'ENOR', 'name' => 'Norway']);
    }

    public function test_an_active_member_gets_controller_in_that_firs_only_and_can_book_there(): void
    {
        $this->switchOn();
        $member = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([$this->member(1000001, active: ['denmark'])]);

        $this->sync();

        $this->assertSame([$this->denmark->id], $this->sourceFirs($member));

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        foreach ([[$this->denmark, false], [$this->norway, true]] as [$fir, $forbidden]) {
            $event = Event::factory()->withShifts()->create([
                'fir_id' => $fir->id,
                'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
                'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
            ]);
            $shift = $event->positions()->create(['callsign' => "{$fir->icao}_CTR"])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
            $response = $this->actingAs($member->fresh())->post("/occurrences/{$event->occurrences()->sole()->id}/shifts/{$shift->id}/booking");

            $forbidden ? $response->assertForbidden() : $response->assertSessionHasNoErrors();
        }
    }

    public function test_a_visiting_endorsement_gives_controller_in_its_areas_firs(): void
    {
        $this->switchOn();
        $visitor = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([
            $this->member(1000001, endorsements: ['visiting' => [['rating' => 'S3', 'areas' => ['Norway'], 'valid_from' => null, 'valid_to' => null]]]),
            $this->member(1000002, active: ['denmark']),
        ]);

        $this->sync();

        $this->assertSame([$this->norway->id], $this->sourceFirs($visitor));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function endorsementsThatGiveNothing(): array
    {
        return [
            'solo' => [['solo' => [['positions' => ['EKCH_APP'], 'valid_from' => null, 'valid_to' => null]]]],
            'facility' => [['facility' => [['rating' => 'MAE EKCH TWR', 'valid_from' => null, 'valid_to' => null]]]],
            'examiner' => [['examiner' => [['rating' => 'S3', 'areas' => ['Denmark'], 'valid_from' => null, 'valid_to' => null]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $endorsements
     */
    #[DataProvider('endorsementsThatGiveNothing')]
    public function test_other_endorsements_alone_give_nothing(array $endorsements): void
    {
        $this->switchOn();
        $student = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([$this->member(1000001, endorsements: $endorsements), $this->member(1000002, active: ['denmark'])]);

        $this->sync();

        $this->assertSame([], $this->sourceFirs($student));
        $this->assertEquals($endorsements, array_filter(Member::query()->find(1000001)->endorsements));
    }

    public function test_someone_who_never_signed_in_gets_controller_when_they_first_do(): void
    {
        $this->switchOn();
        $this->fakeControlCenter([$this->member(1000001, active: ['denmark', 'norway'])]);
        $this->sync();
        $this->assertDatabaseMissing('users', ['vatsim_cid' => 1000001]);

        $this->signIn(1000001, ControllerRating::S2);

        $this->assertEqualsCanonicalizing([$this->denmark->id, $this->norway->id], $this->sourceFirs($this->user(1000001)));
    }

    public function test_members_who_stop_qualifying_lose_source_grants_but_keep_hand_grants(): void
    {
        $this->switchOn();
        $leaver = User::factory()->create(['vatsim_cid' => 1000001]);
        ControllerGrant::factory()->for($leaver)->fromSource()->in($this->denmark)->create();
        ControllerGrant::factory()->for($leaver)->in($this->norway)->create();
        $staying = User::factory()->create(['vatsim_cid' => 1000002]);
        ControllerGrant::factory()->for($staying)->fromSource()->in($this->denmark)->create();
        $this->fakeControlCenter([$this->member(1000001), $this->member(1000002, active: ['denmark'])], removalLimit: 100);

        $this->sync();

        $this->assertSame([], $this->sourceFirs($leaver));
        $this->assertTrue($leaver->controllerGrants()->where('fir_id', $this->norway->id)->where('origin', ControllerGrantOrigin::Hand)->exists());
        $this->assertSame([$this->denmark->id], $this->sourceFirs($staying));
        $this->assertSame([0, 1], [$this->lastRun()->added, $this->lastRun()->removed]);
    }

    public function test_taking_over_from_the_controller_rule_changes_nothing_until_the_first_sync(): void
    {
        Queue::fake();
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => 'SCA']);
        $identity->switchControllerRule(true);
        $members = collect(range(1000001, 1000004))->map(fn (int $cid) => User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => $cid, 'subdivision' => 'SCA']));
        $this->fakeControlCenter([$this->member(1000001, active: ['denmark']), $this->member(1000005, active: ['norway'])]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/integrations/control-center', [
            'enabled' => true,
            'features' => ['roster'],
            'removal_limit' => 25,
            'settings' => ['url' => 'https://cc.example', 'token' => 'cc-key', 'areas' => "denmark = EKDK\nnorway = ENOR"],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        // Before any sync, the Controller Rule still decides, and signing in changes nothing.
        $this->signIn(1000002, ControllerRating::S2);
        $this->assertTrue($members->every(fn (User $user) => app(Controllers::class)->ruleApplies($user->fresh())));
        $this->assertSame(0, ControllerGrant::query()->count());

        $this->sync();

        $this->assertSame(IntegrationRunStatus::Succeeded, $this->lastRun()->status);
        $this->assertFalse($members->contains(fn (User $user) => app(Controllers::class)->ruleApplies($user->fresh())));
        $this->assertSame([$this->denmark->id], $this->sourceFirs($members[0]));
        $this->assertSame([], $this->sourceFirs($members[1]));
    }

    /**
     * @return array<string, array{\Closure(): void, string}>
     */
    public static function badAnswers(): array
    {
        return [
            'a server error' => [fn () => Http::fake([self::USERS_URL => Http::response(['message' => 'Oops'], 500)]), 'Control Center answered with an error (HTTP 500).'],
            'a refused key' => [fn () => Http::fake([self::USERS_URL => Http::response(['message' => 'Unauthorized'], 401)]), 'Control Center turned the API key down.'],
            'a timeout' => [fn () => Http::fake([self::USERS_URL => fn () => throw new ConnectionException('cURL error 28 for https://cc.example?key=cc-key')]), 'Control Center didn’t answer. Check its URL, or try again later.'],
            'no data' => [fn () => Http::fake([self::USERS_URL => Http::response(['users' => []])]), 'Control Center sent something the Hub couldn’t read.'],
            'a member without a CID' => [fn () => Http::fake([self::USERS_URL => Http::response(['data' => [['rating' => 'S2']]])]), 'Control Center sent something the Hub couldn’t read.'],
        ];
    }

    /**
     * @param  \Closure(): void  $fake
     */
    #[DataProvider('badAnswers')]
    public function test_a_bad_answer_changes_nothing_and_is_recorded_as_failed(\Closure $fake, string $message): void
    {
        $this->switchOn();
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        ControllerGrant::factory()->for($controller)->fromSource()->in($this->denmark)->create();
        Member::query()->create(['cid' => 1000001, 'active_areas' => ['denmark'], 'endorsements' => [], 'synced_at' => now()]);
        $fake();

        $this->sync();

        $this->assertSame(IntegrationRunStatus::Failed, $this->lastRun()->status);
        $this->assertSame($message, $this->lastRun()->message);
        $this->assertSame([$this->denmark->id], $this->sourceFirs($controller));
        $this->assertSame(1, Member::query()->count());
    }

    public function test_a_mapped_area_missing_from_control_center_holds_the_run(): void
    {
        $this->switchOn();
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        ControllerGrant::factory()->for($controller)->fromSource()->in($this->norway)->create();
        // "norway" was renamed "Norge" upstream.
        $this->fakeControlCenter([$this->member(1000001, active: ['norge'], inactive: ['denmark'])]);

        $this->sync();

        $this->assertSame(IntegrationRunStatus::Held, $this->lastRun()->status);
        $this->assertStringContainsString('no longer lists “norway”', $this->lastRun()->message);
        $this->assertSame([$this->norway->id], $this->sourceFirs($controller));
    }

    public function test_unmapped_areas_give_nothing_and_are_shown_as_warnings(): void
    {
        $this->switchOn();
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([$this->member(1000001, active: ['iceland'], inactive: ['denmark', 'norway'])]);

        $this->sync();

        $this->assertSame([], $this->sourceFirs($controller));
        $this->actingAs(User::factory()->admin()->create())->get('/admin/integrations/control-center')->assertInertia(fn (Assert $page) => $page
            ->where('runs.0.status', 'succeeded')
            ->where('runs.0.warnings', ['Control Center’s area “iceland” isn’t mapped to a FIR, so it makes nobody a Controller.'])
        );
    }

    public function test_switching_it_off_turns_its_grants_into_hand_grants_and_the_controller_rule_takes_over(): void
    {
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => 'SCA']);
        $identity->switchControllerRule(true);
        $this->switchOn();
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([$this->member(1000001, active: ['denmark'])]);
        $this->sync();
        $rated = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000002, 'subdivision' => 'SCA']);

        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/control-center', [
            'enabled' => true,
            'features' => [],
            'removal_limit' => 25,
            'settings' => ['url' => 'https://cc.example', 'token' => '', 'areas' => "denmark = EKDK\nnorway = ENOR"],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($controller->controllerGrants()->where('fir_id', $this->denmark->id)->where('origin', ControllerGrantOrigin::Hand)->exists());
        $this->assertTrue(app(Controllers::class)->ruleApplies($rated));
        $this->assertSame(0, Member::query()->count());
    }

    public function test_it_cannot_be_switched_on_with_a_key_control_center_turns_down(): void
    {
        Http::fake([self::USERS_URL => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/control-center', [
            'enabled' => true,
            'features' => ['roster'],
            'removal_limit' => 25,
            'settings' => ['url' => 'https://cc.example', 'token' => 'wrong-key', 'areas' => 'denmark = EKDK'],
        ])->assertSessionHasErrors(['enabled' => 'Couldn’t connect, so it stays off: Control Center turned the API key down.']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cc.example/api/v1/users' && $request->hasHeader('Authorization', 'Bearer wrong-key'));
        $this->assertDatabaseCount('integration_settings', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function urlsWithTheApi(): array
    {
        return [
            'with /api' => ['https://cc.example/api'],
            'with /api/v1/' => ['https://cc.example/api/v1/'],
            'as typed by hand' => ['https://cc.example/API/'],
        ];
    }

    #[DataProvider('urlsWithTheApi')]
    public function test_a_url_that_includes_the_api_still_reaches_it(string $url): void
    {
        Http::fake([self::USERS_URL => Http::response(['data' => []])]);

        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/control-center', [
            'enabled' => true,
            'features' => [],
            'removal_limit' => 25,
            'settings' => ['url' => $url, 'token' => 'cc-key', 'areas' => 'denmark = EKDK'],
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cc.example/api/v1/users');
    }

    public function test_the_area_map_must_name_the_hubs_firs(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put('/admin/integrations/control-center', [
            'enabled' => false,
            'features' => [],
            'removal_limit' => 25,
            'settings' => ['url' => 'https://cc.example', 'token' => '', 'areas' => "denmark = EKDK\nsweden = ESAA\niceland"],
        ])->assertSessionHasErrors(['settings.areas']);

        $errors = session('errors')->get('settings.areas');
        $this->assertContains('ESAA isn’t one of the Hub’s FIRs.', $errors);
        $this->assertContains('Write “iceland” as an area and a FIR, like “denmark = EKDK”.', $errors);
    }

    public function test_the_area_map_can_be_set_by_the_server(): void
    {
        config(['integrations.settings.control-center.areas' => 'Denmark=EKDK, norway=ENOR']);
        $this->switchOn(areas: '');
        $controller = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->fakeControlCenter([$this->member(1000001, active: ['norway'])]);

        $this->sync();

        $this->assertSame([$this->norway->id], $this->sourceFirs($controller));
    }

    private function switchOn(string $areas = "denmark = EKDK\nnorway = ENOR", int $removalLimit = 25): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => 'control-center'], [
            'enabled' => true,
            'features' => ['roster'],
            'settings' => ['url' => 'https://cc.example', 'token' => 'cc-key', 'areas' => $areas],
            'removal_limit' => $removalLimit,
        ]);
    }

    /**
     * Control Center answering with these members, every area listed for each as it does.
     *
     * @param  list<array<string, mixed>>  $members
     */
    private function fakeControlCenter(array $members, ?int $removalLimit = null): void
    {
        if ($removalLimit !== null) {
            IntegrationSetting::query()->whereKey('control-center')->update(['removal_limit' => $removalLimit]);
        }

        Http::fake([self::USERS_URL => Http::response(['data' => $members])]);
    }

    /**
     * @param  list<string>  $active
     * @param  list<string>|null  $inactive  Defaults to the other mapped areas.
     * @param  array<string, mixed>  $endorsements
     * @return array<string, mixed>
     */
    private function member(int $cid, array $active = [], ?array $inactive = null, array $endorsements = []): array
    {
        $inactive ??= array_values(array_diff(['denmark', 'norway'], $active));

        return [
            'id' => $cid,
            'rating' => 'S2',
            'atc_active' => $active !== [],
            'atc_active_areas' => [...array_fill_keys($active, true), ...array_fill_keys($inactive, false)],
            'endorsements' => [...['visiting' => null, 'examiner' => null, 'solo' => null, 'facility' => null], ...$endorsements],
        ];
    }

    private function sync(?int $approvedBy = null): void
    {
        app()->call([new RunSync('control-center', 'roster', $approvedBy), 'handle']);
    }

    private function lastRun(): IntegrationRun
    {
        return IntegrationRun::query()->latest('id')->firstOrFail();
    }

    private function user(int $cid): User
    {
        return User::query()->where('vatsim_cid', $cid)->sole();
    }

    /**
     * @return list<int>
     */
    private function sourceFirs(User $user): array
    {
        return $user->controllerGrants()->where('origin', ControllerGrantOrigin::Source)->pluck('fir_id')->all();
    }

    private function signIn(int $cid, ControllerRating $rating): void
    {
        $this->signInThroughProvider(self::connectMember($cid, $rating, 'SCA', email: "{$cid}@example.com"), stay: false)->assertRedirect();
    }
}
