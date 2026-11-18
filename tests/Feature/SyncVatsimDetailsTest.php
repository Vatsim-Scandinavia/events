<?php

namespace Tests\Feature;

use App\Auth\Controllers;
use App\Auth\RoleAssignments;
use App\Auth\Roles;
use App\Auth\Where;
use App\Enums\ControllerRating;
use App\Enums\SignInProvider;
use App\Models\ControllerGrant;
use App\Models\IntegrationSetting;
use App\Models\SignInToken;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Fakes\FakeIntegration;
use Tests\Fakes\FakeRosterSync;
use Tests\TestCase;

class SyncVatsimDetailsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, mixed> What the public API answers per CID: a record, or an HTTP status. */
    private array $publicRecords = [];

    /** @var array<string, mixed> What /api/user answers per access token: a member, or an HTTP status. */
    private array $apiUser = [];

    /** @var list<array<string, mixed>|int> What the token endpoint answers next, in turn. */
    private array $refreshes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['vacc.subdivision' => null, 'session.driver' => 'database']);
        Sleep::fake();
        $identity = app(VaccIdentity::class);
        $identity->save(['subdivision' => 'SCA']);
        $identity->switchControllerRule(true);

        Http::fake(function (Request $request) {
            if (preg_match('#api\.vatsim\.net/v2/members/(\d+)$#', $request->url(), $match)) {
                return $this->answer($this->publicRecords[(int) $match[1]] ?? 404);
            }

            if (str_ends_with($request->url(), '/api/user')) {
                $member = $this->apiUser[str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '')] ?? 401;

                return $this->answer(is_array($member) ? ['data' => $member] : $member);
            }

            if (str_ends_with($request->url(), '/oauth/token')) {
                return $this->answer(array_shift($this->refreshes) ?? 500);
            }

            return null;
        });
    }

    public function test_every_account_is_refreshed_from_the_public_record_without_tokens(): void
    {
        $student = User::factory()->rated(ControllerRating::OBS)->create(['vatsim_cid' => 1000001, 'subdivision' => 'SCA', 'name' => 'Kim', 'email' => 'kim@example.com']);
        $elsewhere = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 1000002, 'subdivision' => 'GER']);
        $this->publicRecords = [
            1000001 => ['id' => 1000001, 'rating' => ControllerRating::S1->value, 'subdivision_id' => 'SCA'],
            1000002 => ['id' => 1000002, 'rating' => ControllerRating::S2->value, 'subdivision_id' => 'SCA'],
        ];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 0 with their tokens and 2 from the public VATSIM API, skipped 0, signed out 0 suspended.')
            ->assertSuccessful();

        $student->refresh();
        $this->assertSame([ControllerRating::S1, 'Kim', 'kim@example.com'], [$student->rating, $student->name, $student->email]);
        $this->assertNotNull($student->details_synced_at);
        $this->assertTrue(app(Controllers::class)->ruleApplies($student));
        $this->assertTrue(app(Controllers::class)->ruleApplies($elsewhere->fresh()));
    }

    public function test_an_account_with_tokens_gets_its_full_details_from_the_sign_in_provider(): void
    {
        $member = User::factory()->rated(ControllerRating::S1)->create(['vatsim_cid' => 1000001, 'name' => 'Old Name', 'subdivision' => 'SCA']);
        $this->giveTokens($member, 'access-1');
        $this->apiUser = ['access-1' => $this->apiMember(1000001, 'New Name', ControllerRating::S3, null)];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 1 with their tokens and 0 from the public VATSIM API, skipped 0, signed out 0 suspended.')
            ->assertSuccessful();

        $member->refresh();
        $this->assertSame(['New Name', ControllerRating::S3, null], [$member->name, $member->rating, $member->subdivision]);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.vatsim.net'));
    }

    public function test_an_expired_token_is_refreshed_first(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1', expiresAt: now()->subDay());
        $this->refreshes = [['expires_in' => 604800, 'access_token' => 'access-2', 'refresh_token' => 'refresh-2']];
        $this->apiUser = ['access-2' => $this->apiMember(1000001, 'Kim', ControllerRating::S2, 'SCA')];

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        $this->assertSame('refresh-2', SignInToken::query()->sole()->refresh_token);
        $this->assertSame('Kim', $member->fresh()->name);
    }

    public function test_a_token_turned_down_even_after_a_refresh_is_dropped_and_the_public_record_used(): void
    {
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->giveTokens($member, 'access-1');
        $this->refreshes = [['expires_in' => 604800, 'access_token' => 'access-2', 'refresh_token' => 'refresh-2']];
        $this->publicRecords = [1000001 => ['id' => 1000001, 'rating' => ControllerRating::C1->value, 'subdivision_id' => 'SCA']];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 0 with their tokens and 1 from the public VATSIM API, skipped 0, signed out 0 suspended.')
            ->assertSuccessful();

        $this->assertSame(0, SignInToken::query()->count());
        $this->assertSame(ControllerRating::C1, $member->fresh()->rating);
    }

    public function test_a_refused_refresh_drops_the_token_and_falls_back_but_an_outage_stops_the_run(): void
    {
        $refused = User::factory()->create(['vatsim_cid' => 1000001, 'details_synced_at' => now()->subDays(3)]);
        $this->giveTokens($refused, 'access-1', expiresAt: now()->subDay());
        $unreachable = User::factory()->create(['vatsim_cid' => 1000002, 'details_synced_at' => now()->subDays(2)]);
        $this->giveTokens($unreachable, 'access-2', expiresAt: now()->subDay());
        // Connect answers a revoked refresh token with 401; then the token endpoint is down.
        $this->refreshes = [401, 503];
        $this->publicRecords = [1000001 => ['id' => 1000001, 'rating' => ControllerRating::S2->value, 'subdivision_id' => 'SCA']];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 0 with their tokens and 1 from the public VATSIM API, skipped 0, signed out 0 suspended.')
            ->assertSuccessful();

        $this->assertFalse($refused->signInToken()->exists());
        // An outage keeps the token, and leaves the Account first in line.
        $this->assertTrue($unreachable->signInToken()->exists());
        $this->assertTrue($unreachable->fresh()->details_synced_at->lessThan(now()->subDay()));
    }

    public function test_it_goes_least_recently_synced_first_and_a_rate_limit_stops_it_until_tomorrow(): void
    {
        $never = User::factory()->create(['vatsim_cid' => 1000003, 'details_synced_at' => null]);
        $oldest = User::factory()->create(['vatsim_cid' => 1000001, 'details_synced_at' => now()->subDays(5)]);
        $newest = User::factory()->create(['vatsim_cid' => 1000002, 'details_synced_at' => now()->subDay()]);
        $this->publicRecords = [
            1000003 => ['id' => 1000003, 'rating' => ControllerRating::S1->value, 'subdivision_id' => 'SCA'],
            1000001 => 429,
        ];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Stopped early: The VATSIM API answered HTTP 429. The rest go first tomorrow.')
            ->assertSuccessful();

        $this->assertNotNull($never->fresh()->details_synced_at);
        $this->assertTrue($oldest->fresh()->details_synced_at->lessThan($newest->fresh()->details_synced_at));
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/1000002'));
    }

    public function test_a_record_vatsim_does_not_know_is_skipped_and_left_alone(): void
    {
        // Sandbox accounts sign in with member IDs the live VATSIM API has never heard of.
        $sandbox = User::factory()->rated(ControllerRating::S2)->create(['vatsim_cid' => 10000005, 'subdivision' => 'SCA']);
        $other = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->publicRecords = [1000001 => ['id' => 1000001, 'rating' => ControllerRating::S1->value, 'subdivision_id' => null]];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 0 with their tokens and 1 from the public VATSIM API, skipped 1, signed out 0 suspended.')
            ->assertSuccessful();

        $this->assertSame([ControllerRating::S2, 'SCA'], [$sandbox->fresh()->rating, $sandbox->fresh()->subdivision]);
        // Tried, so it doesn't hold up everyone else tomorrow.
        $this->assertNotNull($sandbox->fresh()->details_synced_at);
        $this->assertSame(ControllerRating::S1, $other->fresh()->rating);
    }

    public function test_a_suspended_member_is_signed_out_but_keeps_their_roles_and_grants(): void
    {
        $member = User::factory()->rated(ControllerRating::S3)->create(['vatsim_cid' => 1000001, 'subdivision' => 'SCA']);
        app(RoleAssignments::class)->give($member, Roles::EVENT_STAFF);
        ControllerGrant::factory()->for($member)->create();
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => null, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->publicRecords = [1000001 => ['id' => 1000001, 'rating' => ControllerRating::Suspended->value, 'subdivision_id' => 'SCA']];

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Synced 0 with their tokens and 0 from the public VATSIM API, skipped 0, signed out 1 suspended.')
            ->assertSuccessful();

        $this->assertSame(['someone-else'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(ControllerRating::Suspended, $member->fresh()->rating);
        $this->assertSame(1, $member->roleAssignments()->count());
        $this->assertTrue(app(Controllers::class)->isController($member->fresh(), Where::hub()));
    }

    public function test_it_still_runs_while_a_controller_source_is_in_charge(): void
    {
        config(['integrations.registered' => [FakeIntegration::class], 'integrations.settings' => []]);
        FakeRosterSync::$decided = true;
        IntegrationSetting::query()->create(['key' => 'fake', 'enabled' => true, 'features' => ['roster'], 'settings' => ['url' => 'https://fake.example', 'token' => 't']]);
        $member = User::factory()->create(['vatsim_cid' => 1000001]);
        $this->publicRecords = [1000001 => ['id' => 1000001, 'rating' => ControllerRating::C1->value, 'subdivision_id' => 'SCA']];

        $this->artisan('users:sync-vatsim')->assertSuccessful();

        $this->assertSame(ControllerRating::C1, $member->fresh()->rating);
    }

    public function test_the_daily_sync_can_be_switched_off(): void
    {
        config(['services.vatsim.sync' => false]);
        User::factory()->create(['vatsim_cid' => 1000001]);

        $this->artisan('users:sync-vatsim')
            ->expectsOutput('Syncing with VATSIM is switched off (VATSIM_SYNC=false); nothing to sync.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function giveTokens(User $user, string $accessToken, mixed $expiresAt = null): void
    {
        SignInToken::query()->create([
            'user_id' => $user->id,
            'provider' => SignInProvider::Vatsim,
            'access_token' => $accessToken,
            'refresh_token' => "refresh-for-{$accessToken}",
            'expires_at' => $expiresAt ?? now()->addDays(3),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function apiMember(int $cid, string $name, ControllerRating $rating, ?string $subdivision): array
    {
        return [
            'cid' => (string) $cid,
            'personal' => ['name_first' => strtok($name, ' '), 'name_full' => $name, 'email' => "{$cid}@example.com"],
            'vatsim' => ['rating' => ['id' => $rating->value], 'subdivision' => ['id' => $subdivision]],
        ];
    }

    /**
     * @param  array<string, mixed>|int  $answer  A JSON body, or an HTTP status to fail with.
     */
    private function answer(array|int $answer): mixed
    {
        return is_int($answer) ? Http::response(['error' => 'refused'], $answer) : Http::response($answer);
    }
}
